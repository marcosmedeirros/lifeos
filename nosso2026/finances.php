<?php
require_once __DIR__ . '/_bootstrap.php';

// Categorias fixas de despesas
$categories = ['Aluguel', 'Condominio/Agua', 'Internet', 'Luz', 'Gás', 'Outro'];

$year = isset($_GET['year']) ? intval($_GET['year']) : 2026;

// Salvar edição mensal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_month'])) {
    $month = intval($_POST['month']);
    foreach ($categories as $cat) {
        $amount = isset($_POST['amount'][$cat]) ? floatval($_POST['amount'][$cat]) : 0;
        // Zera valores anteriores dessa categoria no mês/ano
        $del = $pdo->prepare("DELETE FROM nosso2026_finances WHERE year=? AND month=? AND category=?");
        $del->execute([$year, $month, $cat]);
        // Reinsere valor único se > 0
        if ($amount > 0) {
            $ins = $pdo->prepare("INSERT INTO nosso2026_finances (month, year, type, amount, category, description) VALUES (?,?,'expense',?,?,?)");
            $ins->execute([$month, $year, $amount, $cat, '']);
        }
    }
    header('Location: ' . n26_link('finances.php?year='.$year));
    exit;
}

// Totais por mês organizados por categoria
$monthData = [];
for($m = 1; $m <= 12; $m++) {
    $expenses = $pdo->query("SELECT category, SUM(amount) as total FROM nosso2026_finances WHERE year=$year AND month=$m GROUP BY category")->fetchAll(PDO::FETCH_KEY_PAIR);
    $total = array_sum($expenses);
    $monthData[$m] = [
        'expenses' => $expenses,
        'total' => $total,
        'each' => $total / 2
    ];
}

$monthNames = ['', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="theme-color" content="#000000">
  <link rel="icon" href="<?= n26_link('icons/icon-192.png') ?>">
  <link rel="manifest" href="<?= n26_link('manifest.json') ?>">
  <link rel="apple-touch-icon" href="<?= n26_link('icons/apple-touch-icon.png') ?>">
  <link rel="stylesheet" href="<?= n26_link('responsive.css') ?>">
  <title>Finanças • Nosso 2026</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body { background:#000; color:#fff; font-family:system-ui,-apple-system,sans-serif; }
    .glass { background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.06); }
    .btn { background:#ffffff; color:#000; padding:0.5rem 1rem; border-radius:0.75rem; font-weight:600; transition:all 0.2s; display:inline-block; text-align:center; border:0; cursor:pointer; }
    .btn:hover { background:#e0e0e0; transform: translateY(-1px); }
    input.money { background:#1a1a1a; border:1px solid #333; color:#fff; }
  </style>
</head>
<body>
  <?php include __DIR__.'/_nav.php'; ?>
  
  <main class="max-w-7xl mx-auto px-4 py-10">
    <div class="flex justify-between items-center mb-8">
      <h1 class="text-3xl font-bold">Finanças <?= $year ?></h1>
    </div>

    <!-- Grid dos 12 Meses com edição inline -->
    <div class="grid md:grid-cols-3 gap-6 mb-8">
      <?php foreach($monthData as $m => $data): ?>
      <form method="post" class="glass rounded-2xl p-6 <?= $m == date('m') ? 'border-white' : '' ?>">
        <input type="hidden" name="save_month" value="1">
        <input type="hidden" name="month" value="<?= $m ?>">
        <h3 class="text-xl font-bold mb-4 flex items-center justify-between">
          <span><?= $monthNames[$m] ?></span>
          <span class="text-xs text-[#999]">Editar</span>
        </h3>
        <div class="space-y-2 text-sm">
          <?php foreach($categories as $cat): $val = $data['expenses'][$cat] ?? 0; ?>
            <div class="flex items-center gap-2">
              <span class="text-[#999] w-32 shrink-0"><?= $cat ?></span>
              <input name="amount[<?= $cat ?>]" type="number" step="0.01" value="<?= number_format($val,2,'.','') ?>" class="money rounded-lg px-3 py-2 w-full" />
            </div>
          <?php endforeach; ?>
          <div class="border-t border-[#222] pt-3 mt-3 text-sm">
            <div class="flex justify-between mb-1">
              <span class="font-bold">Total</span>
              <span class="font-bold text-white">R$ <?= number_format($data['total'], 2, ',', '.') ?></span>
            </div>
            <div class="flex justify-between">
              <span class="text-[#999] text-xs">Cada</span>
              <span class="text-[#999] text-xs font-semibold">R$ <?= number_format($data['each'], 2, ',', '.') ?></span>
            </div>
          </div>
          <div class="pt-3">
            <button type="submit" class="btn w-full">💾 Salvar mês</button>
          </div>
        </div>
      </form>
      <?php endforeach; ?>
    </div>
  </main>
</body>
</html>
