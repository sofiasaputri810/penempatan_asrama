# Sistem Penempatan Penghuni Asrama 

Sistem Pendukung Keputusan (SPK) berbasis web untuk mengoptimalkan penempatan penghuni Asrama menggunakan metode Fuzzy Analytic Hierarchy Process (Fuzzy AHP). Sistem ini menggantikan proses penempatan manual yang subjektif dengan perhitungan bobot kriteria yang terukur dan objektif, serta penempatan otomatis berdasarkan skor tertinggi dan ketersediaan kuota di setiap zona hunian.

## Fitur

- **Login Administrator** – Autentikasi menggunakan username dan password, verifikasi terhadap database, dan redirect ke dashboard setelah berhasil login.
- **Dashboard** – Ringkasan total penghuni, total kuota, dan penghuni belum ditempatkan; monitoring status hunian real-time per zona (A, B, C); visualisasi ketersediaan kuota setiap zona; akses cepat ke menu pengelolaan.
- **Manajemen Data Penghuni** – Tambah, edit, hapus, dan cari data penghuni berdasarkan nama, NIP, atau pangkat. Data yang dikelola meliputi NIP, Nama, Pangkat, Mobilitas Tugas, Jumlah Keluarga, Kondisi Kesehatan, dan Status Penempatan.
- **Pengelolaan Kriteria** – Input dan pengaturan bobot setiap kriteria, transparansi perhitungan bobot, serta kemampuan recalculate bobot kriteria.
- **Perhitungan Fuzzy AHP** – Hitung skor fuzzy otomatis berdasarkan 5 kriteria, penempatan otomatis berdasarkan skor tertinggi dan kuota, reset data untuk mengulang perhitungan, serta feedback status perhitungan dan jumlah penghuni belum ditempatkan.
- **Hasil Penempatan** – Visualisasi hasil penempatan per zona, tabel detail penghuni dengan skor dan status penempatan, serta distribusi penghuni dalam bentuk chart.

## Kriteria Penilaian

| Kode | Kriteria | Deskripsi |
|------|----------|-----------|
| DM | Duty Mobility | Mobilitas tugas militer |
| R | Rank | Tingkat kepangkatan |
| NFM | Number of Family Members | Jumlah anggota keluarga |
| HC | Health Condition | Kondisi kesehatan |
| HL | Home Location | Jarak lokasi rumah saat ini |

## Aturan Penempatan

| Zona | Kuota | Kriteria Penempatan |
|------|-------|---------------------|
| Lokasi A (Depan) | 10 unit | 10 ranking teratas |
| Lokasi B (Tengah) | 19 unit | 19 ranking berikutnya |
| Lokasi C (Belakang) | 21 unit | 21 ranking berikutnya |
| Total | 50 unit | |

## Teknologi

| Komponen | Teknologi |
|----------|-----------|
| Frontend | HTML, CSS, JavaScript |
| Backend | PHP |
| Database | MySQL |
| Metode | Fuzzy AHP |
| Platform | Web Browser (Cross-platform) |

## Alur Penggunaan
Login Admin → Dashboard → Kelola Data Penghuni / Atur Kriteria & Bobot
→ Hitung Skor Fuzzy → Penempatan Otomatis → Lihat Hasil Penempatan
