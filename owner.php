<?php
// Nama session khusus role owner, supaya tidak bentrok/tertimpa
// dengan session role lain (petugas/user/admin) yang aktif di tab lain
// pada browser yang sama.
session_name('owner_sess');
session_start();
include 'koneksi.php';

// Validasi akses: Hanya role 'owner' yang boleh masuk
if (!isset($_SESSION['status_login']) || $_SESSION['role'] != 'owner') {
    header("Location: login.php");
    exit();
}

// === Filter Tanggal Pengecekan ===
$tanggal_dari = $_GET['tanggal_dari'] ?? '';
$tanggal_sampai = $_GET['tanggal_sampai'] ?? '';

$where = '';
if ($tanggal_dari !== '' && $tanggal_sampai !== '') {
    $tanggal_dari_esc = mysqli_real_escape_string($conn, $tanggal_dari);
    $tanggal_sampai_esc = mysqli_real_escape_string($conn, $tanggal_sampai);
    $where = "WHERE tanggal BETWEEN '$tanggal_dari_esc' AND '$tanggal_sampai_esc'";
} elseif ($tanggal_dari !== '') {
    $tanggal_dari_esc = mysqli_real_escape_string($conn, $tanggal_dari);
    $where = "WHERE tanggal >= '$tanggal_dari_esc'";
} elseif ($tanggal_sampai !== '') {
    $tanggal_sampai_esc = mysqli_real_escape_string($conn, $tanggal_sampai);
    $where = "WHERE tanggal <= '$tanggal_sampai_esc'";
}

// Ambil ringkasan data untuk owner (mengikuti filter tanggal jika ada)
// CATATAN PERBAIKAN: total pendapatan sekarang menjumlahkan total_biaya
// DITAMBAH denda (jika ada), supaya uang hasil denda ikut masuk ke
// laporan pendapatan bisnis. Sebelumnya hanya SUM(total_biaya) saja.
$q_pendapatan = mysqli_query($conn, "SELECT SUM(total_biaya + IFNULL(denda,0)) as total, COUNT(*) as total_transaksi FROM reservasi $where");
$data_finance = mysqli_fetch_assoc($q_pendapatan);

$total_pendapatan = $data_finance['total'] ?? 0;
$total_transaksi = $data_finance['total_transaksi'] ?? 0;

// Data tabel lengkap untuk laporan (mengikuti filter tanggal jika ada)
$result = mysqli_query($conn, "SELECT * FROM reservasi $where ORDER BY id DESC");

