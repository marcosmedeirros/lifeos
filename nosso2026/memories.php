<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
  $id = intval($_POST['id']);
  $mem = $pdo->prepare("SELECT image_path FROM nosso2026_memories WHERE id=?");
  $mem->execute([$id]);
  $m = $mem->fetch();
  if ($m && $m['image_path']) {
    $filePath = __DIR__ . '/..' . str_replace('/lifeos', '', $m['image_path']);
    if (file_exists($filePath)) @unlink($filePath);
  }
  $pdo->prepare("DELETE FROM nosso2026_memories WHERE id=?")->execute([$id]);
  header('Location: ' . n26_link('memories.php'));
  exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload'])) {
  $dir = __DIR__ . '/../uploads/nosso2026';
  if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
  if (!empty($_FILES['photo']['name'])) {
    $files = $_FILES['photo'];
    $count = is_array($files['name']) ? count($files['name']) : 0;
    for ($i=0; $i<$count; $i++) {
      if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
      $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
      $safe = 'n26_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
      $target = $dir . '/' . $safe;
      if (move_uploaded_file($files['tmp_name'][$i], $target)) {
        $rel = '/lifeos/uploads/nosso2026/' . $safe;
        if (strpos($_SERVER['HTTP_HOST'],'localhost')===false) {
          $rel = '/uploads/nosso2026/' . $safe;
        }
        $stmt = $pdo->prepare("INSERT INTO nosso2026_memories (owner, title, memory_date, image_path, notes) VALUES (?,?,?,?,?)");
        $stmt->execute(['nosso', '', $_POST['memory_date'] ?? NULL, $rel, '']);
      }
    }
  }
  header('Location: ' . n26_link('memories.php'));
  exit;
}

$items = $pdo->query("SELECT * FROM nosso2026_memories WHERE owner='nosso' ORDER BY memory_date DESC, id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="theme-color" content="#000000">
  <link rel="icon" href="<?= n26_link('icons/icon-192.png') ?>">
  <link rel="manifest" href="<?= n26_link('manifest.json') ?>">
  <link rel="apple-touch-icon" href="<?= n26_link('icons/apple-touch-icon.png') ?>">  <link rel="stylesheet" href="<?= n26_link('responsive.css') ?>">  <title>Memórias • Nosso 2026</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body { background:#000; color:#fff; font-family:system-ui,-apple-system,sans-serif; }
    .glass { background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.06); }
    .btn { background:#ffffff; color:#000; padding:0.5rem 1rem; border-radius:0.75rem; font-weight:600; transition:all 0.2s; display:inline-block; text-align:center; border:0; cursor:pointer; }
    .btn:hover { background:#e0e0e0; transform: translateY(-1px); }
    .memory-card { position:relative; overflow:hidden; border-radius:1rem; transition:transform 0.2s; }
    .memory-card:hover { transform:scale(1.02); }
    .memory-img { width:100%; height:300px; object-fit:cover; }
  </style>
</head>
<body>
  <?php include __DIR__.'/_nav.php'; ?>
  
  <main class="max-w-7xl mx-auto px-4 py-10">
    <!-- Upload -->
    <section class="glass rounded-2xl p-6 mb-8">
      <h2 class="text-2xl font-bold mb-4">Adicionar Memória</h2>
      <form method="post" enctype="multipart/form-data" class="flex gap-3 flex-wrap">
        <input type="hidden" name="upload" value="1">
        <input type="date" name="memory_date" id="memoryDate" class="bg-black border border-[#222] rounded-xl px-4 py-2 text-white" required>
        <input type="file" name="photo[]" multiple accept="image/*" class="flex-1 bg-black border border-[#222] rounded-xl px-4 py-2 text-white" required>
        <button class="btn">📄 Upload</button>
      </form>
      <script>
        document.getElementById('memoryDate').value = new Date().toISOString().split('T')[0];
      </script>
    </section>

    <!-- Galeria Masonry -->
    <div class="grid md:grid-cols-3 gap-6">
      <?php foreach($items as $it): ?>
      <div class="memory-card glass">
        <img src="<?= htmlspecialchars($it['image_path']) ?>" alt="<?= htmlspecialchars($it['title']) ?>" class="memory-img">
        <div class="p-4">
          <div class="flex justify-between items-start mb-2">
            <div class="flex-1">
          <?php if($it['title']): ?>
            <h3 class="font-bold text-lg mb-1"><?= htmlspecialchars($it['title']) ?></h3>
          <?php endif; ?>
          <p class="text-xs text-[#999] mb-2">
            <?= $it['memory_date'] ? date('d/m/Y', strtotime($it['memory_date'])) : '' ?>
          </p>
            </div>
            <form method="post">
              <input type="hidden" name="delete" value="1">
              <input type="hidden" name="id" value="<?= $it['id'] ?>">
              <button class="text-red-400 hover:text-red-300 text-sm" onclick="return confirm('Apagar esta memória?')">🗑️</button>
            </form>
          </div>
          <?php if($it['notes']): ?>
            <p class="text-sm text-[#ccc]"><?= nl2br(htmlspecialchars($it['notes'])) ?></p>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
      
      <?php if(empty($items)): ?>
        <div class="col-span-3 text-center py-20">
          <p class="text-[#999] text-lg mb-2">Nenhuma memória ainda</p>
          <p class="text-sm text-[#666]">Faça upload da primeira foto</p>
        </div>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
