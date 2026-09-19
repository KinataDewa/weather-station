"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";

interface Device {
  id: number;
  device_id: string;
  name: string;
  location: string;
  status: string;
  is_online: boolean;
  last_seen_at: string | null;
  temp_air: number | null;
  humidity: number | null;
}

export default function Home() {
  const router = useRouter();
  const [devices, setDevices] = useState<Device[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [lastUpdated, setLastUpdated] = useState<string>("");
  const [refreshing, setRefreshing] = useState(false);

  const fetchData = async () => {
    setRefreshing(true);
    try {
      const res = await fetch(
        "http://localhost:8000/api/v1/dashboard/overview",
      );
      if (!res.ok) throw new Error("Gagal mengambil data");
      const json = await res.json();
      setDevices(json.data);
      setLastUpdated(
        new Date().toLocaleTimeString("id-ID", { timeZone: "Asia/Jakarta" }),
      );
      setError(null);
    } catch (e) {
      setError("Gagal terhubung ke server");
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchData();
    const interval = setInterval(fetchData, 30000); // auto refresh 30 detik
    return () => clearInterval(interval);
  }, []);

  const onlineCount = devices.filter((d) => d.is_online).length;

  if (loading)
    return (
      <div className="min-h-screen flex items-center justify-center text-slate-300">
        <div className="flex flex-col items-center gap-3">
          <div className="h-10 w-10 rounded-full border-2 border-blue-500/30 border-t-blue-500 animate-spin" />
          <p className="text-sm text-slate-400">Memuat data stasiun...</p>
        </div>
      </div>
    );

  if (error)
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="glass rounded-2xl px-8 py-6 flex flex-col items-center gap-2 text-center">
          <span className="text-3xl">⚠️</span>
          <p className="text-red-400 font-medium">{error}</p>
          <button
            onClick={fetchData}
            className="mt-2 px-4 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-500 transition-colors text-sm font-medium"
          >
            Coba lagi
          </button>
        </div>
      </div>
    );

  return (
    <main className="min-h-screen">
      {/* Header */}
      <header className="sticky top-0 z-10 backdrop-blur-xl bg-slate-950/60 border-b border-white/5">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <div className="flex items-center gap-3">
            <div className="h-11 w-11 shrink-0 rounded-2xl bg-linear-to-br from-sky-400 via-blue-500 to-indigo-600 flex items-center justify-center text-2xl shadow-lg shadow-blue-500/20">
              🌤️
            </div>
            <div>
              <h1 className="text-xl sm:text-2xl font-bold tracking-tight bg-linear-to-r from-white to-slate-300 bg-clip-text text-transparent">
                Weather Station
              </h1>
              <p className="text-xs text-slate-400">
                Dashboard pemantauan cuaca real-time
              </p>
            </div>
          </div>

          <div className="flex items-center gap-3 text-sm">
            <div className="flex items-center gap-2 text-slate-400">
              <span className="relative flex h-2 w-2">
                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75" />
                <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500" />
              </span>
              <span className="hidden sm:inline">Diperbarui</span>{" "}
              {lastUpdated}
            </div>
            <button
              onClick={fetchData}
              className="px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-500 active:scale-95 transition-all text-white text-sm font-medium shadow shadow-blue-600/30 flex items-center gap-1.5"
            >
              <span
                className={refreshing ? "animate-spin inline-block" : "inline-block"}
              >
                ⟳
              </span>
              Refresh
            </button>
          </div>
        </div>
      </header>

      <div className="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
        {/* Stats strip */}
        {devices.length > 0 && (
          <div className="grid grid-cols-3 gap-3 sm:gap-4 mb-6 sm:mb-8">
            <div className="glass rounded-xl px-4 py-3 sm:py-4 text-center">
              <p className="text-xl sm:text-2xl font-bold">{devices.length}</p>
              <p className="text-[11px] sm:text-xs text-slate-400 mt-0.5">
                Total Stasiun
              </p>
            </div>
            <div className="glass rounded-xl px-4 py-3 sm:py-4 text-center">
              <p className="text-xl sm:text-2xl font-bold text-emerald-400">
                {onlineCount}
              </p>
              <p className="text-[11px] sm:text-xs text-slate-400 mt-0.5">
                Online
              </p>
            </div>
            <div className="glass rounded-xl px-4 py-3 sm:py-4 text-center">
              <p className="text-xl sm:text-2xl font-bold text-red-400">
                {devices.length - onlineCount}
              </p>
              <p className="text-[11px] sm:text-xs text-slate-400 mt-0.5">
                Offline
              </p>
            </div>
          </div>
        )}

        {/* Device Cards */}
        {devices.length === 0 ? (
          <div className="text-center text-slate-400 mt-20">
            <p className="text-4xl mb-3">🛰️</p>
            <p className="text-xl">Tidak ada device ditemukan</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 sm:gap-6">
            {devices.map((device, idx) => (
              <div
                key={device.id}
                onClick={() => router.push(`/device/${device.id}`)}
                className="group glass animate-fade-in-up rounded-2xl p-5 cursor-pointer hover:bg-white/[0.07] hover:border-white/20 hover:-translate-y-1 hover:shadow-xl hover:shadow-blue-500/10 transition-all duration-300"
                style={{ animationDelay: `${idx * 60}ms` }}
              >
                {/* Status badge */}
                <div className="flex justify-between items-start mb-4 gap-2">
                  <div className="min-w-0">
                    <h2 className="text-lg font-semibold truncate group-hover:text-blue-400 transition-colors">
                      {device.name}
                    </h2>
                    <p className="text-slate-400 text-xs mt-0.5 truncate">
                      📍 {device.location}
                    </p>
                  </div>
                  <span
                    className={`shrink-0 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border ${
                      device.is_online
                        ? "bg-emerald-500/10 text-emerald-400 border-emerald-500/20"
                        : "bg-red-500/10 text-red-400 border-red-500/20"
                    }`}
                  >
                    <span
                      className={`h-1.5 w-1.5 rounded-full ${
                        device.is_online
                          ? "bg-emerald-400 animate-soft-pulse"
                          : "bg-red-400"
                      }`}
                    />
                    {device.is_online ? "Online" : "Offline"}
                  </span>
                </div>

                {/* Sensor values */}
                <div className="grid grid-cols-2 gap-3 mb-4">
                  <div className="rounded-xl bg-black/20 border border-white/5 p-3 text-center transition-colors group-hover:border-orange-500/20">
                    <p className="text-lg mb-1">🌡️</p>
                    <p className="text-slate-400 text-[11px] mb-0.5">Suhu</p>
                    <p className="text-xl sm:text-2xl font-bold text-orange-400">
                      {device.temp_air !== null
                        ? `${parseFloat(device.temp_air.toString()).toFixed(1)}°C`
                        : "-"}
                    </p>
                  </div>
                  <div className="rounded-xl bg-black/20 border border-white/5 p-3 text-center transition-colors group-hover:border-sky-500/20">
                    <p className="text-lg mb-1">💧</p>
                    <p className="text-slate-400 text-[11px] mb-0.5">
                      Kelembapan
                    </p>
                    <p className="text-xl sm:text-2xl font-bold text-sky-400">
                      {device.humidity !== null
                        ? `${parseFloat(device.humidity.toString()).toFixed(1)}%`
                        : "-"}
                    </p>
                  </div>
                </div>

                {/* Last seen */}
                <div className="flex items-center justify-between pt-3 border-t border-white/5">
                  <p className="text-slate-500 text-[11px]">
                    {device.last_seen_at
                      ? new Date(device.last_seen_at).toLocaleString("id-ID", {
                          timeZone: "Asia/Jakarta",
                        })
                      : "Belum ada data"}
                  </p>
                  <span className="text-blue-400 text-xs opacity-0 group-hover:opacity-100 transition-opacity">
                    Detail →
                  </span>
                </div>

                {/* Warning offline > 15 menit */}
                {!device.is_online && (
                  <div className="mt-3 flex items-center gap-1.5 p-2 rounded-lg bg-red-500/10 border border-red-500/20 text-red-300 text-xs">
                    <span>⚠️</span>
                    Device tidak mengirim data lebih dari 15 menit
                  </div>
                )}
              </div>
            ))}
          </div>
        )}
      </div>
    </main>
  );
}
