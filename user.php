<?php
// Nama session khusus role user (customer), supaya tidak bentrok/tertimpa
// dengan session role lain (petugas/admin/owner) yang aktif di tab lain
// pada browser yang sama.
session_name('user_sess');
session_start();
include 'koneksi.php';

// ==========================================
// PROTEKSI HALAMAN: hanya untuk role "user" yang sudah login
// ==========================================
if (empty($_SESSION['status_login']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit();
}

$nama_user  = $_SESSION['nama_lengkap'];
$username   = $_SESSION['username'];
$pesan      = "";
$pesan_kode = "";

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

    // Pastikan kolom "kategori", "username", "kode_reservasi" & "status_masuk" sudah ada (auto-migrate)
    $cek_kategori = mysqli_query($conn, "SHOW COLUMNS FROM reservasi LIKE 'kategori'");
    if ($cek_kategori && mysqli_num_rows($cek_kategori) == 0) {
        mysqli_query($conn, "ALTER TABLE reservasi ADD COLUMN kategori VARCHAR(20) NOT NULL DEFAULT 'reguler' AFTER kendaraan");
    }
    $cek_username = mysqli_query($conn, "SHOW COLUMNS FROM reservasi LIKE 'username'");
    if ($cek_username && mysqli_num_rows($cek_username) == 0) {
        mysqli_query($conn, "ALTER TABLE reservasi ADD COLUMN username VARCHAR(100) NULL AFTER nama");
    }
    $cek_kode = mysqli_query($conn, "SHOW COLUMNS FROM reservasi LIKE 'kode_reservasi'");
    if ($cek_kode && mysqli_num_rows($cek_kode) == 0) {
        mysqli_query($conn, "ALTER TABLE reservasi ADD COLUMN kode_reservasi VARCHAR(20) NULL UNIQUE AFTER id");
    }
    $cek_status_masuk = mysqli_query($conn, "SHOW COLUMNS FROM reservasi LIKE 'status_masuk'");
    if ($cek_status_masuk && mysqli_num_rows($cek_status_masuk) == 0) {
        mysqli_query($conn, "ALTER TABLE reservasi ADD COLUMN status_masuk VARCHAR(20) NOT NULL DEFAULT 'Belum Masuk' AFTER status_bayar");
    }

    // Buat kode reservasi unik, format: RSV-XXXXXX
    function buat_kode_reservasi($conn) {
        do {
            $kode = 'RSV-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
            $cek  = mysqli_query($conn, "SELECT id FROM reservasi WHERE kode_reservasi = '$kode'");
        } while ($cek && mysqli_num_rows($cek) > 0);
        return $kode;
    }
    $kode_reservasi = buat_kode_reservasi($conn);

    // Matriks tarif per jam: [jenis kendaraan][kategori]
    $tarif_matrix = [
        'motor' => ['reguler' => 2000,  'vip' => 5000,  'vvip' => 8000],
        'mobil' => ['reguler' => 5000,  'vip' => 15000, 'vvip' => 25000],
        'bis'   => ['reguler' => 10000, 'vip' => 20000, 'vvip' => 35000],
        'truk'  => ['reguler' => 12000, 'vip' => 25000, 'vvip' => 40000],
    ];
    $tarif_per_jam = $tarif_matrix[$kendaraan][$kategori] ?? 5000;
    $total_biaya   = $tarif_per_jam * $durasi;

    $query = "INSERT INTO reservasi (kode_reservasi, nama, username, plat, kendaraan, kategori, durasi, tanggal, waktu, total_biaya, status_bayar, status_masuk) 
              VALUES ('$kode_reservasi', '$nama', '$username', '$plat', '$kendaraan', '$kategori', $durasi, '$tanggal', '$waktu', $total_biaya, 'pending', 'Belum Masuk')";

    if (mysqli_query($conn, $query)) {
        $pesan = "Reservasi berhasil disimpan! Plat: $plat. Total: Rp " . number_format($total_biaya, 0, ',', '.');
        $pesan_kode = $kode_reservasi;
    } else {
        $pesan = "Gagal menyimpan data: " . mysqli_error($conn);
    }
}

