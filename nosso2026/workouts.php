<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $date = $_POST['workout_date'];
        $stmt = $pdo->prepare("INSERT INTO nosso2026_workouts (name, workout_date, month, year, done) VALUES (?,?,?,?,?)");
        $stmt->execute([trim($_POST['name']), $date, intval(date('m', strtotime($date))), intval(date('Y', strtotime($date))), isset($_POST['done'])?1:0]);
    } elseif (isset($_POST['toggle'])) {
        $w = $pdo->prepare("SELECT done FROM nosso2026_workouts WHERE id=?")->execute([intval($_POST['id'])]);
        $w = $w->fetch();
        $pdo->prepare("UPDATE nosso2026_workouts SET done=? WHERE id=?")->execute([$w['done']?0:1, intval($_POST['id'])]);
    }
    header('Location: ' . n26_link('workouts.php'));
    exit;
}

// Mês atual
$currentMonth = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$currentYear = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// Workouts do mês
$workoutsQuery = $pdo->prepare("SELECT * FROM nosso2026_workouts WHERE year=? AND month=? ORDER BY workout_date ASC");
$workoutsQuery->execute([$currentYear, $currentMonth]);
$workouts = $workoutsQuery->fetchAll();

// Array de workouts por dia
$workoutsByDay = [];
foreach($workouts as $w) {
    $day = date('j', strtotime($w['workout_date']));
    if(!isset($workoutsByDay[$day])) $workoutsByDay[$day] = [];
    $workoutsByDay[$day][] = $w;
}

// Calcular dias do mês
$firstDay = mktime(0, 0, 0, $currentMonth, 1, $currentYear);
$daysInMonth = date('t', $firstDay);
$dayOfWeek = date('w', $firstDay);
$prevMonth = $currentMonth == 1 ? 12 : $currentMonth - 1;
$prevYear = $currentMonth == 1 ? $currentYear - 1 : $currentYear;
$nextMonth = $currentMonth == 12 ? 1 : $currentMonth + 1;
$nextYear = $currentMonth == 12 ? $currentYear + 1 : $currentYear;

$monthNames = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

// Stats
$totalWorkouts = count($workouts);
$doneWorkouts = count(array_filter($workouts, fn($w) => $w['done']));
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Treinos • Nosso 2026</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body { background:#000; color:#fff; font-family:system-ui,-apple-system,sans-serif; }
    .glass { background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.06); }
    .btn { background:#fff; color:#000; padding:0.5rem 1rem; border-radius:0.75rem; font-weight:600; transition:all 0.2s; display:inline-block; text-align:center; border:0; cursor:pointer; }
    .btn:hover { background:#e5e5e5; transform: translateY(-1px); }
    .day-cell { min-height:90px; border:1px solid #222; }
    .day-cell:hover { background:rgba(255,255,255,0.03); }
  </style>
</head>
<body>
  <?php include __DIR__.'/_nav.php'; ?>
  
  <main class="max-w-7xl mx-auto px-4 py-10">
    <!-- Stats + Navegação -->
    <div class="flex justify-between items-center mb-6">
      <div>
        <h1 class="text-3xl font-bold"><?= $monthNames[$currentMonth] ?> <?= $currentYear ?></h1>
        <p class="text-sm text-[#999]">Concluídos: <span class="text-white font-bold"><?= $doneWorkouts ?>/<?= $totalWorkouts ?></span></p>
      </div>
      <div class="flex gap-2">
        <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="btn">← Anterior</a>
        <a href="?month=<?= date('m') ?>&year=<?= date('Y') ?>" class="btn">Hoje</a>
        <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="btn">Próximo →</a>
      </div>
    </div>

    <!-- Calendário -->
    <div class="glass rounded-2xl p-6 mb-8">
      <div class="grid grid-cols-7 gap-2 mb-2">
        <?php foreach(['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $d): ?>
          <div class="text-center font-bold text-sm text-[#999]"><?= $d ?></div>
        <?php endforeach; ?>
      </div>
      <div class="grid grid-cols-7 gap-2">
        <?php
        for($i = 0; $i < $dayOfWeek; $i++):
          echo '<div class="day-cell rounded-lg"></div>';
        endfor;
        
        for($day = 1; $day <= $daysInMonth; $day++):
          $isToday = ($day == date('j') && $currentMonth == date('m') && $currentYear == date('Y'));
          $hasWorkouts = isset($workoutsByDay[$day]);
        ?>
          <div class="day-cell rounded-lg p-2 <?= $isToday ? 'border-white' : '' ?>">
            <div class="font-bold text-sm <?= $isToday ? 'text-white' : 'text-[#999]' ?>"><?= $day ?></div>
            <?php if($hasWorkouts): ?>
              <div class="mt-1 space-y-1">
                <?php foreach($workoutsByDay[$day] as $w): ?>
                  <form method="post" class="text-xs rounded px-1 py-0.5 truncate cursor-pointer <?= $w['done'] ? 'bg-white text-black' : 'bg-[#333] text-[#999]' ?>" onclick="this.submit()">
                    <input type="hidden" name="toggle" value="1">
                    <input type="hidden" name="id" value="<?= $w['id'] ?>">
                    <button type="submit" class="w-full text-left"><?= htmlspecialchars($w['name']) ?></button>
                  </form>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endfor; ?>
      </div>
    </div>

    <!-- Adicionar Treino -->
    <section class="glass rounded-2xl p-6">
      <h2 class="text-xl font-bold mb-4">Adicionar Treino</h2>
      <form method="post" class="grid md:grid-cols-4 gap-3">
        <input type="hidden" name="add" value="1">
        <input name="name" class="md:col-span-2 bg-black border border-[#222] rounded-xl p-3 text-white" placeholder="Nome do treino" required>
        <input name="workout_date" type="date" class="bg-black border border-[#222] rounded-xl p-3 text-white" required>
        <button class="btn">Adicionar</button>
      </form>
    </section>
  </main>
</body>
</html>
