<?php
// laporan/manajemen status edit area parkir
$pesan_sukses = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_area') {
    $id_area = $_POST['id_area'];
    $nama_area = $_POST['nama_area'];
    $lokasi_detail = $_POST['lokasi_detail'];
    $total_slot = $_POST['total_slot'];
    $status = $_POST['status'];

    // Jika Anda menggunakan database MySQL, uncomment baris bawah ini:
    // mysqli_query($conn, "UPDATE area_parkir SET nama_area='$nama_area', lokasi_detail='$lokasi_detail', total_slot='$total_slot', status='$status' WHERE id='$id_area'");

    $pesan_sukses = "Data area parkir **$nama_area** berhasil diperbarui!";
}

// Data dummy (Ganti dengan: $result = mysqli_query($conn, "SELECT * FROM area_parkir"); dst.)
$daftar_area = [
    ['id' => 1, 'nama' => 'Area Terminal 1A - Mobil', 'lokasi' => 'Lantai 1 - Zona A', 'slot' => 500, 'terisi' => 412, 'tersedia' => 88, 'status' => 'AKTIF'],
    ['id' => 2, 'nama' => 'Area Terminal 1B - Motor', 'lokasi' => 'Basement - Zona B', 'slot' => 1200, 'terisi' => 856, 'tersedia' => 344, 'status' => 'AKTIF'],
    ['id' => 3, 'nama' => 'Area VIP / Eksekutif', 'lokasi' => 'Lantai Dasar Utama', 'slot' => 100, 'terisi' => 45, 'tersedia' => 55, 'status' => 'AKTIF'],
    ['id' => 4, 'nama' => 'Area Bus & Truk', 'lokasi' => 'Zona Khusus Luar', 'slot' => 300, 'terisi' => 180, 'tersedia' => 120, 'status' => 'AKTIF'],
    ['id' => 5, 'nama' => 'Area Terminal 2 - Mobil', 'lokasi' => 'Gedung Parkir T2', 'slot' => 800, 'terisi' => 650, 'tersedia' => 150, 'status' => 'AKTIF'],
];
?>

<div class="p-8 space-y-6">

    <?php if (!empty($pesan_sukses)): ?>
        <div class="bg-emerald-600 text-white p-4 rounded-xl text-xs font-semibold shadow-lg flex items-center justify-between">
            <span><i class="fa-solid fa-circle-check mr-2"></i> <?php echo $pesan_sukses; ?></span>
            <button onclick="this.parentElement.style.display='none'" class="text-white"><i class="fa-solid fa-xmark"></i></button>
        </div>
    <?php endif; ?>

    <!-- Tabel Manajemen Zona & Area Parkir -->
    <div class="bg-slate-900/40 border border-slate-800 rounded-2xl p-6 shadow-2xl backdrop-blur">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-white font-bold text-base">Manajemen Zona & Area Parkir Bandara</h3>
                <p class="text-slate-400 text-xs mt-0.5">Kelola kapasitas, lokasi, dan status operasional zona parkir.</p>
            </div>
            <button onclick="alert('Membuka form tambah area baru')" class="bg-blue-600 hover:bg-blue-700 text-white text-xs px-4 py-2.5 rounded-xl font-semibold shadow-lg transition flex items-center space-x-2">
                <i class="fa-solid fa-plus"></i><span>Tambah Area Baru</span>
            </button>
        </div>

        <!-- Tabel Data -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-800 text-[11px] text-slate-400 uppercase tracking-wider">
                        <th class="py-3 px-4">Nama Area / Terminal</th>
                        <th class="py-3 px-4">Lokasi Detail</th>
                        <th class="py-3 px-4">Total Slot</th>
                        <th class="py-3 px-4">Terisi</th>
                        <th class="py-3 px-4">Tersedia</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 text-xs text-slate-300">
                    <?php foreach ($daftar_area as $area): ?>
                    <tr class="hover:bg-slate-800/30 transition">
                        <td class="py-4 px-4 font-semibold text-white flex items-center space-x-3">
                            <div class="w-8 h-8 rounded-lg bg-blue-600/20 text-blue-400 flex items-center justify-center font-bold">P</div>
                            <span><?php echo $area['nama']; ?></span>
                        </td>
                        <td class="py-4 px-4 text-slate-400"><?php echo $area['lokasi']; ?></td>
                        <td class="py-4 px-4 font-bold text-white"><?php echo $area['slot']; ?></td>
                        <td class="py-4 px-4 font-semibold text-amber-400"><?php echo $area['terisi']; ?></td>
                        <td class="py-4 px-4 font-semibold text-emerald-400"><?php echo $area['tersedia']; ?></td>
                        <td class="py-4 px-4">
                            <span class="bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 px-2.5 py-1 rounded-full text-[10px] font-bold">
                                <?php echo $area['status']; ?>
                            </span>
                        </td>
                        <td class="py-4 px-4 text-center">
                            <!-- Tombol Edit menggunakan fungsi khusus: bukaModalArea -->
                            <button type="button" onclick="bukaModalArea(
                                '<?php echo $area['id']; ?>',
                                '<?php echo addslashes($area['nama']); ?>',
                                '<?php echo addslashes($area['lokasi']); ?>',
                                '<?php echo $area['slot']; ?>',
                                '<?php echo $area['status']; ?>'
                            )" class="w-8 h-8 rounded-lg bg-blue-600/20 hover:bg-blue-600 text-blue-400 hover:text-white transition flex items-center justify-center mx-auto shadow cursor-pointer" title="Edit Area">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL POPUP EDIT AREA -->
<div id="modalEditArea" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 w-full max-w-lg rounded-2xl p-6 shadow-2xl space-y-5 text-slate-200">
        <div class="flex justify-between items-center pb-3 border-b border-slate-800">
            <h3 class="font-bold text-base text-white flex items-center space-x-2">
                <i class="fa-solid fa-pen-to-square text-blue-500"></i>
                <span>Edit Area Parkir</span>
            </h3>
            <button type="button" onclick="tutupModalArea()" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="edit_area">
            <input type="hidden" name="id_area" id="edit_id_area">

            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-400">Nama Area / Terminal</label>
                <input type="text" name="nama_area" id="edit_nama_area" required class="w-full px-3 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-xs text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-400">Lokasi Detail</label>
                <input type="text" name="lokasi_detail" id="edit_lokasi_detail" required class="w-full px-3 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-xs text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-400">Total Slot Parkir</label>
                <input type="number" name="total_slot" id="edit_total_slot" required class="w-full px-3 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-xs text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-400">Status Operasional</label>
                <select name="status" id="edit_status_area" class="w-full px-3 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-xs text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="AKTIF">AKTIF</option>
                    <option value="NONAKTIF">NONAKTIF / MAINTENANCE</option>
                </select>
            </div>

            <div class="flex justify-end space-x-3 pt-4 border-t border-slate-800">
                <button type="button" onclick="tutupModalArea()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-xl transition">Batal</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-xl shadow-lg transition">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- JAVASCRIPT AMAN DARI KONFLIK -->
<script>
    function bukaModalArea(id, nama, lokasi, slot, status) {
        document.getElementById('edit_id_area').value = id;
        document.getElementById('edit_nama_area').value = nama;
        document.getElementById('edit_lokasi_detail').value = lokasi;
        document.getElementById('edit_total_slot').value = slot;
        document.getElementById('edit_status_area').value = status;

        document.getElementById('modalEditArea').classList.remove('hidden');
    }

    function tutupModalArea() {
        document.getElementById('modalEditArea').classList.add('hidden');
    }
</script>