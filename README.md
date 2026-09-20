# Weather Station

Sistem pemantauan cuaca berbasis IoT: perangkat (device) mengirim data sensor (suhu, kelembapan, tekanan, curah hujan, arah/kecepatan angin, dll) ke backend melalui REST API, yang kemudian ditampilkan di dashboard web secara near real-time.

## 1. Cara Menjalankan

### Prasyarat
- Docker & Docker Compose
- Python 3 (untuk simulator perangkat)

### Jalankan seluruh stack

```bash
git clone <repo>
cd weather-station
cp .env.example .env
docker compose up --build
```

Setelah container siap, aplikasi bisa diakses di:

- **Frontend**: http://localhost:3000
- **Backend API**: http://localhost:8000
- **Contoh endpoint API**: http://localhost:8000/api/v1/dashboard/overview

Seeder database otomatis dijalankan saat container backend pertama kali dibuat. Kalau perlu menjalankan migrasi + seeder secara manual (misal setelah reset volume database):

```bash
docker exec weather_backend php artisan migrate --seed
```

### Menjalankan simulator perangkat

Simulator mengirim data sensor palsu ke API secara berkala, untuk mensimulasikan perangkat cuaca sungguhan.

```bash
pip install requests
python simulator.py
```

### Menjalankan unit test

```bash
docker exec weather_backend php artisan test
```

## 2. Arsitektur Singkat

| Komponen    | Teknologi                                    |
|-------------|-----------------------------------------------|
| Backend     | Laravel 13 (PHP 8.4) — REST API               |
| Frontend    | Next.js 16 (TypeScript, Tailwind, Recharts)   |
| Database    | PostgreSQL 16                                 |
| Deployment  | Docker + docker-compose                       |

Seluruh service (database, backend, frontend) dijalankan dengan satu perintah `docker compose up`, tanpa perlu instalasi dependency manual di host selain Docker.

Alur data secara garis besar: perangkat/simulator → `POST` ke endpoint ingestion backend (autentikasi via API key per device) → data divalidasi & dikalibrasi → disimpan sebagai raw reading di database → dashboard frontend menampilkan data terkini dan histori lewat REST API.

## 3. Keputusan Desain & Trade-off

- **Format narrow/long, bukan wide** — satu baris per (device, sensor, waktu) alih-alih satu baris berisi semua sensor per timestamp. Ini membuat penambahan jenis sensor baru tidak memerlukan perubahan skema tabel, dan lebih alami untuk query per-sensor, dengan trade-off jumlah baris yang jauh lebih banyak.
- **Timestamp disimpan UTC, ditampilkan WIB di frontend** — menghindari ambiguitas zona waktu di sisi backend/database, konversi ke waktu lokal hanya dilakukan di layer presentasi.
- **Dedup via unique constraint** `(device_id, sensor_id, device_time)` — mencegah data duplikat akibat retry pengiriman dari perangkat tanpa perlu logika dedup tambahan di aplikasi.
- **Kalibrasi disimpan terpisah** dari `raw_value` — nilai mentah sensor tidak pernah diubah; hasil kalibrasi disimpan sebagai `calibrated_value` dan riwayat parameter kalibrasi (`valid_from`, offset, scale) tersimpan sebagai baris baru, sehingga histori kalibrasi tetap bisa ditelusuri.
- **Agregasi manual via artisan command** (`readings:aggregate`) sebagai pengganti continuous aggregate TimescaleDB, karena stack ini memakai PostgreSQL biasa. Trade-off-nya adalah agregat tidak real-time, tergantung frekuensi job dijalankan.
- **API key di-hash dengan bcrypt**, tidak disimpan plaintext di database — mengikuti praktik standar penyimpanan credential, walau berarti verifikasi tiap request butuh sedikit overhead hashing.
- **Batch dibatasi 200 record per request** — mencegah satu request ingestion terlalu besar/lambat, dengan trade-off perangkat yang punya backlog data besar perlu mengirim beberapa request bertahap.

## 4. Yang Belum Sempat Dikerjakan

Karena keterbatasan waktu pengerjaan, beberapa hal berikut belum diimplementasikan:

- Rate limiting per device pada endpoint ingestion
- Dukungan protokol MQTT (saat ini hanya HTTP)
- WebSocket/SSE untuk update real-time (saat ini frontend melakukan polling setiap 30 detik)
- Wind rose chart untuk visualisasi arah angin
- Export data ke CSV
- Sistem alert (misal notifikasi curah hujan tinggi)
- CI/CD pipeline
