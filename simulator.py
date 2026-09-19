#!/usr/bin/env python3
"""
simulator.py

Simulator device cuaca (weather station) yang mengirim telemetry ke backend
Laravel secara periodik, meniru perilaku firmware IoT di lapangan.

Skenario yang disimulasikan tiap siklus (1 siklus = 60 detik):
  - Normal   : tiap device kirim 1 payload telemetry via POST /ingest/telemetry
  - Batch    : tiap 5 siklus, device pertama (WS-MLG-001) kirim 3 record
               sekaligus via POST /ingest/telemetry/batch (simulasi "catch-up"
               data yang sempat tertahan di device, mis. setelah putus jaringan)
  - Duplikat : tiap 3 siklus, salah satu device (bergantian) mengirim ulang
               payload terakhirnya (device_id + ts identik) untuk menguji
               dedup di backend (unique constraint device_id+sensor_id+device_time)

Hanya pakai library standar Python + `requests`.
"""

from __future__ import annotations

import copy
import math
import random
import time
from dataclasses import dataclass, field
from datetime import datetime

import requests

# ---------------------------------------------------------------------------
# Konfigurasi
# ---------------------------------------------------------------------------

BASE_URL = "http://localhost:8000/api/v1"
TELEMETRY_URL = f"{BASE_URL}/ingest/telemetry"
BATCH_URL = f"{BASE_URL}/ingest/telemetry/batch"

CYCLE_SECONDS = 60          # interval pengiriman normal
BATCH_EVERY_N_CYCLES = 5    # device pertama kirim batch tiap 5 siklus
DUPLICATE_EVERY_N_CYCLES = 3  # kirim ulang payload identik tiap 3 siklus

FW_VERSION = "1.4.2"

DEVICES = [
    {"device_id": "WS-MLG-001", "api_key": "secret-WS-MLG-001"},
    {"device_id": "WS-BTU-001", "api_key": "secret-WS-BTU-001"},
    {"device_id": "WS-KPJ-001", "api_key": "secret-WS-KPJ-001"},
]

REQUEST_TIMEOUT = 10  # detik


# ---------------------------------------------------------------------------
# State per device — dipakai untuk random-walk nilai sensor supaya realistis
# (tidak melompat-lompat antar siklus) dan untuk counter yang harus konsisten
# (seq, rain_counter, ts terakhir untuk skenario duplikat).
# ---------------------------------------------------------------------------

@dataclass
class DeviceState:
    device_id: str
    api_key: str
    seq: int = field(default_factory=lambda: random.randint(10000, 19999))
    battery_v: float = field(default_factory=lambda: round(random.uniform(4.0, 4.2), 2))
    rssi: int = field(default_factory=lambda: random.randint(-75, -55))
    rain_counter: float = 0.0  # reset ke 0 tiap kali simulator (device) restart
    humidity: float = field(default_factory=lambda: random.uniform(55, 75))
    pressure: float = field(default_factory=lambda: random.uniform(1008, 1012))
    wind_speed: float = field(default_factory=lambda: random.uniform(1, 4))
    wind_dir: int = field(default_factory=lambda: random.randint(0, 359))
    last_payload: dict | None = None  # dipakai untuk skenario duplikat


def clamp(value: float, lo: float, hi: float) -> float:
    return max(lo, min(hi, value))


def hour_fraction(dt: datetime) -> float:
    """Jam dalam bentuk desimal, mis. 14:30 -> 14.5, dipakai untuk kurva diurnal."""
    return dt.hour + dt.minute / 60.0


def realistic_temp_air(dt: datetime) -> float:
    """
    Suhu udara 22-30°C, puncak sekitar jam 15:00 (siang-sore), terendah
    sekitar jam 03:00-04:00 dini hari. Pakai kurva cosine + noise kecil.
    """
    hf = hour_fraction(dt)
    base = 26 + 4 * math.cos((hf - 15) / 24 * 2 * math.pi)
    noise = random.uniform(-0.4, 0.4)
    return round(clamp(base + noise, 22, 30), 1)


def realistic_solar_rad(dt: datetime) -> float:
    """
    Radiasi matahari 0 W/m² di malam hari, naik-turun mengikuti kurva sinus
    antara jam 06:00-18:00, puncak ~900 W/m² sekitar tengah hari.
    """
    hf = hour_fraction(dt)
    if hf <= 6 or hf >= 18:
        return 0.0
    value = 900 * math.sin(math.pi * (hf - 6) / 12)
    noise = random.uniform(-20, 20)
    return round(clamp(value + noise, 0, 900), 1)


