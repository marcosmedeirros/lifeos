<?php
require_once __DIR__ . '/_bootstrap.php';

// Criar tabela receitas se não existir
$pdo->exec("CREATE TABLE IF NOT EXISTS nosso2026_recipes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    ingredients TEXT,
    instructions TEXT,
    prep_time INT,
    servings INT,
    category VARCHAR(50),
    owner VARCHAR(20) DEFAULT 'nosso',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $stmt = $pdo->prepare("INSERT INTO nosso2026_recipes (name, ingredients, instructions, prep_time, servings, category, owner) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([
            trim($_POST['name']),
            trim($_POST['ingredients']),
            trim($_POST['instructions']),
            $_POST['prep_time'] ? intval($_POST['prep_time']) : NULL,
            $_POST['servings'] ? intval($_POST['servings']) : NULL,
            $_POST['category'],
            $_POST['owner']
        ]);
    } elseif (isset($_POST['delete'])) {
        $pdo->prepare("DELETE FROM nosso2026_recipes WHERE id=?")->execute([intval($_POST['id'])]);
    }
    header('Location: ' . n26_link('food.php'));
    exit;
}

// Filtros
$category = $_GET['category'] ?? '';
$owner = $_GET['owner'] ?? '';

$sql = "SELECT * FROM nosso2026_recipes WHERE 1=1";
$params = [];
if ($category) { $sql .= " AND category=?"; $params[] = $category; }
if ($owner) { $sql .= " AND owner=?"; $params[] = $owner; }
$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$recipes = $stmt->fetchAll();
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
  <title>Receitas • Nosso 2026</title>
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
    <!-- Filtros -->
    <div class="flex gap-3 mb-6 flex-wrap">
      <a href="<?= n26_link('food.php') ?>" class="btn <?= !$category && !$owner ? 'bg-white' : 'bg-[#222]' ?>">Todas</a>
      <a href="?category=cafe" class="btn <?= $category=='cafe' ? 'bg-white' : 'bg-[#222]' ?>">Café</a>
      <a href="?category=almoco" class="btn <?= $category=='almoco' ? 'bg-white' : 'bg-[#222]' ?>">Almoço</a>
      <a href="?category=jantar" class="btn <?= $category=='jantar' ? 'bg-white' : 'bg-[#222]' ?>">Jantar</a>
      <a href="?category=lanche" class="btn <?= $category=='lanche' ? 'bg-white' : 'bg-[#222]' ?>">Lanche</a>
      <span class="border-l border-[#444] mx-2"></span>
      <a href="?owner=ela" class="btn <?= $owner=='ela' ? 'bg-white' : 'bg-[#222]' ?>">Dela</a>
      <a href="?owner=ele" class="btn <?= $owner=='ele' ? 'bg-white' : 'bg-[#222]' ?>">Dele</a>
      <a href="?owner=nosso" class="btn <?= $owner=='nosso' ? 'bg-white' : 'bg-[#222]' ?>">Nosso</a>
    </div>

    <!-- Adicionar Receita -->
    <section class="glass rounded-2xl p-6 mb-8">
      <h2 class="text-2xl font-bold mb-4">Nova Receita</h2>
      <form method="post" class="space-y-4">
        <input type="hidden" name="add" value="1">
        <div class="grid md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-bold mb-2">Nome da Receita</label>
            <input name="name" class="w-full bg-black border border-[#222] rounded-xl p-3 text-white" placeholder="Ex: Frango grelhado" required>
          </div>
          <div class="grid grid-cols-3 gap-3">
            <div>
              <label class="block text-sm font-bold mb-2">Categoria</label>
              <select name="category" class="w-full bg-black border border-[#222] rounded-xl p-3 text-white">
                <option value="cafe">Café</option>
                <option value="almoco">Almoço</option>
                <option value="jantar">Jantar</option>
                <option value="lanche">Lanche</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-bold mb-2">Porções</label>
              <input type="number" name="servings" class="w-full bg-black border border-[#222] rounded-xl p-3 text-white" placeholder="2">
            </div>
            <div>
              <label class="block text-sm font-bold mb-2">Tempo (min)</label>
              <input type="number" name="prep_time" class="w-full bg-black border border-[#222] rounded-xl p-3 text-white" placeholder="30">
            </div>
          </div>
        </div>
        <div>
          <label class="block text-sm font-bold mb-2">Ingredientes</label>
          <textarea name="ingredients" rows="4" class="w-full bg-black border border-[#222] rounded-xl p-3 text-white" placeholder="Liste os ingredientes, um por linha"></textarea>
        </div>
        <div>
          <label class="block text-sm font-bold mb-2">Modo de Preparo</label>
          <textarea name="instructions" rows="4" class="w-full bg-black border border-[#222] rounded-xl p-3 text-white" placeholder="Descreva o passo a passo"></textarea>
        </div>
        <div class="flex gap-3 items-center">
          <label class="font-bold">Criado por:</label>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="radio" name="owner" value="ela" class="w-4 h-4"> Ela
          </label>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="radio" name="owner" value="ele" class="w-4 h-4"> Ele
          </label>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="radio" name="owner" value="nosso" class="w-4 h-4" checked> Nosso
          </label>
        </div>
        <button class="btn">💾 Salvar Receita</button>
      </form>
    </section>

    <!-- Lista de Receitas -->
    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php foreach($recipes as $r): ?>
      <div class="glass rounded-2xl p-6">
        <div class="flex justify-between items-start mb-3">
          <div>
            <h3 class="text-xl font-bold"><?= htmlspecialchars($r['name']) ?></h3>
            <p class="text-xs text-[#999]"><?= ucfirst($r['category']) ?> • <?= ucfirst($r['owner']) ?></p>
          </div>
          <form method="post" class="inline">
            <input type="hidden" name="delete" value="1">
            <input type="hidden" name="id" value="<?= $r['id'] ?>">
            <button class="text-red-500 hover:text-red-400 text-sm">✕</button>
          </form>
        </div>
        
        <?php if($r['servings'] || $r['prep_time']): ?>
        <div class="flex gap-4 mb-3 text-sm text-[#999]">
          <?php if($r['servings']): ?><span>🍽 <?= $r['servings'] ?> porções</span><?php endif; ?>
          <?php if($r['prep_time']): ?><span>⏱ <?= $r['prep_time'] ?>min</span><?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if($r['ingredients']): ?>
        <div class="mb-3">
          <p class="text-sm font-bold mb-1">Ingredientes:</p>
          <p class="text-sm text-[#999] whitespace-pre-line"><?= htmlspecialchars($r['ingredients']) ?></p>
        </div>
        <?php endif; ?>
        
        <?php if($r['instructions']): ?>
        <div>
          <p class="text-sm font-bold mb-1">Preparo:</p>
          <p class="text-sm text-[#999] whitespace-pre-line"><?= htmlspecialchars($r['instructions']) ?></p>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      
      <?php if(empty($recipes)): ?>
        <div class="col-span-3 text-center py-12 text-[#999]">
          <p>Nenhuma receita encontrada</p>
          <p class="text-sm">Adicione sua primeira receita acima</p>
        </div>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
