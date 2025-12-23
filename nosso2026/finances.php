<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $stmt = $pdo->prepare("INSERT INTO nosso2026_finances (month, year, type, amount, category, description) VALUES (?,?,?,?,?,?)");
        $stmt->execute([intval($_POST['month']), intval($_POST['year']), $_POST['type'], floatval($_POST['amount']), trim($_POST['category']), trim($_POST['description'])]);
    } elseif (isset($_POST['delete'])) {
        $pdo->prepare("DELETE FROM nosso2026_finances WHERE id=?")->execute([intval($_POST['id'])]);
    }
    header('Location: ' . n26_link('finances.php'));
    exit;
}

$year = isset($_GET['year']) ? intval($_GET['year']) : 2026;

// Totais por mês
$monthData = [];
for($m = 1; $m <= 12; $m++) {
    $income = $pdo->query("SELECT SUM(amount) FROM nosso2026_finances WHERE year=$year AND month=$m AND type='income'")->fetchColumn() ?: 0;
    $expense = $pdo->query("SELECT SUM(amount) FROM nosso2026_finances WHERE year=$year AND month=$m AND type='expense'")->fetchColumn() ?: 0;
    $monthData[$m] = ['income' => $income, 'expense' => $expense, 'balance' => $income - $expense];
}

$monthNames = ['', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Finanças • Nosso 2026</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={darkMode:'class'};</script>
  <style>.glass{background:rgba(15,23,42,.8);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.08)}</style>
</head>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Finanças • Nosso 2026</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body { background:#000; color:#fff; font-family:system-ui,-apple-system,sans-serif; }
    .glass { background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.06); }
    .btn { background:#fff; color:#000; padding:0.5rem 1rem; border-radius:0.75rem; font-weight:600; transition:all 0.2s; display:inline-block; text-align:center; border:0; cursor:pointer; }
    .btn:hover { background:#e5e5e5; transform: translateY(-1px); }
  </style>
</head>
<body>
  <?php include __DIR__.'/_nav.php'; ?>
  
  <main class="max-w-7xl mx-auto px-4 py-10">
    <h1 class="text-3xl font-bold mb-8">Finanças <?= $year ?></h1>

    <!-- Grid dos 12 Meses -->
    <div class="grid md:grid-cols-4 gap-6 mb-8">
      <?php foreach($monthData as $m => $data): ?>
      <div class="glass rounded-2xl p-6 <?= $m == date('m') ? 'border-white' : '' ?>">
        <h3 class="text-xl font-bold mb-4"><?= $monthNames[$m] ?></h3>
        <div class="space-y-2 text-sm">
          <div class="flex justify-between">
            <span class="text-[#999]">Entradas</span>
            <span class="text-green-400 font-bold">R$ <?= number_format($data['income'],2,',','.') ?></span>
          </div>
          <div class="flex justify-between">
            <span class="text-[#999]">Saídas</span>
            <span class="text-red-400 font-bold">R$ <?= number_format($data['expense'],2,',','.') ?></span>
          </div>
          <div class="border-t border-[#222] pt-2 flex justify-between">
            <span class="font-bold">Saldo</span>
            <span class="font-bold <?= $data['balance'] >= 0 ? 'text-white' : 'text-red-400' ?>">
              <?= $data['balance'] >= 0 ? '+' : '' ?>R$ <?= number_format($data['balance'],2,',','.') ?>
            </span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Adicionar Lançamento -->
    <section class="glass rounded-2xl p-6 mb-8">
      <h2 class="text-2xl font-bold mb-4">Adicionar Lançamento</h2>
      <form method="post" class="grid md:grid-cols-6 gap-3">
        <input type="hidden" name="add" value="1">
        <select name="month" class="bg-black border border-[#222] rounded-xl p-3 text-white" required>
          <?php for($i=1;$i<=12;$i++): ?>
            <option value="<?= $i ?>" <?= $i == date('m') ? 'selected' : '' ?>><?= $monthNames[$i] ?></option>
          <?php endfor; ?>
        </select>
        <input type="number" name="year" value="<?= $year ?>" class="bg-black border border-[#222] rounded-xl p-3 text-white" required>
        <select name="type" class="bg-black border border-[#222] rounded-xl p-3 text-white">
          <option value="income">Entrada</option>
          <option value="expense">Saída</option>
        </select>
        <input type="number" step="0.01" name="amount" class="bg-black border border-[#222] rounded-xl p-3 text-white" placeholder="Valor" required>
        <input name="category" class="bg-black border border-[#222] rounded-xl p-3 text-white" placeholder="Categoria" required>
        <input name="description" class="bg-black border border-[#222] rounded-xl p-3 text-white" placeholder="Descrição">
        <button class="md:col-span-6 btn">Adicionar</button>
      </form>
    </section>

    <!-- Últimos Lançamentos -->
    <section class="glass rounded-2xl p-6">
      <h2 class="text-2xl font-bold mb-4">Últimos Lançamentos</h2>
      <div class="space-y-2">
        <?php
        $recent = $pdo->query("SELECT * FROM nosso2026_finances WHERE year=$year ORDER BY month DESC, id DESC LIMIT 20")->fetchAll();
        foreach($recent as $r):
        ?>
        <div class="flex items-center justify-between bg-black border border-[#222] rounded-xl p-4">
          <div class="flex-1">
            <p class="font-bold"><?= htmlspecialchars($r['category']) ?></p>
            <p class="text-xs text-[#999]"><?= $monthNames[$r['month']] ?> • <?= htmlspecialchars($r['description']) ?></p>
          </div>
          <div class="text-right">
            <p class="font-bold <?= $r['type']=='income' ? 'text-green-400' : 'text-red-400' ?>">
              <?= $r['type']=='income' ? '+' : '-' ?>R$ <?= number_format($r['amount'],2,',','.') ?>
            </p>
          </div>
          <form method="post" class="ml-3">
            <input type="hidden" name="delete" value="1">
            <input type="hidden" name="id" value="<?= $r['id'] ?>">
            <button class="text-red-500 hover:text-red-400 text-sm">✕</button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
  </main>
</body>
</html>
