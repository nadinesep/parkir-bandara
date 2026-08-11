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

// Ambil ringkasan data untuk owner
$q_pendapatan = mysqli_query($conn, "SELECT SUM(total_biaya) as total, COUNT(*) as total_transaksi FROM reservasi");
$data_finance = mysqli_fetch_assoc($q_pendapatan);

$total_pendapatan = $data_finance['total'] ?? 0;
$total_transaksi = $data_finance['total_transaksi'] ?? 0;

// Data tabel lengkap untuk laporan
$result = mysqli_query($conn, "SELECT * FROM reservasi ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Dashboard - Owner AeroPark</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-900 text-slate-100 font-sans min-h-screen flex flex-col">

    <?php include 'notif-login.php'; ?>

    <!-- Navbar Owner -->
    <header class="bg-slate-950 border-b border-slate-800 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <i class="fa-solid fa-chart-pie text-amber-400 text-xl"></i>
                <span class="font-bold text-lg">AeroPark <span class="text-amber-400">Executive Dashboard</span></span>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-xs bg-amber-400/10 text-amber-400 border border-amber-400/30 px-3 py-1 rounded-full font-semibold">Hak Akses: Owner</span>
                <a href="login.php" class="text-slate-400 hover:text-white text-sm transition"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-6 py-8 flex-1 w-full">
        <!-- Kartu Statistik Utama -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-slate-800 border border-slate-700/60 p-6 rounded-2xl shadow-lg">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Pendapatan Bisnis</p>
                <h3 class="text-3xl font-extrabold text-emerald-400 mt-2">Rp <?php echo number_format($total_pendapatan, 0, ',', '.'); ?></h3>
                <span class="text-xs text-emerald-500 font-medium mt-2 inline-block"><i class="fa-solid fa-arrow-trend-up mr-1"></i> Akumulasi real-time</span>
            </div>

            <div class="bg-slate-800 border border-slate-700/60 p-6 rounded-2xl shadow-lg">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Transaksi Masuk</p>
                <h3 class="text-3xl font-extrabold text-blue-400 mt-2"><?php echo $total_transaksi; ?> <span class="text-sm font-normal text-slate-400">Kendaraan</span></h3>
                <span class="text-xs text-blue-400 font-medium mt-2 inline-block"><i class="fa-solid fa-car mr-1"></i> Data seluruh terminal</span>
            </div>

            <div class="bg-slate-800 border border-slate-700/60 p-6 rounded-2xl shadow-lg">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Status Operasional</p>
                <h3 class="text-2xl font-extrabold text-purple-400 mt-2">Normal / Stabil</h3>
                <span class="text-xs text-slate-400 font-medium mt-2 inline-block">Sistem server berjalan optimal</span>
            </div>
        </div>

        <!-- Tabel Laporan Keuangan & Kendaraan (Read-Only untuk keamanan data Manajer) -->
        <div class="bg-slate-800 border border-slate-700/60 rounded-2xl shadow-lg overflow-hidden">
            <div class="p-6 border-b border-slate-700 flex justify-between items-center">
                <h3 class="font-bold text-lg text-white"><i class="fa-solid fa-file-invoice-dollar mr-2 text-amber-400"></i> Laporan Transaksi Keseluruhan</h3>
                <span class="text-xs bg-slate-700 text-slate-300 px-3 py-1 rounded font-medium">Mode Lihat (Read-Only)</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-900/50 text-slate-400 text-xs uppercase font-semibold border-b border-slate-700">
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
                    <tbody class="divide-y divide-slate-700 text-sm text-slate-300">
                        <?php if (mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr class="hover:bg-slate-750 transition">
                                <td class="p-4 text-slate-500">#<?php echo $row['id']; ?></td>
                                <td class="p-4 font-semibold text-white"><?php echo htmlspecialchars($row['nama']); ?></td>
                                <td class="p-4 font-mono text-amber-300"><?php echo htmlspecialchars($row['plat']); ?></td>
                                <td class="p-4 uppercase text-xs font-bold text-slate-400"><?php echo htmlspecialchars($row['kendaraan']); ?></td>
                                <td class="p-4"><?php echo $row['durasi']; ?> Jam</td>
                                <td class="p-4 text-slate-400"><?php echo $row['tanggal'] . ' ' . $row['waktu']; ?></td>
                                <td class="p-4 font-bold text-emerald-400">Rp <?php echo number_format($row['total_biaya'], 0, ',', '.'); ?></td>
                                <td class="p-4">
                                    <span class="bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 text-xs px-2.5 py-1 rounded-full font-semibold">
                                        <?php echo htmlspecialchars($row['status_bayar']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="p-8 text-center text-slate-500">
                                    Belum ada data laporan transaksi yang tercatat.
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
