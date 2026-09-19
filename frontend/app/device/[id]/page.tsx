"use client";

import { useEffect, useState, useCallback, useMemo } from "react";
import { useParams, useRouter } from "next/navigation";
import {
  LineChart,
  Line,
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
}[] = [
  { key: "temp_air", label: "Suhu Udara", unit: "°C", color: "text-orange-400" },
  { key: "humidity", label: "Kelembapan", unit: "%", color: "text-blue-400" },
  { key: "pressure", label: "Tekanan", unit: "hPa", color: "text-purple-400" },
  { key: "wind_speed", label: "Kec. Angin", unit: "m/s", color: "text-cyan-400" },
  { key: "wind_dir", label: "Arah Angin", unit: "°", color: "text-teal-400" },
  { key: "solar_rad", label: "Radiasi Matahari", unit: "W/m²", color: "text-yellow-400" },
  { key: "rain_counter", label: "Curah Hujan", unit: "mm", color: "text-indigo-400" },
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
      // TODO: debug sementara — hapus setelah chart kosong terselesaikan
      console.log(`[fetchSeries] ${sensorType} raw response:`, json);
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
    const merged = Array.from(map.values()).sort(
      (a, b) => new Date(a.bucket).getTime() - new Date(b.bucket).getTime(),
    );
    // TODO: debug sementara — hapus setelah chart kosong terselesaikan
    console.log("[chartData] tempSeries:", tempSeries);
    console.log("[chartData] humiditySeries:", humiditySeries);
    console.log("[chartData] merged:", merged);
    return merged;
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
    <main className="min-h-screen bg-gray-900 text-white p-6">
      <div className="max-w-6xl mx-auto">
        {/* Header */}
        <div className="flex justify-between items-center mb-8">
          <div>
            <button
              onClick={() => router.push("/")}
              className="mb-2 text-sm text-gray-400 hover:text-white transition flex items-center gap-1"
            >
              ← Kembali ke halaman utama
            </button>
            <h1 className="text-3xl font-bold">
              {latest?.device?.name ?? `Stasiun ${deviceId}`}
            </h1>
            {latest?.device?.location && (
              <p className="text-gray-400 text-sm mt-1">📍 {latest.device.location}</p>
            )}
          </div>
          {latest?.device && (
            <span
              className={`px-3 py-1 rounded-full text-xs font-medium ${
                latest.device.is_online
                  ? "bg-green-900 text-green-300"
                  : "bg-red-900 text-red-300"
              }`}
            >
              {latest.device.is_online ? "● Online" : "● Offline"}
            </span>
          )}
        </div>

        {/* Latest sensor panel */}
        <section className="mb-10">
          <h2 className="text-xl font-semibold mb-4">Nilai Terkini</h2>
          {loadingLatest ? (
            <div className="flex items-center justify-center py-12 text-gray-400">
              Memuat data terkini...
            </div>
          ) : errorLatest ? (
            <div className="flex items-center justify-center py-12 text-red-400">
              {errorLatest}
            </div>
          ) : !latest ? (
            <div className="flex items-center justify-center py-12 text-gray-400">
              Tidak ada data tersedia
            </div>
          ) : (
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
              {SENSOR_META.map((sensor) => (
                <div
                  key={sensor.key}
                  className="bg-gray-800 rounded-xl p-4 border border-gray-700 text-center"
                >
                  <p className="text-gray-400 text-xs mb-1">{sensor.label}</p>
                  <p className={`text-2xl font-bold ${sensor.color}`}>
                    {formatValue(latest[sensor.key])}
                    <span className="text-sm font-normal text-gray-400 ml-1">
                      {latest[sensor.key] !== null ? sensor.unit : ""}
                    </span>
                  </p>
                </div>
              ))}
            </div>
          )}
          {latest?.recorded_at && (
            <p className="text-gray-500 text-xs mt-3">
              Update terakhir:{" "}
              {new Date(latest.recorded_at).toLocaleString("id-ID", {
                timeZone: "Asia/Jakarta",
              })}
            </p>
          )}
        </section>

        {/* Chart section */}
        <section>
          <div className="flex justify-between items-center mb-4">
            <h2 className="text-xl font-semibold">Grafik Suhu &amp; Kelembapan</h2>
            <div className="flex gap-2">
              {(Object.keys(RANGE_CONFIG) as RangeOption[]).map((key) => (
                <button
                  key={key}
                  onClick={() => setRange(key)}
                  className={`px-3 py-1 rounded text-sm transition ${
                    range === key
                      ? "bg-blue-600 text-white"
                      : "bg-gray-800 text-gray-300 hover:bg-gray-700 border border-gray-700"
                  }`}
                >
                  {RANGE_CONFIG[key].label}
                </button>
              ))}
            </div>
          </div>

          <div className="bg-gray-800 rounded-xl p-4 border border-gray-700">
            {loadingChart ? (
              <div className="flex items-center justify-center h-80 text-gray-400">
                Memuat grafik...
              </div>
            ) : errorChart ? (
              <div className="flex items-center justify-center h-80 text-red-400">
                {errorChart}
              </div>
            ) : chartData.length === 0 ? (
              <div className="flex items-center justify-center h-80 text-gray-400">
                Tidak ada data untuk rentang ini
              </div>
            ) : (
              <ResponsiveContainer width="100%" height={360}>
                <LineChart data={chartData} margin={{ top: 10, right: 20, left: 0, bottom: 0 }}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#374151" />
                  <XAxis
                    dataKey="bucket"
                    tickFormatter={formatXAxis}
                    stroke="#9ca3af"
                    fontSize={12}
                  />
                  <YAxis
                    yAxisId="temp"
                    orientation="left"
                    stroke="#fb923c"
                    fontSize={12}
                    label={{ value: "°C", angle: -90, position: "insideLeft", fill: "#fb923c" }}
                  />
                  <YAxis
                    yAxisId="humidity"
                    orientation="right"
                    stroke="#60a5fa"
                    fontSize={12}
                    label={{ value: "%", angle: 90, position: "insideRight", fill: "#60a5fa" }}
                  />
                  <Tooltip
                    contentStyle={{ backgroundColor: "#1f2937", border: "1px solid #374151", borderRadius: 8 }}
                    labelFormatter={(value) =>
                      typeof value === "string"
                        ? new Date(value).toLocaleString("id-ID", { timeZone: "Asia/Jakarta" })
                        : value
                    }
                  />
                  <Legend />
                  <Line
                    yAxisId="temp"
                    type="monotone"
                    dataKey="temp"
                    name="Suhu (°C)"
                    stroke="#fb923c"
                    dot={false}
                    connectNulls
                    strokeWidth={2}
                  />
                  <Line
                    yAxisId="humidity"
                    type="monotone"
                    dataKey="humidity"
                    name="Kelembapan (%)"
                    stroke="#60a5fa"
                    dot={false}
                    connectNulls
                    strokeWidth={2}
                  />
                </LineChart>
              </ResponsiveContainer>
            )}
          </div>
        </section>
      </div>
    </main>
  );
}
