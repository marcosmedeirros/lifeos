<nav class="md:w-72 glass-sidebar p-6 flex flex-col fixed md:fixed md:top-0 md:left-0 w-full z-10 bottom-0 md:bottom-auto md:h-screen md:overflow-y-auto shadow-2xl">
    <div class="mb-10 flex items-center gap-3 px-2">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-purple-600 to-blue-500 flex items-center justify-center shadow-lg shadow-purple-500/20">
            <i class="fas fa-biohazard text-white text-xl"></i>
        </div>
        <h1 class="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-purple-400 to-blue-400">LifeOS</h1>
    </div>
    
    <div class="flex-1 flex flex-col">
        <div class="flex md:flex-col gap-2 overflow-x-auto md:overflow-visible no-scrollbar pb-2 md:pb-0">
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
            <a href="<?php echo BASE_PATH; ?>/modules/workouts.php" class="nav-btn <?php echo ($page === 'workouts') ? 'active' : ''; ?> text-left px-4 py-3 flex items-center gap-3">
                <i class="fas fa-dumbbell w-5 text-center"></i> <span>Treinos</span>
            </a>
            <a href="<?php echo BASE_PATH; ?>/modules/game.php" class="nav-btn <?php echo ($page === 'game') ? 'active' : ''; ?> text-left px-4 py-3 flex items-center gap-3">
                <i class="fas fa-trophy w-5 text-center text-yellow-500"></i> <span>Game</span>
            </a>
        </div>
    </div>
    
    <div class="mt-auto pt-4 border-t border-slate-700/50">
        <a href="?logout=1" class="text-slate-400 hover:text-rose-400 transition flex items-center gap-2 px-4 py-2 rounded-xl">
            <i class="fas fa-sign-out-alt"></i> Sair (<?php echo $_SESSION['user_name'] ?? 'Marcos Medeiros'; ?>)
        </a>
    </div>
</nav>