// ==========================================
// AMBIL DATA KETERSEDIAAN SLOT DARI area_parkir (REAL-TIME)
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

function status_slot($total, $terisi) {
    $sisa   = max(0, $total - $terisi);
    $persen = ($total > 0) ? round(($terisi / $total) * 100) : 0;

    if ($persen >= 90) {
        $label = 'Hampir Penuh'; $warna_teks = 'text-rose-600'; $warna_bar = 'bg-rose-600';
    } elseif ($persen >= 70) {
        $label = 'Hampir Penuh'; $warna_teks = 'text-amber-600'; $warna_bar = 'bg-amber-500';
    } elseif ($persen >= 40) {
        $label = 'Tersedia'; $warna_teks = 'text-emerald-600'; $warna_bar = 'bg-blue-600';
    } else {
        $label = 'Sangat Luas'; $warna_teks = 'text-emerald-600'; $warna_bar = 'bg-emerald-600';
    }

    return ['sisa' => $sisa, 'persen' => $persen, 'label' => $label, 'warna_teks' => $warna_teks, 'warna_bar' => $warna_bar];
}

$status_mobil = status_slot($slot_mobil['total'], $slot_mobil['terisi']);
$status_vip   = status_slot($slot_vip['total'], $slot_vip['terisi']);
$status_motor = status_slot($slot_motor['total'], $slot_motor['terisi']);

