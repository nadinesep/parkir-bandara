<?php
// Nama session khusus role petugas, supaya tidak bentrok/tertimpa
// dengan session role lain (user/admin/owner) yang aktif di tab lain
// pada browser yang sama.
session_name('petugas_sess');
session_start();
include 'koneksi.php';
include 'tarif_helper.php';
include 'area_helper.php';

// Validasi akses: Hanya role 'petugas' yang boleh masuk
if (!isset($_SESSION['status_login']) || $_SESSION['role'] != 'petugas') {
    header("Location: login.php");
    exit();
}

$pesan = "";
$error_msg = "";
$hasil_online = [];
$sudah_cari = false;
$daftar_pending = [];

// Apakah request ini dikirim via AJAX (fetch) dari modal preview struk?
// Kalau ya, jawaban dikembalikan sebagai JSON, bukan redirect halaman.
$is_ajax = (($_POST['ajax'] ?? '') == '1');

// Tab aktif: manual (walk-in) atau online (proses reservasi online)
$mode = $_GET['mode'] ?? 'manual';
if (!in_array($mode, ['manual', 'online'])) {
    $mode = 'manual';
}

// ==================================================================
// AKSI 1: Input manual (walk-in)
// id_area kini ditentukan OTOMATIS oleh cari_id_area_tersedia()
// berdasarkan jenis kendaraan & kategori yang dipilih petugas,
// supaya slot area_parkir.terisi ikut ter-update secara real-time.
// ==================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && ($_POST['aksi'] ?? '') == 'manual') {
    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    $plat = mysqli_real_escape_string($conn, $_POST['plat']);
    $kendaraan = mysqli_real_escape_string($conn, $_POST['kendaraan']);
    $kategori = mysqli_real_escape_string($conn, $_POST['kategori']);
    $durasi = intval($_POST['durasi']);

    // Cari area parkir yang paling cocok & masih ada slot kosong
    $id_area = cari_id_area_tersedia($conn, $_POST['kendaraan'], $_POST['kategori']);

    $total_biaya = hitung_biaya($conn, $_POST['kendaraan'], $_POST['kategori'], $durasi);

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

        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'id' => $id_baru]);
            exit();
        }

        header("Location: cetak_struk.php?id=$id_baru");
        exit();
    } else {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Gagal mencatat data: ' . mysqli_error($conn)]);
            exit();
        }
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
//
// CATATAN PERBAIKAN: kolom `tanggal` dan `waktu` di tabel reservasi
// sebelumnya berisi tanggal/jam RENCANA yang dipilih user saat
// booking online (dari form di user.php), bukan waktu kendaraan
// benar-benar tiba. Ini membuat data di dashboard admin (Pendapatan
// Harian & Trafik Kendaraan) tidak sesuai transaksi asli, karena
// transaksi tercatat di tanggal/jam booking, bukan tanggal/jam
// check-in sungguhan.
//
// Sekarang saat petugas memproses check-in, `tanggal` & `waktu`
// ikut ditimpa dengan waktu SAAT INI (waktu check-in sebenarnya),
// supaya seluruh data pendapatan, trafik, dan perhitungan denda
// (di checkout.php) mengacu ke waktu kendaraan benar-benar masuk.
// ==================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && ($_POST['aksi'] ?? '') == 'proses_online') {
    $mode = 'online';
    $id_reservasi = intval($_POST['id_reservasi']);

    $q = "SELECT * FROM reservasi WHERE id = $id_reservasi AND status_bayar = 'Pending' LIMIT 1";
    $r = mysqli_query($conn, $q);

    if ($r && mysqli_num_rows($r) > 0) {
        $row = mysqli_fetch_assoc($r);

        // Kalau reservasi online dibuat sebelum kolom id_area otomatis
        // terisi (data lama / id_area = 0), cari ulang area yang cocok
        // saat ini juga supaya check-in tetap ikut menambah terisi.
        $id_area_checkin = intval($row['id_area']);
        if ($id_area_checkin <= 0) {
            $id_area_checkin = cari_id_area_tersedia($conn, $row['kendaraan'], $row['kategori']);
        }

        // Catat tanggal & waktu SEBENARNYA kendaraan check-in/masuk,
        // bukan tanggal/waktu rencana saat user booking online.
        $tanggal_checkin = date('Y-m-d');
        $waktu_checkin   = date('H:i:s');

        $update = "UPDATE reservasi SET status_bayar = 'Lunas', id_area = $id_area_checkin, 
                   tanggal = '$tanggal_checkin', waktu = '$waktu_checkin' WHERE id = $id_reservasi";

        if (mysqli_query($conn, $update)) {
            // Tambahkan jumlah terisi di area parkir saat kendaraan benar-benar check-in
            if ($id_area_checkin > 0) {
                mysqli_query($conn, "UPDATE area_parkir SET terisi = terisi + 1 WHERE id = " . $id_area_checkin);
            }

            $_SESSION['notif_transaksi'] = [
                'nama'  => $row['nama'],
                'plat'  => strtoupper($row['plat']),
                'total' => $row['total_biaya']
            ];

            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'id' => $id_reservasi]);
                exit();
            }

            header("Location: cetak_struk.php?id=$id_reservasi");
            exit();
        } else {
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Gagal memproses reservasi: ' . mysqli_error($conn)]);
                exit();
            }
            $error_msg = "Gagal memproses reservasi: " . mysqli_error($conn);
        }
    } else {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Data reservasi tidak ditemukan atau sudah diproses sebelumnya.']);
            exit();
        }
        $error_msg = "Data reservasi tidak ditemukan atau sudah diproses sebelumnya.";
    }
}

