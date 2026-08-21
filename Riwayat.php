<?php
session_name('petugas_sess');
session_start();
include 'koneksi.php';

// Validasi akses: Hanya role 'petugas' yang boleh masuk
if (!isset($_SESSION['status_login']) || $_SESSION['role'] != 'petugas') {
    header("Location: login.php");
    exit();
}

// Ambil parameter filter dari GET
$cari    = isset($_GET['cari']) ? trim($_GET['cari']) : '';
$tanggal = isset($_GET['tanggal']) ? trim($_GET['tanggal']) : '';

$where = [];
if ($cari !== '') {
    $cari_safe = mysqli_real_escape_string($conn, $cari);
    $where[] = "(nama LIKE '%$cari_safe%' OR plat LIKE '%$cari_safe%')";
}
if ($tanggal !== '') {
    $tanggal_safe = mysqli_real_escape_string($conn, $tanggal);
    $where[] = "tanggal = '$tanggal_safe'";
}
$where_sql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

$query = "SELECT * FROM reservasi $where_sql ORDER BY id DESC";
$result = mysqli_query($conn, $query);

// Ringkasan total pendapatan dari hasil filter saat ini
$total_transaksi = 0;
$total_pendapatan = 0;
if ($result) {
    $total_transaksi = mysqli_num_rows($result);
}
// CATATAN PERBAIKAN: total pendapatan sekarang menjumlahkan total_biaya
// DITAMBAH denda (jika ada), supaya uang hasil denda ikut masuk ke
// ringkasan pendapatan di halaman riwayat struk.
$query_total = "SELECT SUM(total_biaya + IFNULL(denda,0)) AS total FROM reservasi $where_sql";
$result_total = mysqli_query($conn, $query_total);
if ($result_total) {
    $row_total = mysqli_fetch_assoc($result_total);
    $total_pendapatan = $row_total['total'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Struk - AeroPark Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #0b0d17;
            background-image:
                radial-gradient(circle at 15% 10%, rgba(220,38,38,0.10) 0%, transparent 45%),
                radial-gradient(circle at 85% 90%, rgba(37,99,235,0.12) 0%, transparent 45%);
        }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #0b0d17; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #dc2626, #2563eb); border-radius: 8px; }
    </style>
</head>
<body class="min-h-screen text-slate-200 flex flex-col">

    <!-- Header Navbar -->
    <header class="bg-[#08090f] text-white shadow-md shadow-black/30 border-b border-red-900/40 sticky top-0 z-50">
        <div class="max-w-6xl mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <div class="bg-gradient-to-br from-red-600 to-blue-700 p-2 rounded-lg ring-1 ring-white/10">
                    <span class="material-symbols-outlined text-white text-[24px]">local_parking</span>
                </div>
                <div>
                    <h1 class="font-bold text-base leading-tight text-slate-100">Riwayat Struk Parkir</h1>
                    <p class="text-xs text-blue-300">Halo, <?php echo htmlspecialchars($_SESSION['nama_lengkap'] ?? $_SESSION['username']); ?></p>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <a href="petugas.php" class="bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 rounded-lg text-xs font-semibold transition flex items-center space-x-1.5 shadow ring-1 ring-white/10">
                    <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                    <span>Input Baru</span>
                </a>
                <a href="login.php" class="bg-red-700 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-xs font-semibold transition flex items-center space-x-1.5 shadow">
                    <span class="material-symbols-outlined text-[16px]">logout</span>
                    <span>Keluar</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="max-w-6xl mx-auto px-6 py-8 flex-1 w-full">

        <!-- Ringkasan -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-6">
            <div class="bg-[#0e1019] rounded-2xl shadow-md shadow-black/30 border border-red-900/30 p-5 flex items-center space-x-4">
                <div class="bg-red-500/10 p-3 rounded-xl">
                    <span class="material-symbols-outlined text-red-400 text-[26px]">receipt_long</span>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Transaksi</p>
                    <p class="text-xl font-bold text-slate-100"><?php echo number_format($total_transaksi, 0, ',', '.'); ?></p>
                </div>
            </div>
            <div class="bg-[#0e1019] rounded-2xl shadow-md shadow-black/30 border border-blue-900/30 p-5 flex items-center space-x-4">
                <div class="bg-blue-500/10 p-3 rounded-xl">
                    <span class="material-symbols-outlined text-blue-400 text-[26px]">payments</span>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Pendapatan</p>
                    <p class="text-xl font-bold text-slate-100">Rp <?php echo number_format($total_pendapatan, 0, ',', '.'); ?></p>
                </div>
            </div>
        </div>

        <div class="bg-[#0e1019] rounded-2xl shadow-xl shadow-black/30 border border-red-900/30 p-8">
            <div class="mb-6 border-b border-slate-800 pb-4">
                <h2 class="text-xl font-bold text-slate-100 tracking-tight">Daftar Riwayat Struk</h2>
                <p class="text-xs text-slate-400 mt-1">Cari dan lihat kembali struk parkir yang pernah dicetak.</p>
            </div>

            <!-- Form Filter -->
            <form action="" method="GET" class="flex flex-col md:flex-row gap-3 mb-6">
                <div class="relative flex-1">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500">
                        <span class="material-symbols-outlined text-[20px]">search</span>
                    </span>
                    <input type="text" name="cari" value="<?php echo htmlspecialchars($cari); ?>" placeholder="Cari nama atau plat nomor..." class="w-full bg-slate-900 border border-slate-700 text-slate-100 placeholder-slate-500 rounded-xl py-2.5 pl-11 pr-4 text-sm focus:ring-2 focus:ring-red-500 focus:bg-slate-900 outline-none transition">
                </div>
                <div class="relative">
                    <input type="date" name="tanggal" value="<?php echo htmlspecialchars($tanggal); ?>" class="bg-slate-900 border border-slate-700 text-slate-100 rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-red-500 focus:bg-slate-900 outline-none transition [color-scheme:dark]">
                </div>
                <button type="submit" class="bg-gradient-to-r from-red-600 to-blue-700 hover:from-red-500 hover:to-blue-600 text-white font-semibold px-6 py-2.5 rounded-xl shadow shadow-red-900/30 transition flex items-center justify-center space-x-2">
                    <span class="material-symbols-outlined text-[18px]">filter_alt</span>
                    <span>Filter</span>
                </button>
                <?php if ($cari !== '' || $tanggal !== ''): ?>
                <a href="riwayat.php" class="bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold px-4 py-2.5 rounded-xl transition flex items-center justify-center space-x-1.5">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                    <span>Reset</span>
                </a>
                <?php endif; ?>
            </form>

            <!-- Tabel Riwayat -->
            <div class="overflow-x-auto rounded-xl border border-slate-800">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-900/60 text-slate-400 text-xs uppercase tracking-wider">
                        <tr>
                            <th class="px-4 py-3">Tanggal / Waktu</th>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Plat Nomor</th>
                            <th class="px-4 py-3">Kendaraan</th>
                            <th class="px-4 py-3">Durasi</th>
                            <th class="px-4 py-3">Total Biaya</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        <?php if ($result && mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <?php
                                    // CATATAN PERBAIKAN: kolom "Total Biaya" sekarang
                                    // menampilkan total_biaya + denda (jika ada), supaya
                                    // konsisten dengan struk yang diterima pengguna.
                                    $denda_row = intval($row['denda'] ?? 0);
                                    $biaya_row = intval($row['total_biaya']) + $denda_row;
                                ?>
                                <tr class="hover:bg-slate-900/60 transition">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="font-medium text-slate-100"><?php echo htmlspecialchars(date('d/m/Y', strtotime($row['tanggal']))); ?></div>
                                        <div class="text-xs text-slate-500"><?php echo htmlspecialchars($row['waktu']); ?></div>
                                    </td>
                                    <td class="px-4 py-3 text-slate-200"><?php echo htmlspecialchars($row['nama']); ?></td>
                                    <td class="px-4 py-3 font-mono uppercase text-slate-200"><?php echo htmlspecialchars($row['plat']); ?></td>
                                    <td class="px-4 py-3 capitalize text-slate-200"><?php echo htmlspecialchars($row['kendaraan']); ?></td>
                                    <td class="px-4 py-3 text-slate-200"><?php echo intval($row['durasi']); ?> jam</td>
                                    <td class="px-4 py-3 font-semibold text-slate-100">
                                        Rp <?php echo number_format($biaya_row, 0, ',', '.'); ?>
                                        <?php if ($denda_row > 0): ?>
                                            <span class="block text-[10px] text-red-400 font-normal mt-0.5">termasuk denda Rp <?php echo number_format($denda_row, 0, ',', '.'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="bg-blue-500/10 text-blue-300 text-xs font-semibold px-2.5 py-1 rounded-full"><?php echo htmlspecialchars($row['status_bayar']); ?></span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <a href="cetak_struk.php?id=<?php echo intval($row['id']); ?>" target="_blank" class="inline-flex items-center space-x-1 bg-red-500/10 hover:bg-red-500/20 text-red-300 text-xs font-semibold px-3 py-1.5 rounded-lg transition">
                                            <span class="material-symbols-outlined text-[16px]">print</span>
                                            <span>Cetak</span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="px-4 py-10 text-center text-slate-500">
                                    <span class="material-symbols-outlined text-[32px] block mb-2">inbox</span>
                                    Tidak ada riwayat struk yang ditemukan.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <?php include 'footer.php'; ?>
</body>
</html>