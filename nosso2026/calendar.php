<?php
require_once __DIR__ . '/_bootstrap.php';

// POST: add/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete'])) {
        $pdo->prepare("DELETE FROM events WHERE id=? AND group_id='nosso2026'")->execute([intval($_POST['id'])]);
    } elseif (isset($_POST['update'])) {
        $stmt = $pdo->prepare("UPDATE events SET title=?, start_date=?, description=? WHERE id=? AND group_id='nosso2026'");
        $stmt->execute([trim($_POST['title']), $_POST['start_date'], trim($_POST['description']), intval($_POST['id'])]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO events (user_id, group_id, title, start_date, description) VALUES (1, 'nosso2026', ?, ?, ?)");
        $stmt->execute([trim($_POST['title']), $_POST['start_date'], trim($_POST['description'])]);
    }
    header('Location: ' . n26_link('calendar.php'));
    exit;
}

// Mês atual
$currentMonth = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$currentYear = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// Eventos do mês
$eventsQuery = $pdo->prepare("SELECT * FROM events WHERE group_id='nosso2026' AND YEAR(start_date)=? AND MONTH(start_date)=? ORDER BY start_date ASC");
$eventsQuery->execute([$currentYear, $currentMonth]);
$events = $eventsQuery->fetchAll();

// Array de eventos por dia
$eventsByDay = [];
foreach($events as $e) {
    $day = date('j', strtotime($e['start_date']));
    if(!isset($eventsByDay[$day])) $eventsByDay[$day] = [];
    $eventsByDay[$day][] = $e;
}

// Calcular dias do mês
$firstDay = mktime(0, 0, 0, $currentMonth, 1, $currentYear);
$daysInMonth = date('t', $firstDay);
$dayOfWeek = date('w', $firstDay); // 0=domingo
$prevMonth = $currentMonth == 1 ? 12 : $currentMonth - 1;
$prevYear = $currentMonth == 1 ? $currentYear - 1 : $currentYear;
$nextMonth = $currentMonth == 12 ? 1 : $currentMonth + 1;
$nextYear = $currentMonth == 12 ? $currentYear + 1 : $currentYear;