// Label periode yang sedang ditampilkan, dipakai juga di kop cetak
if ($tanggal_dari && $tanggal_sampai) {
    $label_periode = 'Periode ' . $tanggal_dari . ' s/d ' . $tanggal_sampai;
} elseif ($tanggal_dari) {
    $label_periode = 'Sejak ' . $tanggal_dari;
} elseif ($tanggal_sampai) {
    $label_periode = 'Sampai dengan ' . $tanggal_sampai;
} else {
    $label_periode = 'Seluruh Periode (Akumulasi Real-time)';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Dashboard - Owner AeroPark</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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

        /* ===================== KOP CETAK ===================== */
        /* Hanya tampil di layar cetak / preview cetak, disembunyikan di layar biasa */
        #kopCetak { display: none; }

        @media print {
            /* Sembunyikan semua elemen navigasi & interaksi yang tidak relevan saat dicetak */
            .no-print { display: none !important; }

            /* Kop laporan resmi hanya muncul di kertas */
            #kopCetak { display: block !important; }

            html, body {
                background: #ffffff !important;
                color: #0f172a !important;
            }

            /* Reset kartu/tabel supaya ringan tinta & rapi di atas kertas putih */
            .print-card {
                background: #ffffff !important;
                border: 1px solid #cbd5e1 !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }
            .print-card * {
                color: #0f172a !important;
            }
            table {
                width: 100% !important;
                border-collapse: collapse !important;
            }
            thead tr {
                background: #f1f5f9 !important;
            }
            th, td {
                border-bottom: 1px solid #e2e8f0 !important;
            }
            main {
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            @page {
                size: A4 landscape;
                margin: 14mm;
            }
        }
    </style>
</head>
<body class="spidey-bg text-slate-100 font-sans min-h-screen flex flex-col relative">
    <div class="web-lines no-print"></div>

    <?php include 'notif-login.php'; ?>

    <!-- Navbar Owner -->
    <header class="no-print bg-[#08090f] border-b border-red-900/40 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <i class="fa-solid fa-chart-pie text-red-500 text-xl"></i>
                <span class="font-bold text-lg">AeroPark <span class="text-blue-400">Executive Dashboard</span></span>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-xs bg-gradient-to-r from-red-600/15 to-blue-600/15 text-blue-300 border border-red-600/30 px-3 py-1 rounded-full font-semibold">Hak Akses: Owner</span>
                <a href="login.php" class="text-slate-400 hover:text-white text-sm transition"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-6 py-8 flex-1 w-full relative z-10">

        <!-- Kop laporan resmi: hanya tampil saat dicetak -->
        <div id="kopCetak">
            <div style="display:flex; justify-content:space-between; align-items:flex-end; border-bottom:2px solid #0f172a; padding-bottom:12px; margin-bottom:16px;">
                <div>
                    <h1 style="font-size:20px; font-weight:800; margin:0;">AeroPark Manager</h1>
                    <p style="font-size:12px; margin:2px 0 0;">Laporan Transaksi Keuangan Parkir Bandara</p>
                </div>
                <div style="text-align:right; font-size:11px;">
                    <p style="margin:0;"><strong><?php echo htmlspecialchars($label_periode); ?></strong></p>
                    <p style="margin:2px 0 0;">Dicetak: <?php echo date('d-m-Y H:i'); ?> WIB</p>
                </div>
            </div>
            <div style="display:flex; gap:24px; font-size:12px; margin-bottom:16px;">
                <p style="margin:0;">Total Pendapatan: <strong>Rp <?php echo number_format($total_pendapatan, 0, ',', '.'); ?></strong></p>
                <p style="margin:0;">Total Transaksi: <strong><?php echo $total_transaksi; ?> Kendaraan</strong></p>
            </div>
        </div>

        <!-- Form Filter Tanggal Pengecekan -->
        <div class="no-print spidey-card p-6 rounded-2xl shadow-lg mb-8">
            <form method="GET" class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Dari Tanggal</label>
                    <input type="date" name="tanggal_dari" value="<?php echo htmlspecialchars($tanggal_dari); ?>"
                        class="bg-[#0b0d17] border border-slate-700 text-slate-100 text-sm rounded-lg px-3 py-2 focus:outline-none focus:border-red-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Sampai Tanggal</label>
                    <input type="date" name="tanggal_sampai" value="<?php echo htmlspecialchars($tanggal_sampai); ?>"
                        class="bg-[#0b0d17] border border-slate-700 text-slate-100 text-sm rounded-lg px-3 py-2 focus:outline-none focus:border-red-500">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="bg-gradient-to-r from-red-600 to-blue-700 hover:from-red-500 hover:to-blue-600 text-white font-semibold text-sm px-4 py-2 rounded-lg transition">
                        <i class="fa-solid fa-magnifying-glass mr-1"></i> Cek Laporan
                    </button>
                    <?php if ($tanggal_dari || $tanggal_sampai): ?>
                    <a href="owner-dashboard.php" class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold text-sm px-4 py-2 rounded-lg transition">
                        Reset
                    </a>
                    <?php endif; ?>
                    <button type="button" onclick="window.print()" class="bg-blue-600 hover:bg-blue-500 text-white font-semibold text-sm px-4 py-2 rounded-lg transition">
                        <i class="fa-solid fa-print mr-1"></i> Cetak Laporan
                    </button>
                </div>
            </form>
            <?php if ($tanggal_dari || $tanggal_sampai): ?>
                <p class="text-xs text-slate-400 mt-3">
                    <i class="fa-solid fa-filter mr-1"></i>
                    Menampilkan data
                    <?php if ($tanggal_dari): ?> dari <span class="text-red-400 font-semibold"><?php echo htmlspecialchars($tanggal_dari); ?></span><?php endif; ?>
                    <?php if ($tanggal_sampai): ?> sampai <span class="text-red-400 font-semibold"><?php echo htmlspecialchars($tanggal_sampai); ?></span><?php endif; ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- Kartu Statistik Utama -->
        <div class="no-print grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="spidey-card p-6 rounded-2xl shadow-lg">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Pendapatan Bisnis</p>
                <h3 class="text-3xl font-extrabold text-red-500 mt-2">Rp <?php echo number_format($total_pendapatan, 0, ',', '.'); ?></h3>
                <span class="text-xs text-red-400 font-medium mt-2 inline-block"><i class="fa-solid fa-arrow-trend-up mr-1"></i> <?php echo ($tanggal_dari || $tanggal_sampai) ? 'Sesuai periode terpilih' : 'Akumulasi real-time'; ?></span>
            </div>

            <div class="spidey-card-alt p-6 rounded-2xl shadow-lg">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Transaksi Masuk</p>
                <h3 class="text-3xl font-extrabold text-blue-400 mt-2"><?php echo $total_transaksi; ?> <span class="text-sm font-normal text-slate-400">Kendaraan</span></h3>
                <span class="text-xs text-blue-400 font-medium mt-2 inline-block"><i class="fa-solid fa-car mr-1"></i> Data seluruh terminal</span>
            </div> 

            <div class="spidey-card p-6 rounded-2xl shadow-lg">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Status Operasional</p>
                <h3 class="text-2xl font-extrabold text-white mt-2">Normal / Stabil</h3>
                <span class="text-xs text-slate-400 font-medium mt-2 inline-block">Sistem server berjalan optimal</span>
            </div>
        </div>

        <!-- Tabel Laporan Keuangan & Kendaraan (Read-Only untuk keamanan data Manajer) -->
        <div class="print-card spidey-card rounded-2xl shadow-lg overflow-hidden">
            <div class="no-print p-6 border-b border-red-900/30 flex justify-between items-center">
                <h3 class="font-bold text-lg text-white"><i class="fa-solid fa-file-invoice-dollar mr-2 text-red-500"></i> Laporan Transaksi Keseluruhan</h3>
                <div class="flex items-center gap-2">
                    <span class="text-xs bg-slate-800 text-slate-300 px-3 py-1 rounded font-medium">Mode Lihat (Read-Only)</span>
                    <button type="button" onclick="window.print()" class="text-xs bg-blue-600 hover:bg-blue-500 text-white px-3 py-1.5 rounded font-semibold transition flex items-center gap-1.5">
                        <i class="fa-solid fa-print"></i> Cetak
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-[#0b0d17]/60 text-slate-400 text-xs uppercase font-semibold border-b border-red-900/30">
                            <th class="p-4">ID</th>
                            <th class="p-4">Pemilik</th>
                            <th class="p-4">Plat Nomor</th>
                            <th class="p-4">Kendaraan</th>
                            <th class="p-4">Durasi</th>
                            <th class="p-4">Waktu Masuk</th>
                            <th class="p-4">Total Pendapatan</th>
                            <th class="p-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/70 text-sm text-slate-300">
                        <?php if (mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <?php
                                // CATATAN PERBAIKAN: tampilkan total_biaya + denda sebagai
                                // total pendapatan per baris, bukan total_biaya saja.
                                $denda_row = $row['denda'] ?? 0;
                                $total_row = $row['total_biaya'] + $denda_row;
                            ?>
                            <tr class="hover:bg-[#12141f] transition">
                                <td class="p-4 text-slate-500">#<?php echo $row['id']; ?></td>
                                <td class="p-4 font-semibold text-white"><?php echo htmlspecialchars($row['nama']); ?></td>
                                <td class="p-4 font-mono text-red-400"><?php echo htmlspecialchars($row['plat']); ?></td>
                                <td class="p-4 uppercase text-xs font-bold text-slate-400"><?php echo htmlspecialchars($row['kendaraan']); ?></td>
                                <td class="p-4"><?php echo $row['durasi']; ?> Jam</td>
                                <td class="p-4 text-slate-400"><?php echo $row['tanggal'] . ' ' . $row['waktu']; ?></td>
                                <td class="p-4 font-bold text-blue-400">
                                    Rp <?php echo number_format($total_row, 0, ',', '.'); ?>
                                    <?php if ($denda_row > 0): ?>
                                        <span class="block text-[10px] text-red-400 font-normal mt-0.5">termasuk denda Rp <?php echo number_format($denda_row, 0, ',', '.'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4">
                                    <span class="bg-blue-500/10 text-blue-400 border border-blue-500/30 text-xs px-2.5 py-1 rounded-full font-semibold">
                                        <?php echo htmlspecialchars($row['status_bayar']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="p-8 text-center text-slate-500">
                                    Belum ada data laporan transaksi pada periode yang dipilih.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <?php if (mysqli_num_rows($result) > 0): ?>
                    <tfoot>
                        <tr class="border-t-2 border-red-900/40 font-bold">
                            <td colspan="6" class="p-4 text-right text-white">Total Akumulasi Pendapatan:</td>
                            <td class="p-4 text-blue-400">Rp <?php echo number_format($total_pendapatan, 0, ',', '.'); ?></td>
                            <td class="p-4"></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </main>

   <div class="no-print">
        <?php include 'footer.php'; ?>
   </div>
</body>
</html>