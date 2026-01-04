<?php
require_once __DIR__ . '/_bootstrap.php';

// Handle POST create/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $stmt = $pdo->prepare("INSERT INTO nosso2026_goals (owner, difficulty, title) VALUES (?,?,?)");
        $stmt->execute([$_POST['owner'], $_POST['difficulty'], trim($_POST['title'])]);
    } elseif (isset($_POST['action']) && $_POST['action'] === 'progress') {
        $stmt = $pdo->prepare("UPDATE nosso2026_goals SET progress=? WHERE id=?");
        $stmt->execute([intval($_POST['progress']), intval($_POST['id'])]);
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
      $stmt = $pdo->prepare("DELETE FROM nosso2026_goals WHERE id=?");
      $stmt->execute([intval($_POST['id'])]);
    } elseif (isset($_POST['action']) && $_POST['action'] === 'edit') {
      $stmt = $pdo->prepare("UPDATE nosso2026_goals SET owner=?, difficulty=?, title=? WHERE id=?");
      $stmt->execute([$_POST['owner'], $_POST['difficulty'], trim($_POST['title']), intval($_POST['id'])]);
    }
    header('Location: ' . n26_link('goals.php'));
    exit;
}

$filter_owner = $_GET['owner'] ?? '';
$filter_diff  = $_GET['difficulty'] ?? '';
$sql = "SELECT * FROM nosso2026_goals WHERE 1=1";
if ($filter_owner) $sql .= " AND owner=".$pdo->quote($filter_owner);
if ($filter_diff) $sql .= " AND difficulty=".$pdo->quote($filter_diff);
$sql .= " ORDER BY owner, difficulty, id DESC";
$goals = $pdo->query($sql)->fetchAll();

function goalsBy($owner){
    global $goals; return array_filter($goals, fn($g) => $g['owner']===$owner);
}
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="theme-color" content="#000000">
  <link rel="icon" href="<?= n26_link('icons/icon-192.png') ?>">
  <link rel="manifest" href="<?= n26_link('manifest.json') ?>">
  <link rel="apple-touch-icon" href="<?= n26_link('icons/apple-touch-icon.png') ?>">  <link rel="stylesheet" href="<?= n26_link('responsive.css') ?>">  <title>Metas • Nosso 2026</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={darkMode:'class',theme:{extend:{fontFamily:{sans:['Outfit','sans-serif']}}}};</script>
  <style>
    body{background:#000;color:#fff}
    .glass{background:#0a0a0a;border:1px solid #222}
    .badge{display:inline-block;padding:.2rem .5rem;border-radius:.5rem;border:1px solid #333}
    .b-facil{background:#111;color:#fff}
    .b-medio{background:#222;color:#fff}
    .b-dificil{background:#333;color:#fff}
    .btn{background:#ffffff;color:#000;padding:.5rem 1rem;border-radius:.5rem;font-weight:700}
  </style>
</head>
<body class="min-h-screen" style="background:#000;color:#fff">
  <?php include __DIR__.'/_nav.php'; ?>
  <main class="max-w-7xl mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-3xl font-bold">Metas 2026</h1>
      <button onclick="openModal()" class="btn">🌟 + Meta</button>
    </div>

    <div class="grid md:grid-cols-3 gap-8">
    <?php foreach ([['nosso','Nossas'],['ele','Marcos'],['ela','Luiza']] as [$owner,$label]): ?>
    <section class="glass p-6 rounded-2xl">
      <h2 class="text-xl font-bold mb-4"><?= $label ?></h2>
      <div class="space-y-4">
        <?php foreach (goalsBy($owner) as $g): ?>
        <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-4 cursor-pointer hover:bg-slate-800/60" onclick="openEdit(<?= htmlspecialchars(json_encode($g)) ?>)">
          <p class="font-semibold mb-2"><?= htmlspecialchars($g['title']) ?></p>
          <p class="text-xs text-slate-400 mb-3">Dificuldade: <span class="badge b-<?= $g['difficulty'] ?>"><?= $g['difficulty'] ?></span></p>
          <div class="flex items-center gap-3">
            <div class="flex-1 bg-black rounded-full h-2">
              <div class="bg-blue-500 h-2 rounded-full" style="width:<?= $g['progress'] ?>%"></div>
            </div>
            <span class="text-sm font-bold"><?= $g['progress'] ?>%</span>
          </div>
        </div>
        <?php endforeach; ?>
        </div>
      </section>
      <?php endforeach; ?>
      </div>

      <!-- Modal de Meta -->
      <div id="goalModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:100;" onclick="closeModal()">
        <div class="glass rounded-2xl p-6 max-w-lg mx-auto mt-20" onclick="event.stopPropagation()">
          <h2 class="text-2xl font-bold mb-4" id="modalTitle">Adicionar Meta</h2>
          <form method="post" id="goalForm">
            <input type="hidden" name="id" id="goalId">
            <input type="hidden" name="action" id="goalAction" value="add">
            <div class="mb-4">
              <label class="block text-sm font-bold mb-2">Para quem</label>
              <select name="owner" id="goalOwner" class="w-full bg-black border border-[#222] rounded-xl p-3 text-white">
                <option value="nosso">Nossas</option>
                <option value="ele">Marcos</option>
                <option value="ela">Luiza</option>
              </select>
            </div>
            <div class="mb-4">
              <label class="block text-sm font-bold mb-2">Dificuldade</label>
              <select name="difficulty" id="goalDifficulty" class="w-full bg-black border border-[#222] rounded-xl p-3 text-white">
                <option value="facil">Fácil</option>
                <option value="medio">Médio</option>
                <option value="dificil">Difícil</option>
              </select>
            </div>
            <div class="mb-4">
              <label class="block text-sm font-bold mb-2">Título</label>
              <input name="title" id="goalTitle" class="w-full bg-black border border-[#222] rounded-xl p-3 text-white" placeholder="Ex.: Correr 10k" required>
            </div>
            <div class="flex gap-3">
              <button type="submit" class="btn flex-1">💾 Salvar</button>
              <button type="button" id="deleteBtn" style="display:none" onclick="deleteGoal()" class="btn" style="background:#dc2626">Excluir</button>
              <button type="button" onclick="closeModal()" class="btn" style="background:#333">Fechar</button>
            </div>
          </form>
        </div>
      </div>
</body>
</html>

  <script>
    function openModal() {
      document.getElementById('modalTitle').textContent = 'Adicionar Meta';
      document.getElementById('goalForm').reset();
      document.getElementById('goalAction').value = 'add';
      document.getElementById('goalId').value = '';
      document.getElementById('goalOwner').value = 'nosso';
      document.getElementById('deleteBtn').style.display = 'none';
      const today = new Date().toISOString().split('T')[0];
      document.getElementById('goalModal').style.display = 'block';
    }
    function openEdit(goal) {
      document.getElementById('modalTitle').textContent = 'Editar Meta';
      document.getElementById('goalId').value = goal.id;
      document.getElementById('goalTitle').value = goal.title;
      document.getElementById('goalDifficulty').value = goal.difficulty;
       document.getElementById('goalOwner').value = goal.owner;
      document.getElementById('goalAction').value = 'edit';
      document.getElementById('deleteBtn').style.display = 'block';
      document.getElementById('goalModal').style.display = 'block';
    }
    function closeModal() {
      document.getElementById('goalModal').style.display = 'none';
    }
    function deleteGoal() {
      if(confirm('Remover meta?')) {
        const form = document.getElementById('goalForm');
        const id = document.getElementById('goalId').value;
        form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' + id + '">';
        form.submit();
      }
    }
  </script>
