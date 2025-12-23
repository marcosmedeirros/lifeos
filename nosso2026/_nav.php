<?php
// Navegação comum do módulo Nosso2026
?>
<header class="glass sticky top-0 z-20" style="background:#000;color:#fff">
  <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <div class="w-9 h-9 rounded-xl bg-white text-black flex items-center justify-center shadow-lg"><span class="font-bold">N26</span></div>
      <h1 class="text-xl font-bold">Nosso 2026</h1>
    </div>
    <nav class="hidden md:flex items-center gap-4 text-sm">
      <a href="<?= n26_link('index.php') ?>" class="hover:text-gray-300">Dashboard</a>
      <a href="<?= n26_link('goals.php') ?>" class="hover:text-gray-300">Metas</a>
      <a href="<?= n26_link('calendar.php') ?>" class="hover:text-gray-300">Compromissos</a>
      <a href="<?= n26_link('food.php') ?>" class="hover:text-gray-300">Alimentação</a>
      <a href="<?= n26_link('workouts.php') ?>" class="hover:text-gray-300">Treinos</a>
      <a href="<?= n26_link('finances.php') ?>" class="hover:text-gray-300">Finanças</a>
      <a href="<?= n26_link('movies.php') ?>" class="hover:text-gray-300">Filmes</a>
      <a href="<?= n26_link('memories.php') ?>" class="hover:text-gray-300">Memórias</a>
    </nav>
    <div class="md:hidden">
      <a href="<?= n26_link('finances.php') ?>" class="btn">Acessar</a>
    </div>
  </div>
</header>