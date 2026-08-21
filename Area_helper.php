<?php
// ==========================================================================
// HELPER BERSAMA: area_helper.php
// Dipakai oleh index.php (reservasi online) dan petugas.php (input manual
// & check-in) supaya id_area SELALU terisi otomatis sesuai jenis kendaraan
// & kategori yang dipilih, dan area_parkir.terisi bisa ikut ter-update
// secara real-time tanpa petugas/pengguna perlu memilih area secara manual.
// ==========================================================================

if (!function_exists('cari_id_area_tersedia')) {
    /**
     * Cari id area_parkir yang paling cocok untuk jenis kendaraan & kategori
     * tertentu, dan masih memiliki slot kosong (terisi < total_slot).
     * Kalau tidak ada area spesifik yang cocok/tersedia, coba area aktif
     * mana pun yang masih longgar sebagai fallback.
     *
     * @return int id area_parkir, atau 0 kalau benar-benar tidak ada slot kosong.
     */
    function cari_id_area_tersedia($conn, $jenis, $kategori) {
        $jenis    = strtolower(trim((string) $jenis));
        $kategori = strtolower(trim((string) $kategori));

        // Tentukan pola nama area yang relevan berdasarkan kategori & jenis.
        // Kategori VIP/VVIP diprioritaskan dulu (lokasi area VIP terpisah
        // dari area reguler apa pun jenis kendaraannya).
        if ($kategori === 'vip' || $kategori === 'vvip') {
            $like = "(nama_area LIKE '%VIP%' OR nama_area LIKE '%Eksekutif%' OR nama_area LIKE '%Valet%')";
        } elseif ($jenis === 'motor') {
            $like = "(nama_area LIKE '%Motor%')";
        } elseif ($jenis === 'bus' || $jenis === 'truk' || $jenis === 'bis') {
            $like = "(nama_area LIKE '%Bus%' OR nama_area LIKE '%Truk%')";
        } else {
            // Default: mobil / reguler
            $like = "(nama_area LIKE '%Mobil%')";
        }

        // Pilih area yang cocok dengan slot kosong TERBANYAK dulu, supaya
        // beban terisi tersebar merata antar area sejenis (mis. Terminal 1A
        // & Terminal 2 sama-sama "Mobil").
        $q = mysqli_query($conn, "SELECT id FROM area_parkir 
                                   WHERE $like AND status = 'Aktif' AND terisi < total_slot 
                                   ORDER BY (total_slot - terisi) DESC LIMIT 1");
        if ($q && mysqli_num_rows($q) > 0) {
            $d = mysqli_fetch_assoc($q);
            return intval($d['id']);
        }

        // Fallback: area spesifik penuh/tidak ditemukan -> pakai area aktif
        // mana pun yang masih ada slot kosong, supaya transaksi tetap
        // tercatat & terisi tetap ter-update walau kategorinya tidak pas.
        $q2 = mysqli_query($conn, "SELECT id FROM area_parkir 
                                    WHERE status = 'Aktif' AND terisi < total_slot 
                                    ORDER BY (total_slot - terisi) DESC LIMIT 1");
        if ($q2 && mysqli_num_rows($q2) > 0) {
            $d2 = mysqli_fetch_assoc($q2);
            return intval($d2['id']);
        }

        // Semua area penuh atau belum ada data area sama sekali.
        return 0;
    }
}