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
      } elseif (isset($_POST['edit'])) {
        $stmt = $pdo->prepare("UPDATE nosso2026_workouts SET name=?, workout_date=?, month=?, year=? WHERE id=?");
        $date = $_POST['workout_date'];
        $stmt->execute([trim($_POST['name']), $date, intval(date('m', strtotime($date))), intval(date('Y', strtotime($date))), intval($_POST['id'])]);
      } elseif (isset($_POST['delete'])) {
        $stmt = $pdo->prepare("DELETE FROM nosso2026_workouts WHERE id=?");
        $stmt->execute([intval($_POST['id'])]);
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
  <meta name="theme-color" content="#000000">
  <link rel="icon" href="<?= n26_link('icons/icon-192.png') ?>">
  <link rel="manifest" href="<?= n26_link('manifest.json') ?>">
  <link rel="apple-touch-icon" href="<?= n26_link('icons/apple-touch-icon.png') ?>">  <link rel="stylesheet" href="<?= n26_link('responsive.css') ?>">  <title>Treinos • Nosso 2026</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body { background:#000; color:#fff; font-family:system-ui,-apple-system,sans-serif; }
    .glass { background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.06); }
    .btn { background:#ffffff; color:#000; padding:0.5rem 1rem; border-radius:0.75rem; font-weight:600; transition:all 0.2s; display:inline-block; text-align:center; border:0; cursor:pointer; }
    .btn:hover { background:#e0e0e0; transform: translateY(-1px); }
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
        <button onclick="openModal()" class="btn">💪 + Treino</button>
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
                  <div class="text-xs rounded px-1 py-0.5 truncate cursor-pointer <?= $w['done'] ? 'bg-white text-black' : 'bg-[#333] text-[#999]' ?>" onclick="openEdit(<?= htmlspecialchars(json_encode($w)) ?>)"><?= htmlspecialchars($w['name']) ?></div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endfor; ?>
      </div>
    </div>

    <!-- Modal de Treino -->
    <div id="treinoModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:100;" onclick="closeModal()">
      <div class="glass rounded-2xl p-6 max-w-md mx-auto mt-20" onclick="event.stopPropagation()">
        <h2 class="text-2xl font-bold mb-4" id="modalTitle">Adicionar Treino</h2>
        <form method="post" id="treinoForm">
          <input type="hidden" name="add" id="formAction" value="1">
          <input type="hidden" name="id" id="workoutId">
          <div class="mb-4">
            <label class="block text-sm font-bold mb-2">Nome do treino</label>
            <input name="name" id="workoutName" class="w-full bg-black border border-[#222] rounded-xl p-3 text-white" required>
          </div>
          <div class="mb-4">
            <label class="block text-sm font-bold mb-2">Data</label>
            <input name="workout_date" id="workoutDate" type="date" class="w-full bg-black border border-[#222] rounded-xl p-3 text-white" required>
          </div>
          <div class="flex gap-3">
            <button type="submit" class="btn flex-1">💾 Salvar</button>
            <button type="button" id="deleteBtn" style="display:none" onclick="deleteWorkout()" class="btn" style="background:#dc2626">Excluir</button>
            <button type="button" onclick="closeModal()" class="btn" style="background:#333">Fechar</button>
          </div>
        </form>
      </div>
    </div>
  </main>

  <script>
      function openModal() {
        document.getElementById('modalTitle').textContent = 'Adicionar Treino';
        document.getElementById('treinoForm').reset();
        document.getElementById('formAction').name = 'add';
        document.getElementById('formAction').value = '1';
        document.getElementById('workoutId').value = '';
        document.getElementById('deleteBtn').style.display = 'none';
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('workoutDate').value = today;
        document.getElementById('treinoModal').style.display = 'block';
      }
      function openEdit(workout) {
        document.getElementById('modalTitle').textContent = 'Editar Treino';
        document.getElementById('workoutId').value = workout.id;
        document.getElementById('workoutName').value = workout.name;
        document.getElementById('workoutDate').value = workout.workout_date.split(' ')[0];
        document.getElementById('workoutDone').checked = workout.done == 1;
        document.getElementById('formAction').name = 'edit';
        document.getElementById('formAction').value = '1';
        document.getElementById('deleteBtn').style.display = 'block';
        document.getElementById('treinoModal').style.display = 'block';
      }
      function closeModal() {
        document.getElementById('treinoModal').style.display = 'none';
      }
      function deleteWorkout() {
        if(confirm('Remover treino?')) {
          const form = document.getElementById('treinoForm');
          const id = document.getElementById('workoutId').value;
          form.innerHTML = '<input type="hidden" name="delete" value="1"><input type="hidden" name="id" value="' + id + '">';
          form.submit();
        }
      }
  </script>
</body>
</html>