def realistic_humidity(state: DeviceState, solar_rad: float) -> float:
    """
    Kelembapan 40-100%, cenderung tinggi malam hari / rendah saat radiasi
    matahari tinggi. Random-walk menuju target supaya perubahan halus.
    """
    target = 90 - (solar_rad / 900) * 45
    step = (target - state.humidity) * 0.15 + random.uniform(-2, 2)
    state.humidity = clamp(state.humidity + step, 40, 100)
    return round(state.humidity, 1)


def realistic_pressure(state: DeviceState) -> float:
    """Tekanan udara 1005-1015 hPa, random-walk kecil antar siklus."""
    step = random.uniform(-0.3, 0.3)
    state.pressure = clamp(state.pressure + step, 1005, 1015)
    return round(state.pressure, 1)


def realistic_wind_speed(state: DeviceState) -> float:
    """Kecepatan angin 0-10 m/s, random-walk kecil antar siklus."""
    step = random.uniform(-0.8, 0.8)
    state.wind_speed = clamp(state.wind_speed + step, 0, 10)
    return round(state.wind_speed, 1)


def realistic_wind_dir(state: DeviceState) -> int:
    """Arah angin 0-359°, random-walk melingkar (wrap-around)."""
    step = random.randint(-20, 20)
    state.wind_dir = (state.wind_dir + step) % 360
    return state.wind_dir


def realistic_rain_counter(state: DeviceState) -> float:
    """
    Rain gauge tipping-bucket: hanya naik (kumulatif), kadang tidak naik
    sama sekali (tidak hujan). Reset ke 0 saat simulator/device di-restart
    (ditangani lewat DeviceState yang dibuat baru tiap start script).
    """
    if random.random() < 0.2:  # ~20% peluang "hujan" tiap siklus
        state.rain_counter += random.choice([0.2, 0.2, 0.4, 0.6])
    return round(state.rain_counter, 1)


def realistic_battery_v(state: DeviceState) -> float:
    """Baterai perlahan turun dengan noise kecil, lantai di 3.3V."""
    drain = random.uniform(0.0005, 0.002)
    noise = random.uniform(-0.01, 0.01)
    state.battery_v = clamp(state.battery_v - drain + noise, 3.3, 4.2)
    return round(state.battery_v, 2)


def realistic_rssi(state: DeviceState) -> int:
    """RSSI berfluktuasi kecil di sekitar nilai sebelumnya."""
    step = random.randint(-3, 3)
    state.rssi = int(clamp(state.rssi + step, -95, -45))
    return state.rssi


def generate_readings(state: DeviceState, dt: datetime) -> list[dict]:
    """Bangun list readings realistis untuk satu titik waktu `dt`."""
    solar_rad = realistic_solar_rad(dt)
    return [
        {"s": "temp_air", "v": realistic_temp_air(dt)},
        {"s": "humidity", "v": realistic_humidity(state, solar_rad)},
        {"s": "pressure", "v": realistic_pressure(state)},
        {"s": "wind_speed", "v": realistic_wind_speed(state)},
        {"s": "wind_dir", "v": realistic_wind_dir(state)},
        {"s": "rain_counter", "v": realistic_rain_counter(state)},
        {"s": "solar_rad", "v": solar_rad},
    ]


# ---------------------------------------------------------------------------
# Pembuatan payload & pengiriman HTTP
# ---------------------------------------------------------------------------

def build_payload(state: DeviceState, dt: datetime) -> dict:
    """Bangun payload sesuai format yang sudah ditentukan (tidak boleh diubah)."""
    state.seq += 1
    return {
        "device_id": state.device_id,
        "fw": FW_VERSION,
        "ts": int(dt.timestamp()),
        "seq": state.seq,
        "battery_v": realistic_battery_v(state),
        "rssi": realistic_rssi(state),
        "readings": generate_readings(state, dt),
    }


def log(msg: str) -> None:
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    print(f"[{timestamp}] {msg}")


def send_telemetry(state: DeviceState, payload: dict, label: str = "NORMAL") -> None:
    headers = {"X-Api-Key": state.api_key, "Content-Type": "application/json"}
    try:
        res = requests.post(TELEMETRY_URL, json=payload, headers=headers, timeout=REQUEST_TIMEOUT)
        log(
            f"[{label}] {state.device_id} -> POST /ingest/telemetry "
            f"(ts={payload['ts']}, seq={payload['seq']}) => HTTP {res.status_code}"
        )
        try:
            log(f"          response: {res.json()}")
        except ValueError:
            log(f"          response (non-JSON): {res.text[:200]}")
    except requests.RequestException as exc:
        log(f"[{label}] {state.device_id} -> GAGAL kirim telemetry: {exc}")


