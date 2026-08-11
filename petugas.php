<?php
// Nama session khusus role petugas, supaya tidak bentrok/tertimpa
// dengan session role lain (user/admin/owner) yang aktif di tab lain
// pada browser yang sama.
session_name('petugas_sess');
session_start();
include 'koneksi.php';

// Validasi akses: Hanya role 'petugas' yang boleh masuk
if (!isset($_SESSION['status_login']) || $_SESSION['role'] != 'petugas') {
    header("Location: login.php");
    exit();
}

$pesan = "";
$error_msg = "";
$hasil_online = [];
$sudah_cari = false;

// Tab aktif: manual (walk-in) atau online (proses reservasi online)
$mode = $_GET['mode'] ?? 'manual';
if (!in_array($mode, ['manual', 'online'])) {
    $mode = 'manual';
}

// ----------------------------------------------------------------
// Fungsi hitung tarif (dipakai bersama oleh input manual & online)
// Mobil: 5000/jam, Motor: 2000/jam, Bus: 8000/jam, Truk: 10000/jam
// VIP / Eksklusif: +50% dari tarif dasar
// ----------------------------------------------------------------
function hitung_tarif($kendaraan, $kategori)
{
    switch ($kendaraan) {
        case 'motor':
            $tarif_per_jam = 2000;
            break;
        case 'bus':
            $tarif_per_jam = 8000;
            break;
        case 'truk':
            $tarif_per_jam = 10000;
            break;
        case 'mobil':
        default:
            $tarif_per_jam = 5000;
            break;
    }

    if ($kategori == 'vip') {
        $tarif_per_jam += $tarif_per_jam * 0.5;
    }

    return $tarif_per_jam;
}

// ==================================================================
// AKSI 1: Input manual (walk-in) - logika ASLI, tidak diubah
// ==================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && ($_POST['aksi'] ?? '') == 'manual') {
    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    $plat = mysqli_real_escape_string($conn, $_POST['plat']);
    $kendaraan = mysqli_real_escape_string($conn, $_POST['kendaraan']);
    $kategori = mysqli_real_escape_string($conn, $_POST['kategori']);
    $durasi = intval($_POST['durasi']);
    $id_area = intval($_POST['id_area']);

    $tarif_per_jam = hitung_tarif($_POST['kendaraan'], $_POST['kategori']);
    $total_biaya = $tarif_per_jam * $durasi;

    $tanggal = date('Y-m-d');
    $waktu = date('H:i:s');
    $status_bayar = "Lunas";

    $query = "INSERT INTO reservasi (nama, plat, kendaraan, kategori, id_area, durasi, tanggal, waktu, total_biaya, status_bayar) 
              VALUES ('$nama', '$plat', '$kendaraan', '$kategori', $id_area, $durasi, '$tanggal', '$waktu', $total_biaya, '$status_bayar')";

    if (mysqli_query($conn, $query)) {
        $id_baru = mysqli_insert_id($conn);

        if ($id_area > 0) {
            mysqli_query($conn, "UPDATE area_parkir SET terisi = terisi + 1 WHERE id = $id_area");
        }

        $_SESSION['notif_transaksi'] = [
            'nama'  => $nama,
            'plat'  => strtoupper($plat),
            'total' => $total_biaya
        ];

        header("Location: cetak_struk.php?id=$id_baru");
        exit();
    } else {
        $error_msg = "Gagal mencatat data: " . mysqli_error($conn);
        $mode = 'manual';
    }
}

