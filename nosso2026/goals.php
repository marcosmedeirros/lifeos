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
  <title>Metas • Nosso 2026</title>
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
    .btn{background:#fff;color:#000;padding:.5rem 1rem;border-radius:.5rem;font-weight:700}
  </style>
</head>
<body class="min-h-screen" style="background:#000;color:#fff">
  <?php include __DIR__.'/_nav.php'; ?>
  <main class="max-w-6xl mx-auto px-4 py-8 space-y-8">
    <section class="glass p-6 rounded-2xl">
      <h2 class="text-2xl font-bold mb-4">Adicionar Meta</h2>
      <form method="post" class="grid md:grid-cols-4 gap-3">
        <input type="hidden" name="action" value="add">
        <div>
          <label class="text-sm">Para quem</label>
          <select name="owner" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2">
            <option value="ela">Ela</option>
            <option value="ele">Ele</option>
            <option value="nosso">Nosso</option>
          </select>
        </div>
        <div>
          <label class="text-sm">Dificuldade</label>
          <select name="difficulty" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2">
            <option value="facil">Fácil</option>
            <option value="medio">Médio</option>
            <option value="dificil">Difícil</option>
          </select>
        </div>
        <div class="md:col-span-2">
          <label class="text-sm">Título</label>
          <input name="title" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2" placeholder="Ex.: Correr 10k juntos" required>
        </div>
        <div class="md:col-span-4">
          <button class="btn">Adicionar</button>
        </div>
      </form>
    </section>

    <section class="glass p-6 rounded-2xl">
      <h2 class="text-xl font-bold mb-4">Filtros</h2>
      <form method="get" class="grid md:grid-cols-4 gap-3">
        <div>
          <label class="text-sm">Para quem</label>
          <select name="owner" class="w-full bg-black border border-[#222] rounded-xl p-2 text-white">
            <option value="">Todos</option>
            <option value="ela" <?= $filter_owner==='ela'?'selected':'' ?>>Ela</option>
            <option value="ele" <?= $filter_owner==='ele'?'selected':'' ?>>Ele</option>
            <option value="nosso" <?= $filter_owner==='nosso'?'selected':'' ?>>Nosso</option>
          </select>
        </div>
        <div>
          <label class="text-sm">Dificuldade</label>
          <select name="difficulty" class="w-full bg-black border border-[#222] rounded-xl p-2 text-white">
            <option value="">Todas</option>
            <option value="facil" <?= $filter_diff==='facil'?'selected':'' ?>>Fácil</option>
            <option value="medio" <?= $filter_diff==='medio'?'selected':'' ?>>Médio</option>
            <option value="dificil" <?= $filter_diff==='dificil'?'selected':'' ?>>Difícil</option>
          </select>
        </div>
        <div class="md:col-span-2 flex items-end">
          <button class="btn">Aplicar</button>
        </div>
      </form>
    </section>

    <?php foreach ([['ela','Metas dela'],['ele','Metas dele'],['nosso','Metas nossas']] as [$owner,$label]): ?>
    <section class="glass p-6 rounded-2xl">
      <h2 class="text-xl font-bold mb-4"><?= $label ?></h2>
      <div class="grid md:grid-cols-2 gap-4">
        <?php foreach (goalsBy($owner) as $g): ?>
        <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-4">
          <div class="flex justify-between items-center mb-2">
            <p class="font-semibold"><?= htmlspecialchars($g['title']) ?></p>
            <form method="post" onsubmit="return confirm('Remover meta?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $g['id'] ?>">
              <button class="text-rose-400 hover:text-rose-300">Remover</button>
            </form>
          </div>
          <p class="text-xs text-slate-400 mb-3">Dificuldade: <span class="badge b-<?= $g['difficulty'] ?>"><?= $g['difficulty'] ?></span></p>
          <form method="post" class="flex items-center gap-3">
            <input type="hidden" name="action" value="progress">
            <input type="hidden" name="id" value="<?= $g['id'] ?>">
            <input type="range" min="0" max="100" name="progress" value="<?= $g['progress'] ?>" class="flex-1">
            <span class="text-sm font-bold"><?= $g['progress'] ?>%</span>
            <button class="btn">Salvar</button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endforeach; ?>
  </main>
</body>
</html>
