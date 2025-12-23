<?php
// nosso2026/index.php - Página pública (sem login), usando o mesmo banco
require_once __DIR__ . '/_bootstrap.php';

// Stats gerais
$totalGoals = $pdo->query("SELECT COUNT(*) FROM nosso2026_goals")->fetchColumn();
$doneGoals = $pdo->query("SELECT COUNT(*) FROM nosso2026_goals WHERE progress=100")->fetchColumn();
$nextEvents = $pdo->query("SELECT title, start_date FROM events WHERE group_id='nosso2026' AND start_date>=NOW() ORDER BY start_date ASC LIMIT 5")->fetchAll();
$m=intval(date('m')); $y=intval(date('Y'));
$inc=$pdo->query("SELECT SUM(amount) FROM nosso2026_finances WHERE month=$m AND year=$y AND type='income'")->fetchColumn()?:0;
$out=$pdo->query("SELECT SUM(amount) FROM nosso2026_finances WHERE month=$m AND year=$y AND type='expense'")->fetchColumn()?:0;
$marketItems = $pdo->query("SELECT * FROM nosso2026_market_list WHERE done=0 ORDER BY id DESC LIMIT 10")->fetchAll();

// POST para mercado (adicionar/marcar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_market'])) {
        $stmt = $pdo->prepare("INSERT INTO nosso2026_market_list (item, qty, notes) VALUES (?,?,?)");
        $stmt->execute([trim($_POST['item']), trim($_POST['qty']), trim($_POST['notes'])]);
    } elseif (isset($_POST['done_market'])) {
        $pdo->prepare("UPDATE nosso2026_market_list SET done=1 WHERE id=?")->execute([intval($_POST['id'])]);
    }
    header('Location: ' . n26_link('index.php'));
    exit;
}