$monthNames = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="theme-color" content="#000000">
  <link rel="icon" href="<?= n26_link('icons/icon-192.png') ?>">
  <link rel="manifest" href="<?= n26_link('manifest.json') ?>">
  <link rel="apple-touch-icon" href="<?= n26_link('icons/apple-touch-icon.png') ?>">  <link rel="stylesheet" href="<?= n26_link('responsive.css') ?>">  <title>Calendário • Nosso 2026</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body { background:#000; color:#fff; font-family:system-ui,-apple-system,sans-serif; }
    .glass { background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.06); }
    .btn { background:#fff; color:#000; padding:0.5rem 1rem; border-radius:0.75rem; font-weight:600; transition:all 0.2s; display:inline-block; text-align:center; border:0; cursor:pointer; }
    .btn:hover { background:#e5e5e5; transform: translateY(-1px); }
    .day-cell { min-height:100px; border:1px solid #222; }
    .day-cell:hover { background:rgba(255,255,255,0.03); }
  </style>
</head>
<body>
  <?php include __DIR__.'/_nav.php'; ?>
  
  <main class="max-w-7xl mx-auto px-4 py-10">
    <!-- Cabeçalho do Calendário -->
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-3xl font-bold"><?= $monthNames[$currentMonth] ?> <?= $currentYear ?></h1>
      <div class="flex gap-2">
        <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="btn">← Anterior</a>
        <a href="?month=<?= date('m') ?>&year=<?= date('Y') ?>" class="btn">Hoje</a>
        <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="btn">Próximo →</a>
        <button onclick="openModal()" class="btn">+ Evento</button>
      </div>
    </div>

    <!-- Grade do Calendário -->
    <div class="glass rounded-2xl p-6 mb-8">
      <div class="grid grid-cols-7 gap-2 mb-2">
        <?php foreach(['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $d): ?>
          <div class="text-center font-bold text-sm text-[#999]"><?= $d ?></div>
        <?php endforeach; ?>
      </div>
      <div class="grid grid-cols-7 gap-2">
        <?php
        // Células vazias antes do primeiro dia
        for($i = 0; $i < $dayOfWeek; $i++):
          echo '<div class="day-cell rounded-lg"></div>';
        endfor;
        
        // Dias do mês
        for($day = 1; $day <= $daysInMonth; $day++):
          $isToday = ($day == date('j') && $currentMonth == date('m') && $currentYear == date('Y'));
          $hasEvents = isset($eventsByDay[$day]);
        ?>
          <div class="day-cell rounded-lg p-2 <?= $isToday ? 'border-white' : '' ?>" onclick="openDay(<?= $day ?>)">
            <div class="font-bold text-sm <?= $isToday ? 'text-white' : 'text-[#999]' ?>"><?= $day ?></div>
            <?php if($hasEvents): ?>
              <div class="mt-2 space-y-1">
                <?php foreach($eventsByDay[$day] as $e): ?>
                  <div class="text-xs bg-white text-black rounded px-1 py-0.5 truncate cursor-pointer" onclick="event.stopPropagation(); openEvent(<?= htmlspecialchars(json_encode($e)) ?>)">
                    <?= htmlspecialchars($e['title']) ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endfor; ?>
      </div>
    </div>

    <!-- Adicionar Evento -->
    <section class="glass rounded-2xl p-6">
      <h2 class="text-xl font-bold mb-4">Adicionar Evento</h2>
      <form method="post" class="grid md:grid-cols-5 gap-3">
        <input name="title" class="md:col-span-2 bg-black border border-[#222] rounded-xl p-3 text-white" placeholder="Título" required>
        <input name="start_date" type="datetime-local" class="bg-black border border-[#222] rounded-xl p-3 text-white" required>
        <input name="description" class="bg-black border border-[#222] rounded-xl p-3 text-white" placeholder="Descrição">
        <button class="btn">Adicionar</button>
      </form>
    </section>

    <!-- Modal de Evento (JavaScript) -->
    <div id="eventModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:100;" onclick="closeModal()">
      <div class="glass rounded-2xl p-6 max-w-lg mx-auto mt-20" onclick="event.stopPropagation()">
        <form method="post" id="eventForm">
          <input type="hidden" id="eventAction" name="add" value="1">
          <input type="hidden" name="id" id="eventId">
          <div class="mb-4">
            <label class="block text-sm font-bold mb-2">Título</label>
            <input name="title" id="eventTitle" class="w-full bg-black border border-[#222] rounded-xl p-3 text-white" required>
          </div>
          <div class="mb-4">
            <label class="block text-sm font-bold mb-2">Data e Hora</label>
            <input name="start_date" id="eventDate" type="datetime-local" class="w-full bg-black border border-[#222] rounded-xl p-3 text-white" required>
          </div>
          <div class="mb-4">
            <label class="block text-sm font-bold mb-2">Descrição</label>
            <textarea name="description" id="eventDesc" class="w-full bg-black border border-[#222] rounded-xl p-3 text-white" rows="3"></textarea>
          </div>
          <div class="flex gap-3">
            <button type="submit" class="btn flex-1">Salvar</button>
            <button type="button" id="deleteBtn" style="display:none" onclick="deleteEvent()" class="btn" style="background:#dc2626">Excluir</button>
            <button type="button" onclick="closeModal()" class="btn" style="background:#333">Fechar</button>
          </div>
        </form>
      </div>
    </div>
  </main>

  <script>
    function openModal() {
      document.getElementById('eventId').value = '';
      document.getElementById('eventTitle').value = '';
      document.getElementById('eventDesc').value = '';
      const now = new Date();
      const year = now.getFullYear();
      const month = String(now.getMonth() + 1).padStart(2, '0');
      const day = String(now.getDate()).padStart(2, '0');
      const hours = String(now.getHours()).padStart(2, '0');
      const minutes = String(now.getMinutes()).padStart(2, '0');
      document.getElementById('eventDate').value = `${year}-${month}-${day}T${hours}:${minutes}`;
      document.getElementById('eventAction').name = 'add';
      document.getElementById('deleteBtn').style.display = 'none';
      document.getElementById('eventModal').style.display = 'block';
    }
    function openEvent(e) {
      document.getElementById('eventId').value = e.id;
      document.getElementById('eventTitle').value = e.title;
      document.getElementById('eventDate').value = e.start_date.replace(' ', 'T').substring(0, 16);
      document.getElementById('eventDesc').value = e.description || '';
      document.getElementById('eventAction').name = 'update';
      document.getElementById('deleteBtn').style.display = 'block';
      document.getElementById('eventModal').style.display = 'block';
    }
    function openDay(day) {
      const month = String(<?= $currentMonth ?>).padStart(2, '0');
      const year = <?= $currentYear ?>;
      const hours = '09';
      const minutes = '00';
      document.getElementById('eventId').value = '';
      document.getElementById('eventTitle').value = '';
      document.getElementById('eventDesc').value = '';
      document.getElementById('eventDate').value = `${year}-${month}-${String(day).padStart(2,'0')}T${hours}:${minutes}`;
      document.getElementById('eventAction').name = 'add';
      document.getElementById('deleteBtn').style.display = 'none';
      document.getElementById('eventModal').style.display = 'block';
    }
    function closeModal() {
      document.getElementById('eventModal').style.display = 'none';
    }
    function deleteEvent() {
      if(confirm('Excluir este evento?')) {
        const form = document.getElementById('eventForm');
        form.innerHTML = '<input type="hidden" name="delete" value="1"><input type="hidden" name="id" value="' + document.getElementById('eventId').value + '">';
        form.submit();
      }
    }
  </script>
</body>
</html>
