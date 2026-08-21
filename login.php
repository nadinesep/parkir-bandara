<?php
include 'koneksi.php';

$error = "";

// ==========================================================
// NAMA SESSION BERBEDA PER ROLE
// Supaya login sebagai petugas & customer (atau admin/owner)
// bisa aktif BERSAMAAN di browser yang sama tanpa sesi yang
// satu menimpa sesi yang lain.
// ==========================================================
function nama_session_untuk_role($role) {
    $peta = [
        'admin'   => 'admin_sess',
        'petugas' => 'petugas_sess',
        'owner'   => 'owner_sess',
        'user'    => 'user_sess',
    ];
    return $peta[$role] ?? 'PHPSESSID';
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);

    // Cek data ke database tabel users (mendukung role admin, petugas, owner, dan user)
    $query = "SELECT * FROM users WHERE username = '$username' AND password = '$password'";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);

        // Set nama session SESUAI ROLE sebelum session_start() dipanggil,
        // supaya cookie session-nya terpisah dari role lain.
        session_name(nama_session_untuk_role($row['role']));
        session_start();

        $_SESSION['status_login'] = true;
        $_SESSION['username'] = $row['username'];
        $_SESSION['nama_lengkap'] = $row['nama_lengkap'];
        $_SESSION['role'] = $row['role'];
        $_SESSION['notif_login_shown'] = false; // Reset agar notifikasi login tampil kembali di halaman tujuan

        // Catat aktivitas login berhasil ke log_aktivitas
        $nama_user_log = !empty($row['nama_lengkap']) ? $row['nama_lengkap'] : $row['username'];
        mysqli_query($conn, "INSERT INTO log_aktivitas (waktu, user, role, aktivitas, ip_address, level) VALUES (
            NOW(),
            '".mysqli_real_escape_string($conn, $nama_user_log)."',
            '".mysqli_real_escape_string($conn, ucfirst($row['role']))."',
            'Berhasil melakukan login ke sistem AeroPark Manager',
            '".mysqli_real_escape_string($conn, $_SERVER['REMOTE_ADDR'])."',
            'Success'
        )");

        // Pengalihan halaman otomatis berdasarkan hak akses (role)
        if ($row['role'] == 'admin') {
            header("Location: admin.php");
        } elseif ($row['role'] == 'petugas') {
            header("Location: petugas.php");
        } elseif ($row['role'] == 'owner') {
            header("Location: owner.php");
        } elseif ($row['role'] == 'user') {
            header("Location: user.php");
        } else {
            // Jaga-jaga jika role di database tidak dikenali
            session_unset();
            session_destroy();
            $error = "Role akun tidak dikenali. Hubungi administrator.";
        }

        if (empty($error)) {
            exit();
        }
    } else {
        $error = "Username atau Password salah!";

        // Catat percobaan login gagal
        mysqli_query($conn, "INSERT INTO log_aktivitas (waktu, user, role, aktivitas, ip_address, level) VALUES (
            NOW(),
            'Unknown User',
            'Guest',
            'Gagal percobaan login dengan username invalid (".mysqli_real_escape_string($conn, $username).")',
            '".mysqli_real_escape_string($conn, $_SERVER['REMOTE_ADDR'])."',
            'Danger'
        )");
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="id">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Login - AeroPark Manager</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "surface-container-highest": "#e2e2e2",
                        "on-secondary-fixed-variant": "#3e4853",
                        "inverse-primary": "#b0c6ff",
                        "on-tertiary": "#ffffff",
                        "surface": "#f9f9f9",
                        "secondary-fixed-dim": "#bdc7d5",
                        "surface-container-high": "#e8e8e8",
                        "on-error-container": "#93000a",
                        "secondary": "#555f6b",
                        "on-secondary": "#ffffff",
                        "surface-tint": "#0958ca",
                        "surface-container-low": "#f3f3f4",
                        "primary": "#00429d",
                        "tertiary-fixed": "#d3e4fe",
                        "on-surface": "#1a1c1c",
                        "surface-container-lowest": "#ffffff",
                        "on-tertiary-container": "#cadaf5",
                        "on-surface-variant": "#424653",
                        "inverse-on-surface": "#f0f1f1",
                        "primary-fixed-dim": "#b0c6ff",
                        "primary-fixed": "#d9e2ff",
                        "surface-bright": "#f9f9f9",
                        "outline": "#737785",
                        "on-primary-fixed": "#001945",
                        "outline-variant": "#c3c6d6",
                        "on-primary": "#ffffff",
                        "surface-container": "#eeeeee",
                        "on-secondary-container": "#5b6571",
                        "on-secondary-fixed": "#131c26",
                        "on-background": "#1a1c1c",
                        "on-primary-container": "#ccd8ff",
                        "error": "#ba1a1a",
                        "on-primary-fixed-variant": "#00419d",
                        "surface-variant": "#e2e2e2",
                        "secondary-fixed": "#d9e3f1",
                        "surface-dim": "#dadada",
                        "secondary-container": "#d9e3f1",
                        "on-error": "#ffffff",
                        "tertiary": "#38485d",
                        "error-container": "#ffdad6",
                        "on-tertiary-fixed-variant": "#38485d",
                        "tertiary-container": "#506076",
                        "background": "#f9f9f9",
                        "on-tertiary-fixed": "#0b1c30",
                        "inverse-surface": "#2f3131",
                        "tertiary-fixed-dim": "#b7c8e1",
                        "primary-container": "#0a58ca"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                    "spacing": {
                        "container-margin": "24px",
                        "stack-gap": "12px",
                        "base": "4px",
                        "gutter": "16px",
                        "card-padding": "20px"
                    },
                    "fontFamily": {
                        "body-lg": ["Inter"],
                        "title-md": ["Inter"],
                        "headline-lg": ["Inter"],
                        "body-md": ["Inter"],
                        "label-sm": ["Inter"],
                        "headline-lg-mobile": ["Inter"],
                        "display-lg": ["Inter"]
                    },
                    "fontSize": {
                        "body-lg": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
                        "title-md": ["18px", {"lineHeight": "24px", "fontWeight": "600"}],
                        "headline-lg": ["32px", {"lineHeight": "40px", "fontWeight": "600"}],
                        "body-md": ["14px", {"lineHeight": "20px", "fontWeight": "400"}],
                        "label-sm": ["12px", {"lineHeight": "16px", "letterSpacing": "0.05em", "fontWeight": "500"}],
                        "headline-lg-mobile": ["24px", {"lineHeight": "32px", "fontWeight": "600"}],
                        "display-lg": ["40px", {"lineHeight": "48px", "letterSpacing": "-0.02em", "fontWeight": "700"}]
                    }
                },
            },
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #070b18;
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .glass-effect {
            background: rgba(8, 12, 28, 0.88);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(220, 38, 38, 0.55);
        }

        /* Ken Burns: foto latar zoom perlahan supaya nggak terasa statis */
        @keyframes kenburns {
            0%   { transform: scale(1) translate(0, 0); }
            100% { transform: scale(1.18) translate(-1.5%, -1%); }
        }
        .bg-kenburns {
            animation: kenburns 14s ease-in-out infinite alternate;
        }

        /* Bintang berkelap-kelip, lebih kontras & lebih cepat */
        @keyframes twinkle {
            0%, 100% { opacity: 0.15; }
            50% { opacity: 1; }
        }
        .stars-twinkle {
            animation: twinkle 2.2s ease-in-out infinite;
        }

        /* Orb cahaya kota melayang, jarak gerak lebih jauh & lebih cepat */
        @keyframes float-slow {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(60px, -70px) scale(1.25); }
        }
        @keyframes float-slow-reverse {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(-70px, 60px) scale(1.3); }
        }
        .orb-float-a { animation: float-slow 6s ease-in-out infinite; }
        .orb-float-b { animation: float-slow-reverse 7s ease-in-out infinite; }
        .orb-float-c { animation: float-slow 8s ease-in-out infinite; }
        .orb-float-d { animation: float-slow-reverse 6.5s ease-in-out infinite; }

        /* Spider-Man theme: red/blue comic glow */
        .spider-card {
            position: relative;
            overflow: hidden;
            box-shadow: 0 18px 55px rgba(0,0,0,.55), 0 0 28px rgba(220,38,38,.18);
        }
        .spider-card::before {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
            opacity: .18;
            background:
                repeating-radial-gradient(circle at 50% 42%, transparent 0 34px, rgba(255,255,255,.9) 35px 36px, transparent 37px 72px),
                linear-gradient(30deg, transparent 49.5%, rgba(255,255,255,.7) 50%, transparent 50.5%),
                linear-gradient(150deg, transparent 49.5%, rgba(255,255,255,.7) 50%, transparent 50.5%);
            mask-image: linear-gradient(to bottom, black, transparent 92%);
        }
        .spider-badge {
            position: relative;
            background: linear-gradient(145deg, #ef1d2f, #a80718);
            border: 2px solid rgba(255,255,255,.85);
            box-shadow: 0 0 0 4px rgba(20,52,140,.75), 0 0 30px rgba(239,29,47,.65);
        }
        .spider-badge::before {
            display: none;
        }
        .spider-badge .material-symbols-outlined {
            opacity: 1;
            color: #ffffff;
            font-size: 40px;
        }
        .spider-title { color: #fff; text-shadow: 0 2px 16px rgba(239,29,47,.55); }
        .spider-subtitle { color: rgba(255,255,255,.78); }
        .spider-label { color: #334155; }
        .spider-input {
            background: rgba(248,250,252,.97) !important;
            border-color: #64748b !important;
        }
        .spider-input:focus {
            border-color: #dc1f2f !important;
            box-shadow: 0 0 0 3px rgba(220,31,47,.16) !important;
        }
        .spider-button {
            background: linear-gradient(90deg, #d7192a, #ef233c 55%, #1646a0);
            box-shadow: 0 8px 20px rgba(0,0,0,.25), 0 0 18px rgba(220,31,47,.25);
        }
        .spider-button:hover { filter: brightness(1.08); }
        .spider-link { color: #1646a0 !important; }
        .spider-divider { border-color: rgba(100,116,139,.35); }

        /* Glow berdenyut di logo */
        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 0 0px 0px rgba(168,85,247,0.5), 0 4px 10px rgba(0,0,0,0.3); }
            50% { box-shadow: 0 0 35px 10px rgba(168,85,247,0.6), 0 4px 10px rgba(0,0,0,0.3); }
        }
        .logo-pulse {
            animation: pulse-glow 2.5s ease-in-out infinite;
        }

        /* Lampu landasan berjalan/berkedip bergiliran di bagian bawah layar */
        @keyframes runway-blink {
            0%, 100% { opacity: 0.2; }
            50% { opacity: 1; }
        }
        .runway-light { animation: runway-blink 1.4s ease-in-out infinite; }

        /* Fade + slide + scale untuk elemen saat halaman dibuka */
        @keyframes fade-in-up {
            0% { opacity: 0; transform: translateY(24px) scale(0.95); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }
        .fade-in-up {
            animation: fade-in-up 0.8s cubic-bezier(0.16, 1, 0.3, 1) both;
        }
        .delay-1 { animation-delay: 0.15s; }
        .delay-2 { animation-delay: 0.35s; }
    </style>
</head>
<body class="bg-background min-h-screen flex flex-col items-center justify-center p-4">
    <!-- Background Illustration Wrapper: bandara malam hari -->
    <div class="fixed inset-0 z-0 overflow-hidden bg-[#050516]">
        <!-- Foto pesawat landing malam hari dengan lampu runway warna-warni (dipilih user) -->
        <div class="absolute inset-0 bg-cover bg-center bg-kenburns" style="background-image: url('https://wallpaperaccess.com/full/2248344.jpg'); filter: brightness(0.72) saturate(1.25) contrast(1.08);"></div>

        <!-- Lapisan gelap malam: navy → ungu tua, tipis supaya foto pesawat & lampu runway tetap terlihat jelas -->
        <div class="absolute inset-0 bg-gradient-to-br from-[#06133b]/42 via-[#3b0712]/20 to-black/48"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-black/55 via-transparent to-black/10"></div>

        <!-- Taburan bintang, berkelap-kelip -->
        <div class="absolute inset-0 opacity-60 stars-twinkle" style="background-image: radial-gradient(1.5px 1.5px at 20% 15%, white, transparent), radial-gradient(1px 1px at 60% 10%, white, transparent), radial-gradient(1px 1px at 80% 25%, white, transparent), radial-gradient(1.5px 1.5px at 35% 8%, white, transparent), radial-gradient(1px 1px at 90% 18%, white, transparent), radial-gradient(1px 1px at 50% 20%, white, transparent), radial-gradient(1.5px 1.5px at 10% 30%, white, transparent), radial-gradient(1px 1px at 70% 5%, white, transparent);"></div>

        <!-- City glow: cahaya kota malam bernuansa ungu/magenta/pink, melayang pelan -->
        <div class="absolute -bottom-32 left-1/4 w-[500px] h-[400px] bg-red-600/18 rounded-full blur-[130px] orb-float-a"></div>
        <div class="absolute -bottom-40 right-1/4 w-[600px] h-[350px] bg-blue-600/15 rounded-full blur-[150px] orb-float-b"></div>
        <div class="absolute -top-20 left-0 w-[350px] h-[350px] bg-blue-500/10 rounded-full blur-[130px] orb-float-c"></div>
        <div class="absolute -top-10 right-0 w-[300px] h-[300px] bg-red-500/10 rounded-full blur-[130px] orb-float-d"></div>

        <!-- Lampu landasan berkedip bergantian di sepanjang bawah layar -->
        <div class="absolute bottom-6 left-0 w-full flex justify-around px-8 pointer-events-none">
            <span class="w-2 h-2 rounded-full bg-cyan-300 runway-light" style="animation-delay:0s"></span>
            <span class="w-2 h-2 rounded-full bg-amber-300 runway-light" style="animation-delay:0.2s"></span>
            <span class="w-2 h-2 rounded-full bg-cyan-300 runway-light" style="animation-delay:0.4s"></span>
            <span class="w-2 h-2 rounded-full bg-amber-300 runway-light" style="animation-delay:0.6s"></span>
            <span class="w-2 h-2 rounded-full bg-cyan-300 runway-light" style="animation-delay:0.8s"></span>
            <span class="w-2 h-2 rounded-full bg-amber-300 runway-light" style="animation-delay:1s"></span>
            <span class="w-2 h-2 rounded-full bg-cyan-300 runway-light" style="animation-delay:1.2s"></span>
        </div>
    </div>

    <!-- Login Container -->
    <main class="relative z-10 w-full max-w-[440px]">
        <a href="index.php" class="flex flex-col items-center mb-8 group fade-in-up">
            <div class="spider-badge p-3 rounded-xl shadow-lg mb-4 logo-pulse group-hover:scale-105 transition-all">
                <span class="material-symbols-outlined text-white text-[40px]" style="font-variation-settings: 'FILL' 1;">flight</span>
            </div>
            <h1 class="font-headline-lg text-headline-lg spider-title tracking-tight group-hover:underline">AeroPark Manager</h1>
            <p class="font-body-md text-body-md spider-subtitle mt-1 drop-shadow-[0_1px_8px_rgba(0,0,0,0.6)]">Sistem Manajemen Logistik Bandara</p>
        </a>

        <div class="glass-effect spider-card shadow-2xl rounded-xl p-card-padding border fade-in-up delay-1">
            <div class="mb-8">
                <h2 class="font-title-md text-title-md text-white">Masuk ke Sistem</h2>
                <p class="font-label-sm text-label-sm text-slate-200">Silakan masukkan kredensial Anda untuk melanjutkan</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="bg-red-950/80 border border-red-400/40 text-red-100 px-4 py-3 rounded-lg mb-4 text-sm flex items-center space-x-2">
                    <span class="material-symbols-outlined text-[20px]">error</span>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-stack-gap">
                <div class="space-y-1.5">
                    <label class="font-label-sm text-label-sm spider-label ml-1" for="username">Username</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-outline group-focus-within:text-primary transition-colors">
                            <span class="material-symbols-outlined text-[20px]">person</span>
                        </div>
                        <input class="w-full spider-input rounded-lg py-3 pl-10 pr-4 text-slate-900 font-body-md focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all placeholder:text-outline-variant" id="username" name="username" placeholder="Masukkan username" required type="text"/>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <div class="flex justify-between items-center ml-1">
                        <label class="font-label-sm text-label-sm spider-label" for="password">Kata Sandi</label>
                        <a class="text-[12px] font-medium spider-link hover:underline" href="#">Lupa Sandi?</a>
                    </div>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-outline group-focus-within:text-primary transition-colors">
                            <span class="material-symbols-outlined text-[20px]">lock</span>
                        </div>
                        <input class="w-full spider-input rounded-lg py-3 pl-10 pr-12 text-slate-900 font-body-md focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all placeholder:text-outline-variant" id="password" name="password" placeholder="Masukkan kata sandi" required type="password"/>
                        <button class="absolute inset-y-0 right-0 pr-3 flex items-center text-outline hover:text-on-surface-variant transition-colors" onclick="togglePassword()" type="button">
                            <span class="material-symbols-outlined text-[20px]" id="password-toggle-icon">visibility</span>
                        </button>
                    </div>
                </div>

                <div class="flex items-center space-x-2 py-2">
                    <input class="w-4 h-4 text-primary border-outline-variant rounded focus:ring-primary transition-colors cursor-pointer" id="remember" name="remember" type="checkbox"/>
                    <label class="font-body-md text-body-md text-slate-200 cursor-pointer select-none" for="remember">Ingat Saya</label>
                </div>

                <button class="w-full spider-button text-white font-title-md py-3.5 rounded-lg shadow-md hover:shadow-lg active:scale-[0.98] transition-all flex items-center justify-center space-x-2 mt-4" type="submit">
                    <span>Masuk Sekarang</span>
                    <span class="material-symbols-outlined text-[20px]">login</span>
                </button>
            </form>

            <div class="mt-6 text-center">
                <p class="font-body-md text-body-md text-slate-300">
                    Belum punya akun? <a class="spider-link font-bold hover:underline" href="register.php">Daftar di sini</a>
                </p>
            </div>

            <div class="mt-6 pt-6 spider-divider border-t text-center">
                <p class="font-label-sm text-label-sm text-slate-300">
                    Butuh bantuan akses? <a class="spider-link font-bold hover:underline" href="#">Hubungi IT Support</a>
                </p>
            </div>
        </div>
    </main>

    <!-- Footer full-width: sengaja diletakkan di luar <main> (yang dibatasi max-w-[440px])
         supaya lebar footer mengikuti lebar layar penuh, bukan lebar card login -->
    <footer class="relative z-10 w-full mt-8 fade-in-up delay-2">
        <?php include 'footer.php'; ?>
    </footer>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('password-toggle-icon');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.textContent = 'visibility_off';
            } else {
                passwordInput.type = 'password';
                toggleIcon.textContent = 'visibility';
            }
        }
        document.addEventListener('mousemove', (e) => {
            const moveX = (e.clientX - window.innerWidth / 2) * 0.005;
            const moveY = (e.clientY - window.innerHeight / 2) * 0.005;
            document.querySelector('.fixed.inset-0.z-0').style.transform = `translate(${moveX}px, ${moveY}px) scale(1.02)`;
        });
    </script>
</body>
</html>