// ==================================================================
// AKSI 2: Cari reservasi online (baris di tabel reservasi dengan
// status_bayar = 'Pending') berdasarkan KODE RESERVASI
// ==================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && ($_POST['aksi'] ?? '') == 'cari_online') {
    $mode = 'online';
    $sudah_cari = true;
    $kode_reservasi = mysqli_real_escape_string($conn, trim($_POST['kode_reservasi']));

    if ($kode_reservasi === '') {
        // Kosongkan pencarian -> tampilkan semua reservasi yang masih Pending
        $q = "SELECT * FROM reservasi WHERE status_bayar = 'Pending' ORDER BY created_at DESC";
    } else {
        $q = "SELECT * FROM reservasi 
              WHERE status_bayar = 'Pending' 
              AND kode_reservasi LIKE '%$kode_reservasi%' 
              ORDER BY created_at DESC";
    }

    $r = mysqli_query($conn, $q);
    if ($r) {
        while ($row = mysqli_fetch_assoc($r)) {
            $hasil_online[] = $row;
        }
    } else {
        $error_msg = "Gagal mencari data: " . mysqli_error($conn);
    }
}

// ==================================================================
// AKSI 3: Proses / check-in reservasi online (ubah status_bayar
// dari 'Pending' menjadi 'Lunas' lalu cetak struk)
// ==================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && ($_POST['aksi'] ?? '') == 'proses_online') {
    $mode = 'online';
    $id_reservasi = intval($_POST['id_reservasi']);

    $q = "SELECT * FROM reservasi WHERE id = $id_reservasi AND status_bayar = 'Pending' LIMIT 1";
    $r = mysqli_query($conn, $q);

    if ($r && mysqli_num_rows($r) > 0) {
        $row = mysqli_fetch_assoc($r);

        $update = "UPDATE reservasi SET status_bayar = 'Lunas' WHERE id = $id_reservasi";

        if (mysqli_query($conn, $update)) {
            // Tambahkan jumlah terisi di area parkir saat kendaraan benar-benar check-in
            if (intval($row['id_area']) > 0) {
                mysqli_query($conn, "UPDATE area_parkir SET terisi = terisi + 1 WHERE id = " . intval($row['id_area']));
            }

            $_SESSION['notif_transaksi'] = [
                'nama'  => $row['nama'],
                'plat'  => strtoupper($row['plat']),
                'total' => $row['total_biaya']
            ];

            header("Location: cetak_struk.php?id=$id_reservasi");
            exit();
        } else {
            $error_msg = "Gagal memproses reservasi: " . mysqli_error($conn);
        }
    } else {
        $error_msg = "Data reservasi tidak ditemukan atau sudah diproses sebelumnya.";
    }
}

