<?php
// notif-login.php
// Komponen notifikasi singkat yang muncul sekali setelah user berhasil login.
// Cukup include file ini di bagian atas <body> pada halaman tujuan (dashboard/admin/owner/petugas).
// Notifikasi akan otomatis hilang setelah beberapa detik dan hanya tampil sekali per sesi login.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Tampilkan notifikasi hanya jika user baru saja login (flag belum pernah ditampilkan)
$tampilkan_notif = isset($_SESSION['status_login']) && $_SESSION['status_login'] === true && empty($_SESSION['notif_login_shown']);

if ($tampilkan_notif) {
    $nama_user = htmlspecialchars($_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'Pengguna');
    $role_user = htmlspecialchars($_SESSION['role'] ?? '');
    $_SESSION['notif_login_shown'] = true; // Tandai sudah ditampilkan agar tidak muncul lagi saat reload
?>
<div id="notif-login-box" class="fixed top-5 right-5 z-[100] max-w-sm w-full">
    <div class="bg-emerald-500/10 border border-emerald-500/30 backdrop-blur-sm text-emerald-300 px-4 py-3 rounded-2xl shadow-2xl flex items-start space-x-3 animate-[fadeInDown_0.4s_ease-out]">
        <span class="material-symbols-outlined text-emerald-400 text-[22px] mt-0.5">check_circle</span>
        <div class="flex-1">
            <p class="text-sm font-semibold text-white">Login Berhasil</p>
            <p class="text-xs text-emerald-300/90 mt-0.5">
                Selamat datang, <span class="font-bold"><?php echo $nama_user; ?></span><?php echo $role_user ? ' (' . ucfirst($role_user) . ')' : ''; ?>.
            </p>
        </div>
        <button onclick="document.getElementById('notif-login-box').remove()" class="text-emerald-400/70 hover:text-white transition">
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
    setTimeout(function () {
        var box = document.getElementById('notif-login-box');
        if (box) {
            box.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            box.style.opacity = '0';
            box.style.transform = 'translateY(-12px)';
            setTimeout(function () { box.remove(); }, 400);
        }
    }, 4000);
</script>
<?php
}
?>