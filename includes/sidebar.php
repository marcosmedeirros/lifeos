<!-- Hamburger Button (Mobile) - Always visible in standalone mode -->
<button id="menu-toggle" class="md:hidden fixed top-4 left-4 z-50 bg-gradient-to-r from-yellow-600 to-yellow-700 hover:from-yellow-500 hover:to-yellow-600 text-white p-3 rounded-xl shadow-lg shadow-yellow-600/30 transition-all duration-300 touch-manipulation" aria-label="Menu" style="display: block !important;">
    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
    </svg>
</button>

<!-- Overlay (Mobile) -->
<div id="menu-overlay" class="hidden fixed inset-0 bg-black/70 z-40 md:hidden"></div>

<!-- Sidebar Navigation -->
<nav id="sidebar" class="glass-sidebar fixed top-0 left-0 h-screen w-72 p-6 flex flex-col z-50 transform -translate-x-full md:translate-x-0 transition-transform duration-300 overflow-y-auto shadow-2xl">
    <div class="mb-10 flex items-center gap-3 px-2">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-yellow-600 to-yellow-700 flex items-center justify-center shadow-lg shadow-yellow-600/20">
            <!-- Calendar Logo SVG -->
            <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="3" y="4" width="18" height="18" rx="2" stroke="white" stroke-width="2"/>
                <path d="M3 10h18" stroke="white" stroke-width="2"/>
                <path d="M7 2v4M17 2v4" stroke="white" stroke-width="2" stroke-linecap="round"/>
                <!-- Checkmarks -->
                <path d="M7 14l1.5 1.5L11 13" stroke="#d4af37" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M13 14l1.5 1.5L17 13" stroke="#d4af37" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M7 18l1.5 1.5L11 17" stroke="#d4af37" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <h1 class="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-yellow-400 to-yellow-500">LifeOS</h1>
        <button id="menu-close" class="md:hidden ml-auto text-white/50 hover:text-white">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>
    
    <div class="flex-1 flex flex-col">
        <div class="flex flex-col gap-2">
            <a href="<?php echo BASE_PATH; ?>/" class="nav-btn <?php echo ($page === 'dashboard') ? 'active' : ''; ?> text-left px-4 py-3 flex items-center gap-3">
                <i class="fas fa-chart-pie w-5 text-center"></i> <span>Dashboard</span>
            </a>
            <a href="<?php echo BASE_PATH; ?>/modules/activities.php" class="nav-btn <?php echo ($page === 'activities') ? 'active' : ''; ?> text-left px-4 py-3 flex items-center gap-3">
                <i class="fas fa-check-circle w-5 text-center"></i> <span>Atividades</span>
            </a>
            <a href="<?php echo BASE_PATH; ?>/modules/events.php" class="nav-btn <?php echo ($page === 'events') ? 'active' : ''; ?> text-left px-4 py-3 flex items-center gap-3">
                <i class="fas fa-calendar-alt w-5 text-center"></i> <span>Calendário</span>
            </a>
            <a href="<?php echo BASE_PATH; ?>/modules/habits.php" class="nav-btn <?php echo ($page === 'habits') ? 'active' : ''; ?> text-left px-4 py-3 flex items-center gap-3">
                <i class="fas fa-fire w-5 text-center"></i> <span>Hábitos</span>
            </a>
            <a href="<?php echo BASE_PATH; ?>/modules/routine.php" class="nav-btn <?php echo ($page === 'routine') ? 'active' : ''; ?> text-left px-4 py-3 flex items-center gap-3">
                <i class="fas fa-sun w-5 text-center"></i> <span>Rotina</span>
            </a>
            <a href="<?php echo BASE_PATH; ?>/modules/goals.php" class="nav-btn <?php echo ($page === 'goals') ? 'active' : ''; ?> text-left px-4 py-3 flex items-center gap-3">
                <i class="fas fa-bullseye w-5 text-center"></i> <span>Metas</span>
            </a>
            <a href="<?php echo BASE_PATH; ?>/modules/finance.php" class="nav-btn <?php echo ($page === 'finance') ? 'active' : ''; ?> text-left px-4 py-3 flex items-center gap-3">
                <i class="fas fa-wallet w-5 text-center"></i> <span>Finanças</span>
            </a>
            <a href="<?php echo BASE_PATH; ?>/modules/strava.php" class="nav-btn <?php echo ($page === 'strava') ? 'active' : ''; ?> text-left px-4 py-3 flex items-center gap-3">
                <i class="fab fa-strava w-5 text-center text-orange-500"></i> <span>Strava</span>
            </a>
            <a href="<?php echo BASE_PATH; ?>/modules/notes.php" class="nav-btn <?php echo ($page === 'notes') ? 'active' : ''; ?> text-left px-4 py-3 flex items-center gap-3">
                <i class="fas fa-sticky-note w-5 text-center"></i> <span>Notas</span>
            </a>
            <a href="<?php echo BASE_PATH; ?>/modules/board.php" class="nav-btn <?php echo ($page === 'board') ? 'active' : ''; ?> text-left px-4 py-3 flex items-center gap-3">
                <i class="fas fa-images w-5 text-center"></i> <span>Board</span>
            </a>
            <a href="<?php echo BASE_PATH; ?>/modules/workouts.php" class="nav-btn <?php echo ($page === 'workouts') ? 'active' : ''; ?> text-left px-4 py-3 flex items-center gap-3">
                <i class="fas fa-dumbbell w-5 text-center"></i> <span>Treinos</span>
            </a>
            <a href="<?php echo BASE_PATH; ?>/modules/game.php" class="nav-btn <?php echo ($page === 'game') ? 'active' : ''; ?> text-left px-4 py-3 flex items-center gap-3">
                <i class="fas fa-trophy w-5 text-center text-yellow-500"></i> <span>Game</span>
            </a>
            <a href="<?php echo BASE_PATH; ?>/modules/chat_life.php" class="nav-btn <?php echo ($page === 'chat_life') ? 'active' : ''; ?> text-left px-4 py-3 flex items-center gap-3">
                <i class="fas fa-comments w-5 text-center text-cyan-400"></i> <span>Chat Life</span>
            </a>
        </div>
        
        <!-- Área do usuário e logout -->
        <div class="mt-6 pt-6 border-t border-white/10">
            <div class="flex items-center gap-3 px-4 py-3 bg-yellow-500/10 rounded-xl mb-3">
                <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-yellow-600 to-yellow-700 flex items-center justify-center shadow-lg shadow-yellow-600/20">
                    <i class="fas fa-user text-white text-sm"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-white truncate"><?php echo $_SESSION['user_name'] ?? 'Usuário'; ?></p>
                    <p class="text-xs text-slate-400">Autenticado</p>
                </div>
            </div>
            <a href="<?php echo BASE_PATH; ?>/logout" class="nav-btn text-left px-4 py-3 flex items-center gap-3 hover:bg-red-500/10 hover:text-red-400 transition">
                <i class="fas fa-sign-out-alt w-5 text-center"></i> <span>Sair</span>
            </a>
        </div>
    </div>
</nav>

<script>
// Mobile menu functionality
const menuToggle = document.getElementById('menu-toggle');
const menuClose = document.getElementById('menu-close');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('menu-overlay');

function openMenu() {
    sidebar.classList.remove('-translate-x-full');
    overlay.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeMenu() {
    sidebar.classList.add('-translate-x-full');
    overlay.classList.add('hidden');
    document.body.style.overflow = '';
}

menuToggle?.addEventListener('click', openMenu);
menuClose?.addEventListener('click', closeMenu);
overlay?.addEventListener('click', closeMenu);

// Close menu on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeMenu();
});

// Close menu when navigating (on mobile)
sidebar?.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', function() {
        if (window.innerWidth < 768) closeMenu();
    });
});
</script>
