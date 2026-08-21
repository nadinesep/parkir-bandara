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
        $cek = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username'");

        if (mysqli_num_rows($cek) > 0) {
            $error = "Username sudah digunakan, silakan pilih username lain!";
        } else {
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "#dc1f2f",
                        "primary-container": "#ef233c",
                        "on-primary": "#ffffff",
                        background: "#070b18",
                        surface: "#070b18",
                        "on-surface": "#ffffff",
                        "on-surface-variant": "#d5d9e4",
                        outline: "#8b93a7",
                        "outline-variant": "#475067",
                        "surface-container-lowest": "#f8fafc",
                        "error-container": "#450a0a",
                        "on-error-container": "#fecaca"
                    },
                    borderRadius: {
                        DEFAULT: "0.25rem",
                        lg: "0.5rem",
                        xl: "0.75rem",
                        full: "9999px"
                    },
                    spacing: {
                        "stack-gap": "12px",
                        "card-padding": "20px"
                    },
                    fontFamily: {
                        body: ["Inter"],
                        title: ["Inter"],
                        headline: ["Inter"]
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #070b18;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        .glass-effect {
            background: rgba(8, 12, 28, 0.88);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(220, 38, 38, 0.55);
            box-shadow: 0 18px 55px rgba(0,0,0,.55), 0 0 28px rgba(220,38,38,.16);
        }

        .spider-card {
            position: relative;
            overflow: hidden;
        }

        .spider-card::before {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
            opacity: .13;
            background:
                repeating-radial-gradient(circle at 50% 45%, transparent 0 34px, rgba(255,255,255,.9) 35px 36px, transparent 37px 72px),
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

        .spider-badge .material-symbols-outlined {
            color: #ffffff;
            font-size: 40px;
        }

        .spider-title {
            color: #ffffff;
            text-shadow: 0 2px 16px rgba(239,29,47,.55);
        }

        .spider-subtitle {
            color: rgba(255,255,255,.78);
        }

        .spider-label {
            color: #d5d9e4;
        }

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

        .spider-button:hover {
            filter: brightness(1.08);
        }

        .spider-link {
            color: #3b82f6 !important;
        }

        .spider-divider {
            border-color: rgba(100,116,139,.35);
        }

        .fade-in {
            animation: fadeIn .5s ease-out both;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px) scale(.99); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
    </style>
</head>

<body class="min-h-screen flex items-center justify-center p-4">
    <!-- Background pesawat tetap dipertahankan -->
    <div class="fixed inset-0 z-0 overflow-hidden">
        <div class="absolute inset-0 bg-cover bg-center opacity-70"
             style="background-image: url('https://images.unsplash.com/photo-1569154941061-e231b4725ef1?auto=format&fit=crop&w=1920&q=80');"></div>
        <div class="absolute inset-0 bg-gradient-to-br from-[#06133b]/42 via-[#3b0712]/20 to-black/48"></div>
        <div class="absolute inset-0 bg-blue-600/10"></div>
    </div>

    <main class="relative z-10 w-full max-w-[570px] fade-in">
        <!-- Logo Section: pesawat, bukan laba-laba -->
        <a href="index.php" class="flex flex-col items-center mb-8 group">
            <div class="spider-badge p-3 rounded-xl mb-4 group-hover:scale-105 transition-all">
                <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">flight</span>
            </div>
            <h1 class="spider-title text-3xl font-bold tracking-tight group-hover:underline">AeroPark Manager</h1>
            <p class="spider-subtitle text-sm mt-1">Sistem Manajemen Logistik Bandara</p>
        </a>

        <!-- Register Card -->
        <div class="glass-effect spider-card rounded-xl p-5">
            <div class="mb-8 relative z-10">
                <h2 class="text-white text-2xl font-bold">Buat Akun Baru</h2>
                <p class="text-slate-300 text-sm">Lengkapi data di bawah untuk mendaftar</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="bg-red-950/80 border border-red-400/40 text-red-100 px-4 py-3 rounded-lg mb-4 text-sm flex items-center space-x-2 relative z-10">
                    <span class="material-symbols-outlined text-[20px]">error</span>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="bg-blue-950/80 border border-blue-400/40 text-blue-100 px-4 py-3 rounded-lg mb-4 text-sm flex items-center space-x-2 relative z-10">
                    <span class="material-symbols-outlined text-[20px]">check_circle</span>
                    <span><?php echo $success; ?></span>
                </div>
            <?php endif; ?>

            <?php if (empty($success)): ?>
            <form action="" method="POST" class="space-y-3 relative z-10">

                <div class="space-y-1.5">
                    <label class="text-sm font-medium spider-label ml-1" for="nama_lengkap">Nama Lengkap</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500 group-focus-within:text-red-600 transition-colors">
                            <span class="material-symbols-outlined text-[20px]">badge</span>
                        </div>
                        <input class="w-full spider-input rounded-lg py-3 pl-10 pr-4 text-slate-900 text-sm focus:ring-2 focus:ring-red-500/20 outline-none transition-all placeholder:text-slate-400"
                               id="nama_lengkap" name="nama_lengkap" placeholder="Masukkan nama lengkap" required type="text"
                               value="<?php echo isset($_POST['nama_lengkap']) ? htmlspecialchars($_POST['nama_lengkap']) : ''; ?>"/>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-sm font-medium spider-label ml-1" for="username">Username</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500 group-focus-within:text-red-600 transition-colors">
                            <span class="material-symbols-outlined text-[20px]">person</span>
                        </div>
                        <input class="w-full spider-input rounded-lg py-3 pl-10 pr-4 text-slate-900 text-sm focus:ring-2 focus:ring-red-500/20 outline-none transition-all placeholder:text-slate-400"
                               id="username" name="username" placeholder="Buat username" required type="text"
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"/>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-sm font-medium spider-label ml-1" for="password">Kata Sandi</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500 group-focus-within:text-red-600 transition-colors">
                            <span class="material-symbols-outlined text-[20px]">lock</span>
                        </div>
                        <input class="w-full spider-input rounded-lg py-3 pl-10 pr-12 text-slate-900 text-sm focus:ring-2 focus:ring-red-500/20 outline-none transition-all placeholder:text-slate-400"
                               id="password" name="password" placeholder="Minimal 6 karakter" required type="password"/>
                        <button class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-500 hover:text-slate-800 transition-colors"
                                onclick="togglePassword('password', 'password-toggle-icon')" type="button">
                            <span class="material-symbols-outlined text-[20px]" id="password-toggle-icon">visibility</span>
                        </button>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-sm font-medium spider-label ml-1" for="konfirmasi_password">Konfirmasi Kata Sandi</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500 group-focus-within:text-red-600 transition-colors">
                            <span class="material-symbols-outlined text-[20px]">lock_reset</span>
                        </div>
                        <input class="w-full spider-input rounded-lg py-3 pl-10 pr-12 text-slate-900 text-sm focus:ring-2 focus:ring-red-500/20 outline-none transition-all placeholder:text-slate-400"
                               id="konfirmasi_password" name="konfirmasi_password" placeholder="Ulangi kata sandi" required type="password"/>
                        <button class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-500 hover:text-slate-800 transition-colors"
                                onclick="togglePassword('konfirmasi_password', 'konfirmasi-toggle-icon')" type="button">
                            <span class="material-symbols-outlined text-[20px]" id="konfirmasi-toggle-icon">visibility</span>
                        </button>
                    </div>
                </div>

                <button class="w-full spider-button text-white font-semibold text-lg py-3.5 rounded-lg shadow-md hover:shadow-lg active:scale-[0.98] transition-all flex items-center justify-center space-x-2 mt-4"
                        type="submit">
                    <span>Daftar Sekarang</span>
                    <span class="material-symbols-outlined text-[20px]">person_add</span>
                </button>
            </form>
            <?php endif; ?>

            <div class="mt-8 pt-6 spider-divider border-t text-center relative z-10">
                <p class="text-sm text-slate-300">
                    Sudah punya akun?
                    <a class="spider-link font-bold hover:underline" href="login.php">Masuk di sini</a>
                </p>
            </div>
        </div>

        <footer class="mt-8 text-center">
            <p class="text-xs text-white/60">
                &copy; <?php echo date('Y'); ?> AeroParking System. Hak Cipta Dilindungi.
            </p>
        </footer>
    </main>

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

        document.addEventListener('mousemove', (e) => {
            const bg = document.querySelector('.fixed.inset-0.z-0');
            if (!bg) return;

            const moveX = (e.clientX - window.innerWidth / 2) * 0.005;
            const moveY = (e.clientY - window.innerHeight / 2) * 0.005;
            bg.style.transform = `translate(${moveX}px, ${moveY}px) scale(1.02)`;
        });
    </script>
</body>
</html>