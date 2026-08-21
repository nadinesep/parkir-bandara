<?php
// PENTING: nama session harus SAMA dengan petugas.php ('petugas_sess'),
// kalau tidak halaman ini akan membuka session yang berbeda sehingga
// status login petugas tidak terbaca dengan benar di sini.
session_name('petugas_sess');
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

// Mode embed: halaman ini dimuat di dalam <iframe> modal preview
// karcis milik petugas.php. Dalam mode ini, tombol aksi bawaan
// disembunyikan karena sudah digantikan tombol pada modal induknya.
$embed = isset($_GET['embed']) && $_GET['embed'] == '1';

$kendaraan_karcis = $data['kendaraan'] ?? 'mobil';
$kategori_karcis  = $data['kategori'] ?? 'reguler';
$kode_reservasi   = $data['kode_reservasi'] ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karcis Parkir #<?php echo $data['id']; ?> - AeroParking</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Courier+Prime:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #0b0d17;
            background-image:
                radial-gradient(circle at 15% 10%, rgba(220,38,38,0.10) 0%, transparent 45%),
                radial-gradient(circle at 85% 90%, rgba(37,99,235,0.12) 0%, transparent 45%);
        }
        .receipt-font { font-family: 'Courier Prime', monospace; }
        .ticket-number { letter-spacing: 0.15em; }
        @media print {
            body { background: white !important; padding: 0 !important; }
            .no-print { display: none !important; }
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
<body class="min-h-screen flex flex-col items-center justify-center p-4">

    <!-- Toast Notifikasi Cetak Karcis (hanya relevan saat halaman ini
         dibuka langsung, bukan saat berada di dalam modal preview) -->
    <?php if (!$embed): ?>
    <div id="toastCetak" class="no-print fixed top-5 right-5 z-[100] translate-x-[120%] opacity-0 transition-all duration-500 ease-out">
        <div class="bg-white border border-blue-200 shadow-2xl rounded-2xl p-4 pr-5 flex items-center gap-3 max-w-sm">
            <div class="bg-red-100 text-red-600 rounded-full w-10 h-10 flex items-center justify-center flex-shrink-0">
                <i class="fa-solid fa-print"></i>
            </div>
            <div>
                <p class="font-bold text-slate-800 text-sm">Karcis Sedang Dicetak...</p>
                <p class="text-xs text-slate-500">Silakan cek jendela cetak pada browser Anda.</p>
            </div>
        </div>
    </div>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <?php endif; ?>

    <!-- Container Karcis -->
    <div class="receipt-card bg-white w-full max-w-sm rounded-2xl shadow-xl border border-slate-200 p-6 text-slate-800 receipt-font">

        <!-- Header Karcis -->
        <div class="text-center border-b-2 border-dashed border-slate-300 pb-4 mb-4">
            <h2 class="font-bold text-lg uppercase tracking-wider text-red-700">AeroParking</h2>
            <p class="text-xs text-slate-500">Bandara Internasional Terminal</p>
            <p class="text-[10px] text-slate-400 mt-1">Karcis Masuk Kendaraan</p>
        </div>

        <!-- Nomor Tiket Besar -->
        <div class="text-center border-b-2 border-dashed border-slate-300 pb-4 mb-4">
            <p class="text-[10px] text-slate-500 uppercase font-bold mb-1">No. Karcis</p>
            <p class="ticket-number text-3xl font-bold text-blue-800">#<?php echo str_pad($data['id'], 6, '0', STR_PAD_LEFT); ?></p>
            <?php if (!empty($kode_reservasi)): ?>
                <p class="text-[10px] text-slate-400 mt-1">Kode Reservasi: <?php echo htmlspecialchars($kode_reservasi); ?></p>
            <?php endif; ?>
        </div>

        <!-- Detail Informasi Masuk -->
        <div class="space-y-2 text-xs border-b-2 border-dashed border-slate-300 pb-4 mb-4">
            <div class="flex justify-between">
                <span class="text-slate-500">Tanggal Masuk</span>
                <span class="font-semibold"><?php echo $data['tanggal']; ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Jam Masuk</span>
                <span class="font-semibold"><?php echo $data['waktu']; ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Pengendara</span>
                <span class="font-semibold"><?php echo htmlspecialchars($data['nama']); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Plat Nomor</span>
                <span class="font-bold uppercase text-red-600"><?php echo htmlspecialchars($data['plat']); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Jenis Kendaraan</span>
                <span class="font-semibold uppercase"><?php echo htmlspecialchars($kendaraan_karcis); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Kategori</span>
                <span class="font-semibold uppercase"><?php echo htmlspecialchars($kategori_karcis); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Estimasi Durasi</span>
                <span class="font-semibold"><?php echo intval($data['durasi']); ?> Jam</span>
            </div>
        </div>

        <!-- Catatan Penting -->
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-3 mb-4">
            <p class="text-[10px] text-blue-800 text-center leading-relaxed">
                Simpan karcis ini dan tunjukkan saat kendaraan keluar.
                Tarif final dihitung otomatis berdasarkan durasi aktual saat check-out.
            </p>
        </div>

        <!-- Footer Karcis -->
        <div class="text-center text-[10px] text-slate-400 space-y-1">
            <p>Kehilangan karcis dikenakan denda sesuai ketentuan berlaku.</p>
            <p class="font-bold text-slate-600 mt-2">=== AEROSYSTEM PARKING ===</p>
        </div>
    </div>

    <?php if (!$embed): ?>
    <!-- Tombol Aksi (Tidak ikut tercetak) -->
    <div class="no-print mt-6 flex space-x-3 w-full max-w-sm">
        <button onclick="cetakKarcis()" class="flex-1 bg-gradient-to-r from-red-600 to-blue-700 hover:from-red-500 hover:to-blue-600 text-white font-semibold py-2.5 rounded-xl shadow transition text-sm flex items-center justify-center space-x-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            <span>Cetak Karcis</span>
        </button>
        <a href="petugas.php" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-semibold py-2.5 px-4 rounded-xl transition text-sm flex items-center justify-center">
            Dashboard
        </a>
    </div>
    <?php endif; ?>

    <script>
        function mainkanSuaraCetak() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const now = ctx.currentTime;

                function nada(freq, start, durasi, volume = 0.12) {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'square';
                    osc.frequency.setValueAtTime(freq, now + start);
                    gain.gain.setValueAtTime(0, now + start);
                    gain.gain.linearRampToValueAtTime(volume, now + start + 0.01);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + start + durasi);
                    osc.connect(gain).connect(ctx.destination);
                    osc.start(now + start);
                    osc.stop(now + start + durasi);
                }

                nada(1046.5, 0,    0.08);
                nada(1046.5, 0.12, 0.08);
                nada(1568,   0.24, 0.2);
            } catch (e) {
                console.warn('Audio tidak bisa diputar:', e);
            }
        }

        function tampilkanToastCetak() {
            const toast = document.getElementById('toastCetak');
            if (!toast) return;

            mainkanSuaraCetak();

            toast.classList.remove('translate-x-[120%]', 'opacity-0');
            toast.classList.add('translate-x-0', 'opacity-100');

            setTimeout(() => {
                toast.classList.add('translate-x-[120%]', 'opacity-0');
                toast.classList.remove('translate-x-0', 'opacity-100');
            }, 4000);
        }

        function cetakKarcis() {
            tampilkanToastCetak();
            setTimeout(() => {
                window.print();
            }, 700);
        }
    </script>

</body>
</html>