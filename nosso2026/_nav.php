<?php
// Navegação comum do módulo Nosso2026
?>
<header class="glass sticky top-0 z-50" style="background:#000;color:#fff">
  <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <div class="w-9 h-9 rounded-xl bg-white text-black flex items-center justify-center shadow-lg"><span class="font-bold">N26</span></div>
      <h1 class="text-xl font-bold">Nosso 2026</h1>
    </div>
    
    <!-- Desktop Navigation -->
    <nav class="hidden md:flex items-center gap-4 text-sm">
      <a href="<?= n26_link('index.php') ?>" class="hover:text-gray-300">Dashboard</a>
      <a href="<?= n26_link('goals.php') ?>" class="hover:text-gray-300">Metas</a>
      <a href="<?= n26_link('calendar.php') ?>" class="hover:text-gray-300">Compromissos</a>
      <a href="<?= n26_link('workouts.php') ?>" class="hover:text-gray-300">Treinos</a>
      <a href="<?= n26_link('finances.php') ?>" class="hover:text-gray-300">Finanças</a>
      <a href="<?= n26_link('memories.php') ?>" class="hover:text-gray-300">Memórias</a>
    </nav>
    
    <!-- Mobile Hamburger Button -->
    <button id="n26-menu-toggle" class="md:hidden text-white focus:outline-none touch-manipulation" aria-label="Menu" style="min-width: 44px; min-height: 44px;">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
      </svg>
    </button>
  </div>
  
  <!-- Mobile Menu (Hidden by default) -->
  <nav id="n26-mobile-menu" class="hidden md:hidden bg-black border-t border-gray-800 max-h-[80vh] overflow-y-auto">
    <div class="px-4 py-2 space-y-1">
      <a href="<?= n26_link('index.php') ?>" class="block py-3 hover:bg-gray-900 rounded px-3 touch-manipulation">📊 Dashboard</a>
      <a href="<?= n26_link('goals.php') ?>" class="block py-3 hover:bg-gray-900 rounded px-3 touch-manipulation">🎯 Metas</a>
      <a href="<?= n26_link('calendar.php') ?>" class="block py-3 hover:bg-gray-900 rounded px-3 touch-manipulation">📅 Compromissos</a>
      <a href="<?= n26_link('workouts.php') ?>" class="block py-3 hover:bg-gray-900 rounded px-3 touch-manipulation">💪 Treinos</a>
      <a href="<?= n26_link('finances.php') ?>" class="block py-3 hover:bg-gray-900 rounded px-3 touch-manipulation">💰 Finanças</a>
      <a href="<?= n26_link('memories.php') ?>" class="block py-3 hover:bg-gray-900 rounded px-3 touch-manipulation">📸 Memórias</a>
      <a href="<?= n26_link('food.php') ?>" class="block py-3 hover:bg-gray-900 rounded px-3 touch-manipulation">🍽️ Alimentação</a>
      <a href="<?= n26_link('market.php') ?>" class="block py-3 hover:bg-gray-900 rounded px-3 touch-manipulation">🛒 Mercado</a>
      <a href="<?= n26_link('movies.php') ?>" class="block py-3 hover:bg-gray-900 rounded px-3 touch-manipulation">🎬 Filmes</a>
    </div>
  </nav>
</header>

<script>
// Toggle mobile menu
document.getElementById('n26-menu-toggle')?.addEventListener('click', function() {
  const menu = document.getElementById('n26-mobile-menu');
  menu.classList.toggle('hidden');
});

// Close menu when clicking outside
document.addEventListener('click', function(event) {
  const menu = document.getElementById('n26-mobile-menu');
  const toggle = document.getElementById('n26-menu-toggle');
  if (menu && toggle && !menu.contains(event.target) && !toggle.contains(event.target)) {
    menu.classList.add('hidden');
  }
});
</script>