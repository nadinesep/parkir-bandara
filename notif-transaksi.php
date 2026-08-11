<?php
// notif-transaksi.php
// Menampilkan notifikasi + suara ketika petugas berhasil menginput data parkir baru.
// Include file ini di bagian atas <body> pada halaman tujuan setelah input berhasil (misalnya cetak_struk.php).

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['notif_transaksi'])) {
    $trx   = $_SESSION['notif_transaksi'];
    $nama  = htmlspecialchars($trx['nama'] ?? '');
    $plat  = htmlspecialchars($trx['plat'] ?? '');
    $total = number_format(intval($trx['total'] ?? 0), 0, ',', '.');

    unset($_SESSION['notif_transaksi']); // Tampilkan sekali saja
?>
<div id="notif-transaksi-box" class="fixed top-5 right-5 z-[100] max-w-sm w-full">
    <div class="bg-blue-500/10 border border-blue-500/30 backdrop-blur-sm text-blue-200 px-4 py-3 rounded-2xl shadow-2xl flex items-start space-x-3 animate-[fadeInDown_0.4s_ease-out]">
        <span class="material-symbols-outlined text-blue-400 text-[22px] mt-0.5">directions_car</span>
        <div class="flex-1">
            <p class="text-sm font-semibold text-white">Transaksi Parkir Berhasil Disimpan</p>
            <p class="text-xs text-blue-200/90 mt-0.5">
                <span class="font-bold"><?php echo $nama; ?></span> &middot; <span class="font-mono"><?php echo $plat; ?></span>
                <br>Total biaya: <span class="font-bold text-emerald-400">Rp <?php echo $total; ?></span>
            </p>
        </div>
        <button onclick="document.getElementById('notif-transaksi-box').remove()" class="text-blue-300/70 hover:text-white transition">
            <span class="material-symbols-outlined text-[18px]">close</span>
        </button>
    </div>
</div>

<style>
@keyframes fadeInDown {
    from { opacity: 0; transform: translateY(-12px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>

<script>
    // Suara notifikasi (dibuat langsung via Web Audio API, tanpa file eksternal)
    (function playNotifSound() {
        try {
            var ctx = new (window.AudioContext || window.webkitAudioContext)();

            function beep(freq, startTime, duration, volume) {
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = freq;
                gain.gain.setValueAtTime(0, startTime);
                gain.gain.linearRampToValueAtTime(volume, startTime + 0.01);
                gain.gain.exponentialRampToValueAtTime(0.001, startTime + duration);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(startTime);
                osc.stop(startTime + duration);
            }

            var now = ctx.currentTime;
            beep(660, now, 0.10, 0.15);            // nada 1
            beep(990, now + 0.11, 0.16, 0.15);      // nada 2 -> efek "ting" khas transaksi
        } catch (e) {
            console.warn('Notifikasi suara tidak dapat diputar:', e);
        }
    })();

    setTimeout(function () {
        var box = document.getElementById('notif-transaksi-box');
        if (box) {
            box.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            box.style.opacity = '0';
            box.style.transform = 'translateY(-12px)';
            setTimeout(function () { box.remove(); }, 400);
        }
    }, 5000);
</script>
<?php
}
?>