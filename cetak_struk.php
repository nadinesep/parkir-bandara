<?php
// PENTING: nama session harus SAMA dengan checkout.php & petugas.php
// ('petugas_sess'), kalau tidak halaman ini akan membuka session
// yang berbeda sehingga $_SESSION['notif_transaksi'] dan status
// login petugas tidak terbaca dengan benar di sini.
session_name('petugas_sess');
session_start();
include 'koneksi.php';
include 'tarif_helper.php';

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

// Denda (jika ada) & total akhir setelah ditambah denda keterlambatan
$denda = intval($data['denda'] ?? 0);
$ada_denda = $denda > 0;
$total_akhir = intval($data['total_biaya']) + $denda;

// Waktu keluar hanya terisi setelah proses checkout di checkout.php
$sudah_keluar = !empty($data['waktu_keluar']);

// ==========================================================
// Tarif jam pertama & jam berikutnya SEKARANG diambil dari tabel
// tarif_parkir (via tarif_helper.php) berdasarkan jenis kendaraan
// & kategori pelanggan (reguler/vip/vvip) pada data reservasi ini,
// bukan angka tetap lagi. Supaya struk selalu menampilkan tarif
// yang benar-benar berlaku saat transaksi tersebut dihitung, dan
// sinkron dengan pengaturan Admin di menu Tarif Parkir.
// ==========================================================
$kendaraan_struk = $data['kendaraan'] ?? 'mobil';
$kategori_struk  = $data['kategori'] ?? 'reguler';

$tarif_struk = ambil_tarif($conn, $kendaraan_struk, $kategori_struk);

$tarif_jam_pertama    = $tarif_struk['tarif_per_jam'];
$tarif_jam_berikutnya = $tarif_struk['tarif_jam_tambahan'];
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
            background-color: #0b0d17;
            background-image:
                radial-gradient(circle at 15% 10%, rgba(220,38,38,0.10) 0%, transparent 45%),
                radial-gradient(circle at 85% 90%, rgba(37,99,235,0.12) 0%, transparent 45%);
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
<body class="min-h-screen flex flex-col items-center justify-center p-4">

    <?php include 'notif-transaksi.php'; ?>

    <!-- Toast Notifikasi Cetak Struk -->
    <div id="toastCetak" class="no-print fixed top-5 right-5 z-[100] translate-x-[120%] opacity-0 transition-all duration-500 ease-out">
        <div class="bg-white border border-blue-200 shadow-2xl rounded-2xl p-4 pr-5 flex items-center gap-3 max-w-sm">
            <div class="bg-red-100 text-red-600 rounded-full w-10 h-10 flex items-center justify-center flex-shrink-0">
                <i class="fa-solid fa-print"></i>
            </div>
            <div>
                <p class="font-bold text-slate-800 text-sm">Struk Sedang Dicetak...</p>
                <p class="text-xs text-slate-500">Silakan cek jendela cetak pada browser Anda.</p>
            </div>
        </div>
    </div>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- Container Struk -->
    <div class="receipt-card bg-white w-full max-w-sm rounded-2xl shadow-xl border border-slate-200 p-6 text-slate-800 receipt-font">
        
        <!-- Header Struk -->
        <div class="text-center border-b-2 border-dashed border-slate-300 pb-4 mb-4">
            <h2 class="font-bold text-lg uppercase tracking-wider text-red-700">AeroParking</h2>
            <p class="text-xs text-slate-500">Bandara Internasional Terminal</p>
            <p class="text-[10px] text-slate-400 mt-1">
                <?php echo $sudah_keluar ? 'Struk Resmi Keluar Kendaraan' : 'Struk Resmi Masuk Kendaraan'; ?>
            </p>
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
            <?php if ($sudah_keluar): ?>
            <div class="flex justify-between">
                <span class="text-slate-500">Waktu Keluar</span>
                <span class="font-semibold"><?php echo date('Y-m-d H:i:s', strtotime($data['waktu_keluar'])); ?></span>
            </div>
            <?php endif; ?>
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
                <span class="font-semibold uppercase"><?php echo htmlspecialchars($data['kendaraan']); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Kategori</span>
                <span class="font-semibold uppercase"><?php echo htmlspecialchars($kategori_struk); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Durasi Estimasi</span>
                <span class="font-semibold"><?php echo $data['durasi']; ?> Jam</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Tarif Jam Pertama</span>
                <span class="font-semibold">Rp <?php echo number_format($tarif_jam_pertama, 0, ',', '.'); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Tarif Jam Berikutnya</span>
                <span class="font-semibold">Rp <?php echo number_format($tarif_jam_berikutnya, 0, ',', '.'); ?> /jam</span>
            </div>
        </div>

        <!-- Total Biaya -->
        <div class="bg-slate-50 rounded-xl p-3 border border-slate-200 mb-4">
            <?php if ($ada_denda): ?>
                <!-- Rincian biaya normal + denda keterlambatan -->
                <div class="space-y-1 text-xs mb-2 pb-2 border-b border-dashed border-slate-300">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Biaya Parkir</span>
                        <span class="font-semibold">Rp <?php echo number_format($data['total_biaya'], 0, ',', '.'); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-rose-600">Denda Keterlambatan</span>
                        <span class="font-semibold text-rose-600">Rp <?php echo number_format($denda, 0, ',', '.'); ?></span>
                    </div>
                </div>
                <div class="text-center">
                    <p class="text-[11px] text-slate-500 uppercase font-semibold">Total Bayar (Termasuk Denda)</p>
                    <p class="text-xl font-bold text-rose-700 mt-0.5">Rp <?php echo number_format($total_akhir, 0, ',', '.'); ?></p>
                    <span class="inline-block mt-1 bg-rose-100 text-rose-800 text-[10px] px-2 py-0.5 rounded font-bold uppercase">
                        Melebihi Durasi
                    </span>
                </div>
            <?php else: ?>
                <div class="text-center">
                    <p class="text-[11px] text-slate-500 uppercase font-semibold">Total Biaya Parkir</p>
                    <p class="text-xl font-bold text-blue-700 mt-0.5">Rp <?php echo number_format($data['total_biaya'], 0, ',', '.'); ?></p>
                    <span class="inline-block mt-1 bg-emerald-100 text-emerald-800 text-[10px] px-2 py-0.5 rounded font-bold uppercase">
                        <?php echo htmlspecialchars($data['status_bayar']); ?>
                    </span>
                </div>
            <?php endif; ?>
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
        <button onclick="cetakStruk()" class="flex-1 bg-gradient-to-r from-red-600 to-blue-700 hover:from-red-500 hover:to-blue-600 text-white font-semibold py-2.5 rounded-xl shadow transition text-sm flex items-center justify-center space-x-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            <span>Cetak Struk</span>
        </button>
        <a href="checkout.php" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-semibold py-2.5 px-4 rounded-xl transition text-sm flex items-center justify-center">
            Daftar Checkout
        </a>
        <a href="petugas.php" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-semibold py-2.5 px-4 rounded-xl transition text-sm flex items-center justify-center">
            Dashboard
        </a>
    </div>

    <script>
        // Nada khas "printer/struk" — tiga nada pendek beruntun
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

        function cetakStruk() {
            tampilkanToastCetak();
            setTimeout(() => {
                window.print();
            }, 700);
        }
    </script>

</body>
</html>