// ==========================================
// RIWAYAT RESERVASI MILIK USER YANG SEDANG LOGIN
// ==========================================
$riwayat = [];
$cek_username_col = mysqli_query($conn, "SHOW COLUMNS FROM reservasi LIKE 'username'");
if ($cek_username_col && mysqli_num_rows($cek_username_col) > 0) {
    $q_riwayat = mysqli_query($conn, "SELECT * FROM reservasi WHERE username = '" . mysqli_real_escape_string($conn, $username) . "' ORDER BY id DESC LIMIT 10");
    if ($q_riwayat) {
        while ($r = mysqli_fetch_assoc($q_riwayat)) {
            $riwayat[] = $r;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - AeroParking</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-50 font-sans text-slate-800">

    <!-- Header / Navbar -->
    <header class="bg-blue-900 text-white shadow-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <i class="fa-solid fa-plane-departure text-2xl text-blue-400"></i>
                <span class="text-xl font-bold tracking-wide">AeroParking<span class="text-blue-400">.</span></span>
            </div>
            <nav class="hidden md:flex space-x-6 text-sm font-medium items-center">
                <a href="#status" class="hover:text-blue-300 transition">Ketersediaan Slot</a>
                <a href="#booking" class="hover:text-blue-300 transition">Reservasi Online</a>
                <a href="#riwayat" class="hover:text-blue-300 transition">Riwayat Saya</a>
                <span class="text-blue-200 border-l border-blue-700 pl-6 flex items-center gap-2">
                    <i class="fa-solid fa-circle-user"></i> <?php echo htmlspecialchars($nama_user); ?>
                </span>
                <a href="logout.php" class="bg-rose-500 hover:bg-rose-600 text-white px-3 py-1.5 rounded-lg font-bold transition"><i class="fa-solid fa-right-from-bracket mr-1"></i> Keluar</a>
            </nav>
        </div>
    </header>

    <!-- Welcome Banner -->
    <section class="bg-gradient-to-br from-blue-900 to-indigo-900 text-white py-10 px-4">
        <div class="max-w-7xl mx-auto">
            <h1 class="text-2xl md:text-3xl font-extrabold">Halo, <?php echo htmlspecialchars($nama_user); ?> 👋</h1>
            <p class="text-blue-200 mt-1">Kelola reservasi parkir dan pantau ketersediaan slot bandara di sini.</p>
        </div>
    </section>

    <!-- Notifikasi -->
    <?php if (!empty($pesan)): ?>
        <div class="max-w-3xl mx-auto mt-8 px-4">
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-6 rounded-2xl shadow-md flex items-start space-x-4">
                <i class="fa-solid fa-circle-check text-2xl text-emerald-600 mt-0.5"></i>
                <div class="flex-1">
                    <h3 class="font-bold text-lg">Berhasil Dipesan!</h3>
                    <p class="text-sm mt-1"><?php echo htmlspecialchars($pesan); ?></p>
                    <?php if (!empty($pesan_kode)): ?>
                    <div class="mt-4 bg-white border-2 border-dashed border-emerald-400 rounded-xl p-4 text-center">
                        <p class="text-xs text-slate-500 mb-1">Kode Reservasi Anda — tunjukkan ke petugas saat masuk area parkir</p>
                        <p class="text-3xl font-black text-emerald-700 tracking-widest"><?php echo htmlspecialchars($pesan_kode); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Ketersediaan Slot -->
    <section id="status" class="max-w-7xl mx-auto px-4 py-12">
        <div class="mb-8">
            <h2 class="text-2xl md:text-3xl font-bold text-slate-900">Ketersediaan Slot Parkir Saat Ini</h2>
            <p class="text-slate-500 mt-2">Data diperbarui secara real-time dari sistem pusat bandara</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <div class="flex justify-between items-center mb-4">
                    <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-3 py-1 rounded-full">Mobil / Bis / Truk</span>
                    <i class="fa-solid fa-car text-blue-600 text-xl"></i>
                </div>
                <div class="text-3xl font-black text-slate-800 mb-1"><?php echo $status_mobil['sisa']; ?> <span class="text-sm font-normal text-slate-500">/ <?php echo $slot_mobil['total']; ?> Slot</span></div>
                <div class="w-full bg-slate-200 rounded-full h-2.5 mt-4">
                    <div class="<?php echo $status_mobil['warna_bar']; ?> h-2.5 rounded-full" style="width: <?php echo $status_mobil['persen']; ?>%"></div>
                </div>
                <p class="text-xs <?php echo $status_mobil['warna_teks']; ?> font-medium mt-3"><i class="fa-solid fa-circle text-[8px] mr-1"></i> <?php echo $status_mobil['label']; ?></p>
            </div>
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <div class="flex justify-between items-center mb-4">
                    <span class="bg-amber-100 text-amber-800 text-xs font-semibold px-3 py-1 rounded-full">VIP / VVIP & Valet</span>
                    <i class="fa-solid fa-crown text-amber-600 text-xl"></i>
                </div>
                <div class="text-3xl font-black text-slate-800 mb-1"><?php echo $status_vip['sisa']; ?> <span class="text-sm font-normal text-slate-500">/ <?php echo $slot_vip['total']; ?> Slot</span></div>
                <div class="w-full bg-slate-200 rounded-full h-2.5 mt-4">
                    <div class="<?php echo $status_vip['warna_bar']; ?> h-2.5 rounded-full" style="width: <?php echo $status_vip['persen']; ?>%"></div>
                </div>
                <p class="text-xs <?php echo $status_vip['warna_teks']; ?> font-medium mt-3"><i class="fa-solid fa-circle text-[8px] mr-1"></i> <?php echo $status_vip['label']; ?></p>
            </div>
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <div class="flex justify-between items-center mb-4">
                    <span class="bg-emerald-100 text-emerald-800 text-xs font-semibold px-3 py-1 rounded-full">Sepeda Motor</span>
                    <i class="fa-solid fa-motorcycle text-emerald-600 text-xl"></i>
                </div>
                <div class="text-3xl font-black text-slate-800 mb-1"><?php echo $status_motor['sisa']; ?> <span class="text-sm font-normal text-slate-500">/ <?php echo $slot_motor['total']; ?> Slot</span></div>
                <div class="w-full bg-slate-200 rounded-full h-2.5 mt-4">
                    <div class="<?php echo $status_motor['warna_bar']; ?> h-2.5 rounded-full" style="width: <?php echo $status_motor['persen']; ?>%"></div>
                </div>
                <p class="text-xs <?php echo $status_motor['warna_teks']; ?> font-medium mt-3"><i class="fa-solid fa-circle text-[8px] mr-1"></i> <?php echo $status_motor['label']; ?></p>
            </div>
        </div>
    </section>

    <!-- Form Reservasi -->
    <section id="booking" class="bg-slate-100 py-16 px-4 border-t border-slate-200">
        <div class="max-w-3xl mx-auto bg-white rounded-3xl shadow-xl p-8 md:p-12">
            <div class="text-center mb-8">
                <h2 class="text-2xl md:text-3xl font-bold text-slate-900">Formulir Reservasi Parkir</h2>
                <p class="text-slate-500 mt-1">Data akan otomatis masuk ke Database MySQL</p>
            </div>

            <form action="" method="POST" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Nama Lengkap</label>
                        <input type="text" name="nama" required value="<?php echo htmlspecialchars($nama_user); ?>" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Nomor Plat Kendaraan</label>
                        <input type="text" name="plat" required class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="Cth: B 1234 XYZ">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Jenis Kendaraan</label>
                        <select name="kendaraan" id="vehicleType" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <option value="motor">Sepeda Motor</option>
                            <option value="mobil" selected>Mobil</option>
                            <option value="bis">Bis</option>
                            <option value="truk">Truk</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Kategori</label>
                        <select name="kategori" id="vehicleCategory" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <option value="reguler" selected>Reguler</option>
                            <option value="vip">VIP</option>
                            <option value="vvip">VVIP</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Durasi (Jam)</label>
                        <input type="number" name="durasi" id="duration" min="1" value="3" required class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Tanggal Masuk</label>
                        <input type="date" name="tanggal" required class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Waktu Masuk</label>
                        <input type="time" name="waktu" required class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4">
                    <span class="text-sm text-slate-600 block mb-2">Estimasi Tarif per Jam:</span>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-center border-collapse">
                            <thead>
                                <tr class="text-slate-500">
                                    <th class="py-1 text-left font-semibold">Kendaraan</th>
                                    <th class="py-1 font-semibold">Reguler</th>
                                    <th class="py-1 font-semibold">VIP</th>
                                    <th class="py-1 font-semibold">VVIP</th>
                                </tr>
                            </thead>
                            <tbody class="text-slate-700">
                                <tr class="border-t border-blue-100"><td class="py-1 text-left">Motor</td><td>Rp 2.000</td><td>Rp 5.000</td><td>Rp 8.000</td></tr>
                                <tr class="border-t border-blue-100"><td class="py-1 text-left">Mobil</td><td>Rp 5.000</td><td>Rp 15.000</td><td>Rp 25.000</td></tr>
                                <tr class="border-t border-blue-100"><td class="py-1 text-left">Bis</td><td>Rp 10.000</td><td>Rp 20.000</td><td>Rp 35.000</td></tr>
                                <tr class="border-t border-blue-100"><td class="py-1 text-left">Truk</td><td>Rp 12.000</td><td>Rp 25.000</td><td>Rp 40.000</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 pt-3 border-t border-blue-200 flex justify-between items-center">
                        <span class="text-sm text-slate-600">Estimasi Total Biaya:</span>
                        <span id="totalPrice" class="text-2xl font-black text-blue-900">Rp 15.000</span>
                    </div>
                </div>

                <button type="submit" name="simpan_reservasi" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-xl shadow-lg transition">Konfirmasi & Simpan Reservasi</button>
            </form>
        </div>
    </section>

    <!-- Riwayat Reservasi Saya -->
    <section id="riwayat" class="max-w-5xl mx-auto px-4 py-16">
        <div class="mb-8">
            <h2 class="text-2xl md:text-3xl font-bold text-slate-900">Riwayat Reservasi Saya</h2>
            <p class="text-slate-500 mt-2">10 reservasi terakhir yang Anda buat</p>
        </div>

        <?php if (count($riwayat) > 0): ?>
        <div class="overflow-x-auto bg-white border border-slate-200 rounded-2xl shadow-sm">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                    <tr>
                        <th class="px-5 py-3">Kode</th>
                        <th class="px-5 py-3">Plat</th>
                        <th class="px-5 py-3">Kendaraan</th>
                        <th class="px-5 py-3">Kategori</th>
                        <th class="px-5 py-3">Tanggal</th>
                        <th class="px-5 py-3">Waktu</th>
                        <th class="px-5 py-3">Durasi</th>
                        <th class="px-5 py-3">Total</th>
                        <th class="px-5 py-3">Masuk?</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($riwayat as $r): ?>
                    <tr>
                        <td class="px-5 py-3 font-mono font-bold text-blue-800"><?php echo htmlspecialchars($r['kode_reservasi'] ?? '-'); ?></td>
                        <td class="px-5 py-3 font-semibold text-slate-800"><?php echo htmlspecialchars($r['plat']); ?></td>
                        <td class="px-5 py-3 capitalize"><?php echo htmlspecialchars($r['kendaraan']); ?></td>
                        <td class="px-5 py-3 capitalize"><?php echo htmlspecialchars($r['kategori'] ?? 'reguler'); ?></td>
                        <td class="px-5 py-3"><?php echo htmlspecialchars($r['tanggal']); ?></td>
                        <td class="px-5 py-3"><?php echo htmlspecialchars($r['waktu']); ?></td>
                        <td class="px-5 py-3"><?php echo intval($r['durasi']); ?> Jam</td>
                        <td class="px-5 py-3 font-semibold text-blue-900">Rp <?php echo number_format($r['total_biaya'], 0, ',', '.'); ?></td>
                        <td class="px-5 py-3">
                            <?php $sudah_masuk = ($r['status_masuk'] ?? 'Belum Masuk') === 'Sudah Masuk'; ?>
                            <span class="<?php echo $sudah_masuk ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'; ?> text-xs font-semibold px-2.5 py-1 rounded-full"><?php echo htmlspecialchars($r['status_masuk'] ?? 'Belum Masuk'); ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="bg-slate-50 border border-dashed border-slate-300 rounded-2xl p-8 text-center text-slate-400 text-sm">
            Anda belum memiliki riwayat reservasi. Buat reservasi pertama Anda pada formulir di atas.
        </div>
        <?php endif; ?>
    </section>

    <footer class="bg-slate-900 text-slate-400 py-8 text-center text-sm">
        <p>&copy; 2026 AeroParking System. Hak Cipta Dilindungi.</p>
    </footer>

    <script>
        const tarifMatrix = {
            motor: { reguler: 2000,  vip: 5000,  vvip: 8000  },
            mobil: { reguler: 5000,  vip: 15000, vvip: 25000 },
            bis:   { reguler: 10000, vip: 20000, vvip: 35000 },
            truk:  { reguler: 12000, vip: 25000, vvip: 40000 }
        };

        function calculatePrice() {
            const type = document.getElementById('vehicleType').value;
            const category = document.getElementById('vehicleCategory').value;
            const duration = parseInt(document.getElementById('duration').value) || 1;
            const rate = (tarifMatrix[type] && tarifMatrix[type][category]) || 5000;
            const total = rate * duration;
            document.getElementById('totalPrice').innerText = 'Rp ' + total.toLocaleString('id-ID');
        }
        document.getElementById('vehicleType').addEventListener('change', calculatePrice);
        document.getElementById('vehicleCategory').addEventListener('change', calculatePrice);
        document.getElementById('duration').addEventListener('input', calculatePrice);
        calculatePrice();
    </script>
</body>
</html>