"use client";

import { useEffect, useState, useCallback, useMemo } from "react";
import { useParams, useRouter } from "next/navigation";
import {
  ComposedChart,
  Area,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  ResponsiveContainer,
} from "recharts";

interface LatestReading {
  temp_air: number | null;
  humidity: number | null;
  pressure: number | null;
  wind_speed: number | null;
  wind_dir: number | null;
  solar_rad: number | null;
  rain_counter: number | null;
  recorded_at?: string | null;
  device?: {
    id: number;
    name: string;
    location: string;
    is_online: boolean;
  };
}

interface ReadingPoint {
  bucket: string;
  value: number | null;
}

type RangeOption = "24h" | "7d" | "30d";

const RANGE_CONFIG: Record<
  RangeOption,
  { label: string; hours: number; interval: "1h" | "1d" }
> = {
  "24h": { label: "24 Jam", hours: 24, interval: "1h" },
  "7d": { label: "7 Hari", hours: 24 * 7, interval: "1h" },
  "30d": { label: "30 Hari", hours: 24 * 30, interval: "1d" },
};

const SENSOR_META: {
  key: keyof Omit<LatestReading, "recorded_at" | "device">;
  label: string;
  unit: string;
  color: string;
  icon: string;
}[] = [
  { key: "temp_air", label: "Suhu Udara", unit: "°C", color: "text-orange-400", icon: "🌡️" },
  { key: "humidity", label: "Kelembapan", unit: "%", color: "text-sky-400", icon: "💧" },
  { key: "pressure", label: "Tekanan", unit: "hPa", color: "text-purple-400", icon: "🌀" },
  { key: "wind_speed", label: "Kec. Angin", unit: "m/s", color: "text-cyan-400", icon: "💨" },
  { key: "wind_dir", label: "Arah Angin", unit: "°", color: "text-teal-400", icon: "🧭" },
  { key: "solar_rad", label: "Radiasi Matahari", unit: "W/m²", color: "text-yellow-400", icon: "☀️" },
  { key: "rain_counter", label: "Curah Hujan", unit: "mm", color: "text-indigo-400", icon: "🌧️" },
];

function formatValue(v: number | null) {
  if (v === null || v === undefined) return "-";
  return parseFloat(v.toString()).toFixed(1);
}

