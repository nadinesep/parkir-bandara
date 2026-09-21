# Sistem Informasi Manajemen Parkir Bandara

Sistem manajemen parkir untuk layanan titip mobil di area bandara — traveler bisa reservasi slot secara online, petugas mencatat kendaraan masuk/keluar, dan admin memantau kapasitas serta laporan transaksi.

**Demo:** https://parkirbandara.infinityfreeapp.com
**Status:** Pengembangan & Debugging

> 🎓 *"Unggul, Berkarakter, Berteknologi"*
*Mockup:*[MockupBandara](https://raw.githubusercontent.com/nadinesep/parkir-bandara/refs/heads/main/parkirbandara.jpg)
*Flowchart:*[FlowchartBandara](https://raw.githubusercontent.com/nadinesep/parkir-bandara/refs/heads/main/sanden.png)
*Algoritma:*[AlgoritmaBandara](https://canva.link/hpkqb9zizc5p52j)
---


## Daftar Isi
- [Fitur Utama](#fitur-utama)
- [Peran Pengguna](#peran-pengguna)
- [Struktur File](#struktur-file)
- [Alur Sistem](#alur-sistem)
- [Skema Database](#skema-database)
- [Instalasi](#instalasi)
- [Tech Stack](#tech-stack)
- [Roadmap](#roadmap)

---

## Fitur Utama
- Cek status slot parkir secara real-time (Zona Reguler / Zona Titip Kunci)
- Reservasi online dengan input plat nomor, bandara tujuan, dan jadwal penerbangan
- Serah terima kendaraan di zona drop-off dengan pencatatan kondisi mobil
- Cetak karcis masuk otomatis dengan nomor unik (`#PB-ID`)
- Perhitungan tarif otomatis berdasarkan durasi titip dan jenis kendaraan
- Pembayaran QRIS atau tunai saat pengambilan kendaraan
- Cetak struk pembayaran dan riwayat transaksi
- Dashboard petugas untuk input kendaraan masuk/keluar
- Dashboard admin/owner untuk laporan dan kelola data master (tarif, bandara, user)

## Peran Pengguna
| Peran | Akses |
|---|---|
| **Traveler** | Cek slot, reservasi online, lihat riwayat, bayar |
| **Petugas** | Input kendaraan masuk, cetak karcis, proses keluar, cetak struk |
| **Admin / Owner** | Kelola tarif, kelola bandara & zona, lihat laporan transaksi |

## Struktur File

```
├── index.php            → Halaman utama (status slot, form cek harga)
├── login.php            → Form login
├── logout.php           → Proses logout
├── register.php         → Pendaftaran akun traveler
├── notif_login.php      → Notifikasi hasil login (sukses/gagal)
├── notif-transaksi.php  → Notifikasi status transaksi
│
├── petugas.php          → Dashboard petugas (input kendaraan, monitor slot)
├── admin.php            → Dashboard admin (laporan, kelola master data)
├── owner.php            → Dashboard owner (ringkasan bisnis)
├── Dashboard.php        → Ringkasan umum (grafik okupansi, pendapatan)
│
├── area.php             → Kelola bandara & zona parkir
├── area_helper.php      → Fungsi bantu perhitungan slot per zona
├── tarif_helper.php     → Fungsi bantu perhitungan tarif otomatis
│
├── cetak_karcis.php     → Cetak karcis kendaraan masuk
├── checkout.php         → Proses pembayaran & keluar kendaraan
├── qrisss.php           → Modul pembayaran QRIS
├── cetak_struk.php      → Cetak struk pembayaran
├── struk_lunas.php      → Tampilan struk setelah lunas
├── riwayat.php          → Riwayat reservasi & transaksi
│
├── user.php             → Kelola data pengguna
├── footer.php           → Komponen footer (reusable)
└── koneksi.php          → Koneksi ke database MySQL
```

## Alur Sistem

**Kendaraan Masuk**
1. Petugas login → input data kendaraan & jadwal penerbangan
2. Sistem mengecek ketersediaan slot pada zona yang dipilih
3. Jika tersedia → simpan data & cetak karcis (`cetak_karcis.php`)
4. Kendaraan diantar ke lahan parkir, status slot diperbarui

**Kendaraan Keluar**
1. Petugas mencari data berdasarkan nomor karcis / plat nomor
2. Sistem mengecek status penerbangan & menghitung biaya (`tarif_helper.php`)
3. Traveler membayar via QRIS (`qrisss.php`) atau tunai
4. Mobil diantar ke titik jemput, struk dicetak (`cetak_struk.php`), status diperbarui

## Skema Database

| Tabel | Keterangan |
|---|---|
| `tb_kendaraan` | PK `id_kendaraan` — plat_nomor, jenis_kendaraan, warna, pemilik |
| `tb_parkir` | PK `id_parkir`, FK `id_kendaraan` — waktu_masuk, waktu_keluar, biaya_total, status |
| `tb_tarif` | PK `id_tarif` — jenis_kendaraan, harga |
| `tb_reservasi` | PK `id_reservasi`, FK `id_user`, `id_bandara` — kode_reservasi, jadwal_penerbangan |
| `tb_bandara` | PK `id_bandara` — nama_bandara, kode_iata |
| `tb_user` | PK `id_user` — nama, role, password |

Relasi: `tb_kendaraan` 1—N `tb_parkir`, `tb_reservasi` N—1 `tb_bandara`, `tb_reservasi` N—1 `tb_user`.

## Instalasi

1. Clone atau salin seluruh file ke folder `htdocs` (XAMPP/Laragon) atau upload ke hosting (mis. InfinityFree)
2. Buat database MySQL, lalu import skema tabel sesuai [Skema Database](#skema-database)
3. Atur kredensial koneksi database di `koneksi.php`
4. Akses `index.php` melalui browser untuk memulai

## Tech Stack
- PHP native
- MySQL / MariaDB
- HTML, CSS, JavaScript
- QRIS API (payment gateway pihak ketiga)

## Roadmap
- [ ] Integrasi notifikasi status penerbangan otomatis (API maskapai/bandara)
- [ ] Perluasan cakupan ke bandara tambahan
- [ ] Aplikasi mobile untuk traveler
- [ ] Laporan pendapatan per zona & per bandara

---
*README ini dibuat berdasarkan struktur proyek Sistem Parkir Bandara, adaptasi dari struktur Sistem Parkir Mall.*