// ==================================================================
// Saat tab "Proses Reservasi Online" dibuka (belum melakukan
// pencarian apa pun), otomatis tampilkan daftar ringkas semua kode
// reservasi yang masih menunggu check-in, supaya petugas tinggal
// klik salah satu kode tanpa perlu menyalin/mengetik manual.
// ==================================================================
if ($mode == 'online' && !$sudah_cari) {
    $q_pending = "SELECT id, kode_reservasi, nama, plat, kendaraan FROM reservasi WHERE status_bayar = 'Pending' ORDER BY created_at DESC LIMIT 30";
    $r_pending = mysqli_query($conn, $q_pending);
    if ($r_pending) {
        while ($row = mysqli_fetch_assoc($r_pending)) {
            $daftar_pending[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Petugas Parkir - AeroPark Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>
        /* ===== Tema Spider-Man: dasar gelap dengan aksen merah/biru + pola web tipis ===== */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #030712;
            background-image:
                radial-gradient(circle at 12% 0%, rgba(220, 38, 38, 0.09), transparent 42%),
                radial-gradient(circle at 88% 8%, rgba(37, 99, 235, 0.10), transparent 40%),
                radial-gradient(circle at 50% 100%, rgba(37, 99, 235, 0.05), transparent 45%),
                repeating-linear-gradient(45deg, transparent 0 38px, rgba(255,255,255,0.015) 38px 39px),
                repeating-linear-gradient(-45deg, transparent 0 38px, rgba(255,255,255,0.015) 38px 39px);
            background-attachment: fixed;
        }
        .font-display { font-family: 'Space Grotesk', sans-serif; }
        .kode-input { letter-spacing: 0.14em; }
        @keyframes fadeSlideIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in { animation: fadeSlideIn 0.35s ease-out; }
        @keyframes beaconPulse {
            0%, 100% { opacity: 1; box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.55); }
            70% { opacity: 0.85; box-shadow: 0 0 0 6px rgba(220, 38, 38, 0); }
        }
        .beacon { animation: beaconPulse 2.4s ease-in-out infinite; }
        a:focus-visible, button:focus-visible, input:focus-visible, select:focus-visible {
            outline: 2px solid #dc2626;
            outline-offset: 2px;
        }
        @media (prefers-reduced-motion: reduce) {
            .fade-in, .beacon { animation: none !important; }
        }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #030712; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #dc2626, #2563eb); border-radius: 8px; }
    </style>
</head>
<body class="min-h-screen text-slate-200 flex flex-col">

    <?php include 'notif-login.php'; ?>

    <!-- Toast Notifikasi Simpan & Cetak Struk -->
    <div id="toastSimpan" class="fixed top-5 right-5 z-[100] translate-x-[120%] opacity-0 transition-all duration-500 ease-out">
        <div class="bg-slate-900 border border-blue-800/50 shadow-2xl shadow-black/50 rounded-2xl p-4 pr-5 flex items-center gap-3 max-w-sm">
            <div class="bg-blue-500/10 text-blue-400 rounded-full w-10 h-10 flex items-center justify-center flex-shrink-0">
                <span class="material-symbols-outlined text-[20px]">save</span>
            </div>
            <div>
                <p class="font-display font-bold text-slate-100 text-sm">Data Disimpan!</p>
                <p class="text-xs text-slate-400">Menyiapkan karcis untuk kendaraan masuk...</p>
            </div>
        </div>
    </div>

    <!-- Toast Notifikasi Proses Check-in -->
    <div id="toastCheckin" class="fixed top-5 right-5 z-[100] translate-x-[120%] opacity-0 transition-all duration-500 ease-out">
        <div class="bg-slate-900 border border-blue-800/50 shadow-2xl shadow-black/50 rounded-2xl p-4 pr-5 flex items-center gap-3 max-w-sm">
            <div class="bg-blue-500/10 text-blue-400 rounded-full w-10 h-10 flex items-center justify-center flex-shrink-0">
                <span class="material-symbols-outlined text-[20px]">check_circle</span>
            </div>
            <div>
                <p class="font-display font-bold text-slate-100 text-sm">Check-in Diproses!</p>
                <p class="text-xs text-slate-400">Menyiapkan karcis kendaraan...</p>
            </div>
        </div>
    </div>

    <!-- ===================== MODAL PREVIEW STRUK (sebelum dicetak) ===================== -->
    <div id="modalPreviewStruk" class="hidden fixed inset-0 z-[200] bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-slate-100 rounded-2xl shadow-2xl w-full max-w-md flex flex-col overflow-hidden" style="max-height: 92vh;">
            <div class="bg-slate-950 px-5 py-4 flex items-center justify-between flex-shrink-0">
                <h3 class="font-display text-slate-100 font-bold text-sm flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px] text-red-500">confirmation_number</span>
                    Preview Karcis Parkir
                </h3>
                <button type="button" onclick="tutupModalStruk()" class="text-slate-400 hover:text-white transition">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto bg-slate-200">
                <iframe id="iframeStruk" src="" class="w-full" style="height: 65vh; border: none;"></iframe>
            </div>
            <div class="bg-white px-5 py-4 flex gap-3 border-t border-slate-200 flex-shrink-0">
                <button type="button" onclick="cetakDariModal()" class="flex-1 bg-red-600 hover:bg-red-500 text-white font-bold py-3 rounded-xl shadow transition flex items-center justify-center gap-2 text-sm">
                    <span class="material-symbols-outlined text-[18px]">print</span>
                    <span>Cetak Karcis</span>
                </button>
                <button type="button" onclick="tutupModalStruk()" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold py-3 px-5 rounded-xl transition text-sm">
                    Selesai
                </button>
            </div>
        </div>
    </div>

    <!-- Header Navbar -->
    <header class="bg-gradient-to-r from-[#050609] via-[#0a0c14] to-[#050609] text-white shadow-lg shadow-black/40 border-b border-red-900/40 sticky top-0 z-50">
        <div class="h-[3px] w-full bg-gradient-to-r from-red-600 via-red-600/30 to-blue-600"></div>
        <div class="max-w-4xl mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <div class="relative bg-red-600/10 p-2.5 rounded-xl backdrop-blur-sm ring-1 ring-red-600/25">
                    <span class="material-symbols-outlined text-red-500 text-[24px]">local_parking</span>
                    <span class="beacon absolute -top-1 -right-1 w-2.5 h-2.5 rounded-full bg-red-500"></span>
                </div>
                <div>
                    <h1 class="font-display font-bold text-base leading-tight text-slate-100 tracking-tight">Panel Petugas Lapangan</h1>
                    <p class="text-xs text-blue-300/80">Halo, <?php echo htmlspecialchars($_SESSION['nama_lengkap'] ?? $_SESSION['username']); ?></p>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <a href="checkout.php" class="bg-white/5 hover:bg-blue-500/10 hover:ring-blue-500/30 text-white px-4 py-2 rounded-lg text-xs font-semibold transition flex items-center space-x-1.5 shadow ring-1 ring-white/10">
                    <span class="material-symbols-outlined text-[16px]">exit_to_app</span>
                    <span>Checkout / Denda</span>
                </a>
                <a href="riwayat.php" class="bg-white/5 hover:bg-red-500/10 hover:ring-red-500/30 text-white px-4 py-2 rounded-lg text-xs font-semibold transition flex items-center space-x-1.5 shadow ring-1 ring-white/10">
                    <span class="material-symbols-outlined text-[16px]">receipt_long</span>
                    <span>Riwayat Struk</span>
                </a>
                <a href="login.php" class="bg-red-700 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-xs font-semibold transition flex items-center space-x-1.5 shadow">
                    <span class="material-symbols-outlined text-[16px]">logout</span>
                    <span>Keluar</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="max-w-4xl mx-auto px-6 py-8 flex-1 w-full">
        <div class="bg-[#0a0c14]/85 backdrop-blur-sm rounded-2xl shadow-xl shadow-black/40 border border-red-900/30 p-8">

            <!-- Tab Switcher -->
            <div class="flex space-x-2 mb-6 border-b border-slate-800">
                <a href="?mode=manual"
                   class="font-display px-4 py-3 text-sm font-semibold border-b-2 transition flex items-center space-x-2
                          <?php echo $mode == 'manual' ? 'border-red-600 text-red-400' : 'border-transparent text-slate-500 hover:text-slate-300'; ?>">
                    <span class="material-symbols-outlined text-[18px]">directions_car</span>
                    <span>Input Manual (Walk-in)</span>
                </a>
                <a href="?mode=online"
                   class="font-display px-4 py-3 text-sm font-semibold border-b-2 transition flex items-center space-x-2
                          <?php echo $mode == 'online' ? 'border-blue-600 text-blue-400' : 'border-transparent text-slate-500 hover:text-slate-300'; ?>">
                    <span class="material-symbols-outlined text-[18px]">qr_code_2</span>
                    <span>Proses Reservasi Online</span>
                </a>
            </div>

            <?php if (!empty($error_msg)): ?>
                <div class="bg-red-600/10 border border-red-800/40 text-red-300 p-4 rounded-xl mb-6 text-sm flex items-center space-x-3 shadow-sm fade-in">
                    <span class="material-symbols-outlined text-red-400">error</span>
                    <span class="font-medium"><?php echo htmlspecialchars($error_msg); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($mode == 'manual'): ?>

                <!-- ===================== FORM INPUT MANUAL ===================== -->
                <div class="mb-6">
                    <h2 class="font-display text-xl font-bold text-slate-100 tracking-tight">Input Kendaraan Masuk</h2>
                    <p class="text-xs text-slate-400 mt-1">Masukkan data identitas dan kendaraan pengunjung bandara dengan akurat.</p>
                </div>

                <form id="formManual" action="?mode=manual" method="POST" class="space-y-5" onsubmit="return prosesSimpan(event)">
                    <input type="hidden" name="aksi" value="manual">

                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Nama Pemilik / Pengendara</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500">
                                <span class="material-symbols-outlined text-[20px]">person</span>
                            </span>
                            <input type="text" name="nama" required class="w-full bg-slate-900 border border-slate-700 text-slate-100 placeholder-slate-500 rounded-xl py-3 pl-11 pr-4 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500/60 focus:bg-slate-900 outline-none transition" placeholder="Contoh: Budi Santoso">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Plat Nomor Kendaraan</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500">
                                    <span class="material-symbols-outlined text-[20px]">directions_car</span>
                                </span>
                                <input type="text" name="plat" required class="w-full bg-slate-900 border border-slate-700 text-slate-100 placeholder-slate-500 rounded-xl py-3 pl-11 pr-4 text-sm uppercase font-mono focus:ring-2 focus:ring-red-500 focus:border-red-500/60 focus:bg-slate-900 outline-none transition" placeholder="AB 1234 XY">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Jenis Kendaraan</label>
                            <div class="relative">
                                <select name="kendaraan" class="w-full bg-slate-900 border border-slate-700 text-slate-100 rounded-xl py-3 px-4 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500/60 focus:bg-slate-900 outline-none transition cursor-pointer">
                                    <option value="mobil">Mobil</option>
                                    <option value="motor">Motor</option>
                                    <option value="bus">Bus</option>
                                    <option value="truk">Truk</option>
                                </select>
                                <p class="text-[10px] text-slate-500 mt-1">Tarif mengikuti pengaturan terbaru di menu Tarif Parkir (Admin). Area parkir dipilih otomatis sesuai jenis &amp; kategori.</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Kategori Parkir</label>
                            <div class="relative">
                                <select name="kategori" class="w-full bg-slate-900 border border-slate-700 text-slate-100 rounded-xl py-3 px-4 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500/60 focus:bg-slate-900 outline-none transition cursor-pointer">
                                    <option value="reguler">Reguler</option>
                                    <option value="vip">VIP / Eksklusif (+50% tarif)</option>
                                    <option value="vvip">VVIP (+100% tarif)</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Estimasi Durasi Parkir (Jam)</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500">
                                    <span class="material-symbols-outlined text-[20px]">schedule</span>
                                </span>
                                <input type="number" name="durasi" value="1" min="1" required class="w-full bg-slate-900 border border-slate-700 text-slate-100 rounded-xl py-3 pl-11 pr-4 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500/60 focus:bg-slate-900 outline-none transition">
                            </div>
                        </div>
                    </div>

                    <button type="submit" id="btnSimpanManual" class="font-display w-full bg-gradient-to-r from-red-600 to-blue-700 hover:from-red-500 hover:to-blue-600 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-red-900/30 hover:shadow-xl active:scale-[0.99] transition flex items-center justify-center space-x-2 mt-4">
                        <span class="material-symbols-outlined text-[20px]">save</span>
                        <span>Simpan & Cetak Karcis Parkir</span>
                    </button>
                </form>

            <?php else: ?>

                <!-- ===================== PROSES RESERVASI ONLINE ===================== -->
                <div class="mb-6">
                    <h2 class="font-display text-xl font-bold text-slate-100 tracking-tight">Proses Reservasi Online</h2>
                    <p class="text-xs text-slate-400 mt-1">Masukkan kode reservasi yang ditunjukkan pengunjung untuk memproses check-in.</p>
                </div>

                <!-- Form Pencarian Kode Reservasi -->
                <form id="formCariOnline" action="?mode=online" method="POST" class="mb-6">
                    <input type="hidden" name="aksi" value="cari_online">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Kode Reservasi</label>
                    <div class="flex flex-col sm:flex-row items-stretch gap-3">
                        <div class="relative flex-1">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-blue-400">
                                <span class="material-symbols-outlined text-[22px]">confirmation_number</span>
                            </span>
                            <input type="text" name="kode_reservasi" id="inputKodeReservasi" autofocus
                                   value="<?php echo isset($_POST['kode_reservasi']) ? htmlspecialchars($_POST['kode_reservasi']) : ''; ?>"
                                   class="kode-input w-full bg-slate-900 border-2 border-slate-700 rounded-xl py-3.5 pl-12 pr-4 text-base font-mono font-bold uppercase text-slate-100 placeholder-slate-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-slate-900 outline-none transition"
                                   placeholder="RSV-8DD728">
                        </div>
                        <button type="submit" class="font-display bg-blue-600 hover:bg-blue-500 text-white font-bold py-3.5 px-8 rounded-xl shadow-lg shadow-blue-900/30 hover:shadow-xl active:scale-[0.99] transition flex items-center justify-center space-x-2 whitespace-nowrap">
                            <span class="material-symbols-outlined text-[20px]">search</span>
                            <span>Cari & Proses</span>
                        </button>
                    </div>
                    <p class="text-[11px] text-slate-500 mt-2 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">info</span>
                        Kosongkan kolom lalu tekan Cari untuk melihat semua reservasi yang masih menunggu check-in — atau klik langsung salah satu kode di bawah ini.
                    </p>
                </form>

                <?php if (!$sudah_cari): ?>
                    <!-- ============ DAFTAR CEPAT: KODE RESERVASI PENDING (KLIK LANGSUNG) ============ -->
                    <div class="mb-8">
                        <h3 class="font-display text-xs font-bold text-slate-400 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px] text-blue-400">bolt</span>
                            Menunggu Check-in — Klik Kode untuk Langsung Proses
                        </h3>

                        <?php if (empty($daftar_pending)): ?>
                            <div class="bg-slate-900/40 border border-dashed border-slate-700 text-slate-500 p-4 rounded-xl text-sm text-center">
                                Belum ada reservasi online yang menunggu check-in saat ini.
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <?php foreach ($daftar_pending as $p): ?>
                                    <button type="button"
                                            onclick="pilihKode('<?php echo htmlspecialchars(addslashes($p['kode_reservasi']), ENT_QUOTES); ?>')"
                                            class="fade-in text-left bg-slate-900/60 hover:bg-blue-600/10 border border-slate-700 hover:border-blue-600 rounded-xl px-4 py-3 transition flex items-center justify-between gap-3 group">
                                        <div class="min-w-0">
                                            <p class="font-mono font-bold text-sm text-blue-300 group-hover:text-blue-200 tracking-wider truncate"><?php echo htmlspecialchars($p['kode_reservasi']); ?></p>
                                            <p class="text-xs text-slate-400 truncate"><?php echo htmlspecialchars($p['nama']); ?> &middot; <?php echo htmlspecialchars(strtoupper($p['plat'])); ?> &middot; <span class="capitalize"><?php echo htmlspecialchars($p['kendaraan']); ?></span></p>
                                        </div>
                                        <span class="material-symbols-outlined text-slate-500 group-hover:text-blue-400 text-[20px] flex-shrink-0">touch_app</span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($sudah_cari): ?>
                    <?php if (empty($hasil_online)): ?>
                        <div class="bg-red-600/10 border border-red-800/40 text-red-300 p-4 rounded-xl mb-6 text-sm flex items-center space-x-3 shadow-sm fade-in">
                            <span class="material-symbols-outlined text-red-400">info</span>
                            <span class="font-medium">Tidak ada reservasi online yang menunggu check-in dengan kode tersebut.</span>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($hasil_online as $row): ?>
                                <?php
                                    $total = intval($row['total_biaya']);
                                ?>
                                <div class="fade-in bg-gradient-to-br from-blue-500/5 to-slate-950 border border-blue-800/40 rounded-2xl p-6 shadow-sm shadow-black/30 hover:shadow-md hover:shadow-black/40 transition">
                                    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
                                        <div class="flex items-center space-x-2 text-blue-300 bg-blue-500/10 px-3 py-1.5 rounded-full">
                                            <span class="material-symbols-outlined text-[18px]">schedule</span>
                                            <span class="font-display font-bold text-xs uppercase tracking-wider">Menunggu Check-in</span>
                                        </div>
                                        <div class="flex items-center space-x-2 bg-slate-950 text-white px-3 py-1.5 rounded-full ring-1 ring-red-900/40">
                                            <span class="material-symbols-outlined text-[16px] text-red-500">confirmation_number</span>
                                            <span class="font-mono font-bold text-xs tracking-wider"><?php echo htmlspecialchars($row['kode_reservasi']); ?></span>
                                        </div>
                                    </div>

                                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm mb-6">
                                        <div>
                                            <dt class="text-slate-500 text-xs uppercase font-semibold">Nama</dt>
                                            <dd class="font-semibold text-slate-100"><?php echo htmlspecialchars($row['nama']); ?></dd>
                                        </div>
                                        <div>
                                            <dt class="text-slate-500 text-xs uppercase font-semibold">Plat Nomor</dt>
                                            <dd class="font-mono font-semibold text-slate-100"><?php echo htmlspecialchars(strtoupper($row['plat'])); ?></dd>
                                        </div>
                                        <div>
                                            <dt class="text-slate-500 text-xs uppercase font-semibold">Jenis Kendaraan</dt>
                                            <dd class="font-semibold text-slate-100 capitalize"><?php echo htmlspecialchars($row['kendaraan']); ?></dd>
                                        </div>
                                        <div>
                                            <dt class="text-slate-500 text-xs uppercase font-semibold">Kategori</dt>
                                            <dd class="font-semibold text-slate-100 capitalize"><?php echo htmlspecialchars($row['kategori']); ?></dd>
                                        </div>
                                        <div>
                                            <dt class="text-slate-500 text-xs uppercase font-semibold">Tanggal &amp; Waktu Booking</dt>
                                            <dd class="font-semibold text-slate-100"><?php echo htmlspecialchars($row['tanggal']); ?> &middot; <?php echo htmlspecialchars($row['waktu']); ?></dd>
                                        </div>
                                        <div>
                                            <dt class="text-slate-500 text-xs uppercase font-semibold">Durasi</dt>
                                            <dd class="font-semibold text-slate-100"><?php echo intval($row['durasi']); ?> Jam</dd>
                                        </div>
                                        <div class="md:col-span-2 pt-3 border-t border-blue-800/30 flex items-center justify-between">
                                            <dt class="text-slate-500 text-xs uppercase font-semibold">Total Biaya</dt>
                                            <dd class="font-display font-extrabold text-xl text-blue-400">Rp <?php echo number_format($total, 0, ',', '.'); ?></dd>
                                        </div>
                                    </dl>

                                    <form action="?mode=online" method="POST" onsubmit="return prosesCheckin(event, this)">
                                        <input type="hidden" name="aksi" value="proses_online">
                                        <input type="hidden" name="id_reservasi" value="<?php echo intval($row['id']); ?>">
                                        <button type="submit" class="font-display w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-blue-900/30 hover:shadow-xl active:scale-[0.99] transition flex items-center justify-center space-x-2">
                                            <span class="material-symbols-outlined text-[20px]">check_circle</span>
                                            <span>Proses Check-in & Cetak Karcis</span>
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

    <script>
        // Nada "ding-dong" naik — notifikasi sukses simpan data
        function mainkanSuaraSimpan() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const now = ctx.currentTime;

                function nada(freq, start, durasi, volume = 0.15) {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(freq, now + start);
                    gain.gain.setValueAtTime(0, now + start);
                    gain.gain.linearRampToValueAtTime(volume, now + start + 0.02);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + start + durasi);
                    osc.connect(gain).connect(ctx.destination);
                    osc.start(now + start);
                    osc.stop(now + start + durasi);
                }

                nada(880, 0, 0.25);
                nada(1318.5, 0.15, 0.35);
            } catch (e) {
                console.warn('Audio tidak bisa diputar:', e);
            }
        }

        function tampilkanToastSimpan() {
            const toast = document.getElementById('toastSimpan');
            if (!toast) return;

            mainkanSuaraSimpan();

            toast.classList.remove('translate-x-[120%]', 'opacity-0');
            toast.classList.add('translate-x-0', 'opacity-100');

            setTimeout(() => {
                toast.classList.add('translate-x-[120%]', 'opacity-0');
                toast.classList.remove('translate-x-0', 'opacity-100');
            }, 4000);
        }

        function tampilkanToastCheckin() {
            const toast = document.getElementById('toastCheckin');
            if (!toast) return;

            mainkanSuaraSimpan();

            toast.classList.remove('translate-x-[120%]', 'opacity-0');
            toast.classList.add('translate-x-0', 'opacity-100');

            setTimeout(() => {
                toast.classList.add('translate-x-[120%]', 'opacity-0');
                toast.classList.remove('translate-x-0', 'opacity-100');
            }, 4000);
        }

        // ===================== MODAL PREVIEW STRUK =====================

        // Buka modal preview KARCIS (bukan struk) untuk id reservasi
        // tertentu. Karcis dicetak saat kendaraan MASUK (belum ada
        // rincian biaya karena tarif final baru dihitung saat keluar).
        // Struk lengkap dengan rincian biaya dicetak terpisah di
        // halaman checkout saat kendaraan KELUAR.
        function bukaPreviewStruk(id) {
            const modal = document.getElementById('modalPreviewStruk');
            const iframe = document.getElementById('iframeStruk');
            if (!modal || !iframe) return;

            iframe.src = 'cetak_karcis.php?id=' + encodeURIComponent(id) + '&embed=1';
            modal.classList.remove('hidden');
        }

        // Tombol "Cetak Struk" di dalam modal: trigger dialog print
        // browser untuk konten iframe (struk), bukan halaman utama.
        function cetakDariModal() {
            const iframe = document.getElementById('iframeStruk');
            if (!iframe || !iframe.contentWindow) return;
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
        }

        // Tutup modal & reset halaman (form kosong lagi, daftar
        // reservasi pending ikut diperbarui).
        function tutupModalStruk() {
            const modal = document.getElementById('modalPreviewStruk');
            if (modal) modal.classList.add('hidden');
            window.location.href = 'petugas.php?mode=<?php echo $mode == 'online' ? 'online' : 'manual'; ?>';
        }

        // Dipanggil saat form manual disubmit: simpan data via AJAX,
        // lalu tampilkan preview struk dalam modal (belum tercetak).
        function prosesSimpan(e) {
            e.preventDefault();
            tampilkanToastSimpan();

            const form = document.getElementById('formManual');
            const btn = document.getElementById('btnSimpanManual');
            if (btn) {
                btn.disabled = true;
                btn.classList.add('opacity-60', 'cursor-not-allowed');
            }

            const formData = new FormData(form);
            formData.set('ajax', '1');

            fetch('?mode=manual', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (btn) {
                    btn.disabled = false;
                    btn.classList.remove('opacity-60', 'cursor-not-allowed');
                }
                if (data.success) {
                    form.reset();
                    bukaPreviewStruk(data.id);
                } else {
                    alert(data.error || 'Gagal menyimpan data.');
                }
            })
            .catch(err => {
                console.error(err);
                if (btn) {
                    btn.disabled = false;
                    btn.classList.remove('opacity-60', 'cursor-not-allowed');
                }
                alert('Terjadi kesalahan saat menyimpan data.');
            });

            return false;
        }

        // Diklik dari daftar cepat kode reservasi pending: isi kolom
        // pencarian lalu langsung submit form pencarian, sehingga
        // petugas tidak perlu mengetik/menyalin kode secara manual.
        function pilihKode(kode) {
            const input = document.getElementById('inputKodeReservasi');
            const form = document.getElementById('formCariOnline');
            if (input) input.value = kode;
            if (form) form.submit();
        }

        // Dipanggil saat tombol "Proses Check-in & Cetak Struk" diklik:
        // proses check-in via AJAX, lalu tampilkan preview struk dalam
        // modal (belum tercetak) sebelum petugas menekan tombol cetak.
        function prosesCheckin(e, form) {
            e.preventDefault();

            const btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.classList.add('opacity-60', 'cursor-not-allowed');
            }

            tampilkanToastCheckin();

            const formData = new FormData(form);
            formData.set('ajax', '1');

            fetch('?mode=online', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    bukaPreviewStruk(data.id);
                } else {
                    alert(data.error || 'Gagal memproses check-in.');
                    if (btn) {
                        btn.disabled = false;
                        btn.classList.remove('opacity-60', 'cursor-not-allowed');
                    }
                }
            })
            .catch(err => {
                console.error(err);
                alert('Terjadi kesalahan saat memproses check-in.');
                if (btn) {
                    btn.disabled = false;
                    btn.classList.remove('opacity-60', 'cursor-not-allowed');
                }
            });

            return false;
        }
    </script>
</body>
</html>