<?php
// nosso2026/index.php - Página pública (sem login), usando o mesmo banco
require_once __DIR__ . '/../config.php';

// Detecta base local vs produção para links locais desta página
$IS_LOCAL = (strpos($_SERVER['HTTP_HOST'] ?? 'localhost', 'localhost') !== false);
$SELF_BASE = $IS_LOCAL ? '/lifeos/nosso2026' : '';
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Nosso 2026</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={darkMode:'class',theme:{extend:{fontFamily:{sans:['Outfit','sans-serif']},colors:{darkbg:'#0f172a',cardbg:'#111827',primary:'#a855f7'}}}};</script>
  <style>
    body{background: radial-gradient(circle at top right, #1e1b4b, #0f172a, #020617); color:#e2e8f0; font-family:'Outfit', sans-serif;}
    .glass{background:rgba(15,23,42,.8); backdrop-filter: blur(12px); border:1px solid rgba(255,255,255,.08)}
    .btn{background:#a855f7; padding:.75rem 1.25rem; border-radius:.75rem; font-weight:700}
    .btn:hover{background:#9333ea}
    .section{scroll-margin-top:6rem}
  </style>
</head>
<body class="min-h-screen">
  <!-- Topbar próprio -->
  <header class="glass sticky top-0 z-20">
    <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-purple-600 to-blue-500 flex items-center justify-center shadow-lg">
          <span class="font-bold">N26</span>
        </div>
        <h1 class="text-xl font-bold">Nosso 2026</h1>
      </div>
      <nav class="hidden md:flex items-center gap-4 text-sm">
        <a href="#inicio" class="hover:text-purple-300">Início</a>
        <a href="#cronograma" class="hover:text-purple-300">Cronograma</a>
        <a href="#galeria" class="hover:text-purple-300">Galeria</a>
        <a href="#contato" class="hover:text-purple-300">Contato</a>
      </nav>
      <div class="md:hidden">
        <a href="#contato" class="btn">Falar com a gente</a>
      </div>
    </div>
  </header>

  <!-- Conteúdo -->
  <main class="max-w-6xl mx-auto px-4 py-10 space-y-16">
    <section id="inicio" class="section grid md:grid-cols-2 gap-8 items-center">
      <div>
        <h2 class="text-3xl md:text-4xl font-extrabold mb-3">Bem-vindo ao Nosso 2026</h2>
        <p class="text-slate-300 mb-6">Esta é uma página pública do seu LifeOS, com menu próprio e sem necessidade de login. Usa o mesmo banco de dados do sistema principal.</p>
        <div class="flex gap-3">
          <a href="#cronograma" class="btn">Ver Cronograma</a>
          <a href="#galeria" class="btn bg-slate-700 hover:bg-slate-600">Abrir Galeria</a>
        </div>
      </div>
      <div class="glass rounded-2xl p-6">
        <h3 class="font-bold text-lg mb-2">Status do Banco</h3>
        <p class="text-sm text-slate-300">Conexão ativa com o mesmo banco utilizado no LifeOS.</p>
      </div>
    </section>

    <section id="cronograma" class="section">
      <div class="glass rounded-2xl p-6">
        <h2 class="text-2xl font-bold mb-2">Cronograma</h2>
        <p class="text-slate-300">Adapte esta seção com o conteúdo que quiser expor publicamente.</p>
      </div>
    </section>

    <section id="galeria" class="section">
      <div class="glass rounded-2xl p-6">
        <h2 class="text-2xl font-bold mb-2">Galeria</h2>
        <p class="text-slate-300">Espaço para fotos, vídeos ou destaques. Você pode criar novos arquivos PHP nesta pasta e linkar no menu acima.</p>
      </div>
    </section>

    <section id="contato" class="section">
      <div class="glass rounded-2xl p-6">
        <h2 class="text-2xl font-bold mb-2">Contato</h2>
        <p class="text-slate-300 mb-4">Personalize com suas informações de contato ou formulários públicos.</p>
        <div class="flex gap-3">
          <a href="<?= $IS_LOCAL ? '/lifeos' : 'https://marcosmedeirros.io' ?>" class="btn bg-slate-700 hover:bg-slate-600">Voltar ao LifeOS</a>
        </div>
      </div>
    </section>
  </main>

  <footer class="max-w-6xl mx-auto px-4 py-10 text-center text-slate-500">
    <p>Nosso 2026 • Parte do LifeOS • <?= date('Y') ?></p>
  </footer>
</body>
</html>
