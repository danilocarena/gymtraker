<?php
// includes/header.php
if (!isset($active_page)) {
    $active_page = 'dashboard';
}
if (!isset($page_title)) {
    $page_title = 'GymTracker Pro';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <meta name="description" content="Sigue y analiza tu progreso en el gimnasio con nuestra herramienta profesional.">
    <link rel="icon" type="image/png" href="components/favicon.png">
    
    <!-- App Mode Meta Tags -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="GymTraker">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#0f172a">
    <link rel="apple-touch-icon" href="components/favicon.png">
    <link rel="manifest" href="manifest.json">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts: Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#22c55e',
                        'primary-hover': '#16a34a',
                        'bg-color': '#0f172a',
                        'panel-bg': 'rgba(30, 41, 59, 0.7)',
                        muted: '#94a3b8',
                    },
                    fontFamily: {
                        sans: ['Outfit', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style type="text/tailwindcss">
        @layer base {
            html, body {
                @apply bg-[#0f172a] text-slate-50 font-sans min-h-screen;
                margin: 0;
                padding: 0;
            }
            body {
                background-image: radial-gradient(circle at 15% 50%, rgba(34, 197, 94, 0.08) 0%, transparent 50%), 
                                  radial-gradient(circle at 85% 30%, rgba(59, 130, 246, 0.08) 0%, transparent 50%);
                background-attachment: fixed;
            }
        }
        @layer components {
            .glass-panel { @apply bg-[#1e293b]/70 backdrop-blur-xl border border-white/10 rounded-2xl p-6 shadow-[0_4px_30px_rgba(0,0,0,0.1)]; }
            .btn-primary { @apply bg-primary text-white border-none py-3 px-6 rounded-xl font-bold cursor-pointer shadow-[0_4px_14px_rgba(34,197,94,0.3)] transition-all duration-300 hover:bg-primary-hover hover:-translate-y-0.5 hover:shadow-[0_6px_20px_rgba(34,197,94,0.4)]; }
            .form-control { @apply w-full p-3 bg-white/5 border border-white/10 rounded-lg text-white font-sans focus:outline-none focus:border-primary transition-colors; }
            .form-control option { @apply bg-[#0f172a]; }
        }
        
        #sidebar {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        @media (min-width: 768px) {
            #sidebar { transform: translateX(0) !important; }
        }
    </style>
    <?php if (isset($extra_css)) echo $extra_css; ?>
    <script>
        function toggleMobileMenu() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }
    </script>
</head>
<body class="bg-[#0f172a] text-slate-50 font-sans min-h-screen">
    <div class="flex flex-col md:flex-row min-h-screen overflow-x-hidden"> <!-- App Container -->
    <!-- Mobile Header -->
    <div class="md:hidden flex items-center justify-between p-4 bg-slate-900/80 backdrop-blur-md border-b border-white/10 sticky top-0 z-40">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center text-white font-black text-xl">G</div>
            <span class="font-extrabold tracking-tighter text-xl text-primary uppercase">GymTraker</span>
        </div>
        <button onclick="toggleMobileMenu()" class="text-white p-2">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path></svg>
        </button>
    </div>

    <!-- Sidebar Overlay (Mobile) -->
    <div id="sidebar-overlay" onclick="toggleMobileMenu()" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-40 md:hidden"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 w-[280px] bg-[#0f172a] border-r border-white/10 py-8 px-6 flex flex-col items-center z-50 -translate-x-full md:translate-x-0 h-full overflow-y-auto shrink-0 shadow-2xl md:shadow-none">
        <div class="flex items-center gap-3 mb-12 w-full justify-center md:justify-start">
            <div class="w-10 h-10 bg-primary rounded-xl flex items-center justify-center text-white shadow-[0_0_20px_rgba(34,197,94,0.4)] shrink-0">
                <span class="text-2xl font-black">G</span>
            </div>
            <div class="overflow-hidden">
                <h1 class="text-2xl font-black tracking-tighter text-primary leading-none uppercase truncate">GymTraker</h1>
                <span class="text-[10px] text-slate-500 font-bold tracking-[2px] uppercase whitespace-nowrap">Pro Edition</span>
            </div>
        </div>

        <nav class="w-full flex-1">
            <ul class="flex flex-col gap-2 list-none p-0 m-0">
                <li>
                    <a href="./" class="nav-link <?= ($active_page == 'dashboard') ? 'text-white bg-primary/10 border-primary/20' : 'text-slate-400 hover:text-white hover:bg-white/5 border-transparent' ?> flex items-center gap-3 py-3 px-4 rounded-xl border transition-all duration-200 group no-underline text-sm font-bold">
                        <span class="text-lg group-hover:scale-110 transition-transform w-5 text-center"><i class="fa-solid fa-house"></i></span>
                        <span class="whitespace-nowrap">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="sesion_nueva" class="nav-link <?= ($active_page == 'sesion') ? 'text-white bg-primary/10 border-primary/20' : 'text-slate-400 hover:text-white hover:bg-white/5 border-transparent' ?> flex items-center gap-3 py-3 px-4 rounded-xl border transition-all duration-200 group no-underline text-sm font-bold">
                        <span class="text-lg group-hover:scale-110 transition-transform w-5 text-center"><i class="fa-solid fa-dumbbell"></i></span>
                        <span class="whitespace-nowrap">Entrenar</span>
                    </a>
                </li>
                <li>
                    <a href="rutinas" class="nav-link <?= ($active_page == 'rutinas') ? 'text-white bg-primary/10 border-primary/20' : 'text-slate-400 hover:text-white hover:bg-white/5 border-transparent' ?> flex items-center gap-3 py-3 px-4 rounded-xl border transition-all duration-200 group no-underline text-sm font-bold">
                        <span class="text-lg group-hover:scale-110 transition-transform w-5 text-center"><i class="fa-solid fa-clipboard-list"></i></span>
                        <span class="whitespace-nowrap">Rutinas</span>
                    </a>
                </li>
                <li>
                    <a href="peso" class="nav-link <?= ($active_page == 'peso') ? 'text-white bg-primary/10 border-primary/20' : 'text-slate-400 hover:text-white hover:bg-white/5 border-transparent' ?> flex items-center gap-3 py-3 px-4 rounded-xl border transition-all duration-200 group no-underline text-sm font-bold">
                        <span class="text-lg group-hover:scale-110 transition-transform w-5 text-center"><i class="fa-solid fa-chart-line"></i></span>
                        <span class="whitespace-nowrap">Mi Peso</span>
                    </a>
                </li>
                <li>
                    <a href="historial" class="nav-link <?= ($active_page == 'historial') ? 'text-white bg-primary/10 border-primary/20' : 'text-slate-400 hover:text-white hover:bg-white/5 border-transparent' ?> flex items-center gap-3 py-3 px-4 rounded-xl border transition-all duration-200 group no-underline text-sm font-bold">
                        <span class="text-lg group-hover:scale-110 transition-transform w-5 text-center"><i class="fa-solid fa-clock-rotate-left"></i></span>
                        <span class="whitespace-nowrap">Historial</span>
                    </a>
                </li>
                <li>
                    <a href="perfil" class="nav-link <?= ($active_page == 'perfil') ? 'text-white bg-primary/10 border-primary/20' : 'text-slate-400 hover:text-white hover:bg-white/5 border-transparent' ?> flex items-center gap-3 py-3 px-4 rounded-xl border transition-all duration-200 group no-underline text-sm font-bold">
                        <span class="text-lg group-hover:scale-110 transition-transform w-5 text-center"><i class="fa-solid fa-user"></i></span>
                        <span class="whitespace-nowrap">Mi Perfil</span>
                    </a>
                </li>
            </ul>
        </nav>

        <div class="w-full pt-6 border-t border-white/5 mt-auto">
            <a href="logout" class="flex items-center gap-3 py-3 px-4 rounded-xl text-red-500 hover:bg-red-500/10 transition-all font-bold group no-underline text-sm">
                <span class="text-lg group-hover:rotate-12 transition-transform w-5 text-center"><i class="fa-solid fa-right-from-bracket"></i></span>
                <span class="whitespace-nowrap uppercase tracking-wider">Cerrar Sesión</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 w-full min-h-screen bg-[#0f172a] py-6 px-4 md:py-10 md:px-12 md:ml-[280px] overflow-x-hidden relative">
