<?php
// ==================================================================
// SUMBER TARIF TUNGGAL — dipakai bersama oleh user.php & petugas.php
// supaya seluruh sistem (reservasi online maupun walk-in) selalu
// memakai tarif yang SAMA PERSIS dengan yang diatur Admin di menu
// "Tarif Parkir" (tabel tarif_parkir).
// ==================================================================

function peta_keyword_kendaraan($kendaraan)
{
    $peta = [
        'motor' => 'Motor',
        'mobil' => 'Mobil',
        'bis'   => 'Bus',   // "Bis" di form user disamakan dengan "Bus" di admin
        'bus'   => 'Bus',
        'truk'  => 'Truk',
    ];
    return $peta[$kendaraan] ?? 'Mobil';
}

function ambil_tarif($conn, $kendaraan, $kategori)
{
    $keyword     = peta_keyword_kendaraan($kendaraan);
    $keyword_esc = mysqli_real_escape_string($conn, $keyword);

    $q = mysqli_query($conn, "SELECT * FROM tarif_parkir WHERE status='Aktif' AND jenis_kendaraan LIKE '%$keyword_esc%' ORDER BY id ASC LIMIT 1");

    if ($q && mysqli_num_rows($q) > 0) {
        $t = mysqli_fetch_assoc($q);
        $tarif_per_jam      = intval($t['tarif_per_jam']);
        $tarif_jam_tambahan = intval($t['tarif_jam_tambahan']);
        $maksimal_harian    = intval($t['maksimal_harian']);
    } else {
        // Tarif cadangan (fallback) jika Admin belum mengatur tarif untuk jenis ini
        switch ($kendaraan) {
            case 'motor':
                $tarif_per_jam = 2000; $tarif_jam_tambahan = 2000; break;
            case 'bis':
            case 'bus':
                $tarif_per_jam = 8000; $tarif_jam_tambahan = 8000; break;
            case 'truk':
                $tarif_per_jam = 10000; $tarif_jam_tambahan = 10000; break;
            case 'mobil':
            default:
                $tarif_per_jam = 5000; $tarif_jam_tambahan = 5000; break;
        }
        $maksimal_harian = 0; // 0 = tidak ada batas
    }

    if ($kategori == 'vip') {
        $tarif_per_jam      += $tarif_per_jam * 0.5;
        $tarif_jam_tambahan += $tarif_jam_tambahan * 0.5;
    } elseif ($kategori == 'vvip') {
        $tarif_per_jam      += $tarif_per_jam * 1.0;
        $tarif_jam_tambahan += $tarif_jam_tambahan * 1.0;
    }

    return [
        'tarif_per_jam'      => (int) round($tarif_per_jam),
        'tarif_jam_tambahan' => (int) round($tarif_jam_tambahan),
        'maksimal_harian'    => $maksimal_harian,
    ];
}

function hitung_biaya($conn, $kendaraan, $kategori, $durasi)
{
    $t = ambil_tarif($conn, $kendaraan, $kategori);
    $durasi = max(1, intval($durasi));

    $biaya = $t['tarif_per_jam'] + ($t['tarif_jam_tambahan'] * ($durasi - 1));

    if ($t['maksimal_harian'] > 0 && $biaya > $t['maksimal_harian']) {
        $biaya = $t['maksimal_harian'];
    }

    return $biaya;
}