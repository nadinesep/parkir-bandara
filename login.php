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
            background-color: #f9f9f9;
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body class="bg-background min-h-screen flex items-center justify-center p-4">
    <!-- Background Illustration Wrapper -->
    <div class="fixed inset-0 z-0 overflow-hidden">
        <div class="absolute inset-0 bg-cover bg-center opacity-70" style="background-image: url('https://images.unsplash.com/photo-1569154941061-e231b4725ef1?auto=format&fit=crop&w=1920&q=80');"></div>
        <div class="absolute inset-0 bg-gradient-to-br from-orange-900/50 via-amber-700/30 to-indigo-950/50"></div>
    </div>

    <!-- Login Container -->
    <main class="relative z-10 w-full max-w-[440px] animate-in fade-in zoom-in duration-500">
        <a href="index.php" class="flex flex-col items-center mb-8 group">
            <div class="bg-primary p-3 rounded-xl shadow-lg mb-4 group-hover:shadow-xl group-hover:scale-105 transition-all">
                <span class="material-symbols-outlined text-on-primary text-[40px]" style="font-variation-settings: 'FILL' 1;">local_parking</span>
            </div>
            <h1 class="font-headline-lg text-headline-lg text-primary tracking-tight group-hover:underline">AeroPark Manager</h1>
            <p class="font-body-md text-body-md text-on-surface-variant mt-1">Sistem Manajemen Logistik Bandara</p>
        </a>

        <div class="glass-effect shadow-2xl rounded-xl p-card-padding border border-outline-variant/30">
            <div class="mb-8">
                <h2 class="font-title-md text-title-md text-on-surface">Masuk ke Sistem</h2>
                <p class="font-label-sm text-label-sm text-on-surface-variant">Silakan masukkan kredensial Anda untuk melanjutkan</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="bg-error-container border border-error/30 text-on-error-container px-4 py-3 rounded-lg mb-4 text-sm flex items-center space-x-2">
                    <span class="material-symbols-outlined text-[20px]">error</span>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-stack-gap">
                <div class="space-y-1.5">
                    <label class="font-label-sm text-label-sm text-on-surface-variant ml-1" for="username">Username</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-outline group-focus-within:text-primary transition-colors">
                            <span class="material-symbols-outlined text-[20px]">person</span>
                        </div>
                        <input class="w-full bg-surface-container-lowest border border-outline-variant rounded-lg py-3 pl-10 pr-4 text-on-surface font-body-md focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all placeholder:text-outline-variant" id="username" name="username" placeholder="Masukkan username" required type="text"/>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <div class="flex justify-between items-center ml-1">
                        <label class="font-label-sm text-label-sm text-on-surface-variant" for="password">Kata Sandi</label>
                        <a class="text-[12px] font-medium text-primary hover:underline" href="#">Lupa Sandi?</a>
                    </div>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-outline group-focus-within:text-primary transition-colors">
                            <span class="material-symbols-outlined text-[20px]">lock</span>
                        </div>
                        <input class="w-full bg-surface-container-lowest border border-outline-variant rounded-lg py-3 pl-10 pr-12 text-on-surface font-body-md focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all placeholder:text-outline-variant" id="password" name="password" placeholder="Masukkan kata sandi" required type="password"/>
                        <button class="absolute inset-y-0 right-0 pr-3 flex items-center text-outline hover:text-on-surface-variant transition-colors" onclick="togglePassword()" type="button">
                            <span class="material-symbols-outlined text-[20px]" id="password-toggle-icon">visibility</span>
                        </button>
                    </div>
                </div>

                <div class="flex items-center space-x-2 py-2">
                    <input class="w-4 h-4 text-primary border-outline-variant rounded focus:ring-primary transition-colors cursor-pointer" id="remember" name="remember" type="checkbox"/>
                    <label class="font-body-md text-body-md text-on-surface-variant cursor-pointer select-none" for="remember">Ingat Saya</label>
                </div>

                <button class="w-full bg-primary hover:bg-primary-container text-on-primary font-title-md py-3.5 rounded-lg shadow-md hover:shadow-lg active:scale-[0.98] transition-all flex items-center justify-center space-x-2 mt-4" type="submit">
                    <span>Masuk Sekarang</span>
                    <span class="material-symbols-outlined text-[20px]">login</span>
                </button>
            </form>

            <div class="mt-6 text-center">
                <p class="font-body-md text-body-md text-on-surface-variant">
                    Belum punya akun? <a class="text-primary font-bold hover:underline" href="register.php">Daftar di sini</a>
                </p>
            </div>

            <div class="mt-6 pt-6 border-t border-outline-variant/30 text-center">
                <p class="font-label-sm text-label-sm text-on-surface-variant">
                    Butuh bantuan akses? <a class="text-primary font-bold hover:underline" href="#">Hubungi IT Support</a>
                </p>
            </div>
        </div>

        <footer class="mt-8 text-center">
            <p class="font-label-sm text-label-sm text-on-surface-variant/60">
                &copy; <?php echo date('Y'); ?> AeroParking System. Hak Cipta Dilindungi.
            </p>
        </footer>
    </main>

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