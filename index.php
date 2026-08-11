<?php
include 'koneksi.php';
$pesan         = "";
$pesan_komentar = "";

// ==========================================
// AUTO-CREATE TABEL KOMENTAR & RATING
// ==========================================
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `komentar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) NOT NULL,
  `rating` int(1) NOT NULL,
  `komentar` text NOT NULL,
  `tanggal` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// ==========================================
// HANDLE SUBMIT: FORM RESERVASI
// ==========================================
if (isset($_POST['simpan_reservasi'])) {
    $nama      = mysqli_real_escape_string($conn, $_POST['nama']);
    $plat      = mysqli_real_escape_string($conn, $_POST['plat']);
    $kendaraan = mysqli_real_escape_string($conn, $_POST['kendaraan']);
    $kategori  = mysqli_real_escape_string($conn, $_POST['kategori']);
    $durasi    = intval($_POST['durasi']);
    $tanggal   = mysqli_real_escape_string($conn, $_POST['tanggal']);
    $waktu     = mysqli_real_escape_string($conn, $_POST['waktu']);

    // Pastikan kolom "kategori" sudah ada di tabel reservasi (auto-migrate)
    $cek_kolom = mysqli_query($conn, "SHOW COLUMNS FROM reservasi LIKE 'kategori'");
    if ($cek_kolom && mysqli_num_rows($cek_kolom) == 0) {
        mysqli_query($conn, "ALTER TABLE reservasi ADD COLUMN kategori VARCHAR(20) NOT NULL DEFAULT 'reguler' AFTER kendaraan");
    }

    // Matriks tarif per jam: [jenis kendaraan][kategori]
    $tarif_matrix = [
        'motor' => ['reguler' => 2000,  'vip' => 5000,  'vvip' => 8000],
        'mobil' => ['reguler' => 5000,  'vip' => 15000, 'vvip' => 25000],
        'bis'   => ['reguler' => 10000, 'vip' => 20000, 'vvip' => 35000],
        'truk'  => ['reguler' => 12000, 'vip' => 25000, 'vvip' => 40000],
    ];
    $tarif_per_jam = $tarif_matrix[$kendaraan][$kategori] ?? 5000;
    $total_biaya   = $tarif_per_jam * $durasi;

    $query = "INSERT INTO reservasi (nama, plat, kendaraan, kategori, durasi, tanggal, waktu, total_biaya, status_bayar) 
              VALUES ('$nama', '$plat', '$kendaraan', '$kategori', $durasi, '$tanggal', '$waktu', $total_biaya, 'Lunas')";
    
    if (mysqli_query($conn, $query)) {
        $pesan = "Reservasi berhasil disimpan! Atas nama <b>$nama</b> (Plat: $plat). Total: Rp " . number_format($total_biaya, 0, ',', '.');
    } else {
        $pesan = "Gagal menyimpan data: " . mysqli_error($conn);
    }
}

// ==========================================
// HANDLE SUBMIT: FORM KOMENTAR & RATING
// ==========================================
if (isset($_POST['simpan_komentar'])) {
    $nama_k     = mysqli_real_escape_string($conn, $_POST['nama_komentar']);
    $rating     = intval($_POST['rating']);
    $isi_k      = mysqli_real_escape_string($conn, $_POST['isi_komentar']);

    // Batasi rating antara 1 - 5
    if ($rating < 1) $rating = 1;
    if ($rating > 5) $rating = 5;

    $query_k = "INSERT INTO komentar (nama, rating, komentar) VALUES ('$nama_k', $rating, '$isi_k')";

    if (mysqli_query($conn, $query_k)) {
        $pesan_komentar = "Terima kasih, ulasan Anda berhasil dikirim!";
    } else {
        $pesan_komentar = "Gagal mengirim ulasan: " . mysqli_error($conn);
    }
}

// ==========================================
// AMBIL DATA KETERSEDIAAN SLOT DARI area_parkir (REAL-TIME)
// Dikelompokkan menjadi 3 kategori berdasarkan kata kunci nama area:
// - Motor       : nama area mengandung "Motor"
// - VIP & Valet : nama area mengandung "VIP", "Eksekutif", atau "Valet"
// - Mobil/Reg   : sisanya (Mobil, Bus/Truk, Terminal umum, dll)
// ==========================================
function ambil_slot_kategori($conn, $like_patterns) {
    $where = [];
    foreach ($like_patterns as $p) {
        $where[] = "nama_area LIKE '%$p%'";
    }
    $where_sql = implode(' OR ', $where);
    $q = mysqli_query($conn, "SELECT SUM(total_slot) as total, SUM(terisi) as terisi FROM area_parkir WHERE $where_sql");
    $total  = 0;
    $terisi = 0;
    if ($q) {
        $d = mysqli_fetch_assoc($q);
        $total  = intval($d['total'] ?? 0);
        $terisi = intval($d['terisi'] ?? 0);
    }
    return ['total' => $total, 'terisi' => $terisi];
}

$slot_motor = ambil_slot_kategori($conn, ['Motor']);
$slot_vip   = ambil_slot_kategori($conn, ['VIP', 'Eksekutif', 'Valet']);

// Mobil/Regular = semua area YANG BUKAN motor & BUKAN VIP (Mobil, Bus/Truk, Terminal umum, dll)
$q_mobil = mysqli_query($conn, "SELECT SUM(total_slot) as total, SUM(terisi) as terisi FROM area_parkir 
    WHERE nama_area NOT LIKE '%Motor%' 
    AND nama_area NOT LIKE '%VIP%' 
    AND nama_area NOT LIKE '%Eksekutif%' 
    AND nama_area NOT LIKE '%Valet%'");
$slot_mobil = ['total' => 0, 'terisi' => 0];
if ($q_mobil) {
    $d_mobil = mysqli_fetch_assoc($q_mobil);
    $slot_mobil['total']  = intval($d_mobil['total'] ?? 0);
    $slot_mobil['terisi'] = intval($d_mobil['terisi'] ?? 0);
}

// Fungsi bantu: hitung persentase & tentukan label/warna status
function status_slot($total, $terisi) {
    $sisa   = max(0, $total - $terisi);
    $persen = ($total > 0) ? round(($terisi / $total) * 100) : 0;

    if ($persen >= 90) {
        $label = 'Hampir Penuh';
        $warna_teks = 'text-rose-600';
        $warna_bar  = 'bg-rose-600';
    } elseif ($persen >= 70) {
        $label = 'Hampir Penuh';
        $warna_teks = 'text-amber-600';
        $warna_bar  = 'bg-amber-500';
    } elseif ($persen >= 40) {
        $label = 'Tersedia';
        $warna_teks = 'text-emerald-600';
        $warna_bar  = 'bg-blue-600';
    } else {
        $label = 'Sangat Luas';
        $warna_teks = 'text-emerald-600';
        $warna_bar  = 'bg-emerald-600';
    }

    return [
        'sisa'   => $sisa,
        'persen' => $persen,
        'label'  => $label,
        'warna_teks' => $warna_teks,
        'warna_bar'  => $warna_bar
    ];
}

$status_mobil = status_slot($slot_mobil['total'], $slot_mobil['terisi']);
$status_vip   = status_slot($slot_vip['total'], $slot_vip['terisi']);
$status_motor = status_slot($slot_motor['total'], $slot_motor['terisi']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Parkir Bandara Internasional</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        /* ==========================================
           ANIMASI PESAWAT TAKE-OFF DI HERO SECTION
           ========================================== */
        #home { overflow: hidden; }

        .plane-wrapper {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 3;
        }

        .plane-svg {
            position: absolute;
            width: 90px;
            height: auto;
            left: -12%;
            bottom: 18%;
            animation: takeOff 9s cubic-bezier(0.45, 0, 0.55, 1) infinite;
            filter: drop-shadow(0 6px 10px rgba(0,0,0,0.35));
        }

        /* Jejak asap di belakang pesawat */
        .plane-trail {
            position: absolute;
            left: -30%;
            bottom: 19%;
            width: 260px;
            height: 6px;
            border-radius: 999px;
            background: linear-gradient(to left, rgba(255,255,255,0.55), rgba(255,255,255,0));
            animation: takeOffTrail 9s cubic-bezier(0.45, 0, 0.55, 1) infinite;
            filter: blur(1px);
        }

        @keyframes takeOff {
            0%   { left: -12%;  bottom: 16%; transform: rotate(0deg)   scale(0.85); opacity: 0; }
            8%   { opacity: 1; }
            35%  { left: 38%;   bottom: 26%; transform: rotate(-8deg)  scale(1); }
            65%  { left: 70%;   bottom: 46%; transform: rotate(-16deg) scale(1.05); }
            92%  { opacity: 1; }
            100% { left: 112%;  bottom: 68%; transform: rotate(-16deg) scale(1.1); opacity: 0; }
        }

        @keyframes takeOffTrail {
            0%   { left: -30%; bottom: 17%;  transform: rotate(0deg);   opacity: 0; }
            8%   { opacity: 0.8; }
            35%  { left: 18%;  bottom: 27%;  transform: rotate(-8deg); }
            65%  { left: 50%;  bottom: 47%;  transform: rotate(-16deg); }
            92%  { opacity: 0.7; }
            100% { left: 92%;  bottom: 69%;  transform: rotate(-16deg); opacity: 0; }
        }

        /* Pesawat kedua, terbang berlawanan arah (kanan ke kiri, melintas mendatar) */
        .plane-svg-2 {
            position: absolute;
            width: 60px;
            height: auto;
            right: -12%;
            top: 20%;
            animation: crossOver 11s cubic-bezier(0.45, 0, 0.55, 1) infinite;
            animation-delay: 3.5s;
            filter: drop-shadow(0 6px 10px rgba(0,0,0,0.35));
            opacity: 0;
        }

        .plane-trail-2 {
            position: absolute;
            right: -30%;
            top: 22.5%;
            width: 170px;
            height: 4px;
            border-radius: 999px;
            background: linear-gradient(to right, rgba(255,255,255,0.5), rgba(255,255,255,0));
            animation: crossOverTrail 11s cubic-bezier(0.45, 0, 0.55, 1) infinite;
            animation-delay: 3.5s;
            filter: blur(1px);
            opacity: 0;
        }

        @keyframes crossOver {
            0%   { right: -12%; top: 32%; transform: scaleX(-1) rotate(4deg)  scale(0.75); opacity: 0; }
            10%  { opacity: 0.95; }
            50%  { right: 46%;  top: 14%; transform: scaleX(-1) rotate(-2deg) scale(0.9); }
            90%  { opacity: 0.95; }
            100% { right: 112%; top: 8%;  transform: scaleX(-1) rotate(-6deg) scale(1); opacity: 0; }
        }

        @keyframes crossOverTrail {
            0%   { right: -30%; top: 34.5%; transform: rotate(4deg);  opacity: 0; }
            10%  { opacity: 0.6; }
            50%  { right: 26%;  top: 16.5%; transform: rotate(-2deg); }
            90%  { opacity: 0.6; }
            100% { right: 92%;  top: 10.5%; transform: rotate(-6deg); opacity: 0; }
        }

        /* Awan kecil dekoratif yang melayang pelan di latar */
        .cloud {
            position: absolute;
            color: rgba(255,255,255,0.18);
            animation: driftCloud linear infinite;
        }
        @keyframes driftCloud {
            0%   { transform: translateX(0); }
            100% { transform: translateX(-140px); }
        }
    </style>
</head>
<body class="bg-slate-50 font-sans text-slate-800">

    <!-- Header / Navbar -->
    <header class="bg-blue-900 text-white shadow-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <i class="fa-solid fa-plane-departure text-2xl text-blue-400"></i>
                <span class="text-xl font-bold tracking-wide">AeroParking<span class="text-blue-400">.</span></span>
            </div>
            <nav class="hidden md:flex space-x-6 text-sm font-medium items-center">
                <a href="#home" class="hover:text-blue-300 transition">Beranda</a>
                <a href="#status" class="hover:text-blue-300 transition">Ketersediaan Slot</a>
                <a href="#tatacara" class="hover:text-blue-300 transition">Tata Cara</a>
                <a href="#booking" class="hover:text-blue-300 transition">Reservasi Online</a>
                <a href="#ulasan" class="hover:text-blue-300 transition">Ulasan</a>
                <a href="#bantuan" class="hover:text-blue-300 transition">Pusat Bantuan</a>
                <a href="admin.php" class="bg-amber-500 hover:bg-amber-600 text-slate-900 px-3 py-1.5 rounded-lg font-bold transition"><i class="fa-solid fa-lock mr-1"></i> Admin Panel</a>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section id="home"
        class="relative text-white py-24 px-4 bg-cover bg-center bg-no-repeat"
        style="background-image: linear-gradient(rgba(15, 23, 90, 0.78), rgba(30, 27, 130, 0.82)), url('https://images.unsplash.com/photo-1734387261199-4a4090bf78d7?auto=format&fit=crop&w=1920&q=80');">

        <!-- Awan dekoratif -->
        <i class="fa-solid fa-cloud cloud text-5xl" style="top:10%; left:70%; animation-duration:38s;"></i>
        <i class="fa-solid fa-cloud cloud text-4xl" style="top:22%; left:20%; animation-duration:46s;"></i>
        <i class="fa-solid fa-cloud cloud text-3xl" style="top:6%; left:45%; animation-duration:30s;"></i>

        <!-- Animasi pesawat take-off -->
        <div class="plane-wrapper">
            <div class="plane-trail"></div>
            <svg class="plane-svg" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
                <path fill="#ffffff" d="M482.3 192c34.2 0 61.7 27.5 61.7 61.5c0 34.2-27.5 61.7-61.7 61.7h-98.5L269.7 469.1c-5.7 8.2-15 13-25 13h-46c-11.8 0-20.4-11.1-17.5-22.5l45.6-166.7L112 293.9l-27.5 41.3c-3.4 5.1-9.1 8.1-15.2 8.1H31.9c-9.4 0-17-7.6-17-17c0-1.4 .2-2.9 .6-4.3l24.3-88.7c1.3-4.7 1.3-9.7 0-14.4L15.5 130.2c-.4-1.4-.6-2.9-.6-4.3c0-9.4 7.6-17 17-17h37.4c6.1 0 11.8 3 15.2 8.1L112 158.3l114.8 .1L181.2 -8.2c-2.9-11.4 5.7-22.5 17.5-22.5h46c10 0 19.3 4.8 25 13L383.8 130.5h98.5z" transform="translate(0 40) rotate(-8 256 256)"/>
            </svg>

            <!-- Pesawat kedua: arah berlawanan (kanan ke kiri) -->
            <div class="plane-trail-2"></div>
            <svg class="plane-svg-2" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
                <path fill="#ffffff" d="M482.3 192c34.2 0 61.7 27.5 61.7 61.5c0 34.2-27.5 61.7-61.7 61.7h-98.5L269.7 469.1c-5.7 8.2-15 13-25 13h-46c-11.8 0-20.4-11.1-17.5-22.5l45.6-166.7L112 293.9l-27.5 41.3c-3.4 5.1-9.1 8.1-15.2 8.1H31.9c-9.4 0-17-7.6-17-17c0-1.4 .2-2.9 .6-4.3l24.3-88.7c1.3-4.7 1.3-9.7 0-14.4L15.5 130.2c-.4-1.4-.6-2.9-.6-4.3c0-9.4 7.6-17 17-17h37.4c6.1 0 11.8 3 15.2 8.1L112 158.3l114.8 .1L181.2 -8.2c-2.9-11.4 5.7-22.5 17.5-22.5h46c10 0 19.3 4.8 25 13L383.8 130.5h98.5z" transform="translate(0 40) rotate(-8 256 256)"/>
            </svg>
        </div>

        <div class="max-w-4xl mx-auto text-center relative" style="z-index:4;">
            <h1 class="text-4xl md:text-5xl font-extrabold mb-6 leading-tight drop-shadow-lg">Solusi Parkir Bandara Terintegrasi Database</h1>
            <p class="text-lg text-blue-100 mb-8 drop-shadow">Pesan slot parkir Anda sekarang dan data akan langsung tercatat di sistem pusat bandara.</p>
            <div class="flex justify-center gap-4">
                <a href="#booking" class="bg-amber-500 hover:bg-amber-600 text-slate-900 font-bold px-6 py-3 rounded-xl shadow-lg transition">Booking Slot</a>
                <a href="admin.php" class="bg-white/10 hover:bg-white/20 border border-white/30 px-6 py-3 rounded-xl font-semibold transition backdrop-blur-sm">Masuk Admin Panel</a>
            </div>
        </div>
    </section>

    <!-- Notifikasi Sukses -->
    <?php if (!empty($pesan)): ?>
        <div class="max-w-3xl mx-auto mt-8 px-4">
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-6 rounded-2xl shadow-md flex items-start space-x-4">
                <i class="fa-solid fa-circle-check text-2xl text-emerald-600 mt-0.5"></i>
                <div>
                    <h3 class="font-bold text-lg">Berhasil Dipesan!</h3>
                    <p class="text-sm mt-1"><?php echo $pesan; ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Status Slot Parkir Real-time (TERHUBUNG KE DATABASE area_parkir) -->
    <section id="status" class="max-w-7xl mx-auto px-4 py-12">
        <div class="text-center mb-10">
            <h2 class="text-2xl md:text-3xl font-bold text-slate-900">Ketersediaan Slot Parkir Saat Ini</h2>
            <p class="text-slate-500 mt-2">Data informasi kapasitas slot area parkir bandara</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Mobil / Regular -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <div class="flex justify-between items-center mb-4">
                    <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-3 py-1 rounded-full">Mobil / Regular</span>
                    <i class="fa-solid fa-car text-blue-600 text-xl"></i>
                </div>
                <div class="text-3xl font-black text-slate-800 mb-1"><?php echo $status_mobil['sisa']; ?> <span class="text-sm font-normal text-slate-500">/ <?php echo $slot_mobil['total']; ?> Slot</span></div>
                <div class="w-full bg-slate-200 rounded-full h-2.5 mt-4">
                    <div class="<?php echo $status_mobil['warna_bar']; ?> h-2.5 rounded-full" style="width: <?php echo $status_mobil['persen']; ?>%"></div>
                </div>
                <p class="text-xs <?php echo $status_mobil['warna_teks']; ?> font-medium mt-3"><i class="fa-solid fa-circle text-[8px] mr-1"></i> <?php echo $status_mobil['label']; ?></p>
            </div>
            <!-- VIP -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <div class="flex justify-between items-center mb-4">
                    <span class="bg-amber-100 text-amber-800 text-xs font-semibold px-3 py-1 rounded-full">VIP & Valet</span>
                    <i class="fa-solid fa-crown text-amber-600 text-xl"></i>
                </div>
                <div class="text-3xl font-black text-slate-800 mb-1"><?php echo $status_vip['sisa']; ?> <span class="text-sm font-normal text-slate-500">/ <?php echo $slot_vip['total']; ?> Slot</span></div>
                <div class="w-full bg-slate-200 rounded-full h-2.5 mt-4">
                    <div class="<?php echo $status_vip['warna_bar']; ?> h-2.5 rounded-full" style="width: <?php echo $status_vip['persen']; ?>%"></div>
                </div>
                <p class="text-xs <?php echo $status_vip['warna_teks']; ?> font-medium mt-3"><i class="fa-solid fa-circle text-[8px] mr-1"></i> <?php echo $status_vip['label']; ?></p>
            </div>
            <!-- Motor -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <div class="flex justify-between items-center mb-4">
                    <span class="bg-emerald-100 text-emerald-800 text-xs font-semibold px-3 py-1 rounded-full">Sepeda Motor</span>
                    <i class="fa-solid fa-motorcycle text-emerald-600 text-xl"></i>
                </div>
                <div class="text-3xl font-black text-slate-800 mb-1"><?php echo $status_motor['sisa']; ?> <span class="text-sm font-normal text-slate-500">/ <?php echo $slot_motor['total']; ?> Slot</span></div>
                <div class="w-full bg-slate-200 rounded-full h-2.5 mt-4">
                    <div class="<?php echo $status_motor['warna_bar']; ?> h-2.5 rounded-full" style="width: <?php echo $status_motor['persen']; ?>%"></div>
                </div>
                <p class="text-xs <?php echo $status_motor['warna_teks']; ?> font-medium mt-3"><i class="fa-solid fa-circle text-[8px] mr-1"></i> <?php echo $status_motor['label']; ?></p>
            </div>
        </div>
    </section>

    <!-- Tata Cara Parkir di Bandara -->
    <section id="tatacara" class="bg-white py-16 px-4 border-t border-slate-200">
        <div class="max-w-6xl mx-auto">
            <div class="text-center mb-12">
                <h2 class="text-2xl md:text-3xl font-bold text-slate-900">Tata Cara Parkir di Bandara</h2>
                <p class="text-slate-500 mt-2">Ikuti langkah-langkah berikut agar proses parkir Anda lancar dan aman</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-6 relative">
                    <div class="w-10 h-10 flex items-center justify-center bg-blue-600 text-white font-bold rounded-full mb-4">1</div>
                    <i class="fa-solid fa-ticket text-blue-600 text-xl mb-3"></i>
                    <h3 class="font-bold text-slate-900 mb-1">Ambil Tiket Masuk</h3>
                    <p class="text-sm text-slate-500">Tekan tombol pada mesin gerbang atau tunjukkan bukti reservasi online untuk mendapatkan tiket masuk parkir.</p>
                </div>
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-6 relative">
                    <div class="w-10 h-10 flex items-center justify-center bg-blue-600 text-white font-bold rounded-full mb-4">2</div>
                    <i class="fa-solid fa-signs-post text-blue-600 text-xl mb-3"></i>
                    <h3 class="font-bold text-slate-900 mb-1">Ikuti Petunjuk Arah</h3>
                    <p class="text-sm text-slate-500">Ikuti papan penunjuk menuju area/zona parkir sesuai jenis kendaraan (Motor, Mobil, Bis, Truk) dan kategori (Reguler, VIP, VVIP) Anda.</p>
                </div>
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-6 relative">
                    <div class="w-10 h-10 flex items-center justify-center bg-blue-600 text-white font-bold rounded-full mb-4">3</div>
                    <i class="fa-solid fa-square-parking text-blue-600 text-xl mb-3"></i>
                    <h3 class="font-bold text-slate-900 mb-1">Parkir Sesuai Marka</h3>
                    <p class="text-sm text-slate-500">Posisikan kendaraan rapi di dalam garis marka slot yang tersedia dan pastikan tidak menghalangi jalur kendaraan lain.</p>
                </div>
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-6 relative">
                    <div class="w-10 h-10 flex items-center justify-center bg-blue-600 text-white font-bold rounded-full mb-4">4</div>
                    <i class="fa-solid fa-shield-halved text-blue-600 text-xl mb-3"></i>
                    <h3 class="font-bold text-slate-900 mb-1">Simpan Tiket Baik-baik</h3>
                    <p class="text-sm text-slate-500">Jangan sampai hilang atau rusak, karena tiket dibutuhkan sebagai bukti saat proses pembayaran dan keluar area parkir.</p>
                </div>
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-6 relative">
                    <div class="w-10 h-10 flex items-center justify-center bg-blue-600 text-white font-bold rounded-full mb-4">5</div>
                    <i class="fa-solid fa-money-bill-wave text-blue-600 text-xl mb-3"></i>
                    <h3 class="font-bold text-slate-900 mb-1">Lakukan Pembayaran</h3>
                    <p class="text-sm text-slate-500">Saat akan keluar, lakukan pembayaran sesuai durasi parkir melalui kasir, mesin otomatis, atau non-tunai (QRIS/kartu).</p>
                </div>
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-6 relative">
                    <div class="w-10 h-10 flex items-center justify-center bg-blue-600 text-white font-bold rounded-full mb-4">6</div>
                    <i class="fa-solid fa-door-open text-blue-600 text-xl mb-3"></i>
                    <h3 class="font-bold text-slate-900 mb-1">Gate Terbuka Otomatis</h3>
                    <p class="text-sm text-slate-500">Setelah pembayaran terverifikasi di sistem, palang pintu keluar akan terbuka secara otomatis.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Form Reservasi Parkir -->
    <section id="booking" class="bg-slate-100 py-16 px-4 border-t border-slate-200">
        <div class="max-w-3xl mx-auto bg-white rounded-3xl shadow-xl p-8 md:p-12">
            <div class="text-center mb-8">
                <h2 class="text-2xl md:text-3xl font-bold text-slate-900">Formulir Reservasi Parkir</h2>
                <p class="text-slate-500 mt-1">Data akan otomatis masuk ke Database MySQL</p>
            </div>

            <form action="" method="POST" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Nama Lengkap</label>
                        <input type="text" name="nama" required class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="Cth: Budi Santoso">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Nomor Plat Kendaraan</label>
                        <input type="text" name="plat" required class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="Cth: B 1234 XYZ">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Jenis Kendaraan</label>
                        <select name="kendaraan" id="vehicleType" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <option value="motor">Sepeda Motor</option>
                            <option value="mobil" selected>Mobil</option>
                            <option value="bis">Bis</option>
                            <option value="truk">Truk</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Kategori</label>
                        <select name="kategori" id="vehicleCategory" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <option value="reguler" selected>Reguler</option>
                            <option value="vip">VIP</option>
                            <option value="vvip">VVIP</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Durasi (Jam)</label>
                        <input type="number" name="durasi" id="duration" min="1" value="3" required class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Tanggal Masuk</label>
                        <input type="date" name="tanggal" required class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Waktu Masuk</label>
                        <input type="time" name="waktu" required class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4">
                    <span class="text-sm text-slate-600 block mb-2">Estimasi Tarif per Jam:</span>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-center border-collapse">
                            <thead>
                                <tr class="text-slate-500">
                                    <th class="py-1 text-left font-semibold">Kendaraan</th>
                                    <th class="py-1 font-semibold">Reguler</th>
                                    <th class="py-1 font-semibold">VIP</th>
                                    <th class="py-1 font-semibold">VVIP</th>
                                </tr>
                            </thead>
                            <tbody class="text-slate-700">
                                <tr class="border-t border-blue-100"><td class="py-1 text-left">Motor</td><td>Rp 2.000</td><td>Rp 5.000</td><td>Rp 8.000</td></tr>
                                <tr class="border-t border-blue-100"><td class="py-1 text-left">Mobil</td><td>Rp 5.000</td><td>Rp 15.000</td><td>Rp 25.000</td></tr>
                                <tr class="border-t border-blue-100"><td class="py-1 text-left">Bis</td><td>Rp 10.000</td><td>Rp 20.000</td><td>Rp 35.000</td></tr>
                                <tr class="border-t border-blue-100"><td class="py-1 text-left">Truk</td><td>Rp 12.000</td><td>Rp 25.000</td><td>Rp 40.000</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 pt-3 border-t border-blue-200 flex justify-between items-center">
                        <span class="text-sm text-slate-600">Estimasi Total Biaya:</span>
                        <span id="totalPrice" class="text-2xl font-black text-blue-900">Rp 15.000</span>
                    </div>
                </div>

                <button type="submit" name="simpan_reservasi" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-xl shadow-lg transition">Konfirmasi & Simpan ke Database</button>
            </form>
        </div>
    </section>

    <!-- Komentar & Rating -->
    <section id="ulasan" class="max-w-5xl mx-auto px-4 py-16">
        <div class="text-center mb-10">
            <h2 class="text-2xl md:text-3xl font-bold text-slate-900">Ulasan Pengguna</h2>
            <p class="text-slate-500 mt-2">Bagikan pengalaman parkir Anda di bandara kami</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            <!-- Form Komentar -->
            <div class="lg:col-span-5">
                <div class="bg-white border border-slate-200 rounded-3xl shadow-xl p-6 md:p-8">
                    <h3 class="font-bold text-slate-900 text-lg mb-4">Tulis Ulasan Anda</h3>

                    <?php if (!empty($pesan_komentar)): ?>
                        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl text-sm mb-4">
                            <?php echo htmlspecialchars($pesan_komentar); ?>
                        </div>
                    <?php endif; ?>

                    <form action="#ulasan" method="POST" class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Nama</label>
                            <input type="text" name="nama_komentar" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="Nama Anda">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Rating</label>
                            <div class="flex flex-row-reverse justify-end gap-1 text-2xl peer" id="starRating">
                                <input type="radio" name="rating" value="5" id="r5" class="hidden peer/r5">
                                <label for="r5" class="star-label cursor-pointer text-slate-300 peer-checked/r5:text-amber-400"><i class="fa-solid fa-star"></i></label>

                                <input type="radio" name="rating" value="4" id="r4" class="hidden peer/r4">
                                <label for="r4" class="star-label cursor-pointer text-slate-300 peer-checked/r4:text-amber-400"><i class="fa-solid fa-star"></i></label>

                                <input type="radio" name="rating" value="3" id="r3" class="hidden peer/r3">
                                <label for="r3" class="star-label cursor-pointer text-slate-300 peer-checked/r3:text-amber-400"><i class="fa-solid fa-star"></i></label>

                                <input type="radio" name="rating" value="2" id="r2" class="hidden peer/r2">
                                <label for="r2" class="star-label cursor-pointer text-slate-300 peer-checked/r2:text-amber-400"><i class="fa-solid fa-star"></i></label>

                                <input type="radio" name="rating" value="1" id="r1" checked class="hidden peer/r1">
                                <label for="r1" class="star-label cursor-pointer text-slate-300 peer-checked/r1:text-amber-400"><i class="fa-solid fa-star"></i></label>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">Klik bintang untuk memberi rating (1-5)</p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Komentar</label>
                            <textarea name="isi_komentar" rows="4" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="Bagaimana pengalaman parkir Anda?"></textarea>
                        </div>

                        <button type="submit" name="simpan_komentar" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl shadow-lg transition">Kirim Ulasan</button>
                    </form>
                </div>
            </div>

            <!-- Daftar Komentar -->
            <div class="lg:col-span-7 space-y-4">
                <?php
                $res_komentar = mysqli_query($conn, "SELECT * FROM komentar ORDER BY id DESC LIMIT 20");
                if ($res_komentar && mysqli_num_rows($res_komentar) > 0):
                    while ($kom = mysqli_fetch_assoc($res_komentar)):
                        $rating_kom = intval($kom['rating']);
                ?>
                <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <h4 class="font-bold text-slate-900"><?php echo htmlspecialchars($kom['nama']); ?></h4>
                            <p class="text-[11px] text-slate-400"><?php echo htmlspecialchars(date('d M Y, H:i', strtotime($kom['tanggal']))); ?></p>
                        </div>
                        <div class="text-amber-400 text-sm whitespace-nowrap">
                            <?php
                            for ($i = 1; $i <= 5; $i++) {
                                echo ($i <= $rating_kom) ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>';
                            }
                            ?>
                        </div>
                    </div>
                    <p class="text-sm text-slate-600 leading-relaxed"><?php echo nl2br(htmlspecialchars($kom['komentar'])); ?></p>
                </div>
                <?php endwhile; else: ?>
                <div class="bg-slate-50 border border-dashed border-slate-300 rounded-2xl p-8 text-center text-slate-400 text-sm">
                    Belum ada ulasan. Jadilah yang pertama memberikan ulasan!
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ========================================== -->
    <!-- PUSAT BANTUAN -->
    <!-- ========================================== -->
    <section id="bantuan" class="bg-white py-16 px-4 border-t border-slate-200">
        <div class="max-w-6xl mx-auto">
            <div class="text-center mb-12">
                <span class="inline-flex items-center gap-2 bg-blue-100 text-blue-800 text-xs font-semibold px-3 py-1 rounded-full mb-3">
                    <i class="fa-solid fa-headset"></i> Pusat Bantuan
                </span>
                <h2 class="text-2xl md:text-3xl font-bold text-slate-900">Butuh Bantuan? Kami Siap Membantu</h2>
                <p class="text-slate-500 mt-2 max-w-xl mx-auto">Pengguna baru atau mengalami kendala saat parkir dan reservasi? Tim petugas bandara siap membantu Anda kapan saja.</p>
            </div>

            <!-- Kartu Kontak -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-14">
                <a href="tel:+622112345678" class="bg-slate-50 border border-slate-200 rounded-2xl p-6 text-center hover:shadow-lg hover:border-blue-300 transition">
                    <div class="w-12 h-12 flex items-center justify-center bg-blue-600 text-white rounded-full mx-auto mb-4">
                        <i class="fa-solid fa-phone text-lg"></i>
                    </div>
                    <h3 class="font-bold text-slate-900 mb-1">Call Center</h3>
                    <p class="text-sm text-slate-500">(021) 1234-5678</p>
                    <p class="text-xs text-slate-400 mt-1">24 Jam Setiap Hari</p>
                </a>

                <a href="https://wa.me/6281234567890" target="_blank" class="bg-slate-50 border border-slate-200 rounded-2xl p-6 text-center hover:shadow-lg hover:border-emerald-300 transition">
                    <div class="w-12 h-12 flex items-center justify-center bg-emerald-500 text-white rounded-full mx-auto mb-4">
                        <i class="fa-brands fa-whatsapp text-xl"></i>
                    </div>
                    <h3 class="font-bold text-slate-900 mb-1">WhatsApp</h3>
                    <p class="text-sm text-slate-500">+62 812-3456-7890</p>
                    <p class="text-xs text-slate-400 mt-1">Respon Cepat 08.00 - 22.00</p>
                </a>

                <a href="mailto:bantuan@aeroparking.co.id" class="bg-slate-50 border border-slate-200 rounded-2xl p-6 text-center hover:shadow-lg hover:border-amber-300 transition">
                    <div class="w-12 h-12 flex items-center justify-center bg-amber-500 text-white rounded-full mx-auto mb-4">
                        <i class="fa-solid fa-envelope text-lg"></i>
                    </div>
                    <h3 class="font-bold text-slate-900 mb-1">Email</h3>
                    <p class="text-sm text-slate-500 break-all">bantuan@aeroparking.co.id</p>
                    <p class="text-xs text-slate-400 mt-1">Dibalas dalam 1x24 jam</p>
                </a>

                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-6 text-center">
                    <div class="w-12 h-12 flex items-center justify-center bg-slate-700 text-white rounded-full mx-auto mb-4">
                        <i class="fa-solid fa-location-dot text-lg"></i>
                    </div>
                    <h3 class="font-bold text-slate-900 mb-1">Loket Informasi</h3>
                    <p class="text-sm text-slate-500">Terminal Kedatangan, Lt. 1</p>
                    <p class="text-xs text-slate-400 mt-1">Dekat Pintu Keluar Parkir</p>
                </div>
            </div>

            <!-- FAQ -->
            <div class="max-w-3xl mx-auto">
                <h3 class="text-xl font-bold text-slate-900 mb-6 text-center">Pertanyaan yang Sering Diajukan</h3>
                <div class="space-y-3" id="faqAccordion">

                    <div class="faq-item bg-slate-50 border border-slate-200 rounded-2xl overflow-hidden">
                        <button type="button" class="faq-question w-full flex justify-between items-center text-left px-5 py-4 font-semibold text-slate-800">
                            <span>Bagaimana cara melakukan reservasi parkir?</span>
                            <i class="fa-solid fa-chevron-down text-slate-400 transition-transform"></i>
                        </button>
                        <div class="faq-answer max-h-0 overflow-hidden transition-all duration-300">
                            <p class="px-5 pb-4 text-sm text-slate-500">Isi formulir pada bagian "Reservasi Online", lengkapi data diri, jenis kendaraan, kategori, tanggal, dan waktu masuk. Data akan langsung tersimpan di sistem pusat bandara.</p>
                        </div>
                    </div>

                    <div class="faq-item bg-slate-50 border border-slate-200 rounded-2xl overflow-hidden">
                        <button type="button" class="faq-question w-full flex justify-between items-center text-left px-5 py-4 font-semibold text-slate-800">
                            <span>Apa perbedaan kategori Reguler, VIP, dan VVIP?</span>
                            <i class="fa-solid fa-chevron-down text-slate-400 transition-transform"></i>
                        </button>
                        <div class="faq-answer max-h-0 overflow-hidden transition-all duration-300">
                            <p class="px-5 pb-4 text-sm text-slate-500">Reguler adalah area parkir standar dengan tarif paling terjangkau. VIP menawarkan lokasi lebih dekat ke terminal dengan layanan tambahan. VVIP adalah kelas tertinggi dengan lokasi paling strategis dan fasilitas eksklusif, dengan tarif per jam yang lebih tinggi.</p>
                        </div>
                    </div>

                    <div class="faq-item bg-slate-50 border border-slate-200 rounded-2xl overflow-hidden">
                        <button type="button" class="faq-question w-full flex justify-between items-center text-left px-5 py-4 font-semibold text-slate-800">
                            <span>Saya pengguna baru, apa yang harus saya lakukan pertama kali?</span>
                            <i class="fa-solid fa-chevron-down text-slate-400 transition-transform"></i>
                        </button>
                        <div class="faq-answer max-h-0 overflow-hidden transition-all duration-300">
                            <p class="px-5 pb-4 text-sm text-slate-500">Cek dulu ketersediaan slot pada bagian "Ketersediaan Slot", lalu ikuti panduan pada "Tata Cara Parkir". Jika masih bingung, hubungi Call Center atau datangi Loket Informasi di Terminal Kedatangan.</p>
                        </div>
                    </div>

                    <div class="faq-item bg-slate-50 border border-slate-200 rounded-2xl overflow-hidden">
                        <button type="button" class="faq-question w-full flex justify-between items-center text-left px-5 py-4 font-semibold text-slate-800">
                            <span>Bagaimana jika tiket parkir saya hilang?</span>
                            <i class="fa-solid fa-chevron-down text-slate-400 transition-transform"></i>
                        </button>
                        <div class="faq-answer max-h-0 overflow-hidden transition-all duration-300">
                            <p class="px-5 pb-4 text-sm text-slate-500">Segera hubungi petugas di loket keluar atau Call Center kami. Petugas akan memverifikasi data kendaraan Anda melalui sistem sebelum proses pembayaran denda kehilangan tiket.</p>
                        </div>
                    </div>

                    <div class="faq-item bg-slate-50 border border-slate-200 rounded-2xl overflow-hidden">
                        <button type="button" class="faq-question w-full flex justify-between items-center text-left px-5 py-4 font-semibold text-slate-800">
                            <span>Metode pembayaran apa saja yang tersedia?</span>
                            <i class="fa-solid fa-chevron-down text-slate-400 transition-transform"></i>
                        </button>
                        <div class="faq-answer max-h-0 overflow-hidden transition-all duration-300">
                            <p class="px-5 pb-4 text-sm text-slate-500">Kami menerima pembayaran tunai di kasir, mesin pembayaran otomatis, serta non-tunai melalui QRIS dan kartu debit/kredit.</p>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>

    <!-- Tombol Bantuan Mengambang (Floating Help Button) -->
    <a href="#bantuan" class="fixed bottom-6 right-6 z-50 bg-blue-700 hover:bg-blue-800 text-white w-14 h-14 rounded-full shadow-xl flex items-center justify-center transition group" title="Pusat Bantuan">
        <i class="fa-solid fa-headset text-xl"></i>
        <span class="absolute right-16 bg-slate-900 text-white text-xs font-medium px-3 py-1.5 rounded-lg opacity-0 group-hover:opacity-100 transition whitespace-nowrap pointer-events-none">Butuh Bantuan?</span>
    </a>

    <footer class="bg-slate-900 text-slate-400 py-8 text-center text-sm">
        <p>&copy; 2026 AeroParking System. Hak Cipta Dilindungi.</p>
    </footer>

    <script>
        // Matriks tarif per jam: [jenis kendaraan][kategori]
        const tarifMatrix = {
            motor: { reguler: 2000,  vip: 5000,  vvip: 8000  },
            mobil: { reguler: 5000,  vip: 15000, vvip: 25000 },
            bis:   { reguler: 10000, vip: 20000, vvip: 35000 },
            truk:  { reguler: 12000, vip: 25000, vvip: 40000 }
        };

        function calculatePrice() {
            const type = document.getElementById('vehicleType').value;
            const category = document.getElementById('vehicleCategory').value;
            const duration = parseInt(document.getElementById('duration').value) || 1;
            const rate = (tarifMatrix[type] && tarifMatrix[type][category]) || 5000;
            const total = rate * duration;
            document.getElementById('totalPrice').innerText = 'Rp ' + total.toLocaleString('id-ID');
        }
        document.getElementById('vehicleType').addEventListener('change', calculatePrice);
        document.getElementById('vehicleCategory').addEventListener('change', calculatePrice);
        document.getElementById('duration').addEventListener('input', calculatePrice);
        calculatePrice();

        // Efek hover untuk rating bintang
        const starWrap = document.getElementById('starRating');
        if (starWrap) {
            const labels = Array.from(starWrap.querySelectorAll('label.star-label'));
            // labels berurutan dari bintang 5 ke 1 di DOM (karena flex-row-reverse)
            const highlightUpTo = (index) => {
                labels.forEach((lbl, i) => {
                    lbl.classList.toggle('text-amber-400', i >= index);
                    lbl.classList.toggle('text-slate-300', i < index);
                });
            };
            const resetToChecked = () => {
                const checked = starWrap.querySelector('input[name="rating"]:checked');
                const value = checked ? parseInt(checked.value) : 0;
                const startIndex = labels.length - value;
                highlightUpTo(startIndex);
            };
            labels.forEach((lbl, i) => {
                lbl.addEventListener('mouseenter', () => highlightUpTo(i));
                lbl.addEventListener('mouseleave', resetToChecked);
                lbl.addEventListener('click', () => setTimeout(resetToChecked, 0));
            });
            resetToChecked();
        }

        // Accordion FAQ Pusat Bantuan
        document.querySelectorAll('#faqAccordion .faq-question').forEach(btn => {
            btn.addEventListener('click', () => {
                const item = btn.closest('.faq-item');
                const answer = item.querySelector('.faq-answer');
                const icon = btn.querySelector('i');
                const isOpen = answer.style.maxHeight && answer.style.maxHeight !== '0px';

                // Tutup semua item lain
                document.querySelectorAll('#faqAccordion .faq-answer').forEach(a => a.style.maxHeight = '0px');
                document.querySelectorAll('#faqAccordion .faq-question i').forEach(i => i.classList.remove('rotate-180'));

                // Buka item yang diklik jika sebelumnya tertutup
                if (!isOpen) {
                    answer.style.maxHeight = answer.scrollHeight + 'px';
                    icon.classList.add('rotate-180');
                }
            });
        });
    </script>
</body>
</html>