// Progresso do ano
$progress = 0;
$yearStart = strtotime('2026-01-01');
$yearEnd = strtotime('2026-12-31 23:59:59');
$now = time();
if ($now < $yearStart) { $progress = 0; }
elseif ($now > $yearEnd) { $progress = 100; }
else { $progress = round((($now - $yearStart)/($yearEnd - $yearStart))*100); }
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
    body{background:#000;color:#fff;font-family:'Outfit', sans-serif}
    .glass{background:#0a0a0a;border:1px solid #222}
    .btn{background:#fff;color:#000;padding:.75rem 1.25rem;border-radius:.75rem;font-weight:700}
    .section{scroll-margin-top:6rem}
  </style>
</head>
<body class="min-h-screen">
  <!-- Topbar -->
  <?php include __DIR__ . '/_nav.php'; ?>

  <main class="max-w-7xl mx-auto px-4 py-10">
    <!-- Barra de progresso do ano -->
    <section class="glass p-6 rounded-2xl mb-8">
      <div class="flex justify-between items-center mb-2">
        <h2 class="text-2xl font-bold">2026</h2>
        <span class="text-sm text-[#999]"><?= $progress ?>% completo</span>
      </div>
      <div class="w-full h-2 bg-[#1a1a1a] rounded-full overflow-hidden">
        <div style="width: <?= $progress ?>%" class="h-full bg-white"></div>
      </div>
    </section>

    <!-- Grid de Widgets -->
    <div class="grid md:grid-cols-3 gap-6 mb-8">
      <!-- Metas -->
      <div class="glass p-6 rounded-2xl">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-xl font-bold">Metas</h3>
          <a href="<?= n26_link('goals.php') ?>" class="text-xs hover:text-gray-300">Ver todas →</a>
        </div>
        <div class="text-center py-6">
          <div class="text-5xl font-bold mb-2"><?= $doneGoals ?><span class="text-2xl text-[#999]">/<?= $totalGoals ?></span></div>
          <p class="text-sm text-[#999]">concluídas</p>
        </div>
        <div class="w-full h-2 bg-[#1a1a1a] rounded-full overflow-hidden">
          <div style="width: <?= $totalGoals>0 ? round(($doneGoals/$totalGoals)*100) : 0 ?>%" class="h-full bg-white"></div>
        </div>
      </div>

      <!-- Finanças do Mês -->
      <div class="glass p-6 rounded-2xl">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-xl font-bold">Finanças</h3>
          <a href="<?= n26_link('finances.php') ?>" class="text-xs hover:text-gray-300">Ver todas →</a>
        </div>
        <div class="space-y-3">
          <div class="flex justify-between items-center">
            <span class="text-sm text-[#999]">Entradas</span>
            <span class="font-bold text-green-400">R$ <?= number_format($inc,2,',','.') ?></span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-sm text-[#999]">Saídas</span>
            <span class="font-bold text-red-400">R$ <?= number_format($out,2,',','.') ?></span>
          </div>
          <div class="border-t border-[#222] pt-3 flex justify-between items-center">
            <span class="text-sm font-bold">Saldo</span>
            <span class="font-bold text-lg"><?= $inc-$out>=0?'+':'' ?>R$ <?= number_format($inc-$out,2,',','.') ?></span>
          </div>
        </div>
      </div>

      <!-- Próximos Eventos -->
      <div class="glass p-6 rounded-2xl">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-xl font-bold">Próximos</h3>
          <a href="<?= n26_link('calendar.php') ?>" class="text-xs hover:text-gray-300">Ver calendário →</a>
        </div>
        <div class="space-y-3">
          <?php if(empty($nextEvents)): ?>
            <p class="text-sm text-[#999] text-center py-4">Nenhum evento agendado</p>
          <?php else: foreach($nextEvents as $e): ?>
            <div class="flex gap-3">
              <div class="text-center">
                <div class="text-2xl font-bold"><?= date('d', strtotime($e['start_date'])) ?></div>
                <div class="text-xs text-[#999]"><?= date('M', strtotime($e['start_date'])) ?></div>
              </div>
              <div class="flex-1 border-l border-[#222] pl-3">
                <p class="text-sm font-semibold"><?= htmlspecialchars($e['title']) ?></p>
                <p class="text-xs text-[#999]"><?= date('H:i', strtotime($e['start_date'])) ?></p>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <!-- Lista de Mercado -->
    <section class="glass p-6 rounded-2xl">
      <h3 class="text-xl font-bold mb-4">Lista de Mercado</h3>
      <form method="post" class="grid md:grid-cols-4 gap-3 mb-6">
        <input type="hidden" name="add_market" value="1">
        <input name="item" class="w-full bg-black border border-[#222] rounded-xl p-3 text-white" placeholder="Item" required>
        <input name="qty" class="w-full bg-black border border-[#222] rounded-xl p-3 text-white" placeholder="Qtd (opcional)">
        <input name="notes" class="w-full bg-black border border-[#222] rounded-xl p-3 text-white" placeholder="Notas">
        <button class="btn">Adicionar</button>
      </form>
      <div class="grid md:grid-cols-2 gap-3">
        <?php foreach($marketItems as $it): ?>
        <form method="post" class="flex items-center justify-between border border-[#222] rounded-xl p-3">
          <div class="flex-1">
            <p class="font-semibold"><?= htmlspecialchars($it['item']) ?></p>
            <?php if($it['qty'] || $it['notes']): ?>
              <p class="text-xs text-[#999]"><?= htmlspecialchars($it['qty']) ?> <?= htmlspecialchars($it['notes']) ?></p>
            <?php endif; ?>
          </div>
          <input type="hidden" name="done_market" value="1">
          <input type="hidden" name="id" value="<?= $it['id'] ?>">
          <button class="btn">OK</button>
        </form>
        <?php endforeach; ?>
        <?php if(empty($marketItems)): ?>
          <p class="text-sm text-[#999] text-center py-4 col-span-2">Lista vazia</p>
        <?php endif; ?>
      </div>
    </section>
  </main>

  <footer class="max-w-6xl mx-auto px-4 py-10 text-center text-[#999]">
    <p>Nosso 2026 • Parte do LifeOS • <?= date('Y') ?></p>
  </footer>
</body>
</html>
