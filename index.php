<?php
include 'koneksi.php';
include 'area_helper.php';
$pesan         = "";
$pesan_komentar = "";

// Fungsi hitung biaya bertingkat: jam pertama + (durasi-1) x tambahan, dibatasi maksimal harian
function hitung_tarif_bertingkat($tarif, $durasi) {
    if ($durasi < 1) $durasi = 1;
    $total = $tarif['jam1'] + (($durasi - 1) * $tarif['tambahan']);
    if ($tarif['maks'] > 0 && $total > $tarif['maks']) {
        $total = $tarif['maks'];
    }
    return $total;
}

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

    // Pastikan kolom "kode_reservasi" sudah ada di tabel reservasi (auto-migrate)
    $cek_kode = mysqli_query($conn, "SHOW COLUMNS FROM reservasi LIKE 'kode_reservasi'");
    if ($cek_kode && mysqli_num_rows($cek_kode) == 0) {
        mysqli_query($conn, "ALTER TABLE reservasi ADD COLUMN kode_reservasi VARCHAR(20) NULL AFTER id");
    }

    // Pastikan kolom "id_area" sudah ada di tabel reservasi (auto-migrate).
    // Kolom ini dipakai supaya area_parkir.terisi bisa ikut ter-update
    // otomatis saat reservasi online ini di-check-in oleh petugas.
    $cek_area_kolom = mysqli_query($conn, "SHOW COLUMNS FROM reservasi LIKE 'id_area'");
    if ($cek_area_kolom && mysqli_num_rows($cek_area_kolom) == 0) {
        mysqli_query($conn, "ALTER TABLE reservasi ADD COLUMN id_area INT(11) NOT NULL DEFAULT 0 AFTER kendaraan");
    }

    // Generate kode reservasi unik dengan format RSV-XXXXXX, pastikan belum dipakai
    do {
        $kode_reservasi = 'RSV-' . strtoupper(bin2hex(random_bytes(3)));
        $cek_unik = mysqli_query($conn, "SELECT id FROM reservasi WHERE kode_reservasi = '$kode_reservasi'");
    } while ($cek_unik && mysqli_num_rows($cek_unik) > 0);

    // Matriks tarif bertingkat: [jenis kendaraan][kategori] => [jam1, tambahan, maks_harian]
    // jam1      = tarif untuk jam pertama
    // tambahan  = tarif tiap jam berikutnya (jam ke-2 dan seterusnya)
    // maks      = batas maksimal biaya per hari (24 jam)
    $tarif_matrix = [
        'motor' => [
            'reguler' => ['jam1' => 5000,  'tambahan' => 2000,  'maks' => 40000],
            'vip'     => ['jam1' => 7500,  'tambahan' => 3000,  'maks' => 40000],
            'vvip'    => ['jam1' => 10000, 'tambahan' => 4000,  'maks' => 40000],
        ],
        'mobil' => [
            'reguler' => ['jam1' => 10000, 'tambahan' => 5000,  'maks' => 100000],
            'vip'     => ['jam1' => 15000, 'tambahan' => 7500,  'maks' => 100000],
            'vvip'    => ['jam1' => 20000, 'tambahan' => 10000, 'maks' => 100000],
        ],
        'bis' => [
            'reguler' => ['jam1' => 25000, 'tambahan' => 10000, 'maks' => 250000],
            'vip'     => ['jam1' => 37500, 'tambahan' => 15000, 'maks' => 250000],
            'vvip'    => ['jam1' => 50000, 'tambahan' => 20000, 'maks' => 250000],
        ],
        'truk' => [
            'reguler' => ['jam1' => 30000, 'tambahan' => 20000, 'maks' => 350000],
            'vip'     => ['jam1' => 45000, 'tambahan' => 30000, 'maks' => 350000],
            'vvip'    => ['jam1' => 60000, 'tambahan' => 40000, 'maks' => 350000],
        ],
    ];

    $tarif_dipilih = $tarif_matrix[$kendaraan][$kategori] ?? ['jam1' => 5000, 'tambahan' => 5000, 'maks' => 100000];
    $total_biaya   = hitung_tarif_bertingkat($tarif_dipilih, $durasi);

    // Tentukan area parkir otomatis sesuai jenis kendaraan & kategori,
    // supaya saat reservasi ini di-check-in oleh petugas, area_parkir.terisi
    // langsung ikut bertambah pada area yang tepat (lihat area_helper.php).
    $id_area_reservasi = cari_id_area_tersedia($conn, $kendaraan, $kategori);

    // status_bayar diset 'Pending' -> reservasi online menunggu diproses/check-in oleh petugas di lapangan
    $query = "INSERT INTO reservasi (kode_reservasi, nama, plat, kendaraan, kategori, id_area, durasi, tanggal, waktu, total_biaya, status_bayar) 
              VALUES ('$kode_reservasi', '$nama', '$plat', '$kendaraan', '$kategori', $id_area_reservasi, $durasi, '$tanggal', '$waktu', $total_biaya, 'Pending')";
    
    if (mysqli_query($conn, $query)) {
        $pesan = "Reservasi berhasil disimpan! Kode reservasi Anda: <b class=\"text-base\">$kode_reservasi</b>. Atas nama <b>$nama</b> (Plat: $plat). Total: Rp " . number_format($total_biaya, 0, ',', '.') . ". Simpan kode ini dan tunjukkan ke petugas saat tiba di bandara untuk check-in.";
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
        $warna_teks = 'text-rose-400';
        $warna_bar  = 'bg-rose-600';
    } elseif ($persen >= 70) {
        $label = 'Hampir Penuh';
        $warna_teks = 'text-amber-400';
        $warna_bar  = 'bg-amber-500';
    } elseif ($persen >= 40) {
        $label = 'Tersedia';
        $warna_teks = 'text-blue-400';
        $warna_bar  = 'bg-blue-600';
    } else {
        $label = 'Sangat Luas';
        $warna_teks = 'text-emerald-400';
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

// ==========================================
    // DATA GRAFIK KERAMAIAN PARKIR (6 BULAN TERAKHIR)
    // Menghitung jumlah reservasi per bulan.
    // Pada sistem ini setiap reservasi mewakili 1 pengguna/kendaraan
    // yang melakukan pemesanan parkir.
    // ==========================================

    $bulan_label = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

    $q_keramaian = mysqli_query($conn, "
        SELECT DATE_FORMAT(tanggal, '%Y-%m') AS periode, COUNT(*) AS jumlah
        FROM reservasi
        WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY periode
        ORDER BY periode ASC
    ");

    $data_keramaian = [];
    if ($q_keramaian) {
        while ($row = mysqli_fetch_assoc($q_keramaian)) {
            $data_keramaian[$row['periode']] = intval($row['jumlah']);
        }
    }

    // Susun label & jumlah pengguna untuk 6 bulan terakhir secara berurutan
    // Bulan tanpa reservasi otomatis diisi 0.
    $grafik_label = [];
    $grafik_keramaian = [];

    for ($i = 5; $i >= 0; $i--) {
        $ts = strtotime("-$i month");
        $key = date('Y-m', $ts);

        $grafik_label[] = $bulan_label[intval(date('n', $ts)) - 1] . ' ' . date('y', $ts);
        $grafik_keramaian[] = $data_keramaian[$key] ?? 0;
    }

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Parkir Bandara Internasional</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <style>
        /* ==========================================
           ANIMASI LANGIT BANDARA: PESAWAT TERBANG, KONTRAIL,
           LAMPU KEDIP, MATAHARI, DAN LAMPU LANDASAN
           ========================================== */
        #home { overflow: hidden; }

        .plane-wrapper {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 3;
        }

        /* --- Cahaya senja merah-biru berdenyut (aksen tema) --- */
        .sun-glow {
            position: absolute;
            top: 8%;
            right: 12%;
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(239,68,68,0.9) 0%, rgba(220,38,38,0.35) 45%, rgba(37,99,235,0) 75%);
            filter: blur(2px);
            animation: sunPulse 6s ease-in-out infinite;
            z-index: 1;
        }
        @keyframes sunPulse {
            0%, 100% { opacity: .75; transform: scale(1); }
            50%      { opacity: 1;   transform: scale(1.08); }
        }

        /* --- Pesawat utama: lepas landas dari kiri-bawah ke kanan-atas --- */
        .plane-svg {
            position: absolute;
            width: 92px;
            height: auto;
            left: -12%;
            bottom: 18%;
            animation: takeOff 12s cubic-bezier(0.45, 0, 0.55, 1) infinite;
            filter: drop-shadow(0 6px 10px rgba(0,0,0,0.35));
        }
        .plane-svg .nav-light-red {
            animation: blinkLight 1.1s steps(1) infinite;
        }
        .plane-svg .nav-light-green {
            animation: blinkLight 1.1s steps(1) infinite;
            animation-delay: .55s;
        }
        @keyframes blinkLight {
            0%, 45%  { opacity: 1; }
            46%,100% { opacity: .15; }
        }

        @keyframes takeOff {
            0%   { left: -12%;  bottom: 16%; transform: rotate(0deg)   scale(0.85); opacity: 0; }
            8%   { opacity: 1; }
            35%  { left: 38%;   bottom: 26%; transform: rotate(-8deg)  scale(1); }
            65%  { left: 70%;   bottom: 46%; transform: rotate(-16deg) scale(1.05); }
            92%  { opacity: 1; }
            100% { left: 112%;  bottom: 68%; transform: rotate(-16deg) scale(1.1); opacity: 0; }
        }

        /* --- Kontrail berupa rentetan gumpalan asap yang memudar --- */
        .trail-puff {
            position: absolute;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,0.75), rgba(255,255,255,0) 72%);
            filter: blur(1.5px);
            animation: puffTrail 12s cubic-bezier(0.45, 0, 0.55, 1) infinite, puffFade 12s linear infinite;
        }
        @keyframes puffTrail {
            0%   { left: -12%;  bottom: 16.6%; transform: scale(.4); }
            35%  { left: 38%;   bottom: 26.6%; transform: scale(.9); }
            65%  { left: 70%;   bottom: 46.6%; transform: scale(1.1); }
            100% { left: 112%;  bottom: 68.6%; transform: scale(1.3); }
        }
        @keyframes puffFade {
            0%, 6%   { opacity: 0; }
            10%      { opacity: .8; }
            85%      { opacity: .25; }
            100%     { opacity: 0; }
        }

        /* --- Pesawat kedua: melintas mendatar arah berlawanan, lebih tinggi & kecil --- */
        .plane-svg-2 {
            position: absolute;
            width: 58px;
            height: auto;
            right: -12%;
            top: 20%;
            animation: crossOver 14s cubic-bezier(0.45, 0, 0.55, 1) infinite;
            animation-delay: 4.5s;
            filter: drop-shadow(0 6px 10px rgba(0,0,0,0.35));
            opacity: 0;
        }
        .plane-svg-2 .nav-light-red { animation: blinkLight 1.1s steps(1) infinite; }
        .plane-svg-2 .nav-light-green { animation: blinkLight 1.1s steps(1) infinite; animation-delay: .55s; }

        @keyframes crossOver {
            0%   { right: -12%; top: 32%; transform: scaleX(-1) rotate(4deg)  scale(0.75); opacity: 0; }
            10%  { opacity: 0.95; }
            50%  { right: 46%;  top: 14%; transform: scaleX(-1) rotate(-2deg) scale(0.9); }
            90%  { opacity: 0.95; }
            100% { right: 112%; top: 8%;  transform: scaleX(-1) rotate(-6deg) scale(1); opacity: 0; }
        }

        .trail-puff-2 {
            position: absolute;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,0.6), rgba(255,255,255,0) 72%);
            filter: blur(1px);
            animation: puffTrail2 14s cubic-bezier(0.45, 0, 0.55, 1) infinite, puffFade2 14s linear infinite;
            animation-delay: 4.5s;
        }
        @keyframes puffTrail2 {
            0%   { right: -12%; top: 34.5%; transform: scale(.35); }
            50%  { right: 46%;  top: 16.5%; transform: scale(.75); }
            100% { right: 112%; top: 10.5%; transform: scale(1); }
        }
        @keyframes puffFade2 {
            0%, 8%  { opacity: 0; }
            14%     { opacity: .65; }
            88%     { opacity: .2; }
            100%    { opacity: 0; }
        }

        /* --- Pesawat ketiga: siluet jauh & samar untuk kedalaman (paling lambat, paling tinggi) --- */
        .plane-svg-3 {
            position: absolute;
            width: 34px;
            height: auto;
            left: -8%;
            top: 12%;
            opacity: .35;
            filter: drop-shadow(0 3px 4px rgba(0,0,0,0.25)) brightness(1.4);
            animation: farFly 26s linear infinite;
            animation-delay: 2s;
        }
        @keyframes farFly {
            0%   { left: -8%;  top: 12%; opacity: 0; }
            6%   { opacity: .35; }
            94%  { opacity: .35; }
            100% { left: 105%; top: 9%;  opacity: 0; }
        }

        /* --- Awan dekoratif melayang di latar (paralaks pelan) --- */
        .cloud {
            position: absolute;
            color: rgba(255,255,255,0.18);
            animation: driftCloud linear infinite;
        }
        @keyframes driftCloud {
            0%   { transform: translateX(0); }
            100% { transform: translateX(-140px); }
        }

        /* --- Bintang kecil berkelip di langit senja (dekat matahari) --- */
        .sky-star {
            position: absolute;
            width: 3px;
            height: 3px;
            border-radius: 50%;
            background: #ffffff;
            animation: starTwinkle ease-in-out infinite;
            z-index: 1;
        }
        @keyframes starTwinkle {
            0%, 100% { opacity: .15; transform: scale(.8); }
            50%      { opacity: .9;  transform: scale(1.3); }
        }

        /* --- Strip lampu tepi landasan pacu di dasar hero, berkedip bergantian merah/biru --- */
        .runway-lights {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 4px;
            display: flex;
            justify-content: space-between;
            padding: 0 3%;
            z-index: 2;
            pointer-events: none;
        }
        .runway-lights span {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: #ef4444;
            box-shadow: 0 0 6px 2px rgba(239,68,68,.7);
            animation: runwayBlink 1.6s ease-in-out infinite;
        }
        .runway-lights span:nth-child(even) {
            background: #3b82f6;
            box-shadow: 0 0 6px 2px rgba(59,130,246,.7);
        }
        @keyframes runwayBlink {
            0%, 100% { opacity: .3; }
            50%      { opacity: 1; }
        }
    </style>
