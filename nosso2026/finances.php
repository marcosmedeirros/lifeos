<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $stmt = $pdo->prepare("INSERT INTO nosso2026_finances (month, year, type, amount, category, description) VALUES (?,?,?,?,?,?)");
        $stmt->execute([intval($_POST['month']), intval($_POST['year']), $_POST['type'], floatval($_POST['amount']), trim($_POST['category']), trim($_POST['description'])]);
    } elseif (isset($_POST['delete'])) {
        $pdo->prepare("DELETE FROM nosso2026_finances WHERE id=?")->execute([intval($_POST['id'])]);
      } elseif (isset($_POST['edit'])) {
        $stmt = $pdo->prepare("UPDATE nosso2026_finances SET month=?, type=?, amount=?, category=?, description=? WHERE id=?");
        $stmt->execute([intval($_POST['month']), $_POST['type'], floatval($_POST['amount']), trim($_POST['category']), trim($_POST['description']), intval($_POST['id'])]);
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
  <meta name="theme-color" content="#000000">
  <link rel="icon" href="<?= n26_link('icons/icon-192.png') ?>">
  <link rel="manifest" href="<?= n26_link('manifest.json') ?>">
  <link rel="apple-touch-icon" href="<?= n26_link('icons/apple-touch-icon.png') ?>">  <link rel="stylesheet" href="<?= n26_link('responsive.css') ?>">  <title>Finanças • Nosso 2026</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body { background:#000; color:#fff; font-family:system-ui,-apple-system,sans-serif; }
    .glass { background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.06); }
    .btn { background:#d4af37; color:#000; padding:0.5rem 1rem; border-radius:0.75rem; font-weight:600; transition:all 0.2s; display:inline-block; text-align:center; border:0; cursor:pointer; }
    .btn:hover { background:#c19b1a; transform: translateY(-1px); }
  </style>
</head>
<body>
  <?php include __DIR__.'/_nav.php'; ?>
  
  <main class="max-w-7xl mx-auto px-4 py-10">
    <div class="flex justify-between items-center mb-8">
      <h1 class="text-3xl font-bold">Finanças 2026</h1>
      <button onclick="openModal()" class="btn">💰 + Lançamento</button>
    </div>

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
          <div class="ml-3 cursor-pointer" onclick="openEdit(<?= htmlspecialchars(json_encode($r)) ?>)" title="Clique para editar">
            <span class="text-[#999] hover:text-white text-sm">⋯</span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Modal de Lançamento -->
    <div id="lancamentoModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:100;" onclick="closeModal()">
      <div class="glass rounded-2xl p-6 max-w-md mx-auto mt-20" onclick="event.stopPropagation()">
        <h2 class="text-2xl font-bold mb-4" id="modalTitle">Adicionar Lançamento</h2>
        <form method="post" id="financesForm">
          <input type="hidden" name="add" id="formAction" value="1">
          <input type="hidden" name="id" id="financeId">
          <input type="hidden" name="year" value="<?= $year ?>">
          <div class="mb-4">
            <label class="block text-sm font-bold mb-2">Mês</label>
            <select name="month" id="financeMonth" class="w-full bg-black border border-[#222] rounded-xl p-3 text-white">
              <?php for($i=1;$i<=12;$i++): ?>
                <option value="<?= $i ?>" <?= $i == date('m') ? 'selected' : '' ?>><?= $monthNames[$i] ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="mb-4">
            <label class="block text-sm font-bold mb-2">Tipo</label>
            <select name="type" id="financeType" class="w-full bg-black border border-[#222] rounded-xl p-3 text-white">
              <option value="income">Entrada</option>
              <option value="expense">Saída</option>
            </select>
          </div>
          <div class="mb-4">
            <label class="block text-sm font-bold mb-2">Valor</label>
            <input type="number" step="0.01" name="amount" id="financeAmount" class="w-full bg-black border border-[#222] rounded-xl p-3 text-white" required>
          </div>
          <div class="mb-4">
            <label class="block text-sm font-bold mb-2">Categoria</label>
            <input name="category" id="financeCategory" class="w-full bg-black border border-[#222] rounded-xl p-3 text-white" placeholder="Ex: Mercado" required>
          </div>
          <div class="mb-4">
            <label class="block text-sm font-bold mb-2">Descrição</label>
            <input name="description" id="financeDescription" class="w-full bg-black border border-[#222] rounded-xl p-3 text-white" placeholder="Opcional">
          </div>
          <div class="flex gap-3">
            <button type="submit" class="btn flex-1">💾 Salvar</button>
            <button type="button" id="deleteBtn" style="display:none" onclick="deleteFinance()" class="btn" style="background:#dc2626">Excluir</button>
            <button type="button" onclick="closeModal()" class="btn" style="background:#333">Fechar</button>
          </div>
        </form>
      </div>
    </div>
  </main>

  <script>
    function openModal() {
      document.getElementById('modalTitle').textContent = 'Adicionar Lançamento';
      document.getElementById('financesForm').reset();
      document.getElementById('formAction').name = 'add';
      document.getElementById('formAction').value = '1';
      document.getElementById('financeId').value = '';
      document.getElementById('deleteBtn').style.display = 'none';
      document.getElementById('financeMonth').value = new Date().getMonth() + 1;
      document.getElementById('lancamentoModal').style.display = 'block';
    }
    function openEdit(finance) {
      document.getElementById('modalTitle').textContent = 'Editar Lançamento';
      document.getElementById('financeId').value = finance.id;
      document.getElementById('financeMonth').value = finance.month;
      document.getElementById('financeType').value = finance.type;
      document.getElementById('financeAmount').value = finance.amount;
      document.getElementById('financeCategory').value = finance.category;
      document.getElementById('financeDescription').value = finance.description || '';
      document.getElementById('formAction').name = 'edit';
      document.getElementById('formAction').value = '1';
      document.getElementById('deleteBtn').style.display = 'block';
      document.getElementById('lancamentoModal').style.display = 'block';
    }
    function closeModal() { document.getElementById('lancamentoModal').style.display = 'none'; }
      function deleteFinance() {
        if(confirm('Remover lançamento?')) {
          const form = document.getElementById('financesForm');
          const id = document.getElementById('financeId').value;
          form.innerHTML = '<input type="hidden" name="delete" value="1"><input type="hidden" name="id" value="' + id + '">';
          form.submit();
        }
      }
  </script>
</body>
</html>
