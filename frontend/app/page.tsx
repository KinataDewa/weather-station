"use client";

import { useEffect, useState } from "react";

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
  const [devices, setDevices] = useState<Device[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [lastUpdated, setLastUpdated] = useState<string>("");

  const fetchData = async () => {
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
    }
  };

  useEffect(() => {
    fetchData();
    const interval = setInterval(fetchData, 30000); // auto refresh 30 detik
    return () => clearInterval(interval);
  }, []);

  if (loading)
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-900 text-white">
        <p className="text-xl">Memuat data...</p>
      </div>
    );

  if (error)
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-900 text-red-400">
        <p className="text-xl">{error}</p>
      </div>
    );

  return (
    <main className="min-h-screen bg-gray-900 text-white p-6">
      <div className="max-w-6xl mx-auto">
        {/* Header */}
        <div className="flex justify-between items-center mb-8">
          <h1 className="text-3xl font-bold">🌤️ Weather Station Dashboard</h1>
          <div className="text-sm text-gray-400">
            <span>Terakhir diperbarui: {lastUpdated}</span>
            <button
              onClick={fetchData}
              className="ml-4 px-3 py-1 bg-blue-600 hover:bg-blue-700 rounded text-sm"
            >
              Refresh
            </button>
          </div>
        </div>

        {/* Device Cards */}
        {devices.length === 0 ? (
          <div className="text-center text-gray-400 mt-20">
            <p className="text-xl">Tidak ada device ditemukan</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {devices.map((device) => (
              <div
                key={device.id}
                className="bg-gray-800 rounded-xl p-6 border border-gray-700 hover:border-blue-500 transition"
              >
                {/* Status badge */}
                <div className="flex justify-between items-start mb-4">
                  <h2 className="text-lg font-semibold">{device.name}</h2>
                  <span
                    className={`px-2 py-1 rounded-full text-xs font-medium ${
                      device.is_online
                        ? "bg-green-900 text-green-300"
                        : "bg-red-900 text-red-300"
                    }`}
                  >
                    {device.is_online ? "● Online" : "● Offline"}
                  </span>
                </div>

                <p className="text-gray-400 text-sm mb-4">
                  📍 {device.location}
                </p>

                {/* Sensor values */}
                <div className="grid grid-cols-2 gap-4 mb-4">
                  <div className="bg-gray-700 rounded-lg p-3 text-center">
                    <p className="text-gray-400 text-xs mb-1">Suhu</p>
                    <p className="text-2xl font-bold text-orange-400">
                      {device.temp_air !== null
                        ? `${parseFloat(device.temp_air.toString()).toFixed(1)}°C`
                        : "-"}
                    </p>
                  </div>
                  <div className="bg-gray-700 rounded-lg p-3 text-center">
                    <p className="text-gray-400 text-xs mb-1">Kelembapan</p>
                    <p className="text-2xl font-bold text-blue-400">
                      {device.humidity !== null
                        ? `${parseFloat(device.humidity.toString()).toFixed(1)}%`
                        : "-"}
                    </p>
                  </div>
                </div>

                {/* Last seen */}
                <p className="text-gray-500 text-xs">
                  Update terakhir:{" "}
                  {device.last_seen_at
                    ? new Date(device.last_seen_at).toLocaleString("id-ID", {
                        timeZone: "Asia/Jakarta",
                      })
                    : "-"}
                </p>

                {/* Warning offline > 15 menit */}
                {!device.is_online && (
                  <div className="mt-3 p-2 bg-red-900/50 rounded text-red-300 text-xs">
                    ⚠️ Device tidak mengirim data lebih dari 15 menit
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