export default function DeviceDetail() {
  const params = useParams();
  const router = useRouter();
  const deviceId = params?.id as string;

  const [latest, setLatest] = useState<LatestReading | null>(null);
  const [loadingLatest, setLoadingLatest] = useState(true);
  const [errorLatest, setErrorLatest] = useState<string | null>(null);

  const [tempSeries, setTempSeries] = useState<ReadingPoint[]>([]);
  const [humiditySeries, setHumiditySeries] = useState<ReadingPoint[]>([]);
  const [loadingChart, setLoadingChart] = useState(true);
  const [errorChart, setErrorChart] = useState<string | null>(null);

  const [range, setRange] = useState<RangeOption>("24h");

  const fetchLatest = useCallback(async () => {
    try {
      const res = await fetch(
        `http://localhost:8000/api/v1/devices/${deviceId}/readings/latest`,
      );
      if (!res.ok) throw new Error("Gagal mengambil data terkini");
      const json = await res.json();
      setLatest(json.data ?? json);
      setErrorLatest(null);
    } catch {
      setErrorLatest("Gagal terhubung ke server");
    } finally {
      setLoadingLatest(false);
    }
  }, [deviceId]);

  const fetchSeries = useCallback(
    async (sensorType: string, from: string, to: string, interval: string) => {
      const url = `http://localhost:8000/api/v1/readings?device_id=${deviceId}&sensor_type=${sensorType}&from=${from}&to=${to}&interval=${interval}&agg=avg`;
      const res = await fetch(url);
      if (!res.ok) throw new Error("Gagal mengambil data chart");
      const json = await res.json();
      const rows = (json.data ?? json) as Record<string, unknown>[];
      return rows.map((row) => {
        const bucket = (row.ts ?? row.bucket ?? row.timestamp ?? row.time ?? row.recorded_at) as string;
        const rawValue = row.value !== undefined ? row.value : row.avg;
        const value =
          rawValue === null || rawValue === undefined ? null : parseFloat(rawValue as string);
        return {
          bucket,
          value: value !== null && Number.isNaN(value) ? null : value,
        };
      }) as ReadingPoint[];
    },
    [deviceId],
  );

  const fetchChartData = useCallback(async () => {
    setLoadingChart(true);
    try {
      const { hours, interval } = RANGE_CONFIG[range];
      const to = new Date();
      const from = new Date(to.getTime() - hours * 60 * 60 * 1000);

      const [tempData, humidityData] = await Promise.all([
        fetchSeries("temp_air", from.toISOString(), to.toISOString(), interval),
        fetchSeries("humidity", from.toISOString(), to.toISOString(), interval),
      ]);

      setTempSeries(tempData);
      setHumiditySeries(humidityData);
      setErrorChart(null);
    } catch {
      setErrorChart("Gagal memuat data grafik");
      setTempSeries([]);
      setHumiditySeries([]);
    } finally {
      setLoadingChart(false);
    }
  }, [range, fetchSeries]);

  useEffect(() => {
    fetchLatest();
    const interval = setInterval(fetchLatest, 30000);
    return () => clearInterval(interval);
  }, [fetchLatest]);

  useEffect(() => {
    fetchChartData();
  }, [fetchChartData]);

  const chartData = useMemo(() => {
    const map = new Map<string, { bucket: string; temp: number | null; humidity: number | null }>();
    for (const point of tempSeries) {
      map.set(point.bucket, { bucket: point.bucket, temp: point.value, humidity: null });
    }
    for (const point of humiditySeries) {
      const existing = map.get(point.bucket);
      if (existing) {
        existing.humidity = point.value;
      } else {
        map.set(point.bucket, { bucket: point.bucket, temp: null, humidity: point.value });
      }
    }
    return Array.from(map.values()).sort(
      (a, b) => new Date(a.bucket).getTime() - new Date(b.bucket).getTime(),
    );
  }, [tempSeries, humiditySeries]);

  const formatXAxis = (value: string) => {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    if (range === "30d") {
      return date.toLocaleDateString("id-ID", { day: "2-digit", month: "short", timeZone: "Asia/Jakarta" });
    }
    return date.toLocaleTimeString("id-ID", { hour: "2-digit", minute: "2-digit", timeZone: "Asia/Jakarta" });
  };

  return (
    <main className="min-h-screen">
      {/* Header */}
      <header className="sticky top-0 z-10 backdrop-blur-xl bg-slate-950/60 border-b border-white/5">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <div className="min-w-0">
            <button
              onClick={() => router.push("/")}
              className="mb-2 text-sm text-slate-400 hover:text-white transition-colors flex items-center gap-1"
            >
              ← Kembali ke halaman utama
            </button>
            <h1 className="text-xl sm:text-2xl font-bold tracking-tight truncate bg-linear-to-r from-white to-slate-300 bg-clip-text text-transparent">
              {latest?.device?.name ?? `Stasiun ${deviceId}`}
            </h1>
            {latest?.device?.location && (
              <p className="text-slate-400 text-sm mt-1">📍 {latest.device.location}</p>
            )}
          </div>
          {latest?.device && (
            <span
              className={`shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium border ${
                latest.device.is_online
                  ? "bg-emerald-500/10 text-emerald-400 border-emerald-500/20"
                  : "bg-red-500/10 text-red-400 border-red-500/20"
              }`}
            >
              <span
                className={`h-1.5 w-1.5 rounded-full ${
                  latest.device.is_online
                    ? "bg-emerald-400 animate-soft-pulse"
                    : "bg-red-400"
                }`}
              />
              {latest.device.is_online ? "Online" : "Offline"}
            </span>
          )}
        </div>
      </header>

      <div className="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
        {/* Latest sensor panel */}
        <section className="mb-10">
          <h2 className="text-lg sm:text-xl font-semibold mb-4 flex items-center gap-2">
            <span>📊</span> Nilai Terkini
          </h2>
          {loadingLatest ? (
            <div className="flex items-center justify-center py-12 text-slate-400 gap-3">
              <div className="h-6 w-6 rounded-full border-2 border-blue-500/30 border-t-blue-500 animate-spin" />
              Memuat data terkini...
            </div>
          ) : errorLatest ? (
            <div className="flex items-center justify-center py-12 text-red-400">
              ⚠️ {errorLatest}
            </div>
          ) : !latest ? (
            <div className="flex items-center justify-center py-12 text-slate-400">
              Tidak ada data tersedia
            </div>
          ) : (
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
              {SENSOR_META.map((sensor, idx) => (
                <div
                  key={sensor.key}
                  className="glass animate-fade-in-up rounded-2xl p-4 text-center hover:bg-white/[0.07] hover:-translate-y-0.5 transition-all duration-300"
                  style={{ animationDelay: `${idx * 50}ms` }}
                >
                  <p className="text-2xl mb-1.5">{sensor.icon}</p>
                  <p className="text-slate-400 text-xs mb-1">{sensor.label}</p>
                  <p className={`text-xl sm:text-2xl font-bold ${sensor.color}`}>
                    {formatValue(latest[sensor.key])}
                    <span className="text-sm font-normal text-slate-400 ml-1">
                      {latest[sensor.key] !== null ? sensor.unit : ""}
                    </span>
                  </p>
                </div>
              ))}
            </div>
          )}
          {latest?.recorded_at && (
            <p className="text-slate-500 text-xs mt-4">
              Update terakhir:{" "}
              {new Date(latest.recorded_at).toLocaleString("id-ID", {
                timeZone: "Asia/Jakarta",
              })}
            </p>
          )}
        </section>

        {/* Chart section */}
        <section>
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <h2 className="text-lg sm:text-xl font-semibold flex items-center gap-2">
              <span>📈</span> Grafik Suhu &amp; Kelembapan
            </h2>
            <div className="inline-flex rounded-xl bg-black/20 border border-white/10 p-1 gap-1 self-start sm:self-auto">
              {(Object.keys(RANGE_CONFIG) as RangeOption[]).map((key) => (
                <button
                  key={key}
                  onClick={() => setRange(key)}
                  className={`px-3 py-1.5 rounded-lg text-xs sm:text-sm font-medium transition-all ${
                    range === key
                      ? "bg-blue-600 text-white shadow shadow-blue-600/30"
                      : "text-slate-400 hover:text-white hover:bg-white/5"
                  }`}
                >
                  {RANGE_CONFIG[key].label}
                </button>
              ))}
            </div>
          </div>

          <div className="glass rounded-2xl p-3 sm:p-5">
            {loadingChart ? (
              <div className="flex items-center justify-center h-80 sm:h-105 text-slate-400 gap-3">
                <div className="h-6 w-6 rounded-full border-2 border-blue-500/30 border-t-blue-500 animate-spin" />
                Memuat grafik...
              </div>
            ) : errorChart ? (
              <div className="flex items-center justify-center h-80 sm:h-105 text-red-400">
                ⚠️ {errorChart}
              </div>
            ) : chartData.length === 0 ? (
              <div className="flex items-center justify-center h-80 sm:h-105 text-slate-400">
                Tidak ada data untuk rentang ini
              </div>
            ) : (
              <ResponsiveContainer width="100%" height={420}>
                <ComposedChart data={chartData} margin={{ top: 10, right: 20, left: 0, bottom: 0 }}>
                  <defs>
                    <linearGradient id="tempGradient" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%" stopColor="#fb923c" stopOpacity={0.35} />
                      <stop offset="95%" stopColor="#fb923c" stopOpacity={0} />
                    </linearGradient>
                    <linearGradient id="humidityGradient" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%" stopColor="#38bdf8" stopOpacity={0.35} />
                      <stop offset="95%" stopColor="#38bdf8" stopOpacity={0} />
                    </linearGradient>
                  </defs>
                  <CartesianGrid strokeDasharray="3 3" stroke="#1e293b" vertical={false} />
                  <XAxis
                    dataKey="bucket"
                    tickFormatter={formatXAxis}
                    stroke="#64748b"
                    fontSize={12}
                    tickLine={false}
                  />
                  <YAxis
                    yAxisId="temp"
                    orientation="left"
                    stroke="#fb923c"
                    fontSize={12}
                    tickLine={false}
                    axisLine={false}
                    label={{ value: "°C", angle: -90, position: "insideLeft", fill: "#fb923c" }}
                  />
                  <YAxis
                    yAxisId="humidity"
                    orientation="right"
                    stroke="#38bdf8"
                    fontSize={12}
                    tickLine={false}
                    axisLine={false}
                    label={{ value: "%", angle: 90, position: "insideRight", fill: "#38bdf8" }}
                  />
                  <Tooltip
                    contentStyle={{
                      backgroundColor: "rgba(15, 23, 42, 0.9)",
                      border: "1px solid rgba(255,255,255,0.1)",
                      borderRadius: 12,
                      backdropFilter: "blur(8px)",
                    }}
                    labelStyle={{ color: "#e2e8f0" }}
                    labelFormatter={(value) =>
                      typeof value === "string"
                        ? new Date(value).toLocaleString("id-ID", { timeZone: "Asia/Jakarta" })
                        : value
                    }
                  />
                  <Legend />
                  <Area
                    yAxisId="temp"
                    type="monotone"
                    dataKey="temp"
                    name="Suhu (°C)"
                    stroke="#fb923c"
                    strokeWidth={2.5}
                    fill="url(#tempGradient)"
                    dot={false}
                    connectNulls
                    activeDot={{ r: 5 }}
                  />
                  <Area
                    yAxisId="humidity"
                    type="monotone"
                    dataKey="humidity"
                    name="Kelembapan (%)"
                    stroke="#38bdf8"
                    strokeWidth={2.5}
                    fill="url(#humidityGradient)"
                    dot={false}
                    connectNulls
                    activeDot={{ r: 5 }}
                  />
                </ComposedChart>
              </ResponsiveContainer>
            )}
          </div>
        </section>
      </div>
    </main>
  );
}
