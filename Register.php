<?php
session_start();
include 'koneksi.php';

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nama_lengkap = mysqli_real_escape_string($conn, trim($_POST['nama_lengkap']));
    $username     = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password     = mysqli_real_escape_string($conn, $_POST['password']);
    $konfirmasi   = mysqli_real_escape_string($conn, $_POST['konfirmasi_password']);

    if ($nama_lengkap === "" || $username === "" || $password === "") {
        $error = "Semua kolom wajib diisi!";
    } elseif ($password !== $konfirmasi) {
        $error = "Konfirmasi kata sandi tidak cocok!";
    } elseif (strlen($password) < 6) {
        $error = "Kata sandi minimal 6 karakter!";
    } else {
        // Cek apakah username sudah dipakai
        $cek = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username'");

        if (mysqli_num_rows($cek) > 0) {
            $error = "Username sudah digunakan, silakan pilih username lain!";
        } else {
            // Role default untuk pendaftaran mandiri (pengguna umum/customer)
            $role = "user";

            $query = "INSERT INTO users (username, password, nama_lengkap, role) 
                      VALUES ('$username', '$password', '$nama_lengkap', '$role')";

            if (mysqli_query($conn, $query)) {
                $success = "Akun berhasil dibuat! Silakan masuk menggunakan akun Anda.";
            } else {
                $error = "Gagal mendaftar: " . mysqli_error($conn);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="id">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Daftar Akun - AeroPark Manager</title>
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
    <!-- Gambar diganti foto sunset bandara agar konsisten dengan login.php (tanpa mockup form pudar di belakang) -->
    <div class="fixed inset-0 z-0 overflow-hidden">
        <div class="absolute inset-0 bg-cover bg-center opacity-70" style="background-image: url('https://images.unsplash.com/photo-1569154941061-e231b4725ef1?auto=format&fit=crop&w=1920&q=80');"></div>
        <div class="absolute inset-0 bg-gradient-to-br from-orange-900/50 via-amber-700/30 to-indigo-950/50"></div>
    </div>

    <!-- Register Container -->
    <main class="relative z-10 w-full max-w-[440px] animate-in fade-in zoom-in duration-500">
        <!-- Logo Section -->
        <a href="index.php" class="flex flex-col items-center mb-8 group">
            <div class="bg-primary p-3 rounded-xl shadow-lg mb-4 group-hover:shadow-xl group-hover:scale-105 transition-all">
                <span class="material-symbols-outlined text-on-primary text-[40px]" style="font-variation-settings: 'FILL' 1;">local_parking</span>
            </div>
            <h1 class="font-headline-lg text-headline-lg text-primary tracking-tight group-hover:underline">AeroPark Manager</h1>
            <p class="font-body-md text-body-md text-on-surface-variant mt-1">Sistem Manajemen Logistik Bandara</p>
        </a>

        <!-- Register Card -->
        <div class="glass-effect shadow-2xl rounded-xl p-card-padding border border-outline-variant/30">
            <div class="mb-8">
                <h2 class="font-title-md text-title-md text-on-surface">Buat Akun Baru</h2>
                <p class="font-label-sm text-label-sm text-on-surface-variant">Lengkapi data di bawah untuk mendaftar</p>
            </div>

            <!-- Pesan Error -->
            <?php if (!empty($error)): ?>
                <div class="bg-error-container border border-error/30 text-on-error-container px-4 py-3 rounded-lg mb-4 text-sm flex items-center space-x-2">
                    <span class="material-symbols-outlined text-[20px]">error</span>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <!-- Pesan Sukses -->
            <?php if (!empty($success)): ?>
                <div class="bg-secondary-container border border-primary/30 text-primary px-4 py-3 rounded-lg mb-4 text-sm flex items-center space-x-2">
                    <span class="material-symbols-outlined text-[20px]">check_circle</span>
                    <span><?php echo $success; ?></span>
                </div>
            <?php endif; ?>

            <?php if (empty($success)): ?>
            <form action="" method="POST" class="space-y-stack-gap">
                <!-- Nama Lengkap -->
                <div class="space-y-1.5">
                    <label class="font-label-sm text-label-sm text-on-surface-variant ml-1" for="nama_lengkap">Nama Lengkap</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-outline group-focus-within:text-primary transition-colors">
                            <span class="material-symbols-outlined text-[20px]">badge</span>
                        </div>
                        <input class="w-full bg-surface-container-lowest border border-outline-variant rounded-lg py-3 pl-10 pr-4 text-on-surface font-body-md focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all placeholder:text-outline-variant" id="nama_lengkap" name="nama_lengkap" placeholder="Masukkan nama lengkap" required type="text" value="<?php echo isset($_POST['nama_lengkap']) ? htmlspecialchars($_POST['nama_lengkap']) : ''; ?>"/>
                    </div>
                </div>

                <!-- Username -->
                <div class="space-y-1.5">
                    <label class="font-label-sm text-label-sm text-on-surface-variant ml-1" for="username">Username</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-outline group-focus-within:text-primary transition-colors">
                            <span class="material-symbols-outlined text-[20px]">person</span>
                        </div>
                        <input class="w-full bg-surface-container-lowest border border-outline-variant rounded-lg py-3 pl-10 pr-4 text-on-surface font-body-md focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all placeholder:text-outline-variant" id="username" name="username" placeholder="Buat username" required type="text" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"/>
                    </div>
                </div>

                <!-- Password -->
                <div class="space-y-1.5">
                    <label class="font-label-sm text-label-sm text-on-surface-variant ml-1" for="password">Kata Sandi</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-outline group-focus-within:text-primary transition-colors">
                            <span class="material-symbols-outlined text-[20px]">lock</span>
                        </div>
                        <input class="w-full bg-surface-container-lowest border border-outline-variant rounded-lg py-3 pl-10 pr-12 text-on-surface font-body-md focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all placeholder:text-outline-variant" id="password" name="password" placeholder="Minimal 6 karakter" required type="password"/>
                        <button class="absolute inset-y-0 right-0 pr-3 flex items-center text-outline hover:text-on-surface-variant transition-colors" onclick="togglePassword('password', 'password-toggle-icon')" type="button">
                            <span class="material-symbols-outlined text-[20px]" id="password-toggle-icon">visibility</span>
                        </button>
                    </div>
                </div>

                <!-- Konfirmasi Password -->
                <div class="space-y-1.5">
                    <label class="font-label-sm text-label-sm text-on-surface-variant ml-1" for="konfirmasi_password">Konfirmasi Kata Sandi</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-outline group-focus-within:text-primary transition-colors">
                            <span class="material-symbols-outlined text-[20px]">lock_reset</span>
                        </div>
                        <input class="w-full bg-surface-container-lowest border border-outline-variant rounded-lg py-3 pl-10 pr-12 text-on-surface font-body-md focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all placeholder:text-outline-variant" id="konfirmasi_password" name="konfirmasi_password" placeholder="Ulangi kata sandi" required type="password"/>
                        <button class="absolute inset-y-0 right-0 pr-3 flex items-center text-outline hover:text-on-surface-variant transition-colors" onclick="togglePassword('konfirmasi_password', 'konfirmasi-toggle-icon')" type="button">
                            <span class="material-symbols-outlined text-[20px]" id="konfirmasi-toggle-icon">visibility</span>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <button class="w-full bg-primary hover:bg-primary-container text-on-primary font-title-md py-3.5 rounded-lg shadow-md hover:shadow-lg active:scale-[0.98] transition-all flex items-center justify-center space-x-2 mt-4" type="submit">
                    <span>Daftar Sekarang</span>
                    <span class="material-symbols-outlined text-[20px]">person_add</span>
                </button>
            </form>
            <?php endif; ?>

            <!-- Link ke Login -->
            <div class="mt-8 pt-6 border-t border-outline-variant/30 text-center">
                <p class="font-label-sm text-label-sm text-on-surface-variant">
                    Sudah punya akun? <a class="text-primary font-bold hover:underline" href="login.php">Masuk di sini</a>
                </p>
            </div>
        </div>

        <!-- Footer -->
        <footer class="mt-8 text-center">
            <p class="font-label-sm text-label-sm text-on-surface-variant/60">
                &copy; <?php echo date('Y'); ?> AeroParking System. Hak Cipta Dilindungi.
            </p>
        </footer>
    </main>

    <!-- Micro-interaction Scripts -->
    <script>
        function togglePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(iconId);
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.textContent = 'visibility_off';
            } else {
                passwordInput.type = 'password';
                toggleIcon.textContent = 'visibility';
            }
        }
        // Background parallax effect
        document.addEventListener('mousemove', (e) => {
            const moveX = (e.clientX - window.innerWidth / 2) * 0.005;
            const moveY = (e.clientY - window.innerHeight / 2) * 0.005;
            document.querySelector('.fixed.inset-0.z-0').style.transform = `translate(${moveX}px, ${moveY}px) scale(1.02)`;
        });
    </script>
</body>
</html>