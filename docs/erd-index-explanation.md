# Penjelasan Index — Weather Station

Dokumen ini menjelaskan setiap index/constraint yang dibuat di migration, dan query apa yang dilayaninya. Referensi skema: [erd.mmd](erd.mmd).

## `users`

- **UNIQUE(`email`)** — menjamin satu email hanya dipakai satu akun, sekaligus mempercepat lookup saat login (`WHERE email = ?`).

## `devices`

- **UNIQUE(`device_id`)** — `device_id` adalah identitas eksternal perangkat (dikirim tiap request ingestion), unique constraint ini mencegah dua device fisik terdaftar dengan kode yang sama, dan mempercepat `authenticateDevice()` yang melakukan `WHERE device_id = ?` di setiap request masuk (endpoint dengan trafik paling tinggi di sistem).
- **INDEX(`status`)** — melayani query dashboard/admin yang memfilter device berdasarkan status, misal "tampilkan semua device yang sedang `maintenance`" atau menghitung jumlah device `active` untuk ringkasan overview.
- **INDEX(`location_id`)** — melayani join/filter "semua device di lokasi X" (dipakai FK juga otomatis butuh index agar `ON DELETE RESTRICT` tidak full-scan tabel anak saat menghapus lokasi).
- **INDEX(`last_seen_at`)** — melayani query monitoring "device mana yang tidak mengirim data lebih dari N menit" (`WHERE last_seen_at < now() - interval`), dipakai untuk mendeteksi device offline.

## `device_status_histories`

- **INDEX(`device_id`, `changed_at`)** — melayani query riwayat status per device terurut waktu (`WHERE device_id = ? ORDER BY changed_at`), misal menampilkan timeline status di halaman detail device. `device_id` di depan karena selalu difilter by device tertentu; `changed_at` di belakang untuk range/order tanpa sort tambahan.

## `sensor_types`

- **UNIQUE(`code`)** — `code` (misal `temp_air`, `humidity`) dipakai sebagai kunci lookup logis dari payload sensor yang dikirim device (`s` di payload ingestion dicocokkan ke `sensor_types.code` lewat relasi). Unique constraint mencegah duplikasi tipe sensor dan mempercepat pencarian tipe saat memproses reading masuk.

## `sensors`

- **UNIQUE(`serial_number`)** — serial number adalah identitas fisik unit sensor; constraint ini mencegah dua baris mewakili unit fisik yang sama, dan mempercepat pencarian sensor berdasarkan serial saat provisioning/registrasi alat baru.
- **INDEX(`sensor_type_id`)** — melayani query "semua sensor dengan tipe X" dan mendukung FK ke `sensor_types` agar delete/restrict pada tipe sensor tidak full-scan.

## `sensor_installations`

- **INDEX(`device_id`, `installed_at`)** — melayani pencarian "sensor apa saja yang pernah/sedang terpasang di device ini", termasuk query utama di `IngestController::processReading()` yang mencari instalasi aktif pada device tertentu di waktu `ts` tertentu (`WHERE device_id = ? AND installed_at <= ts ...`).
- **INDEX(`sensor_id`, `installed_at`)** — arah sebaliknya: melayani "di device mana saja sensor ini pernah dipasang", berguna untuk audit riwayat pemasangan satu unit sensor yang mungkin dipindah-pindah antar device.

Kedua index diperlukan (bukan satu composite dua arah) karena pola akses berbeda: ingestion selalu mulai dari `device_id` (dari request), sedangkan audit/riwayat sensor mulai dari `sensor_id`.

## `sensor_calibrations`

- **INDEX(`sensor_id`, `valid_from`)** — melayani pencarian kalibrasi yang berlaku pada suatu waktu tertentu untuk sensor tertentu (`WHERE sensor_id = ? AND valid_from <= ts ORDER BY valid_from DESC LIMIT 1`), persis pola query yang dipakai `processReading()` untuk menentukan parameter kalibrasi (`offset`, `scale`) yang aktif saat reading masuk. `sensor_id` di depan (equality filter), `valid_from` di belakang untuk range + order.

## `sensor_readings`

- **UNIQUE(`device_id`, `sensor_id`, `device_time`)** — mendefinisikan satu pembacaan sensor sebagai kombinasi unik device + sensor + waktu perangkat. Dipakai langsung oleh `SensorReading::firstOrCreate()` untuk deduplikasi otomatis: kalau device mengirim ulang data yang sama (retry jaringan), insert kedua akan gagal karena melanggar constraint ini, dan ditangkap sebagai status `duplicate` alih-alih menimpa data.
- **INDEX(`device_id`, `sensor_id`, `device_time`)** — secara teknis index ini otomatis terbentuk dari unique constraint di atas (kolom sama persis), jadi index terpisah ini sebenarnya redundan di PostgreSQL karena unique constraint sudah membuat B-tree index sendiri. Query yang dilayani: histori pembacaan satu sensor di satu device dalam rentang waktu (`WHERE device_id = ? AND sensor_id = ? AND device_time BETWEEN ? AND ?`), yang merupakan query utama chart di halaman detail device. Urutan kolom mengikuti leftmost-prefix rule: `device_id` dan `sensor_id` sebagai equality filter (kardinalitas tinggi, ditaruh di depan), `device_time` di belakang untuk range scan sekaligus menghasilkan urutan waktu tanpa sort tambahan.

## `reading_aggregates`

- **UNIQUE(`device_id`, `sensor_id`, `interval`, `bucket_time`)** — satu bucket agregat (misal "device A, sensor suhu, interval 1 jam, jam 10:00") harus unik. Dipakai oleh command `readings:aggregate` yang melakukan `upsert` berdasarkan kombinasi kolom ini — kalau command dijalankan ulang untuk periode yang sama, baris lama diperbarui (bukan duplikat baru), sehingga job agregasi aman dijalankan berkali-kali (idempotent).
- **INDEX(`device_id`, `sensor_id`, `interval`, `bucket_time`)** — sama seperti pada `sensor_readings`, index ini redundan terhadap index yang otomatis dibuat oleh unique constraint di atas. Query yang dilayani: mengambil data agregat untuk chart dashboard per interval tertentu (`WHERE device_id = ? AND sensor_id = ? AND interval = ? AND bucket_time BETWEEN ? AND ?`), dipakai saat frontend menampilkan grafik histori jam/hari tanpa perlu query jutaan baris raw dari `sensor_readings`.

## Catatan umum

- Pada `sensor_readings` dan `reading_aggregates`, index eksplisit yang ditambahkan setelah `unique()` dengan kolom persis sama sebenarnya tidak menambah manfaat query di PostgreSQL (unique constraint sudah membuat index-nya sendiri) — keduanya tetap didefinisikan di migration untuk membuat intent index secara eksplisit terlihat di skema, namun bisa dihapus tanpa memengaruhi performa.
- Semua composite index di atas mengikuti prinsip yang sama: kolom dengan equality filter (ID relasi) ditaruh di depan, kolom yang dipakai untuk range/order (timestamp) ditaruh di akhir — supaya index bisa dipakai maksimal sesuai leftmost-prefix rule B-tree PostgreSQL.
