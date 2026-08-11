<?php
session_start();

/* =========================================================
   KONEKSI DATABASE
   Sesuaikan dengan koneksi yang sudah kamu pakai di index.php
   ========================================================= */
$host = "localhost";
$db   = "bandara";
$user = "root";
$pass = "";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

/* =========================================================
   IDENTITAS USER
   Ganti bagian ini sesuai sistem login kamu.
   Sementara pakai session 'nama_user' atau contoh statis.
   ========================================================= */
$nama_user = $_SESSION['nama_user'] ?? 'Budi Santoso';

/* =========================================================
   AMBIL DATA RESERVASI MILIK USER
   Asumsi tabel: reservasi
   Kolom: id, nama, plat, jenis_kendaraan, durasi, tanggal_masuk,
          waktu_masuk, total_biaya, status, created_at
   ========================================================= */
$stmt = $conn->prepare("SELECT id, nama, plat, jenis_kendaraan, durasi,
                                tanggal_masuk, waktu_masuk, total_biaya, status
                         FROM reservasi
                         WHERE nama = ?
                         ORDER BY tanggal_masuk DESC, waktu_masuk DESC");
$stmt->bind_param("s", $nama_user);
$stmt->execute();
$result = $stmt->get_result();

$reservasi = [];
$total_aktif = 0;
$total_selesai = 0;
$total_biaya_keseluruhan = 0;

while ($row = $result->fetch_assoc()) {
    $reservasi[] = $row;
    $total_biaya_keseluruhan += (float) $row['total_biaya'];
    if (strtolower($row['status']) === 'aktif') {
        $total_aktif++;
    } elseif (strtolower($row['status']) === 'selesai') {
        $total_selesai++;
    }
}
$total_reservasi = count($reservasi);
$stmt->close();
$conn->close();

function formatRupiah($angka) {
    return "Rp " . number_format((float) $angka, 0, ',', '.');
}

function statusBadge($status) {
    $status = strtolower($status);
    $map = [
        'aktif'      => 'bg-blue-100 text-blue-700',
        'selesai'    => 'bg-green-100 text-green-700',
        'dibatalkan' => 'bg-red-100 text-red-700',
        'menunggu'   => 'bg-yellow-100 text-yellow-700',
    ];
    $class = $map[$status] ?? 'bg-gray-100 text-gray-700';
    return "<span class=\"px-3 py-1 rounded-full text-xs font-semibold $class\">" . ucfirst($status) . "</span>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Saya | AeroParking</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
  body { font-family: 'Segoe UI', system-ui, sans-serif; }
</style>
</head>
<body class="bg-gray-50">

<!-- NAVBAR -->
<nav class="bg-blue-900 text-white sticky top-0 z-50 shadow">
  <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
    <a href="index.php" class="flex items-center gap-2 text-xl font-bold">
      <i class="fa-solid fa-plane-departure"></i> AeroParking<span class="text-orange-400">.</span>
    </a>
    <div class="hidden md:flex items-center gap-8 text-sm font-medium">
      <a href="index.php" class="hover:text-orange-300">Beranda</a>
      <a href="slot.php" class="hover:text-orange-300">Ketersediaan Slot</a>
      <a href="reservasi.php" class="hover:text-orange-300">Reservasi Online</a>
      <a href="dashboard.php" class="text-orange-400">Dashboard Saya</a>
      <a href="bantuan.php" class="hover:text-orange-300">Pusat Bantuan</a>
    </div>
    <div class="flex items-center gap-3">
      <span class="hidden sm:block text-sm">Halo, <strong><?= htmlspecialchars($nama_user) ?></strong></span>
      <div class="w-9 h-9 rounded-full bg-orange-400 flex items-center justify-center font-bold">
        <?= strtoupper(substr($nama_user, 0, 1)) ?>
      </div>
    </div>
  </div>
</nav>

<div class="max-w-7xl mx-auto px-6 py-10">

  <!-- HEADER -->
  <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
    <div>
      <h1 class="text-3xl font-bold text-gray-900">Dashboard Reservasi Saya</h1>
      <p class="text-gray-500 mt-1">Pantau status parkir dan riwayat reservasi kamu di sini.</p>
    </div>
    <a href="reservasi.php" class="inline-flex items-center gap-2 bg-orange-500 hover:bg-orange-600 text-white font-semibold px-5 py-3 rounded-xl shadow transition">
      <i class="fa-solid fa-plus"></i> Reservasi Baru
    </a>
  </div>

  <!-- STAT CARDS -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-10">

    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-500 text-sm">Total Reservasi</p>
          <p class="text-2xl font-bold text-gray-900 mt-1"><?= $total_reservasi ?></p>
        </div>
        <div class="w-12 h-12 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center text-xl">
          <i class="fa-solid fa-square-parking"></i>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-500 text-sm">Sedang Aktif</p>
          <p class="text-2xl font-bold text-gray-900 mt-1"><?= $total_aktif ?></p>
        </div>
        <div class="w-12 h-12 rounded-xl bg-orange-100 text-orange-600 flex items-center justify-center text-xl">
          <i class="fa-solid fa-car"></i>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-500 text-sm">Selesai</p>
          <p class="text-2xl font-bold text-gray-900 mt-1"><?= $total_selesai ?></p>
        </div>
        <div class="w-12 h-12 rounded-xl bg-green-100 text-green-700 flex items-center justify-center text-xl">
          <i class="fa-solid fa-circle-check"></i>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-500 text-sm">Total Pengeluaran</p>
          <p class="text-2xl font-bold text-gray-900 mt-1"><?= formatRupiah($total_biaya_keseluruhan) ?></p>
        </div>
        <div class="w-12 h-12 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center text-xl">
          <i class="fa-solid fa-wallet"></i>
        </div>
      </div>
    </div>

  </div>

  <!-- TABEL RIWAYAT RESERVASI -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
      <h2 class="text-lg font-bold text-gray-900">Riwayat Reservasi</h2>
      <span class="text-sm text-gray-400"><?= $total_reservasi ?> data</span>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50 text-gray-500 text-left">
            <th class="px-6 py-3 font-semibold">No. Plat</th>
            <th class="px-6 py-3 font-semibold">Jenis Kendaraan</th>
            <th class="px-6 py-3 font-semibold">Tanggal Masuk</th>
            <th class="px-6 py-3 font-semibold">Waktu Masuk</th>
            <th class="px-6 py-3 font-semibold">Durasi</th>
            <th class="px-6 py-3 font-semibold">Total Biaya</th>
            <th class="px-6 py-3 font-semibold">Status</th>
            <th class="px-6 py-3 font-semibold text-right">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php if (empty($reservasi)): ?>
            <tr>
              <td colspan="8" class="px-6 py-10 text-center text-gray-400">
                Belum ada reservasi. Yuk buat reservasi pertamamu!
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($reservasi as $r): ?>
              <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 font-medium text-gray-900"><?= htmlspecialchars($r['plat']) ?></td>
                <td class="px-6 py-4 text-gray-600"><?= htmlspecialchars($r['jenis_kendaraan']) ?></td>
                <td class="px-6 py-4 text-gray-600"><?= htmlspecialchars($r['tanggal_masuk']) ?></td>
                <td class="px-6 py-4 text-gray-600"><?= htmlspecialchars($r['waktu_masuk']) ?></td>
                <td class="px-6 py-4 text-gray-600"><?= (int) $r['durasi'] ?> jam</td>
                <td class="px-6 py-4 font-semibold text-gray-900"><?= formatRupiah($r['total_biaya']) ?></td>
                <td class="px-6 py-4"><?= statusBadge($r['status']) ?></td>
                <td class="px-6 py-4 text-right">
                  <a href="detail_reservasi.php?id=<?= (int) $r['id'] ?>" class="text-blue-700 hover:underline font-medium">
                    Detail
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<footer class="text-center text-gray-400 text-sm py-8">
  &copy; <?= date('Y') ?> AeroParking &mdash; Sistem Parkir Bandara Internasional
</footer>

</body>
</html>