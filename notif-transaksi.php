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

            function runSequence() {
                // Compressor + master gain: supaya volume bisa dipompa keras tanpa suara pecah/clip
                var compressor = ctx.createDynamicsCompressor();
                compressor.threshold.value = -12;
                compressor.knee.value = 20;
                compressor.ratio.value = 8;
                compressor.attack.value = 0.002;
                compressor.release.value = 0.15;

                var masterGain = ctx.createGain();
                masterGain.gain.value = 1.0; // volume utama, dipompa maksimal lewat compressor
                masterGain.connect(compressor);
                compressor.connect(ctx.destination);

                // Satu nada = 2 osilator (fundamental + harmonik) supaya berkarakter, bukan beep polos
                function chime(freq, startTime, duration, volume, type1, type2) {
                    var osc1 = ctx.createOscillator();
                    var osc2 = ctx.createOscillator();
                    var gain = ctx.createGain();

                    osc1.type = type1 || 'triangle';
                    osc2.type = type2 || 'sine';
                    osc1.frequency.value = freq;
                    osc2.frequency.value = freq * 2.01; // sedikit detune biar berkilau (shimmer)

                    gain.gain.setValueAtTime(0, startTime);
                    gain.gain.linearRampToValueAtTime(volume, startTime + 0.015);
                    gain.gain.exponentialRampToValueAtTime(0.001, startTime + duration);

                    osc1.connect(gain);
                    osc2.connect(gain);
                    gain.connect(masterGain);

                    osc1.start(startTime);
                    osc2.start(startTime);
                    osc1.stop(startTime + duration);
                    osc2.stop(startTime + duration);
                }

                // "Thump" bass di awal biar berasa nendang seperti cha-ching kasir
                function thump(startTime) {
                    var osc = ctx.createOscillator();
                    var gain = ctx.createGain();
                    osc.type = 'square';
                    osc.frequency.setValueAtTime(180, startTime);
                    osc.frequency.exponentialRampToValueAtTime(60, startTime + 0.12);
                    gain.gain.setValueAtTime(0.9, startTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, startTime + 0.15);
                    osc.connect(gain);
                    gain.connect(masterGain);
                    osc.start(startTime);
                    osc.stop(startTime + 0.15);
                }

                var now = ctx.currentTime;

                thump(now);

                // Arpeggio naik yang khas & mudah dikenali: C5 - E5 - G5 - C6, ditutup nada shimmer tinggi
                chime(523.25, now + 0.02, 0.18, 0.9);   // C5
                chime(659.25, now + 0.10, 0.18, 0.9);   // E5
                chime(783.99, now + 0.18, 0.20, 0.95);  // G5
                chime(1046.5, now + 0.27, 0.35, 1.0, 'triangle', 'square'); // C6 -> puncak, paling keras & tajam
                chime(1567.98, now + 0.30, 0.40, 0.5, 'sine', 'sine');      // G6 shimmer lembut menutup
            }

            if (ctx.state === 'suspended') {
                // Browser memblokir autoplay audio sebelum ada interaksi user.
                // Coba resume dulu; kalau masih gagal, baru bunyi begitu user klik/sentuh apa saja di halaman.
                ctx.resume().then(function () {
                    if (ctx.state === 'running') runSequence();
                }).catch(function () {});

                var unlock = function () {
                    ctx.resume().then(runSequence);
                    document.removeEventListener('click', unlock);
                    document.removeEventListener('touchstart', unlock);
                    document.removeEventListener('keydown', unlock);
                };
                document.addEventListener('click', unlock, { once: true });
                document.addEventListener('touchstart', unlock, { once: true });
                document.addEventListener('keydown', unlock, { once: true });
            } else {
                runSequence();
            }
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