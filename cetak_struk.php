<?php
session_start();
include 'koneksi.php';

if (!isset($_GET['id'])) {
    header("Location: petugas.php");
    exit();
}

$id_reservasi = intval($_GET['id']);
$query = mysqli_query($conn, "SELECT * FROM reservasi WHERE id = $id_reservasi");
$data = mysqli_fetch_assoc($query);

if (!$data) {
    die("Data reservasi parkir tidak ditemukan!");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Parkir #<?php echo $data['id']; ?> - AeroParking</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Courier+Prime:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .receipt-font {
            font-family: 'Courier Prime', monospace;
        }
        @media print {
            body {
                background: white !important;
                padding: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            .receipt-card {
                box-shadow: none !important;
                border: none !important;
                width: 100% !important;
                max-width: 100% !important;
                padding: 0 !important;
            }
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen flex flex-col items-center justify-center p-4">

    <?php include 'notif-transaksi.php'; ?>

    <!-- Container Struk -->
    <div class="receipt-card bg-white w-full max-w-sm rounded-2xl shadow-xl border border-slate-200 p-6 text-slate-800 receipt-font">
        
        <!-- Header Struk -->
        <div class="text-center border-b-2 border-dashed border-slate-300 pb-4 mb-4">
            <h2 class="font-bold text-lg uppercase tracking-wider text-blue-900">AeroParking</h2>
            <p class="text-xs text-slate-500">Bandara Internasional Terminal</p>
            <p class="text-[10px] text-slate-400 mt-1">Struk Resmi Masuk Kendaraan</p>
        </div>

        <!-- Detail Informasi -->
        <div class="space-y-2 text-xs border-b-2 border-dashed border-slate-300 pb-4 mb-4">
            <div class="flex justify-between">
                <span class="text-slate-500">No. Tiket / ID</span>
                <span class="font-bold">#<?php echo $data['id']; ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Tanggal</span>
                <span class="font-semibold"><?php echo $data['tanggal']; ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Waktu Masuk</span>
                <span class="font-semibold"><?php echo $data['waktu']; ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Pengendara</span>
                <span class="font-semibold"><?php echo htmlspecialchars($data['nama']); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Plat Nomor</span>
                <span class="font-bold uppercase text-blue-600"><?php echo htmlspecialchars($data['plat']); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Jenis Kendaraan</span>
                <span class="font-semibold uppercase"><?php echo htmlspecialchars($data['kendaraan']); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Durasi Estimasi</span>
                <span class="font-semibold"><?php echo $data['durasi']; ?> Jam</span>
            </div>
        </div>

        <!-- Total Biaya -->
        <div class="bg-slate-50 rounded-xl p-3 border border-slate-200 text-center mb-4">
            <p class="text-[11px] text-slate-500 uppercase font-semibold">Total Biaya Parkir</p>
            <p class="text-xl font-bold text-blue-700 mt-0.5">Rp <?php echo number_format($data['total_biaya'], 0, ',', '.'); ?></p>
            <span class="inline-block mt-1 bg-emerald-100 text-emerald-800 text-[10px] px-2 py-0.5 rounded font-bold uppercase">
                <?php echo htmlspecialchars($data['status_bayar']); ?>
            </span>
        </div>

        <!-- Bagian QR Code / QRIS -->
        <div class="text-center border-b-2 border-dashed border-slate-300 pb-4 mb-4">
            <p class="text-[10px] text-slate-500 mb-2 uppercase font-bold">Scan QRIS untuk Validasi / Info</p>
            <!-- Ubah 'qrisss.JPEG' sesuai dengan nama file gambar QR Anda -->
            <img src="qrisss.JPEG" alt="QR Code Pembayaran" class="w-36 h-36 mx-auto object-contain border border-slate-200 rounded-lg p-1 bg-white">
            <p class="text-[9px] text-slate-400 mt-1">NMID: ID1026554794697</p>
        </div>

        <!-- Footer Struk -->
        <div class="text-center text-[10px] text-slate-400 space-y-1">
            <p>Simpan struk ini sebagai bukti parkir sah.</p>
            <p>Terima kasih atas kunjungan Anda.</p>
            <p class="font-bold text-slate-600 mt-2">=== AEROSYSTEM PARKING ===</p>
        </div>
    </div>

    <!-- Tombol Aksi (Tidak ikut tercetak) -->
    <div class="no-print mt-6 flex space-x-3 w-full max-w-sm">
        <button onclick="window.print()" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-xl shadow transition text-sm flex items-center justify-center space-x-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            <span>Cetak Struk</span>
        </button>
        <a href="petugas.php" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-semibold py-2.5 px-4 rounded-xl transition text-sm flex items-center justify-center">
            Kembali
        </a>
    </div>

</body>
</html>