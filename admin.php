<?php
// Nama session khusus role admin, supaya tidak bentrok/tertimpa
// dengan session role lain (petugas/user/owner) yang aktif di tab lain
// pada browser yang sama.
session_name('admin_sess');
session_start();
include 'koneksi.php';

// Validasi akses: Hanya role 'admin' yang boleh masuk
if (!isset($_SESSION['status_login']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

$page   = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id     = isset($_GET['id']) ? intval($_GET['id']) : 0;

// ==========================================
// AUTO-CREATE TABEL & DATA DUMMY
// ==========================================
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `tarif_parkir` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jenis_kendaraan` varchar(50) NOT NULL,
  `keterangan` varchar(100) DEFAULT NULL,
  `tarif_per_jam` int(11) NOT NULL,
  `tarif_jam_tambahan` int(11) NOT NULL,
  `maksimal_harian` int(11) NOT NULL,
  `status` enum('Aktif','Nonaktif') DEFAULT 'Aktif',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `data_kendaraan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_kendaraan` varchar(20) NOT NULL,
  `plat_nomor` varchar(20) NOT NULL,
  `jenis` varchar(50) NOT NULL,
  `warna` varchar(30) NOT NULL,
  `nama_pemilik` varchar(100) NOT NULL,
  `status` varchar(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `area_parkir` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_area` varchar(100) NOT NULL,
  `lokasi` varchar(100) NOT NULL,
  `total_slot` int(11) NOT NULL,
  `terisi` int(11) NOT NULL,
  `status` varchar(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Seed data dummy jika tabel kosong
$cek = mysqli_query($conn, "SELECT * FROM data_kendaraan");
if ($cek && mysqli_num_rows($cek) == 0) {
    mysqli_query($conn, "INSERT INTO `data_kendaraan` (`id_kendaraan`, `plat_nomor`, `jenis`, `warna`, `nama_pemilik`, `status`) VALUES
    ('#VK-9021', 'B 1234 ABC', 'Mobil (Pribadi)', 'Putih', 'Andi Wijaya', 'Parkir'),
    ('#VK-9022', 'F 5678 DE', 'Motor', 'Hitam', 'Siti Aminah', 'Keluar'),
    ('#VK-9023', 'L 9901 GHI', 'Bus/Truk', 'Biru', 'Trans Airport', 'Parkir');");
}
$cek2 = mysqli_query($conn, "SELECT * FROM area_parkir");
if ($cek2 && mysqli_num_rows($cek2) == 0) {
    mysqli_query($conn, "INSERT INTO `area_parkir` (`nama_area`, `lokasi`, `total_slot`, `terisi`, `status`) VALUES
    ('Area Terminal 1A - Mobil', 'Lantai 1 - Zona A', 500, 412, 'Aktif'),
    ('Area Terminal 1B - Motor', 'Basement - Zona B', 1200, 856, 'Aktif'),
    ('Area VIP / Eksekutif', 'Lantai Dasar Utama', 100, 45, 'Aktif');");
}
$cek3 = mysqli_query($conn, "SELECT * FROM tarif_parkir");
if ($cek3 && mysqli_num_rows($cek3) == 0) {
    mysqli_query($conn, "INSERT INTO `tarif_parkir` (`jenis_kendaraan`, `keterangan`, `tarif_per_jam`, `tarif_jam_tambahan`, `maksimal_harian`, `status`) VALUES
    ('Mobil (Pribadi)', 'Tarif Standar Roda 4', 5000, 3000, 50000, 'Aktif'),
    ('Motor', 'Tarif Standar Roda 2', 2000, 1000, 20000, 'Aktif'),
    ('Bus/Truk', 'Tarif Kendaraan Besar', 10000, 5000, 100000, 'Aktif');");
}

// ==========================================
// HANDLE PROSES CRUD
// ==========================================
if (isset($_POST['simpan_kendaraan'])) {
    $id_k    = mysqli_real_escape_string($conn, $_POST['id_kendaraan'] ?? '');
    $plat    = mysqli_real_escape_string($conn, $_POST['plat_nomor'] ?? '');
    $jenis   = mysqli_real_escape_string($conn, $_POST['jenis'] ?? '');
    $warna   = mysqli_real_escape_string($conn, $_POST['warna'] ?? '');
    $pemilik = mysqli_real_escape_string($conn, $_POST['nama_pemilik'] ?? '');
    $status  = mysqli_real_escape_string($conn, $_POST['status'] ?? '');

    if ($id > 0) {
        mysqli_query($conn, "UPDATE data_kendaraan SET id_kendaraan='$id_k', plat_nomor='$plat', jenis='$jenis', warna='$warna', nama_pemilik='$pemilik', status='$status' WHERE id=$id");
    } else {
        mysqli_query($conn, "INSERT INTO data_kendaraan (id_kendaraan, plat_nomor, jenis, warna, nama_pemilik, status) VALUES ('$id_k', '$plat', '$jenis', '$warna', '$pemilik', '$status')");
    }
    header("Location: admin.php?page=kendaraan");
    exit();
}
if ($page == 'kendaraan' && $action == 'hapus' && $id > 0) {
    mysqli_query($conn, "DELETE FROM data_kendaraan WHERE id=$id");
    header("Location: admin.php?page=kendaraan");
    exit();
}

if (isset($_POST['simpan_area'])) {
    $nama_area  = mysqli_real_escape_string($conn, $_POST['nama_area'] ?? '');
    $lokasi     = mysqli_real_escape_string($conn, $_POST['lokasi'] ?? '');
    $total_slot = intval($_POST['total_slot'] ?? 0);
    $terisi     = intval($_POST['terisi'] ?? 0);
    $status     = mysqli_real_escape_string($conn, $_POST['status'] ?? '');

    if ($id > 0) {
        mysqli_query($conn, "UPDATE area_parkir SET nama_area='$nama_area', lokasi='$lokasi', total_slot=$total_slot, terisi=$terisi, status='$status' WHERE id=$id");
    } else {
        mysqli_query($conn, "INSERT INTO area_parkir (nama_area, lokasi, total_slot, terisi, status) VALUES ('$nama_area', '$lokasi', $total_slot, $terisi, '$status')");
    }
    header("Location: admin.php?page=area");
    exit();
}
if ($page == 'area' && $action == 'hapus' && $id > 0) {
    mysqli_query($conn, "DELETE FROM area_parkir WHERE id=$id");
    header("Location: admin.php?page=area");
    exit();
}

if (isset($_POST['simpan_tarif'])) {
    $jenis_kendaraan    = mysqli_real_escape_string($conn, $_POST['jenis_kendaraan'] ?? '');
    $keterangan         = mysqli_real_escape_string($conn, $_POST['keterangan'] ?? '');
    $tarif_per_jam      = intval($_POST['tarif_per_jam'] ?? 0);
    $tarif_jam_tambah   = intval($_POST['tarif_jam_tambahan'] ?? 0);
    $maksimal_harian    = intval($_POST['maksimal_harian'] ?? 0);
    $status             = mysqli_real_escape_string($conn, $_POST['status'] ?? '');

    if ($id > 0) {
        mysqli_query($conn, "UPDATE tarif_parkir SET jenis_kendaraan='$jenis_kendaraan', keterangan='$keterangan', tarif_per_jam=$tarif_per_jam, tarif_jam_tambahan=$tarif_jam_tambah, maksimal_harian=$maksimal_harian, status='$status' WHERE id=$id");
    } else {
        mysqli_query($conn, "INSERT INTO tarif_parkir (jenis_kendaraan, keterangan, tarif_per_jam, tarif_jam_tambahan, maksimal_harian, status) VALUES ('$jenis_kendaraan', '$keterangan', $tarif_per_jam, $tarif_jam_tambah, $maksimal_harian, '$status')");
    }
    header("Location: admin.php?page=tarif");
    exit();
}
if ($page == 'tarif' && $action == 'hapus' && $id > 0) {
    mysqli_query($conn, "DELETE FROM tarif_parkir WHERE id=$id");
    header("Location: admin.php?page=tarif");
    exit();
}

if ($page == 'transaksi' && $action == 'hapus' && $id > 0) {
    // Kurangi jumlah terisi di area terkait sebelum data transaksi dihapus (anggap kendaraan keluar)
    $q_area_trx = mysqli_query($conn, "SELECT id_area FROM reservasi WHERE id=$id");
    if ($q_area_trx && mysqli_num_rows($q_area_trx) > 0) {
        $d_area_trx  = mysqli_fetch_assoc($q_area_trx);
        $id_area_trx = intval($d_area_trx['id_area'] ?? 0);
        if ($id_area_trx > 0) {
            mysqli_query($conn, "UPDATE area_parkir SET terisi = GREATEST(terisi - 1, 0) WHERE id = $id_area_trx");
        }
    }

    mysqli_query($conn, "DELETE FROM reservasi WHERE id=$id");
    header("Location: admin.php?page=transaksi");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - AeroPark Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }

        /* ===== Tema Spider-Man: aksen merah/biru di atas dasar gelap, dengan pola web tipis ===== */
        .spidey-bg {
            background-color: #0b0d17;
            background-image:
                radial-gradient(circle at 15% 10%, rgba(220,38,38,0.10) 0%, transparent 45%),
                radial-gradient(circle at 85% 90%, rgba(37,99,235,0.12) 0%, transparent 45%);
        }
        .web-lines {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            opacity: 0.05;
            background-image:
                repeating-linear-gradient(45deg, transparent 0 38px, rgba(255,255,255,0.6) 38px 39px),
                repeating-linear-gradient(-45deg, transparent 0 38px, rgba(255,255,255,0.6) 38px 39px);
        }
        .spidey-card {
            background-color: rgba(17, 15, 30, 0.85);
            border: 1px solid rgba(220, 38, 38, 0.18);
        }
        .spidey-card-alt {
            background-color: rgba(10, 12, 24, 0.9);
            border: 1px solid rgba(37, 99, 235, 0.22);
        }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #0b0d17; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #dc2626, #2563eb); border-radius: 8px; }
    </style>
</head>
<body class="spidey-bg text-slate-100 min-h-screen flex antialiased relative">
    <div class="web-lines"></div>

    <?php include 'notif-login.php'; ?>

    <!-- Sidebar -->
    <aside class="w-64 bg-[#08090f] border-r border-red-900/40 flex flex-col justify-between hidden md:flex sticky top-0 h-screen shrink-0 z-10">
        <div>
            <div class="p-6 border-b border-red-900/40 flex items-center space-x-3">
                <div class="bg-gradient-to-br from-red-600 to-blue-700 p-2 rounded-xl text-white flex items-center justify-center shadow-lg shadow-red-600/30">
                    <span class="material-symbols-outlined text-[22px]">local_parking</span>
                </div>
                <div>
                    <h1 class="font-bold text-sm text-white tracking-wide">AeroPark</h1>
                    <p class="text-[10px] text-red-500 font-medium">Administrator Panel</p>
                </div>
            </div>

            <nav class="p-4 space-y-1.5 text-sm font-medium">
                <a href="admin.php?page=dashboard" class="flex items-center space-x-3 px-4 py-3 rounded-xl transition <?php echo ($page == 'dashboard') ? 'bg-gradient-to-r from-red-600 to-blue-700 text-white shadow-lg shadow-red-600/25' : 'text-slate-400 hover:bg-[#12141f] hover:text-white'; ?>">
                    <span class="material-symbols-outlined text-[20px]">grid_view</span>
                    <span>Dashboard</span>
                </a>
                <a href="admin.php?page=statistik" class="flex items-center space-x-3 px-4 py-3 rounded-xl transition <?php echo ($page == 'statistik') ? 'bg-gradient-to-r from-red-600 to-blue-700 text-white shadow-lg shadow-red-600/25' : 'text-slate-400 hover:bg-[#12141f] hover:text-white'; ?>">
                    <span class="material-symbols-outlined text-[20px]">monitoring</span>
                    <span>Statistik Parkir</span>
                </a>
                <a href="admin.php?page=kendaraan" class="flex items-center space-x-3 px-4 py-3 rounded-xl transition <?php echo ($page == 'kendaraan') ? 'bg-gradient-to-r from-red-600 to-blue-700 text-white shadow-lg shadow-red-600/25' : 'text-slate-400 hover:bg-[#12141f] hover:text-white'; ?>">
                    <span class="material-symbols-outlined text-[20px]">directions_car</span>
                    <span>Data Kendaraan</span>
                </a>
                <a href="admin.php?page=area" class="flex items-center space-x-3 px-4 py-3 rounded-xl transition <?php echo ($page == 'area') ? 'bg-gradient-to-r from-red-600 to-blue-700 text-white shadow-lg shadow-red-600/25' : 'text-slate-400 hover:bg-[#12141f] hover:text-white'; ?>">
                    <span class="material-symbols-outlined text-[20px]">local_parking</span>
                    <span>Area Parkir</span>
                </a>
                <a href="admin.php?page=tarif" class="flex items-center space-x-3 px-4 py-3 rounded-xl transition <?php echo ($page == 'tarif') ? 'bg-gradient-to-r from-red-600 to-blue-700 text-white shadow-lg shadow-red-600/25' : 'text-slate-400 hover:bg-[#12141f] hover:text-white'; ?>">
                    <span class="material-symbols-outlined text-[20px]">payments</span>
                    <span>Tarif Parkir</span>
                </a>
                <a href="admin.php?page=transaksi" class="flex items-center space-x-3 px-4 py-3 rounded-xl transition <?php echo ($page == 'transaksi') ? 'bg-gradient-to-r from-red-600 to-blue-700 text-white shadow-lg shadow-red-600/25' : 'text-slate-400 hover:bg-[#12141f] hover:text-white'; ?>">
                    <span class="material-symbols-outlined text-[20px]">receipt_long</span>
                    <span>Transaksi</span>
                </a>
                <a href="admin.php?page=log_aktivitas" class="flex items-center space-x-3 px-4 py-3 rounded-xl transition <?php echo ($page == 'log_aktivitas') ? 'bg-gradient-to-r from-red-600 to-blue-700 text-white shadow-lg shadow-red-600/25' : 'text-slate-400 hover:bg-[#12141f] hover:text-white'; ?>">
                    <span class="material-symbols-outlined text-[20px]">history</span>
                    <span>Log Aktivitas</span>
                </a>
            </nav>
        </div>

        <div class="p-4 border-t border-red-900/40">
            <a href="login.php" class="w-full bg-red-600/15 hover:bg-red-600/30 text-red-400 border border-red-600/30 px-4 py-2.5 rounded-xl text-xs font-semibold transition flex items-center justify-center space-x-2">
                <span class="material-symbols-outlined text-[16px]">logout</span>
                <span>Keluar Sistem</span>
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0 relative z-10">
        <header class="bg-[#08090f]/90 backdrop-blur border-b border-red-900/40 sticky top-0 z-40 px-6 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <span class="text-slate-400 text-xs font-semibold uppercase tracking-wider">Dashboard / <span class="text-white capitalize"><?php echo htmlspecialchars($page); ?></span></span>
            </div>
            <div class="text-xs text-slate-400">Halo, <strong class="text-white"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></strong></div>
        </header>

        <main class="p-6 md:p-8 max-w-7xl w-full mx-auto space-y-8 flex-1">
            
            <!-- ================= PAGE: DASHBOARD ================= -->
            <?php if ($page == 'dashboard'): ?>
                <?php
                // Query aman dengan pengecekan mysqli_query
                $q_k     = mysqli_query($conn, "SELECT * FROM data_kendaraan");
                $tot_k   = $q_k ? mysqli_num_rows($q_k) : 0;
                
                $q_trx   = mysqli_query($conn, "SELECT * FROM reservasi");
                $tot_trx = $q_trx ? mysqli_num_rows($q_trx) : 0;

                // CATATAN PERBAIKAN: total pendapatan sekarang HANYA menghitung
                // transaksi yang statusnya 'Lunas' (kendaraan sudah benar-benar
                // check-in/masuk), supaya reservasi online yang masih 'Pending'
                // (baru dibooking, belum tiba) tidak ikut dihitung sebagai
                // pendapatan. Denda tetap ikut dijumlahkan.
                $q_sum          = mysqli_query($conn, "SELECT SUM(total_biaya + IFNULL(denda,0)) as total FROM reservasi WHERE status_bayar = 'Lunas'");
                $sum_pendapatan = 0;
                if ($q_sum) {
                    $d_sum = mysqli_fetch_assoc($q_sum);
                    $sum_pendapatan = isset($d_sum['total']) ? intval($d_sum['total']) : 0;
                }

                // ----- Data untuk Chart: Pendapatan Harian (7 hari terakhir) -----
                $chart_pendapatan_labels = [];
                $chart_pendapatan_data   = [];
                $nama_hari = ['Sun' => 'Min', 'Mon' => 'Sen', 'Tue' => 'Sel', 'Wed' => 'Rab', 'Thu' => 'Kam', 'Fri' => 'Jum', 'Sat' => 'Sab'];
                for ($i = 6; $i >= 0; $i--) {
                    $tgl_loop = date('Y-m-d', strtotime("-$i days"));
                    $chart_pendapatan_labels[] = $nama_hari[date('D', strtotime($tgl_loop))];

                    // CATATAN PERBAIKAN: sama seperti di atas, hanya transaksi
                    // 'Lunas' yang dihitung supaya grafik pendapatan harian
                    // sesuai dengan transaksi asli (bukan booking yang belum
                    // check-in).
                    $q_hari     = mysqli_query($conn, "SELECT SUM(total_biaya + IFNULL(denda,0)) as total FROM reservasi WHERE tanggal = '$tgl_loop' AND status_bayar = 'Lunas'");
                    $total_hari = 0;
                    if ($q_hari) {
                        $d_hari     = mysqli_fetch_assoc($q_hari);
                        $total_hari = isset($d_hari['total']) ? intval($d_hari['total']) : 0;
                    }
                    $chart_pendapatan_data[] = $total_hari;
                }

                // ----- Data untuk Chart: Trafik Kendaraan (per 2 jam, hari ini) -----
                $chart_trafik_labels = [];
                $chart_trafik_data   = [];
                $tgl_hari_ini = date('Y-m-d');
                for ($j = 0; $j < 24; $j += 2) {
                    $chart_trafik_labels[] = str_pad($j, 2, '0', STR_PAD_LEFT) . ':00';

                    $jam_awal  = str_pad($j, 2, '0', STR_PAD_LEFT) . ':00:00';
                    $jam_akhir = str_pad($j + 1, 2, '0', STR_PAD_LEFT) . ':59:59';

                    // CATATAN PERBAIKAN: trafik kendaraan hanya menghitung
                    // kendaraan yang statusnya 'Lunas' (benar-benar check-in),
                    // bukan reservasi online yang masih 'Pending'.
                    $q_jam   = mysqli_query($conn, "SELECT COUNT(*) as jml FROM reservasi WHERE tanggal = '$tgl_hari_ini' AND waktu BETWEEN '$jam_awal' AND '$jam_akhir' AND status_bayar = 'Lunas'");
                    $jml_jam = 0;
                    if ($q_jam) {
                        $d_jam   = mysqli_fetch_assoc($q_jam);
                        $jml_jam = isset($d_jam['jml']) ? intval($d_jam['jml']) : 0;
                    }
                    $chart_trafik_data[] = $jml_jam;
                }
                ?>
                <div class="space-y-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="spidey-card p-5 rounded-2xl shadow-lg">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Kendaraan</p>
                            <h3 class="text-2xl font-extrabold text-white mt-1"><?php echo $tot_k; ?> Unit</h3>
                        </div>
                        <div class="spidey-card p-5 rounded-2xl shadow-lg">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Transaksi</p>
                            <h3 class="text-2xl font-extrabold text-white mt-1"><?php echo $tot_trx; ?> Data</h3>
                        </div>
                        <div class="spidey-card-alt p-5 rounded-2xl shadow-lg">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Pendapatan Total</p>
                            <h3 class="text-2xl font-extrabold text-white mt-1">Rp <?php echo number_format($sum_pendapatan, 0, ',', '.'); ?></h3>
                        </div>
                        <div class="spidey-card-alt p-5 rounded-2xl shadow-lg">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Sistem Status</p>
                            <h3 class="text-2xl font-extrabold text-blue-400 mt-1">Online (Aktif)</h3>
                        </div>
                    </div>
                    <div class="spidey-card p-6 rounded-2xl shadow-lg space-y-3">
                        <h3 class="font-bold text-white text-base flex items-center gap-2">
                            <span class="inline-block w-2 h-2 rounded-full bg-red-600"></span>
                            Selamat Datang di AeroPark Manager
                        </h3>
                        <p class="text-xs text-slate-400 leading-relaxed">Gunakan menu navigasi di samping untuk mengelola data kendaraan, memantau area parkir, mengatur tarif, dan mencatat transaksi masuk/keluar secara real-time.</p>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div class="spidey-card p-6 rounded-2xl shadow-lg space-y-1">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="font-bold text-white text-sm">Pendapatan Harian</h3>
                                    <p class="text-[10px] text-slate-500">Data 7 hari terakhir (Juta Rupiah)</p>
                                </div>
                            </div>
                            <div class="h-64 pt-2">
                                <canvas id="chartPendapatan"></canvas>
                            </div>
                        </div>

                        <div class="spidey-card-alt p-6 rounded-2xl shadow-lg space-y-1">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="font-bold text-white text-sm">Trafik Kendaraan</h3>
                                    <p class="text-[10px] text-slate-500">Perbandingan volume per jam - hari ini</p>
                                </div>
                            </div>
                            <div class="h-64 pt-2">
                                <canvas id="chartTrafik"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const gridColor = 'rgba(148, 163, 184, 0.1)';
                    const tickColor = '#94a3b8';

                    // ===== Chart: Pendapatan Harian (merah Spider-Man) =====
                    const ctxPendapatan = document.getElementById('chartPendapatan').getContext('2d');
                    const gradientPendapatan = ctxPendapatan.createLinearGradient(0, 0, 0, 250);
                    gradientPendapatan.addColorStop(0, 'rgba(220, 38, 38, 0.45)');
                    gradientPendapatan.addColorStop(1, 'rgba(220, 38, 38, 0)');

                    new Chart(ctxPendapatan, {
                        type: 'bar',
                        data: {
                            labels: <?php echo json_encode($chart_pendapatan_labels); ?>,
                            datasets: [{
                                label: 'Pendapatan',
                                data: <?php echo json_encode($chart_pendapatan_data); ?>,
                                backgroundColor: gradientPendapatan,
                                borderColor: '#dc2626',
                                borderWidth: 1.5,
                                borderRadius: 6,
                                maxBarThickness: 36
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: (ctx) => 'Rp ' + ctx.parsed.y.toLocaleString('id-ID')
                                    }
                                }
                            },
                            scales: {
                                x: { grid: { display: false }, ticks: { color: tickColor, font: { size: 10 } } },
                                y: {
                                    grid: { color: gridColor },
                                    ticks: {
                                        color: tickColor,
                                        font: { size: 10 },
                                        callback: (val) => (val >= 1000000) ? (val / 1000000) + 'jt' : val
                                    }
                                }
                            }
                        }
                    });

                    // ===== Chart: Trafik Kendaraan (biru Spider-Man) =====
                    const ctxTrafik = document.getElementById('chartTrafik').getContext('2d');
                    const gradientTrafik = ctxTrafik.createLinearGradient(0, 0, 0, 250);
                    gradientTrafik.addColorStop(0, 'rgba(37, 99, 235, 0.4)');
                    gradientTrafik.addColorStop(1, 'rgba(37, 99, 235, 0)');

                    new Chart(ctxTrafik, {
                        type: 'line',
                        data: {
                            labels: <?php echo json_encode($chart_trafik_labels); ?>,
                            datasets: [{
                                label: 'Kendaraan',
                                data: <?php echo json_encode($chart_trafik_data); ?>,
                                borderColor: '#3b82f6',
                                backgroundColor: gradientTrafik,
                                borderWidth: 2,
                                fill: true,
                                tension: 0.4,
                                pointRadius: 3,
                                pointBackgroundColor: '#3b82f6',
                                pointBorderColor: '#0b0d17'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: (ctx) => ctx.parsed.y + ' kendaraan'
                                    }
                                }
                            },
                            scales: {
                                x: { grid: { display: false }, ticks: { color: tickColor, font: { size: 10 } } },
                                y: {
                                    grid: { color: gridColor },
                                    ticks: { color: tickColor, font: { size: 10 }, precision: 0 }
                                }
                            }
                        }
                    });
                });
                </script>

            <!-- ================= PAGE: STATISTIK ================= -->
            <?php elseif ($page == 'statistik'): ?>
                <div class="space-y-6">
                    <div class="spidey-card p-6 rounded-2xl shadow-lg space-y-4">
                        <h3 class="font-bold text-white text-base">Statistik Analitik Parkir Berdasarkan Area</h3>
                        <div class="space-y-4 pt-2">
                            <?php
                            $res_area = mysqli_query($conn, "SELECT * FROM area_parkir");
                            if ($res_area && mysqli_num_rows($res_area) > 0):
                                while ($a = mysqli_fetch_assoc($res_area)):
                                    $total_slot = intval($a['total_slot'] ?? 0);
                                    $terisi     = intval($a['terisi'] ?? 0);
                                    $persen     = ($total_slot > 0) ? round(($terisi / $total_slot) * 100) : 0;

                                    // Tentukan warna & label berdasarkan tingkat okupansi
                                    // (skema Spider-Man: merah = penuh/bahaya, biru = normal, hijau = lengang)
                                    if ($persen >= 90) {
                                        $bar_gradient = 'from-red-600 to-rose-700';
                                        $badge_class  = 'bg-red-600/15 text-red-400 border-red-600/30';
                                        $label_status = 'Hampir Penuh';
                                        $glow         = 'shadow-[0_0_10px_rgba(220,38,38,0.55)]';
                                    } elseif ($persen >= 70) {
                                        $bar_gradient = 'from-amber-400 to-orange-500';
                                        $badge_class  = 'bg-amber-500/15 text-amber-400 border-amber-500/30';
                                        $label_status = 'Padat';
                                        $glow         = 'shadow-[0_0_10px_rgba(245,158,11,0.4)]';
                                    } elseif ($persen >= 40) {
                                        $bar_gradient = 'from-blue-600 to-blue-400';
                                        $badge_class  = 'bg-blue-500/15 text-blue-400 border-blue-500/30';
                                        $label_status = 'Normal';
                                        $glow         = 'shadow-[0_0_10px_rgba(37,99,235,0.4)]';
                                    } else {
                                        $bar_gradient = 'from-emerald-500 to-teal-400';
                                        $badge_class  = 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30';
                                        $label_status = 'Lengang';
                                        $glow         = 'shadow-[0_0_10px_rgba(16,185,129,0.35)]';
                                    }
                            ?>
                            <div class="space-y-1.5">
                                <div class="flex justify-between items-center text-xs font-semibold">
                                    <div class="flex items-center gap-2">
                                        <span class="text-white"><?php echo htmlspecialchars($a['nama_area']); ?></span>
                                        <span class="text-[9px] font-bold uppercase tracking-wide border <?php echo $badge_class; ?> px-2 py-0.5 rounded-full"><?php echo $label_status; ?></span>
                                    </div>
                                    <span class="text-slate-400"><?php echo $terisi; ?> / <?php echo $total_slot; ?> Slot (<?php echo $persen; ?>%)</span>
                                </div>
                                <div class="w-full bg-[#0b0d17] h-3 rounded-full overflow-hidden border border-slate-700/80 p-[1px]">
                                    <div class="bg-gradient-to-r <?php echo $bar_gradient; ?> h-full rounded-full transition-all duration-700 ease-out <?php echo $glow; ?>" style="width: <?php echo $persen; ?>%;"></div>
                                </div>
                            </div>
                            <?php endwhile; else: ?>
                            <p class="text-xs text-slate-400">Belum ada data area parkir untuk statistik.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            <!-- ================= PAGE: KENDARAAN ================= -->
            <?php elseif ($page == 'kendaraan'): ?>
                <?php 
                $edit_data = ['id' => '', 'id_kendaraan' => '#VK-' . rand(1000,9999), 'plat_nomor' => '', 'jenis' => 'Mobil (Pribadi)', 'warna' => '', 'nama_pemilik' => '', 'status' => 'Parkir'];
                if ($action == 'edit' && $id > 0) {
                    $q_edit = mysqli_query($conn, "SELECT * FROM data_kendaraan WHERE id=$id");
                    if ($q_edit && mysqli_num_rows($q_edit) > 0) {
                        $edit_data = mysqli_fetch_assoc($q_edit);
                    }
                }
                ?>
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                    <div class="lg:col-span-4">
                        <form action="admin.php?page=kendaraan&id=<?php echo $edit_data['id']; ?>" method="POST" class="spidey-card p-5 rounded-2xl shadow-xl space-y-4">
                            <h3 class="font-bold text-white text-sm border-b border-red-900/30 pb-2"><?php echo ($action == 'edit') ? 'Edit Data Kendaraan' : 'Tambah Kendaraan Baru'; ?></h3>
                            <div>
                                <label class="block text-xs font-bold text-slate-400 mb-1">ID Kendaraan</label>
                                <input type="text" name="id_kendaraan" value="<?php echo htmlspecialchars($edit_data['id_kendaraan']); ?>" required class="w-full bg-[#0b0d17] border border-slate-700 text-white text-xs rounded-xl px-3 py-2.5 focus:outline-none focus:border-red-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-400 mb-1">Plat Nomor</label>
                                <input type="text" name="plat_nomor" value="<?php echo htmlspecialchars($edit_data['plat_nomor']); ?>" placeholder="Contoh: B 1234 XYZ" required class="w-full bg-[#0b0d17] border border-slate-700 text-white text-xs rounded-xl px-3 py-2.5 focus:outline-none focus:border-red-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-400 mb-1">Jenis Kendaraan</label>
                                <select name="jenis" class="w-full bg-[#0b0d17] border border-slate-700 text-white text-xs rounded-xl px-3 py-2.5 focus:outline-none focus:border-red-500">
                                    <option value="Mobil (Pribadi)" <?php echo ($edit_data['jenis'] == 'Mobil (Pribadi)') ? 'selected' : ''; ?>>Mobil (Pribadi)</option>
                                    <option value="Motor" <?php echo ($edit_data['jenis'] == 'Motor') ? 'selected' : ''; ?>>Motor</option>
                                    <option value="Bus/Truk" <?php echo ($edit_data['jenis'] == 'Bus/Truk') ? 'selected' : ''; ?>>Bus/Truk</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-400 mb-1">Warna</label>
                                <input type="text" name="warna" value="<?php echo htmlspecialchars($edit_data['warna']); ?>" required class="w-full bg-[#0b0d17] border border-slate-700 text-white text-xs rounded-xl px-3 py-2.5 focus:outline-none focus:border-red-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-400 mb-1">Nama Pemilik</label>
                                <input type="text" name="nama_pemilik" value="<?php echo htmlspecialchars($edit_data['nama_pemilik']); ?>" required class="w-full bg-[#0b0d17] border border-slate-700 text-white text-xs rounded-xl px-3 py-2.5 focus:outline-none focus:border-red-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-400 mb-1">Status</label>
                                <select name="status" class="w-full bg-[#0b0d17] border border-slate-700 text-white text-xs rounded-xl px-3 py-2.5 focus:outline-none focus:border-red-500">
                                    <option value="Parkir" <?php echo ($edit_data['status'] == 'Parkir') ? 'selected' : ''; ?>>Parkir</option>
                                    <option value="Keluar" <?php echo ($edit_data['status'] == 'Keluar') ? 'selected' : ''; ?>>Keluar</option>
                                </select>
                            </div>
                            <div class="flex space-x-2 pt-2">
                                <button type="submit" name="simpan_kendaraan" class="flex-1 bg-gradient-to-r from-red-600 to-blue-700 hover:from-red-500 hover:to-blue-600 text-white font-bold py-2.5 rounded-xl text-xs uppercase transition">Simpan</button>
                                <?php if ($action == 'edit'): ?>
                                    <a href="admin.php?page=kendaraan" class="bg-slate-800 hover:bg-slate-700 text-white font-bold px-4 py-2.5 rounded-xl text-xs text-center transition">Batal</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>

                    <div class="lg:col-span-8">
                        <div class="spidey-card-alt p-6 rounded-2xl shadow-xl space-y-4">
                            <h3 class="font-bold text-white text-base">Daftar Kendaraan Terdaftar</h3>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="border-b border-red-900/30 text-slate-400">
                                            <th class="p-3">ID</th>
                                            <th class="p-3">Plat Nomor</th>
                                            <th class="p-3">Jenis</th>
                                            <th class="p-3">Warna / Pemilik</th>
                                            <th class="p-3">Status</th>
                                            <th class="p-3 text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-800/70">
                                        <?php
                                        $res = mysqli_query($conn, "SELECT * FROM data_kendaraan ORDER BY id DESC");
                                        if ($res && mysqli_num_rows($res) > 0):
                                            while ($row = mysqli_fetch_assoc($res)):
                                        ?>
                                        <tr>
                                            <td class="p-3 font-bold text-white"><?php echo htmlspecialchars($row['id_kendaraan'] ?? ''); ?></td>
                                            <td class="p-3 font-mono"><?php echo htmlspecialchars($row['plat_nomor'] ?? ''); ?></td>
                                            <td class="p-3"><?php echo htmlspecialchars($row['jenis'] ?? ''); ?></td>
                                            <td class="p-3"><?php echo htmlspecialchars($row['warna'] ?? ''); ?> <br><span class="text-[10px] text-slate-400"><?php echo htmlspecialchars($row['nama_pemilik'] ?? ''); ?></span></td>
                                            <td class="p-3"><span class="text-blue-400 font-bold"><?php echo htmlspecialchars($row['status'] ?? ''); ?></span></td>
                                            <td class="p-3 text-center space-x-1 whitespace-nowrap">
                                                <a href="admin.php?page=kendaraan&action=edit&id=<?php echo $row['id']; ?>" class="inline-block bg-amber-500/20 hover:bg-amber-500/30 text-amber-400 px-2.5 py-1 rounded-lg text-[10px] font-bold transition">Edit</a>
                                                <a href="admin.php?page=kendaraan&action=hapus&id=<?php echo $row['id']; ?>" onclick="return confirm('Hapus data ini?')" class="inline-block bg-red-600/20 hover:bg-red-600/30 text-red-400 px-2.5 py-1 rounded-lg text-[10px] font-bold transition">Hapus</a>
                                            </td>
                                        </tr>
                                        <?php endwhile; else: ?>
                                        <tr><td colspan="6" class="p-4 text-center text-slate-400">Belum ada data kendaraan.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            <!-- ================= PAGE: AREA PARKIR ================= -->
            <?php elseif ($page == 'area'): ?>
                <?php 
                $edit_area = ['id' => '', 'nama_area' => '', 'lokasi' => '', 'total_slot' => 100, 'terisi' => 0, 'status' => 'Aktif'];
                if ($action == 'edit' && $id > 0) {
                    $q_edit = mysqli_query($conn, "SELECT * FROM area_parkir WHERE id=$id");
                    if ($q_edit && mysqli_num_rows($q_edit) > 0) {
                        $edit_area = mysqli_fetch_assoc($q_edit);
                    }
                }
                ?>
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                    <div class="lg:col-span-4">
                        <form action="admin.php?page=area&id=<?php echo $edit_area['id']; ?>" method="POST" class="spidey-card p-5 rounded-2xl shadow-xl space-y-4">
                            <h3 class="font-bold text-white text-sm border-b border-red-900/30 pb-2"><?php echo ($action == 'edit') ? 'Edit Area Parkir' : 'Tambah Area Baru'; ?></h3>
                            <div>
                                <label class="block text-xs font-bold text-slate-400 mb-1">Nama Area</label>
                                <input type="text" name="nama_area" value="<?php echo htmlspecialchars($edit_area['nama_area']); ?>" required class="w-full bg-[#0b0d17] border border-slate-700 text-white text-xs rounded-xl px-3 py-2.5 focus:outline-none focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-400 mb-1">Lokasi</label>
                                <input type="text" name="lokasi" value="<?php echo htmlspecialchars($edit_area['lokasi']); ?>" required class="w-full bg-[#0b0d17] border border-slate-700 text-white text-xs rounded-xl px-3 py-2.5 focus:outline-none focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-400 mb-1">Total Slot</label>
                                <input type="number" name="total_slot" value="<?php echo $edit_area['total_slot']; ?>" required class="w-full bg-[#0b0d17] border border-slate-700 text-white text-xs rounded-xl px-3 py-2.5 focus:outline-none focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-400 mb-1">Terisi</label>
                                <input type="number" name="terisi" value="<?php echo $edit_area['terisi']; ?>" required class="w-full bg-[#0b0d17] border border-slate-700 text-white text-xs rounded-xl px-3 py-2.5 focus:outline-none focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-400 mb-1">Status</label>
                                <select name="status" class="w-full bg-[#0b0d17] border border-slate-700 text-white text-xs rounded-xl px-3 py-2.5 focus:outline-none focus:border-blue-500">
                                    <option value="Aktif" <?php echo ($edit_area['status'] == 'Aktif') ? 'selected' : ''; ?>>Aktif</option>
                                    <option value="Nonaktif" <?php echo ($edit_area['status'] == 'Nonaktif') ? 'selected' : ''; ?>>Nonaktif</option>
                                </select>
                            </div>
                            <div class="flex space-x-2 pt-2">
                                <button type="submit" name="simpan_area" class="flex-1 bg-gradient-to-r from-red-600 to-blue-700 hover:from-red-500 hover:to-blue-600 text-white font-bold py-2.5 rounded-xl text-xs uppercase transition">Simpan</button>
                                <?php if ($action == 'edit'): ?>
                                    <a href="admin.php?page=area" class="bg-slate-800 hover:bg-slate-700 text-white font-bold px-4 py-2.5 rounded-xl text-xs text-center transition">Batal</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>

                    <div class="lg:col-span-8">
                        <div class="spidey-card-alt p-6 rounded-2xl shadow-xl space-y-4">
                            <h3 class="font-bold text-white text-base">Manajemen Area Parkir</h3>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="border-b border-red-900/30 text-slate-400">
                                            <th class="p-3">Nama Area</th>
                                            <th class="p-3">Lokasi</th>
                                            <th class="p-3">Slot Total / Terisi</th>
                                            <th class="p-3">Status</th>
                                            <th class="p-3 text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-800/70">
                                        <?php
                                        $res = mysqli_query($conn, "SELECT * FROM area_parkir ORDER BY id DESC");
                                        if ($res && mysqli_num_rows($res) > 0):
                                            while ($row = mysqli_fetch_assoc($res)):
                                        ?>
                                        <tr>
                                            <td class="p-3 font-bold text-white"><?php echo htmlspecialchars($row['nama_area'] ?? ''); ?></td>
                                            <td class="p-3"><?php echo htmlspecialchars($row['lokasi'] ?? ''); ?></td>
                                            <td class="p-3"><?php echo intval($row['terisi'] ?? 0); ?> / <?php echo intval($row['total_slot'] ?? 0); ?></td>
                                            <td class="p-3"><span class="text-blue-400 font-bold"><?php echo htmlspecialchars($row['status'] ?? ''); ?></span></td>
                                            <td class="p-3 text-center space-x-1 whitespace-nowrap">
                                                <a href="admin.php?page=area&action=edit&id=<?php echo $row['id']; ?>" class="inline-block bg-amber-500/20 hover:bg-amber-500/30 text-amber-400 px-2.5 py-1 rounded-lg text-[10px] font-bold transition">Edit</a>
                                                <a href="admin.php?page=area&action=hapus&id=<?php echo $row['id']; ?>" onclick="return confirm('Hapus area ini?')" class="inline-block bg-red-600/20 hover:bg-red-600/30 text-red-400 px-2.5 py-1 rounded-lg text-[10px] font-bold transition">Hapus</a>
                                            </td>
                                        </tr>
                                        <?php endwhile; else: ?>
                                        <tr><td colspan="5" class="p-4 text-center text-slate-400">Belum ada data area parkir.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            <!-- ================= PAGE: TARIF PARKIR ================= -->
            <?php elseif ($page == 'tarif'): ?>
                <?php 
                $edit_tarif = ['id' => '', 'jenis_kendaraan' => '', 'keterangan' => '', 'tarif_per_jam' => 0, 'tarif_jam_tambahan' => 0, 'maksimal_harian' => 0, 'status' => 'Aktif'];
                if ($action == 'edit' && $id > 0) {
                    $q_edit = mysqli_query($conn, "SELECT * FROM tarif_parkir WHERE id=$id");
                    if ($q_edit && mysqli_num_rows($q_edit) > 0) {
                        $edit_tarif = mysqli_fetch_assoc($q_edit);
                    }
                }
                ?>
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                    <div class="lg:col-span-4">
                        <form action="admin.php?page=tarif&id=<?php echo $edit_tarif['id']; ?>" method="POST" class="spidey-card p-5 rounded-2xl shadow-xl space-y-3">
                            <h3 class="font-bold text-white text-sm border-b border-red-900/30 pb-2"><?php echo ($action == 'edit') ? 'Edit Tarif' : 'Tambah Tarif Baru'; ?></h3>
                            <div>
                                <label class="block text-xs font-bold text-slate-400 mb-1">Jenis Kendaraan</label>
                                <input type="text" name="jenis_kendaraan" value="<?php echo htmlspecialchars($edit_tarif['jenis_kendaraan']); ?>" required class="w-full bg-[#0b0d17] border border-slate-700 text-white text-xs rounded-xl px-3 py-2 focus:outline-none focus:border-red-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-400 mb-1">Keterangan</label>
                                <input type="text" name="keterangan" value="<?php echo htmlspecialchars($edit_tarif['keterangan']); ?>" class="w-full bg-[#0b0d17] border border-slate-700 text-white text-xs rounded-xl px-3 py-2 focus:outline-none focus:border-red-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-400 mb-1">Tarif Per Jam (Rp)</label>
                                <input type="number" name="tarif_per_jam" value="<?php echo $edit_tarif['tarif_per_jam']; ?>" required class="w-full bg-[#0b0d17] border border-slate-700 text-white text-xs rounded-xl px-3 py-2 focus:outline-none focus:border-red-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-400 mb-1">Tarif Jam Tambahan (Rp)</label>
                                <input type="number" name="tarif_jam_tambahan" value="<?php echo $edit_tarif['tarif_jam_tambahan']; ?>" required class="w-full bg-[#0b0d17] border border-slate-700 text-white text-xs rounded-xl px-3 py-2 focus:outline-none focus:border-red-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-400 mb-1">Maksimal Harian (Rp)</label>
                                <input type="number" name="maksimal_harian" value="<?php echo $edit_tarif['maksimal_harian']; ?>" required class="w-full bg-[#0b0d17] border border-slate-700 text-white text-xs rounded-xl px-3 py-2 focus:outline-none focus:border-red-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-400 mb-1">Status</label>
                                <select name="status" class="w-full bg-[#0b0d17] border border-slate-700 text-white text-xs rounded-xl px-3 py-2 focus:outline-none focus:border-red-500">
                                    <option value="Aktif" <?php echo ($edit_tarif['status'] == 'Aktif') ? 'selected' : ''; ?>>Aktif</option>
                                    <option value="Nonaktif" <?php echo ($edit_tarif['status'] == 'Nonaktif') ? 'selected' : ''; ?>>Nonaktif</option>
                                </select>
                            </div>
                            <div class="flex space-x-2 pt-2">
                                <button type="submit" name="simpan_tarif" class="flex-1 bg-gradient-to-r from-red-600 to-blue-700 hover:from-red-500 hover:to-blue-600 text-white font-bold py-2.5 rounded-xl text-xs uppercase transition">Simpan</button>
                                <?php if ($action == 'edit'): ?>
                                    <a href="admin.php?page=tarif" class="bg-slate-800 hover:bg-slate-700 text-white font-bold px-4 py-2.5 rounded-xl text-xs text-center transition">Batal</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>

                    <div class="lg:col-span-8">
                        <div class="spidey-card-alt p-6 rounded-2xl shadow-xl space-y-4">
                            <h3 class="font-bold text-white text-base">Daftar Pengaturan Tarif</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php
                                $res = mysqli_query($conn, "SELECT * FROM tarif_parkir");
                                if ($res && mysqli_num_rows($res) > 0):
                                    while ($t = mysqli_fetch_assoc($res)):
                                ?>
                                <div class="bg-[#0b0d17] border border-red-900/25 p-4 rounded-xl space-y-2 text-xs flex flex-col justify-between">
                                    <div class="space-y-1">
                                        <div class="flex justify-between items-start">
                                            <h4 class="font-bold text-white text-sm"><?php echo htmlspecialchars($t['jenis_kendaraan'] ?? ''); ?></h4>
                                            <span class="text-[10px] bg-blue-500/10 text-blue-400 px-2 py-0.5 rounded font-bold"><?php echo htmlspecialchars($t['status'] ?? ''); ?></span>
                                        </div>
                                        <p class="text-slate-400 text-[11px]"><?php echo htmlspecialchars($t['keterangan'] ?? ''); ?></p>
                                        <div class="border-t border-slate-800 pt-2 space-y-1">
                                            <div class="flex justify-between"><span class="text-slate-400">Per Jam:</span> <span class="font-bold text-white">Rp <?php echo number_format(intval($t['tarif_per_jam'] ?? 0), 0, ',', '.'); ?></span></div>
                                            <div class="flex justify-between"><span class="text-slate-400">Jam Berikutnya:</span> <span class="font-bold text-red-400">Rp <?php echo number_format(intval($t['tarif_jam_tambahan'] ?? 0), 0, ',', '.'); ?> /jam</span></div>
                                            <div class="flex justify-between"><span class="text-slate-400">Maksimal:</span> <span class="font-bold text-blue-400">Rp <?php echo number_format(intval($t['maksimal_harian'] ?? 0), 0, ',', '.'); ?></span></div>
                                        </div>
                                    </div>
                                    <div class="pt-2 flex justify-end space-x-1 border-t border-slate-800/60 mt-2">
                                        <a href="admin.php?page=tarif&action=edit&id=<?php echo $t['id']; ?>" class="bg-amber-500/20 hover:bg-amber-500/30 text-amber-400 px-2.5 py-1 rounded text-[10px] font-bold transition">Edit</a>
                                        <a href="admin.php?page=tarif&action=hapus&id=<?php echo $t['id']; ?>" onclick="return confirm('Hapus tarif ini?')" class="bg-red-600/20 hover:bg-red-600/30 text-red-400 px-2.5 py-1 rounded text-[10px] font-bold transition">Hapus</a>
                                    </div>
                                </div>
                                <?php endwhile; else: ?>
                                <div class="col-span-2 text-center text-slate-400 py-4">Belum ada data tarif parkir.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            <!-- ================= PAGE: TRANSAKSI ================= -->
            <?php elseif ($page == 'transaksi'): ?>
                <div class="space-y-6">
                    <?php if (isset($_GET['status']) && $_GET['status'] == 'sukses'): ?>
                        <div class="bg-blue-600/15 border border-blue-600/30 text-blue-400 px-4 py-3 rounded-2xl text-xs font-semibold">
                            Transaksi berhasil disimpan dan gate keluar telah dibuka!
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 gap-6 items-start">
<div>
    <div class="spidey-card p-6 rounded-2xl shadow-xl space-y-4">
        <h3 class="font-bold text-white text-base">Riwayat Transaksi Parkir</h3>
        <p class="text-[10px] text-slate-500 -mt-2">Termasuk data yang diinput oleh petugas lapangan.</p>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-red-900/30 text-slate-400">
                        <th class="p-3">Tiket</th>
                        <th class="p-3">Nama / Plat</th>
                        <th class="p-3">Durasi</th>
                        <th class="p-3">Biaya</th>
                        <th class="p-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/70">
                    <?php
                    $res = mysqli_query($conn, "SELECT * FROM reservasi ORDER BY id DESC LIMIT 10");
                    if ($res && mysqli_num_rows($res) > 0):
                        while ($trx = mysqli_fetch_assoc($res)):
                            // CATATAN PERBAIKAN: biaya yang ditampilkan di riwayat
                            // transaksi sekarang termasuk denda (jika ada), supaya
                            // konsisten dengan struk yang diterima pengguna.
                            $denda_trx = intval($trx['denda'] ?? 0);
                            $biaya_trx = intval($trx['total_biaya'] ?? 0) + $denda_trx;
                    ?>
                    <tr>
                        <td class="p-3 font-bold text-white">AP<?php echo str_pad($trx['id'], 6, '0', STR_PAD_LEFT); ?></td>
                        <td class="p-3">
                            <div class="font-mono uppercase"><?php echo htmlspecialchars($trx['plat'] ?? ''); ?></div>
                            <div class="text-[10px] text-slate-400"><?php echo htmlspecialchars($trx['nama'] ?? ''); ?></div>
                        </td>
                        <td class="p-3 text-slate-300"><?php echo intval($trx['durasi'] ?? 0); ?> Jam</td>
                        <td class="p-3 font-bold text-blue-400">
                            Rp <?php echo number_format($biaya_trx, 0, ',', '.'); ?>
                            <?php if ($denda_trx > 0): ?>
                                <span class="block text-[10px] text-red-400 font-normal mt-0.5">termasuk denda Rp <?php echo number_format($denda_trx, 0, ',', '.'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="p-3 text-center">
                            <a href="admin.php?page=transaksi&action=hapus&id=<?php echo $trx['id']; ?>" onclick="return confirm('Hapus transaksi ini?')" class="inline-block bg-red-600/20 hover:bg-red-600/30 text-red-400 px-2.5 py-1 rounded-lg text-[10px] font-bold transition">Hapus</a>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="5" class="p-4 text-center text-slate-400">Belum ada riwayat transaksi.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

            <!-- ================= PAGE: LOG AKTIVITAS ================= -->
            <?php elseif ($page == 'log_aktivitas'): ?>
                <div class="space-y-6">
                    <div class="spidey-card p-6 rounded-2xl shadow-xl space-y-4">
                        <h3 class="font-bold text-white text-base">Riwayat Login & Aktivitas Sistem</h3>
                        <p class="text-[10px] text-slate-500 -mt-2">Mencatat aktivitas login, transaksi, dan perubahan data di seluruh sistem.</p>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="border-b border-red-900/30 text-slate-400">
                                        <th class="p-3">Waktu</th>
                                        <th class="p-3">User</th>
                                        <th class="p-3">Role</th>
                                        <th class="p-3">Aktivitas</th>
                                        <th class="p-3">IP Address</th>
                                        <th class="p-3">Level</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800/70">
                                    <?php
                                    $res = mysqli_query($conn, "SELECT * FROM log_aktivitas ORDER BY id DESC LIMIT 50");
                                    if ($res && mysqli_num_rows($res) > 0):
                                        while ($lg = mysqli_fetch_assoc($res)):
                                            $level = $lg['level'] ?? 'Info';
                                            switch ($level) {
                                                case 'Success': $badge = 'bg-blue-500/15 text-blue-400 border-blue-500/30'; break;
                                                case 'Warning': $badge = 'bg-amber-500/15 text-amber-400 border-amber-500/30'; break;
                                                case 'Danger':  $badge = 'bg-red-600/15 text-red-400 border-red-600/30'; break;
                                                default:        $badge = 'bg-slate-500/15 text-slate-300 border-slate-500/30'; break;
                                            }
                                    ?>
                                    <tr>
                                        <td class="p-3 text-slate-300 whitespace-nowrap"><?php echo htmlspecialchars($lg['waktu']); ?></td>
                                        <td class="p-3 font-bold text-white"><?php echo htmlspecialchars($lg['user']); ?></td>
                                        <td class="p-3"><?php echo htmlspecialchars($lg['role']); ?></td>
                                        <td class="p-3"><?php echo htmlspecialchars($lg['aktivitas']); ?></td>
                                        <td class="p-3 font-mono text-slate-400"><?php echo htmlspecialchars($lg['ip_address']); ?></td>
                                        <td class="p-3"><span class="text-[10px] font-bold uppercase border <?php echo $badge; ?> px-2 py-0.5 rounded-full"><?php echo htmlspecialchars($level); ?></span></td>
                                    </tr>
                                    <?php endwhile; else: ?>
                                    <tr><td colspan="6" class="p-4 text-center text-slate-400">Belum ada aktivitas tercatat.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <div class="spidey-card p-6 rounded-2xl text-slate-400">
                    Halaman <span class="text-white font-bold capitalize"><?php echo htmlspecialchars($page); ?></span> tidak ditemukan.
                </div>
            <?php endif; ?>

        </main>

        <?php include 'footer.php'; ?>
    </div>

</body>
</html>