</head>
<body class="bg-slate-50 font-sans text-slate-800">

    <!-- Header / Navbar -->
    <header class="bg-slate-900 text-white shadow-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <i class="fa-solid fa-plane-departure text-2xl text-red-500"></i>
                <span class="text-xl font-bold tracking-wide">AeroParking<span class="text-red-500">.</span></span>
            </div>
            <nav class="hidden md:flex space-x-6 text-sm font-medium items-center">
                <a href="#home" class="hover:text-red-400 transition">Beranda</a>
                <a href="#status" class="hover:text-red-400 transition">Ketersediaan Slot</a>
                <a href="#pendapatan" class="hover:text-red-400 transition">Keramaian Parkir</a>
                <a href="#tatacara" class="hover:text-red-400 transition">Tata Cara</a>
                <a href="#ulasan" class="hover:text-red-400 transition">Ulasan</a>
                <a href="#bantuan" class="hover:text-red-400 transition">Pusat Bantuan</a>
                <a href="login.php" class="bg-red-600 hover:bg-red-500 text-white px-3 py-1.5 rounded-lg font-bold transition"><i class="fa-solid fa-lock mr-1"></i> Admin Panel</a>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section id="home"
        class="relative text-white py-24 px-4 bg-cover bg-center bg-no-repeat"
        style="background-image: linear-gradient(rgba(20, 10, 20, 0.82), rgba(15, 20, 45, 0.88)), url('https://images.unsplash.com/photo-1652483614757-1c7d1aafa996?auto=format&fit=crop&w=1920&q=80');">

        <!-- Cahaya senja & bintang kecil -->
        <div class="sun-glow"></div>
        <div class="sky-star" style="top:11%; left:74%; animation-duration:2.4s; animation-delay:0s;"></div>
        <div class="sky-star" style="top:16%; left:81%; animation-duration:3.1s; animation-delay:-1s;"></div>
        <div class="sky-star" style="top:9%;  left:66%; animation-duration:2.8s; animation-delay:-.4s;"></div>
        <div class="sky-star" style="top:19%; left:70%; animation-duration:3.6s; animation-delay:-2s;"></div>

        <!-- Awan dekoratif -->
        <i class="fa-solid fa-cloud cloud text-5xl" style="top:10%; left:70%; animation-duration:38s;"></i>
        <i class="fa-solid fa-cloud cloud text-4xl" style="top:22%; left:20%; animation-duration:46s;"></i>
        <i class="fa-solid fa-cloud cloud text-3xl" style="top:6%; left:45%; animation-duration:30s;"></i>
        <i class="fa-solid fa-cloud cloud text-2xl" style="top:32%; left:60%; animation-duration:52s; animation-delay:-10s;"></i>

        <!-- Animasi pesawat -->
        <div class="plane-wrapper">

            <!-- Pesawat jauh, siluet samar untuk kedalaman -->
            <svg class="plane-svg-3" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
                <path fill="#ffffff" d="M482.3 192c34.2 0 61.7 27.5 61.7 61.5c0 34.2-27.5 61.7-61.7 61.7h-98.5L269.7 469.1c-5.7 8.2-15 13-25 13h-46c-11.8 0-20.4-11.1-17.5-22.5l45.6-166.7L112 293.9l-27.5 41.3c-3.4 5.1-9.1 8.1-15.2 8.1H31.9c-9.4 0-17-7.6-17-17c0-1.4 .2-2.9 .6-4.3l24.3-88.7c1.3-4.7 1.3-9.7 0-14.4L15.5 130.2c-.4-1.4-.6-2.9-.6-4.3c0-9.4 7.6-17 17-17h37.4c6.1 0 11.8 3 15.2 8.1L112 158.3l114.8 .1L181.2 -8.2c-2.9-11.4 5.7-22.5 17.5-22.5h46c10 0 19.3 4.8 25 13L383.8 130.5h98.5z" transform="translate(0 40) rotate(-8 256 256)"/>
            </svg>

            <!-- Kontrail pesawat utama (gumpalan asap berurutan) -->
            <div class="trail-puff" style="animation-delay: 0s;"></div>
            <div class="trail-puff" style="animation-delay: -0.6s;"></div>
            <div class="trail-puff" style="animation-delay: -1.2s;"></div>
            <div class="trail-puff" style="animation-delay: -1.8s;"></div>
            <div class="trail-puff" style="animation-delay: -2.4s;"></div>
            <div class="trail-puff" style="animation-delay: -3s;"></div>

            <!-- Pesawat utama: lepas landas -->
            <svg class="plane-svg" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
                <path fill="#ffffff" d="M482.3 192c34.2 0 61.7 27.5 61.7 61.5c0 34.2-27.5 61.7-61.7 61.7h-98.5L269.7 469.1c-5.7 8.2-15 13-25 13h-46c-11.8 0-20.4-11.1-17.5-22.5l45.6-166.7L112 293.9l-27.5 41.3c-3.4 5.1-9.1 8.1-15.2 8.1H31.9c-9.4 0-17-7.6-17-17c0-1.4 .2-2.9 .6-4.3l24.3-88.7c1.3-4.7 1.3-9.7 0-14.4L15.5 130.2c-.4-1.4-.6-2.9-.6-4.3c0-9.4 7.6-17 17-17h37.4c6.1 0 11.8 3 15.2 8.1L112 158.3l114.8 .1L181.2 -8.2c-2.9-11.4 5.7-22.5 17.5-22.5h46c10 0 19.3 4.8 25 13L383.8 130.5h98.5z" transform="translate(0 40) rotate(-8 256 256)"/>
                <circle class="nav-light-red"   cx="60"  cy="330" r="9" fill="#ef4444"/>
                <circle class="nav-light-green" cx="452" cy="222" r="9" fill="#3b82f6"/>
            </svg>

            <!-- Kontrail pesawat kedua -->
            <div class="trail-puff-2" style="animation-delay: 4.5s;"></div>
            <div class="trail-puff-2" style="animation-delay: 4.9s;"></div>
            <div class="trail-puff-2" style="animation-delay: 5.3s;"></div>
            <div class="trail-puff-2" style="animation-delay: 5.7s;"></div>

            <!-- Pesawat kedua: arah berlawanan (kanan ke kiri) -->
            <svg class="plane-svg-2" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
                <path fill="#ffffff" d="M482.3 192c34.2 0 61.7 27.5 61.7 61.5c0 34.2-27.5 61.7-61.7 61.7h-98.5L269.7 469.1c-5.7 8.2-15 13-25 13h-46c-11.8 0-20.4-11.1-17.5-22.5l45.6-166.7L112 293.9l-27.5 41.3c-3.4 5.1-9.1 8.1-15.2 8.1H31.9c-9.4 0-17-7.6-17-17c0-1.4 .2-2.9 .6-4.3l24.3-88.7c1.3-4.7 1.3-9.7 0-14.4L15.5 130.2c-.4-1.4-.6-2.9-.6-4.3c0-9.4 7.6-17 17-17h37.4c6.1 0 11.8 3 15.2 8.1L112 158.3l114.8 .1L181.2 -8.2c-2.9-11.4 5.7-22.5 17.5-22.5h46c10 0 19.3 4.8 25 13L383.8 130.5h98.5z" transform="translate(0 40) rotate(-8 256 256)"/>
                <circle class="nav-light-red"   cx="60"  cy="330" r="9" fill="#ef4444"/>
                <circle class="nav-light-green" cx="452" cy="222" r="9" fill="#3b82f6"/>
            </svg>
        </div>

        <!-- Lampu tepi landasan pacu -->
        <div class="runway-lights">
            <span style="animation-delay:0s;"></span>
            <span style="animation-delay:.15s;"></span>
            <span style="animation-delay:.3s;"></span>
            <span style="animation-delay:.45s;"></span>
            <span style="animation-delay:.6s;"></span>
            <span style="animation-delay:.75s;"></span>
            <span style="animation-delay:.9s;"></span>
            <span style="animation-delay:1.05s;"></span>
            <span style="animation-delay:1.2s;"></span>
            <span style="animation-delay:1.35s;"></span>
        </div>

        <div class="max-w-4xl mx-auto text-center relative" style="z-index:4;">
            <h1 class="text-4xl md:text-5xl font-extrabold mb-6 leading-tight drop-shadow-lg">Solusi Parkir Bandara Terintegrasi Database</h1>
            <p class="text-lg text-blue-100 mb-8 drop-shadow">Pesan slot parkir Anda sekarang dan data akan langsung tercatat di sistem pusat bandara.</p>
            <div class="flex justify-center gap-4">
                <a href="login.php" class="bg-white/10 hover:bg-white/20 border border-white/30 px-6 py-3 rounded-xl font-semibold transition backdrop-blur-sm">Masuk Admin Panel</a>
            </div>
        </div>
    </section>

    <!-- Status Slot Parkir Real-time (TERHUBUNG KE DATABASE area_parkir) -->
    <section id="status" class="relative py-16 px-4 overflow-hidden" style="background: linear-gradient(180deg, #0a0f23 0%, #141a34 55%, #0a0f23 100%);">
        <div class="sky-star" style="top:8%; left:18%; animation-duration:3.1s; animation-delay:-.6s;"></div>
        <div class="sky-star" style="top:13%; left:80%; animation-duration:2.6s; animation-delay:-1.8s;"></div>
        <div class="max-w-7xl mx-auto relative">
            <div class="text-center mb-10">
                <h2 class="text-2xl md:text-3xl font-bold text-white">Ketersediaan Slot Parkir Saat Ini</h2>
                <p class="text-slate-400 mt-2">Data informasi kapasitas slot area parkir bandara</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Mobil / Regular -->
                <div class="bg-white/5 border border-white/10 backdrop-blur-sm p-6 rounded-2xl hover:border-blue-400/30 transition">
                    <div class="flex justify-between items-center mb-4">
                        <span class="bg-blue-400/10 text-blue-300 text-xs font-semibold px-3 py-1 rounded-full">Mobil / Regular</span>
                        <i class="fa-solid fa-car text-blue-300 text-xl"></i>
                    </div>
                    <p class="text-[10px] text-slate-400 uppercase tracking-wide mb-1">Slot Tersedia</p>
                    <div class="text-3xl font-black text-white mb-1"><?php echo $status_mobil['sisa']; ?> <span class="text-sm font-normal text-slate-400">/ <?php echo $slot_mobil['total']; ?> Slot</span></div>
                    <div class="w-full bg-white/10 rounded-full h-2.5 mt-4">
                        <div class="<?php echo $status_mobil['warna_bar']; ?> h-2.5 rounded-full" style="width: <?php echo $status_mobil['persen']; ?>%"></div>
                    </div>
                    <p class="text-xs <?php echo $status_mobil['warna_teks']; ?> font-medium mt-3"><i class="fa-solid fa-circle text-[8px] mr-1"></i> <?php echo $status_mobil['label']; ?></p>
                </div>
                <!-- VIP -->
                <div class="bg-white/5 border border-white/10 backdrop-blur-sm p-6 rounded-2xl hover:border-red-400/30 transition">
                    <div class="flex justify-between items-center mb-4">
                        <span class="bg-red-400/10 text-red-300 text-xs font-semibold px-3 py-1 rounded-full">VIP & Valet</span>
                        <i class="fa-solid fa-crown text-red-300 text-xl"></i>
                    </div>
                    <p class="text-[10px] text-slate-400 uppercase tracking-wide mb-1">Slot Tersedia</p>
                    <div class="text-3xl font-black text-white mb-1"><?php echo $status_vip['sisa']; ?> <span class="text-sm font-normal text-slate-400">/ <?php echo $slot_vip['total']; ?> Slot</span></div>
                    <div class="w-full bg-white/10 rounded-full h-2.5 mt-4">
                        <div class="<?php echo $status_vip['warna_bar']; ?> h-2.5 rounded-full" style="width: <?php echo $status_vip['persen']; ?>%"></div>
                    </div>
                    <p class="text-xs <?php echo $status_vip['warna_teks']; ?> font-medium mt-3"><i class="fa-solid fa-circle text-[8px] mr-1"></i> <?php echo $status_vip['label']; ?></p>
                </div>
                <!-- Motor -->
                <div class="bg-white/5 border border-white/10 backdrop-blur-sm p-6 rounded-2xl hover:border-slate-400/40 transition">
                    <div class="flex justify-between items-center mb-4">
                        <span class="bg-slate-400/10 text-slate-200 text-xs font-semibold px-3 py-1 rounded-full">Sepeda Motor</span>
                        <i class="fa-solid fa-motorcycle text-slate-200 text-xl"></i>
                    </div>
                    <p class="text-[10px] text-slate-400 uppercase tracking-wide mb-1">Slot Tersedia</p>
                    <div class="text-3xl font-black text-white mb-1"><?php echo $status_motor['sisa']; ?> <span class="text-sm font-normal text-slate-400">/ <?php echo $slot_motor['total']; ?> Slot</span></div>
                    <div class="w-full bg-white/10 rounded-full h-2.5 mt-4">
                        <div class="<?php echo $status_motor['warna_bar']; ?> h-2.5 rounded-full" style="width: <?php echo $status_motor['persen']; ?>%"></div>
                    </div>
                    <p class="text-xs <?php echo $status_motor['warna_teks']; ?> font-medium mt-3"><i class="fa-solid fa-circle text-[8px] mr-1"></i> <?php echo $status_motor['label']; ?></p>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================== -->
    <!-- GRAFIK KERAMAIAN PARKIR -->
    <!-- ========================================== -->
    <section id="pendapatan" class="relative py-16 px-4 overflow-hidden" style="background: linear-gradient(180deg, #0a0f23 0%, #141a34 55%, #0a0f23 100%);">
        <div class="sky-star" style="top:10%; left:20%; animation-duration:3.2s; animation-delay:-.8s;"></div>
        <div class="sky-star" style="top:15%; left:78%; animation-duration:2.7s; animation-delay:-1.6s;"></div>

        <div class="max-w-7xl mx-auto relative">
            <div class="text-center mb-10">
                <span class="inline-flex items-center gap-2 bg-white/5 border border-white/10 text-blue-300 text-xs font-semibold px-3 py-1 rounded-full mb-3">
                    <i class="fa-solid fa-users"></i> Aktivitas Parkir
                </span>
                <h2 class="text-2xl md:text-3xl font-bold text-white">Grafik Keramaian Parkir Bandara</h2>
                <p class="text-slate-400 mt-2">Jumlah pengguna/kendaraan yang melakukan reservasi parkir selama 6 bulan terakhir</p>
            </div>

            <div class="bg-white/5 border border-white/10 backdrop-blur-sm p-6 rounded-2xl shadow-xl">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-sm text-slate-400">Total aktivitas parkir</p>
                        <p class="text-2xl font-black text-white">
                            <?php echo number_format(array_sum($grafik_keramaian), 0, ',', '.'); ?>
                            <span class="text-sm font-normal text-slate-400">reservasi</span>
                        </p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-blue-500/10 border border-blue-400/20 flex items-center justify-center">
                        <i class="fa-solid fa-users text-blue-400 text-xl"></i>
                    </div>
                </div>

                <div style="position: relative; height: 320px;">
                    <canvas id="chartKeramaian"></canvas>
                </div>
            </div>
        </div>
    </section>

    <!-- Tata Cara Parkir di Bandara -->
    <section id="tatacara" class="relative py-16 px-4 overflow-hidden" style="background: linear-gradient(180deg, #0a0f23 0%, #141a34 55%, #0a0f23 100%);">
        <!-- Bintang dekoratif tipis untuk menyatu dengan hero -->
        <div class="sky-star" style="top:8%; left:12%; animation-duration:3.4s; animation-delay:-1.2s;"></div>
        <div class="sky-star" style="top:18%; left:88%; animation-duration:2.9s; animation-delay:-.3s;"></div>
        <div class="sky-star" style="top:6%; left:60%; animation-duration:3.8s; animation-delay:-2.1s;"></div>

        <div class="max-w-6xl mx-auto relative">
            <div class="text-center mb-12">
                <span class="inline-flex items-center gap-2 bg-white/5 border border-white/10 text-red-300 text-xs font-semibold px-3 py-1 rounded-full mb-3">
                    <i class="fa-solid fa-route"></i> Panduan Parkir
                </span>
                <h2 class="text-2xl md:text-3xl font-bold text-white">Tata Cara Parkir di Bandara</h2>
                <p class="text-slate-400 mt-2">Ikuti langkah-langkah berikut agar proses parkir Anda lancar dan aman</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="bg-white/5 border border-white/10 backdrop-blur-sm rounded-2xl p-6 relative hover:border-red-400/30 transition">
                    <div class="w-10 h-10 flex items-center justify-center bg-red-600 text-white font-bold rounded-full mb-4">1</div>
                    <i class="fa-solid fa-ticket text-red-500 text-xl mb-3"></i>
                    <h3 class="font-bold text-white mb-1">Ambil Tiket Masuk</h3>
                    <p class="text-sm text-slate-400">Tekan tombol pada mesin gerbang atau tunjukkan bukti reservasi online untuk mendapatkan tiket masuk parkir.</p>
                </div>
                <div class="bg-white/5 border border-white/10 backdrop-blur-sm rounded-2xl p-6 relative hover:border-blue-400/30 transition">
                    <div class="w-10 h-10 flex items-center justify-center bg-blue-600 text-white font-bold rounded-full mb-4">2</div>
                    <i class="fa-solid fa-signs-post text-blue-400 text-xl mb-3"></i>
                    <h3 class="font-bold text-white mb-1">Ikuti Petunjuk Arah</h3>
                    <p class="text-sm text-slate-400">Ikuti papan penunjuk menuju area/zona parkir sesuai jenis kendaraan (Motor, Mobil, Bis, Truk) dan kategori (Reguler, VIP, VVIP) Anda.</p>
                </div>
                <div class="bg-white/5 border border-white/10 backdrop-blur-sm rounded-2xl p-6 relative hover:border-red-400/30 transition">
                    <div class="w-10 h-10 flex items-center justify-center bg-red-600 text-white font-bold rounded-full mb-4">3</div>
                    <i class="fa-solid fa-square-parking text-red-500 text-xl mb-3"></i>
                    <h3 class="font-bold text-white mb-1">Parkir Sesuai Marka</h3>
                    <p class="text-sm text-slate-400">Posisikan kendaraan rapi di dalam garis marka slot yang tersedia dan pastikan tidak menghalangi jalur kendaraan lain.</p>
                </div>
                <div class="bg-white/5 border border-white/10 backdrop-blur-sm rounded-2xl p-6 relative hover:border-blue-400/30 transition">
                    <div class="w-10 h-10 flex items-center justify-center bg-blue-600 text-white font-bold rounded-full mb-4">4</div>
                    <i class="fa-solid fa-shield-halved text-blue-400 text-xl mb-3"></i>
                    <h3 class="font-bold text-white mb-1">Simpan Tiket Baik-baik</h3>
                    <p class="text-sm text-slate-400">Jangan sampai hilang atau rusak, karena tiket dibutuhkan sebagai bukti saat proses pembayaran dan keluar area parkir.</p>
                </div>
                <div class="bg-white/5 border border-white/10 backdrop-blur-sm rounded-2xl p-6 relative hover:border-red-400/30 transition">
                    <div class="w-10 h-10 flex items-center justify-center bg-red-600 text-white font-bold rounded-full mb-4">5</div>
                    <i class="fa-solid fa-money-bill-wave text-red-500 text-xl mb-3"></i>
                    <h3 class="font-bold text-white mb-1">Lakukan Pembayaran</h3>
                    <p class="text-sm text-slate-400">Saat akan keluar, lakukan pembayaran sesuai durasi parkir melalui kasir, mesin otomatis, atau non-tunai (QRIS/kartu).</p>
                </div>
                <div class="bg-white/5 border border-white/10 backdrop-blur-sm rounded-2xl p-6 relative hover:border-blue-400/30 transition">
                    <div class="w-10 h-10 flex items-center justify-center bg-blue-600 text-white font-bold rounded-full mb-4">6</div>
                    <i class="fa-solid fa-door-open text-blue-400 text-xl mb-3"></i>
                    <h3 class="font-bold text-white mb-1">Gate Terbuka Otomatis</h3>
                    <p class="text-sm text-slate-400">Setelah pembayaran terverifikasi di sistem, palang pintu keluar akan terbuka secara otomatis.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Komentar & Rating -->
    <section id="ulasan" class="relative py-16 px-4 overflow-hidden" style="background: linear-gradient(180deg, #0a0f23 0%, #141a34 55%, #0a0f23 100%);">
        <div class="sky-star" style="top:9%; left:16%; animation-duration:3s; animation-delay:-1.4s;"></div>
        <div class="sky-star" style="top:14%; left:84%; animation-duration:3.5s; animation-delay:-.5s;"></div>
        <div class="max-w-5xl mx-auto relative">
            <div class="text-center mb-10">
                <h2 class="text-2xl md:text-3xl font-bold text-white">Ulasan Pengguna</h2>
                <p class="text-slate-400 mt-2">Bagikan pengalaman parkir Anda di bandara kami</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                <!-- Form Komentar -->
                <div class="lg:col-span-5">
                    <div class="bg-white border border-slate-200 rounded-3xl shadow-2xl p-6 md:p-8">
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
                                    <label for="r5" class="star-label cursor-pointer text-slate-300 peer-checked/r5:text-red-500"><i class="fa-solid fa-star"></i></label>

                                    <input type="radio" name="rating" value="4" id="r4" class="hidden peer/r4">
                                    <label for="r4" class="star-label cursor-pointer text-slate-300 peer-checked/r4:text-red-500"><i class="fa-solid fa-star"></i></label>

                                    <input type="radio" name="rating" value="3" id="r3" class="hidden peer/r3">
                                    <label for="r3" class="star-label cursor-pointer text-slate-300 peer-checked/r3:text-red-500"><i class="fa-solid fa-star"></i></label>

                                    <input type="radio" name="rating" value="2" id="r2" class="hidden peer/r2">
                                    <label for="r2" class="star-label cursor-pointer text-slate-300 peer-checked/r2:text-red-500"><i class="fa-solid fa-star"></i></label>

                                    <input type="radio" name="rating" value="1" id="r1" checked class="hidden peer/r1">
                                    <label for="r1" class="star-label cursor-pointer text-slate-300 peer-checked/r1:text-red-500"><i class="fa-solid fa-star"></i></label>
                                </div>
                                <p class="text-[11px] text-slate-400 mt-1">Klik bintang untuk memberi rating (1-5)</p>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Komentar</label>
                                <textarea name="isi_komentar" rows="4" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="Bagaimana pengalaman parkir Anda?"></textarea>
                            </div>

                            <button type="submit" name="simpan_komentar" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-xl shadow-lg transition">Kirim Ulasan</button>
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
                    <div class="bg-white/5 border border-white/10 backdrop-blur-sm rounded-2xl p-5 hover:border-blue-400/30 transition">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <h4 class="font-bold text-white"><?php echo htmlspecialchars($kom['nama']); ?></h4>
                                <p class="text-[11px] text-slate-500"><?php echo htmlspecialchars(date('d M Y, H:i', strtotime($kom['tanggal']))); ?></p>
                            </div>
                            <div class="text-red-400 text-sm whitespace-nowrap">
                                <?php
                                for ($i = 1; $i <= 5; $i++) {
                                    echo ($i <= $rating_kom) ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>';
                                }
                                ?>
                            </div>
                        </div>
                        <p class="text-sm text-slate-300 leading-relaxed"><?php echo nl2br(htmlspecialchars($kom['komentar'])); ?></p>
                    </div>
                    <?php endwhile; else: ?>
                    <div class="bg-white/5 border border-dashed border-white/20 rounded-2xl p-8 text-center text-slate-400 text-sm">
                        Belum ada ulasan. Jadilah yang pertama memberikan ulasan!
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================== -->
    <!-- PUSAT BANTUAN -->
    <!-- ========================================== -->
    <section id="bantuan" class="relative py-16 px-4 overflow-hidden" style="background: linear-gradient(180deg, #0a0f23 0%, #141a34 55%, #0a0f23 100%);">
        <div class="sky-star" style="top:9%; left:15%; animation-duration:3.3s; animation-delay:-1s;"></div>
        <div class="sky-star" style="top:15%; left:85%; animation-duration:2.8s; animation-delay:-2s;"></div>
        <div class="max-w-6xl mx-auto relative">
            <div class="text-center mb-12">
                <span class="inline-flex items-center gap-2 bg-white/5 border border-white/10 text-red-300 text-xs font-semibold px-3 py-1 rounded-full mb-3">
                    <i class="fa-solid fa-headset"></i> Pusat Bantuan
                </span>
                <h2 class="text-2xl md:text-3xl font-bold text-white">Butuh Bantuan? Kami Siap Membantu</h2>
                <p class="text-slate-400 mt-2 max-w-xl mx-auto">Pengguna baru atau mengalami kendala saat parkir dan reservasi? Tim petugas bandara siap membantu Anda kapan saja.</p>
            </div>

            <!-- Kartu Kontak -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-14">
                <a href="tel:+622112345678" class="bg-white/5 border border-white/10 backdrop-blur-sm rounded-2xl p-6 text-center hover:shadow-lg hover:border-red-400/30 transition">
                    <div class="w-12 h-12 flex items-center justify-center bg-red-600 text-white rounded-full mx-auto mb-4">
                        <i class="fa-solid fa-phone text-lg"></i>
                    </div>
                    <h3 class="font-bold text-white mb-1">Call Center</h3>
                    <p class="text-sm text-slate-300">(021) 1234-5678</p>
                    <p class="text-xs text-slate-500 mt-1">24 Jam Setiap Hari</p>
                </a>

                <a href="https://wa.me/6281234567890" target="_blank" class="bg-white/5 border border-white/10 backdrop-blur-sm rounded-2xl p-6 text-center hover:shadow-lg hover:border-emerald-400/30 transition">
                    <div class="w-12 h-12 flex items-center justify-center bg-emerald-500 text-white rounded-full mx-auto mb-4">
                        <i class="fa-brands fa-whatsapp text-xl"></i>
                    </div>
                    <h3 class="font-bold text-white mb-1">WhatsApp</h3>
                    <p class="text-sm text-slate-300">+62 812-3456-7890</p>
                    <p class="text-xs text-slate-500 mt-1">Respon Cepat 08.00 - 22.00</p>
                </a>

                <a href="mailto:bantuan@aeroparking.co.id" class="bg-white/5 border border-white/10 backdrop-blur-sm rounded-2xl p-6 text-center hover:shadow-lg hover:border-blue-400/30 transition">
                    <div class="w-12 h-12 flex items-center justify-center bg-blue-600 text-white rounded-full mx-auto mb-4">
                        <i class="fa-solid fa-envelope text-lg"></i>
                    </div>
                    <h3 class="font-bold text-white mb-1">Email</h3>
                    <p class="text-sm text-slate-300 break-all">bantuan@aeroparking.co.id</p>
                    <p class="text-xs text-slate-500 mt-1">Dibalas dalam 1x24 jam</p>
                </a>

                <div class="bg-white/5 border border-white/10 backdrop-blur-sm rounded-2xl p-6 text-center">
                    <div class="w-12 h-12 flex items-center justify-center bg-slate-700 text-white rounded-full mx-auto mb-4">
                        <i class="fa-solid fa-location-dot text-lg"></i>
                    </div>
                    <h3 class="font-bold text-white mb-1">Loket Informasi</h3>
                    <p class="text-sm text-slate-300">Terminal Kedatangan, Lt. 1</p>
                    <p class="text-xs text-slate-500 mt-1">Dekat Pintu Keluar Parkir</p>
                </div>
            </div>

            <!-- FAQ -->
            <div class="max-w-3xl mx-auto">
                <h3 class="text-xl font-bold text-white mb-6 text-center">Pertanyaan yang Sering Diajukan</h3>
                <div class="space-y-3" id="faqAccordion">

                    <div class="faq-item bg-white/5 border border-white/10 backdrop-blur-sm rounded-2xl overflow-hidden">
                        <button type="button" class="faq-question w-full flex justify-between items-center text-left px-5 py-4 font-semibold text-white">
                            <span>Bagaimana cara melakukan reservasi parkir?</span>
                            <i class="fa-solid fa-chevron-down text-red-400 transition-transform"></i>
                        </button>
                        <div class="faq-answer max-h-0 overflow-hidden transition-all duration-300">
                            <p class="px-5 pb-4 text-sm text-slate-400">Isi formulir pada bagian "Reservasi Online", lengkapi data diri, jenis kendaraan, kategori, tanggal, dan waktu masuk. Data akan langsung tersimpan di sistem pusat bandara.</p>
                        </div>
                    </div>

                    <div class="faq-item bg-white/5 border border-white/10 backdrop-blur-sm rounded-2xl overflow-hidden">
                        <button type="button" class="faq-question w-full flex justify-between items-center text-left px-5 py-4 font-semibold text-white">
                            <span>Apa perbedaan kategori Reguler, VIP, dan VVIP?</span>
                            <i class="fa-solid fa-chevron-down text-red-400 transition-transform"></i>
                        </button>
                        <div class="faq-answer max-h-0 overflow-hidden transition-all duration-300">
                            <p class="px-5 pb-4 text-sm text-slate-400">Reguler adalah area parkir standar dengan tarif paling terjangkau. VIP menawarkan lokasi lebih dekat ke terminal dengan layanan tambahan. VVIP adalah kelas tertinggi dengan lokasi paling strategis dan fasilitas eksklusif, dengan tarif per jam yang lebih tinggi.</p>
                        </div>
                    </div>

                    <div class="faq-item bg-white/5 border border-white/10 backdrop-blur-sm rounded-2xl overflow-hidden">
                        <button type="button" class="faq-question w-full flex justify-between items-center text-left px-5 py-4 font-semibold text-white">
                            <span>Saya pengguna baru, apa yang harus saya lakukan pertama kali?</span>
                            <i class="fa-solid fa-chevron-down text-red-400 transition-transform"></i>
                        </button>
                        <div class="faq-answer max-h-0 overflow-hidden transition-all duration-300">
                            <p class="px-5 pb-4 text-sm text-slate-400">Cek dulu ketersediaan slot pada bagian "Ketersediaan Slot", lalu ikuti panduan pada "Tata Cara Parkir". Jika masih bingung, hubungi Call Center atau datangi Loket Informasi di Terminal Kedatangan.</p>
                        </div>
                    </div>

                    <div class="faq-item bg-white/5 border border-white/10 backdrop-blur-sm rounded-2xl overflow-hidden">
                        <button type="button" class="faq-question w-full flex justify-between items-center text-left px-5 py-4 font-semibold text-white">
                            <span>Bagaimana jika tiket parkir saya hilang?</span>
                            <i class="fa-solid fa-chevron-down text-red-400 transition-transform"></i>
                        </button>
                        <div class="faq-answer max-h-0 overflow-hidden transition-all duration-300">
                            <p class="px-5 pb-4 text-sm text-slate-400">Segera hubungi petugas di loket keluar atau Call Center kami. Petugas akan memverifikasi data kendaraan Anda melalui sistem sebelum proses pembayaran denda kehilangan tiket.</p>
                        </div>
                    </div>

                    <div class="faq-item bg-white/5 border border-white/10 backdrop-blur-sm rounded-2xl overflow-hidden">
                        <button type="button" class="faq-question w-full flex justify-between items-center text-left px-5 py-4 font-semibold text-white">
                            <span>Metode pembayaran apa saja yang tersedia?</span>
                            <i class="fa-solid fa-chevron-down text-red-400 transition-transform"></i>
                        </button>
                        <div class="faq-answer max-h-0 overflow-hidden transition-all duration-300">
                            <p class="px-5 pb-4 text-sm text-slate-400">Kami menerima pembayaran tunai di kasir, mesin pembayaran otomatis, serta non-tunai melalui QRIS dan kartu debit/kredit.</p>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>

    <!-- Tombol Bantuan Mengambang (Floating Help Button) -->
    <a href="#bantuan" class="fixed bottom-6 right-6 z-50 bg-slate-900 hover:bg-slate-800 border border-red-400/30 text-white w-14 h-14 rounded-full shadow-xl flex items-center justify-center transition group" title="Pusat Bantuan">
        <i class="fa-solid fa-headset text-xl"></i>
        <span class="absolute right-16 bg-slate-900 text-white text-xs font-medium px-3 py-1.5 rounded-lg opacity-0 group-hover:opacity-100 transition whitespace-nowrap pointer-events-none">Butuh Bantuan?</span>
    </a>

    <?php include 'footer.php'; ?>

    <script>
        // Grafik Keramaian Parkir
        // Data berasal dari jumlah reservasi per bulan pada tabel reservasi.
        const chartEl = document.getElementById('chartKeramaian');

        if (chartEl && typeof Chart === 'undefined') {
            console.error('Chart.js gagal dimuat dari CDN — periksa koneksi internet server/browser.');
        }

        if (chartEl && typeof Chart !== 'undefined') {
            const ctx2d = chartEl.getContext('2d');
            const gradientFill = ctx2d.createLinearGradient(0, 0, 0, 320);
            gradientFill.addColorStop(0, 'rgba(59, 130, 246, 0.40)');
            gradientFill.addColorStop(0.55, 'rgba(59, 130, 246, 0.14)');
            gradientFill.addColorStop(1, 'rgba(59, 130, 246, 0)');

            new Chart(chartEl, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($grafik_label); ?>,
                    datasets: [{
                        label: 'Orang Parkir',
                        data: <?php echo json_encode($grafik_keramaian); ?>,
                        fill: true,
                        backgroundColor: gradientFill,
                        borderColor: '#3b82f6',
                        borderWidth: 4,
                        tension: 0.45,
                        cubicInterpolationMode: 'monotone',
                        pointRadius: 5,
                        pointBackgroundColor: '#3b82f6',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointHoverRadius: 8,
                        pointHoverBackgroundColor: '#2563eb',
                        pointHoverBorderColor: '#ffffff',
                        pointHoverBorderWidth: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { intersect: false, mode: 'index' },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0a0f23',
                            borderColor: 'rgba(59,130,246,0.45)',
                            borderWidth: 1,
                            padding: 12,
                            displayColors: false,
                            callbacks: {
                                title: function(items) {
                                    return 'Keramaian: ' + items[0].label;
                                },
                                label: function(ctx) {
                                    return ctx.parsed.y.toLocaleString('id-ID') + ' pengguna parkir';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: { color: '#94a3b8', font: { size: 12 } },
                            grid: { display: false }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: '#94a3b8',
                                precision: 0,
                                callback: function(value) {
                                    return value + ' orang';
                                }
                            },
                            grid: { color: 'rgba(148,163,184,0.10)' }
                        }
                    }
                }
            });
        }

        // Efek hover untuk rating bintang
        const starWrap = document.getElementById('starRating');
        if (starWrap) {
            const labels = Array.from(starWrap.querySelectorAll('label.star-label'));
            // labels berurutan dari bintang 5 ke 1 di DOM (karena flex-row-reverse)
            const highlightUpTo = (index) => {
                labels.forEach((lbl, i) => {
                    lbl.classList.toggle('text-red-500', i >= index);
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