def send_batch(state: DeviceState, records: list[dict]) -> None:
    headers = {"X-Api-Key": state.api_key, "Content-Type": "application/json"}
    payload = {
        "device_id": state.device_id,
        "fw": FW_VERSION,
        "batch": records,
    }
    try:
        res = requests.post(BATCH_URL, json=payload, headers=headers, timeout=REQUEST_TIMEOUT)
        ts_list = [r["ts"] for r in records]
        log(
            f"[BATCH] {state.device_id} -> POST /ingest/telemetry/batch "
            f"({len(records)} record, ts={ts_list}) => HTTP {res.status_code}"
        )
        try:
            log(f"          response: {res.json()}")
        except ValueError:
            log(f"          response (non-JSON): {res.text[:200]}")
    except requests.RequestException as exc:
        log(f"[BATCH] {state.device_id} -> GAGAL kirim batch: {exc}")


def build_batch_records(state: DeviceState, now_dt: datetime, count: int = 3) -> list[dict]:
    """
    Bangun `count` record historis (mis. data yang sempat tertahan di device
    saat offline), dengan ts mundur tiap CYCLE_SECONDS dari sekarang.
    """
    records = []
    for i in range(count, 0, -1):
        dt = datetime.fromtimestamp(now_dt.timestamp() - i * CYCLE_SECONDS)
        state.seq += 1
        records.append(
            {
                "ts": int(dt.timestamp()),
                "seq": state.seq,
                "battery_v": realistic_battery_v(state),
                "rssi": realistic_rssi(state),
                "readings": generate_readings(state, dt),
            }
        )
    return records


# ---------------------------------------------------------------------------
# Main loop
# ---------------------------------------------------------------------------

def main() -> None:
    states = [DeviceState(device_id=d["device_id"], api_key=d["api_key"]) for d in DEVICES]

    log("=== Weather station simulator dimulai ===")
    log(f"Endpoint telemetry : {TELEMETRY_URL}")
    log(f"Endpoint batch     : {BATCH_URL}")
    log(f"Device disimulasikan: {', '.join(s.device_id for s in states)}")
    log(f"Interval kirim normal: {CYCLE_SECONDS} detik")
    log("Tekan Ctrl+C untuk berhenti.\n")

    cycle = 0
    try:
        while True:
            cycle += 1
            now_dt = datetime.now()
            log(f"--- Siklus #{cycle} ({now_dt.strftime('%Y-%m-%d %H:%M:%S')}) ---")

            # 1) Skenario NORMAL — semua device kirim 1 payload
            for state in states:
                payload = build_payload(state, now_dt)
                send_telemetry(state, payload, label="NORMAL")
                state.last_payload = copy.deepcopy(payload)

            # 2) Skenario BATCH — tiap BATCH_EVERY_N_CYCLES, device pertama
            #    kirim 3 record historis sekaligus ke endpoint batch
            if cycle % BATCH_EVERY_N_CYCLES == 0:
                batch_state = states[0]
                records = build_batch_records(batch_state, now_dt, count=3)
                send_batch(batch_state, records)

            # 3) Skenario DUPLIKAT — tiap DUPLICATE_EVERY_N_CYCLES, salah satu
            #    device (bergantian) mengirim ulang payload TERAKHIRNYA persis
            #    (device_id + ts identik) untuk menguji dedup di backend
            if cycle % DUPLICATE_EVERY_N_CYCLES == 0:
                dup_index = (cycle // DUPLICATE_EVERY_N_CYCLES - 1) % len(states)
                dup_state = states[dup_index]
                if dup_state.last_payload is not None:
                    dup_payload = copy.deepcopy(dup_state.last_payload)
                    send_telemetry(dup_state, dup_payload, label="DUPLIKAT")

            log(f"--- Siklus #{cycle} selesai, tidur {CYCLE_SECONDS} detik ---\n")
            time.sleep(CYCLE_SECONDS)
    except KeyboardInterrupt:
        log("\nSimulator dihentikan oleh user (Ctrl+C). Selesai.")


if __name__ == "__main__":
    main()