// Ambil daftar area parkir aktif untuk ditampilkan di dropdown form
$daftar_area = mysqli_query($conn, "SELECT * FROM area_parkir WHERE status='Aktif' ORDER BY nama_area ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Petugas Parkir - AeroPark Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .kode-input { letter-spacing: 0.1em; }
        @keyframes fadeSlideIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in { animation: fadeSlideIn 0.35s ease-out; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-900 flex flex-col">

    <?php include 'notif-login.php'; ?>

    <!-- Header Navbar -->
    <header class="bg-gradient-to-r from-blue-900 to-blue-800 text-white shadow-lg sticky top-0 z-50">
        <div class="max-w-4xl mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <div class="bg-white/10 p-2.5 rounded-xl backdrop-blur-sm ring-1 ring-white/10">
                    <span class="material-symbols-outlined text-white text-[24px]">local_parking</span>
                </div>
                <div>
                    <h1 class="font-bold text-base leading-tight">Panel Petugas Lapangan</h1>
                    <p class="text-xs text-blue-200">Halo, <?php echo htmlspecialchars($_SESSION['nama_lengkap'] ?? $_SESSION['username']); ?></p>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <a href="riwayat.php" class="bg-white/10 hover:bg-white/20 text-white px-4 py-2 rounded-lg text-xs font-semibold transition flex items-center space-x-1.5 shadow ring-1 ring-white/10">
                    <span class="material-symbols-outlined text-[16px]">receipt_long</span>
                    <span>Riwayat Struk</span>
                </a>
                <a href="login.php" class="bg-rose-600 hover:bg-rose-500 text-white px-4 py-2 rounded-lg text-xs font-semibold transition flex items-center space-x-1.5 shadow">
                    <span class="material-symbols-outlined text-[16px]">logout</span>
                    <span>Keluar</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="max-w-4xl mx-auto px-6 py-8 flex-1 w-full">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200/80 p-8">

            <!-- Tab Switcher -->
            <div class="flex space-x-2 mb-6 border-b border-slate-100">
                <a href="?mode=manual"
                   class="px-4 py-3 text-sm font-semibold border-b-2 transition flex items-center space-x-2
                          <?php echo $mode == 'manual' ? 'border-blue-700 text-blue-700' : 'border-transparent text-slate-500 hover:text-slate-700'; ?>">
                    <span class="material-symbols-outlined text-[18px]">directions_car</span>
                    <span>Input Manual (Walk-in)</span>
                </a>
                <a href="?mode=online"
                   class="px-4 py-3 text-sm font-semibold border-b-2 transition flex items-center space-x-2
                          <?php echo $mode == 'online' ? 'border-blue-700 text-blue-700' : 'border-transparent text-slate-500 hover:text-slate-700'; ?>">
                    <span class="material-symbols-outlined text-[18px]">qr_code_2</span>
                    <span>Proses Reservasi Online</span>
                </a>
            </div>

            <?php if (!empty($error_msg)): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-xl mb-6 text-sm flex items-center space-x-3 shadow-sm fade-in">
                    <span class="material-symbols-outlined text-rose-600">error</span>
                    <span class="font-medium"><?php echo htmlspecialchars($error_msg); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($mode == 'manual'): ?>

                <!-- ===================== FORM INPUT MANUAL ===================== -->
                <div class="mb-6">
                    <h2 class="text-xl font-bold text-slate-800 tracking-tight">Input Kendaraan Masuk</h2>
                    <p class="text-xs text-slate-500 mt-1">Masukkan data identitas dan kendaraan pengunjung bandara dengan akurat.</p>
                </div>

                <form action="?mode=manual" method="POST" class="space-y-5">
                    <input type="hidden" name="aksi" value="manual">

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Nama Pemilik / Pengendara</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
                                <span class="material-symbols-outlined text-[20px]">person</span>
                            </span>
                            <input type="text" name="nama" required class="w-full bg-slate-50 border border-slate-300 rounded-xl py-3 pl-11 pr-4 text-sm focus:ring-2 focus:ring-blue-600 focus:bg-white outline-none transition" placeholder="Contoh: Budi Santoso">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Plat Nomor Kendaraan</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
                                    <span class="material-symbols-outlined text-[20px]">directions_car</span>
                                </span>
                                <input type="text" name="plat" required class="w-full bg-slate-50 border border-slate-300 rounded-xl py-3 pl-11 pr-4 text-sm uppercase font-mono focus:ring-2 focus:ring-blue-600 focus:bg-white outline-none transition" placeholder="AB 1234 XY">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Jenis Kendaraan</label>
                            <div class="relative">
                                <select name="kendaraan" class="w-full bg-slate-50 border border-slate-300 rounded-xl py-3 px-4 text-sm focus:ring-2 focus:ring-blue-600 focus:bg-white outline-none transition cursor-pointer">
                                    <option value="mobil">Mobil (Rp 5.000 / Jam)</option>
                                    <option value="motor">Motor (Rp 2.000 / Jam)</option>
                                    <option value="bus">Bus (Rp 8.000 / Jam)</option>
                                    <option value="truk">Truk (Rp 10.000 / Jam)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Kategori Parkir</label>
                            <div class="relative">
                                <select name="kategori" class="w-full bg-slate-50 border border-slate-300 rounded-xl py-3 px-4 text-sm focus:ring-2 focus:ring-blue-600 focus:bg-white outline-none transition cursor-pointer">
                                    <option value="reguler">Reguler</option>
                                    <option value="vip">VIP / Eksklusif (+50% tarif)</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Estimasi Durasi Parkir (Jam)</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
                                    <span class="material-symbols-outlined text-[20px]">schedule</span>
                                </span>
                                <input type="number" name="durasi" value="1" min="1" required class="w-full bg-slate-50 border border-slate-300 rounded-xl py-3 pl-11 pr-4 text-sm focus:ring-2 focus:ring-blue-600 focus:bg-white outline-none transition">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Area Parkir</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
                                <span class="material-symbols-outlined text-[20px]">local_parking</span>
                            </span>
                            <select name="id_area" required class="w-full bg-slate-50 border border-slate-300 rounded-xl py-3 pl-11 pr-4 text-sm focus:ring-2 focus:ring-blue-600 focus:bg-white outline-none transition cursor-pointer">
                                <option value="">-- Pilih Area --</option>
                                <?php
                                if ($daftar_area && mysqli_num_rows($daftar_area) > 0):
                                    while ($ar = mysqli_fetch_assoc($daftar_area)):
                                        $sisa = intval($ar['total_slot']) - intval($ar['terisi']);
                                ?>
                                <option value="<?php echo $ar['id']; ?>"><?php echo htmlspecialchars($ar['nama_area']); ?> (Sisa <?php echo $sisa; ?> slot)</option>
                                <?php
                                    endwhile;
                                else:
                                ?>
                                <option value="" disabled>Belum ada area parkir aktif</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-blue-700 hover:bg-blue-800 text-white font-bold py-3.5 rounded-xl shadow-lg hover:shadow-xl active:scale-[0.99] transition flex items-center justify-center space-x-2 mt-4">
                        <span class="material-symbols-outlined text-[20px]">save</span>
                        <span>Simpan & Cetak Struk Parkir</span>
                    </button>
                </form>

            <?php else: ?>

                <!-- ===================== PROSES RESERVASI ONLINE ===================== -->
                <div class="mb-6">
                    <h2 class="text-xl font-bold text-slate-800 tracking-tight">Proses Reservasi Online</h2>
                    <p class="text-xs text-slate-500 mt-1">Masukkan kode reservasi yang ditunjukkan pengunjung untuk memproses check-in.</p>
                </div>

                <!-- Form Pencarian Kode Reservasi -->
                <form action="?mode=online" method="POST" class="mb-6">
                    <input type="hidden" name="aksi" value="cari_online">
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Kode Reservasi</label>
                    <div class="flex flex-col sm:flex-row items-stretch gap-3">
                        <div class="relative flex-1">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-blue-600">
                                <span class="material-symbols-outlined text-[22px]">confirmation_number</span>
                            </span>
                            <input type="text" name="kode_reservasi" autofocus
                                   value="<?php echo isset($_POST['kode_reservasi']) ? htmlspecialchars($_POST['kode_reservasi']) : ''; ?>"
                                   class="kode-input w-full bg-slate-50 border-2 border-slate-200 rounded-xl py-3.5 pl-12 pr-4 text-base font-mono font-bold uppercase text-slate-800 focus:ring-2 focus:ring-blue-600 focus:border-blue-600 focus:bg-white outline-none transition"
                                   placeholder="RSV-8DD728">
                        </div>
                        <button type="submit" class="bg-blue-700 hover:bg-blue-800 text-white font-bold py-3.5 px-8 rounded-xl shadow-lg hover:shadow-xl active:scale-[0.99] transition flex items-center justify-center space-x-2 whitespace-nowrap">
                            <span class="material-symbols-outlined text-[20px]">search</span>
                            <span>Cari & Proses</span>
                        </button>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-2 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">info</span>
                        Kosongkan kolom lalu tekan Cari untuk melihat semua reservasi yang masih menunggu check-in.
                    </p>
                </form>

                <?php if ($sudah_cari): ?>
                    <?php if (empty($hasil_online)): ?>
                        <div class="bg-amber-50 border border-amber-200 text-amber-800 p-4 rounded-xl mb-6 text-sm flex items-center space-x-3 shadow-sm fade-in">
                            <span class="material-symbols-outlined text-amber-600">info</span>
                            <span class="font-medium">Tidak ada reservasi online yang menunggu check-in dengan kode tersebut.</span>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($hasil_online as $row): ?>
                                <?php
                                    $total = intval($row['total_biaya']);
                                ?>
                                <div class="fade-in bg-gradient-to-br from-emerald-50 to-white border border-emerald-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition">
                                    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
                                        <div class="flex items-center space-x-2 text-emerald-700 bg-emerald-100 px-3 py-1.5 rounded-full">
                                            <span class="material-symbols-outlined text-[18px]">schedule</span>
                                            <span class="font-bold text-xs uppercase tracking-wider">Menunggu Check-in</span>
                                        </div>
                                        <div class="flex items-center space-x-2 bg-slate-900 text-white px-3 py-1.5 rounded-full">
                                            <span class="material-symbols-outlined text-[16px] text-emerald-400">confirmation_number</span>
                                            <span class="font-mono font-bold text-xs tracking-wider"><?php echo htmlspecialchars($row['kode_reservasi']); ?></span>
                                        </div>
                                    </div>

                                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm mb-6">
                                        <div>
                                            <dt class="text-slate-500 text-xs uppercase font-semibold">Nama</dt>
                                            <dd class="font-semibold text-slate-800"><?php echo htmlspecialchars($row['nama']); ?></dd>
                                        </div>
                                        <div>
                                            <dt class="text-slate-500 text-xs uppercase font-semibold">Plat Nomor</dt>
                                            <dd class="font-mono font-semibold text-slate-800"><?php echo htmlspecialchars(strtoupper($row['plat'])); ?></dd>
                                        </div>
                                        <div>
                                            <dt class="text-slate-500 text-xs uppercase font-semibold">Jenis Kendaraan</dt>
                                            <dd class="font-semibold text-slate-800 capitalize"><?php echo htmlspecialchars($row['kendaraan']); ?></dd>
                                        </div>
                                        <div>
                                            <dt class="text-slate-500 text-xs uppercase font-semibold">Kategori</dt>
                                            <dd class="font-semibold text-slate-800 capitalize"><?php echo htmlspecialchars($row['kategori']); ?></dd>
                                        </div>
                                        <div>
                                            <dt class="text-slate-500 text-xs uppercase font-semibold">Tanggal &amp; Waktu Booking</dt>
                                            <dd class="font-semibold text-slate-800"><?php echo htmlspecialchars($row['tanggal']); ?> &middot; <?php echo htmlspecialchars($row['waktu']); ?></dd>
                                        </div>
                                        <div>
                                            <dt class="text-slate-500 text-xs uppercase font-semibold">Durasi</dt>
                                            <dd class="font-semibold text-slate-800"><?php echo intval($row['durasi']); ?> Jam</dd>
                                        </div>
                                        <div class="md:col-span-2 pt-3 border-t border-emerald-200 flex items-center justify-between">
                                            <dt class="text-slate-500 text-xs uppercase font-semibold">Total Biaya</dt>
                                            <dd class="font-extrabold text-xl text-emerald-700">Rp <?php echo number_format($total, 0, ',', '.'); ?></dd>
                                        </div>
                                    </dl>

                                    <form action="?mode=online" method="POST">
                                        <input type="hidden" name="aksi" value="proses_online">
                                        <input type="hidden" name="id_reservasi" value="<?php echo intval($row['id']); ?>">
                                        <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3.5 rounded-xl shadow-lg hover:shadow-xl active:scale-[0.99] transition flex items-center justify-center space-x-2">
                                            <span class="material-symbols-outlined text-[20px]">check_circle</span>
                                            <span>Proses Check-in & Cetak Struk</span>
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

            <?php endif; ?>

        </div>
    </main>

   <?php include 'footer.php'; ?>
</body>
</html>
