<?php
require_once __DIR__ . '/_bootstrap.php';

// Função para formatar data em formato brasileiro
function formatDateBR($date, $includeTime = false) {
    if (!$date) return '';
    $timestamp = strtotime($date);
    if ($includeTime && date('H:i', $timestamp) != '00:00') {
        return date('d/m/Y', $timestamp) . ' às ' . date('H:i', $timestamp);
    }
    return date('d/m/Y', $timestamp);
}

// Função para converter data BR para formato SQL
function convertDateBRtoSQL($dateBR, $timeBR = null) {
    if (strpos($dateBR, '/') !== false) {
        // Formato DD/MM/YYYY
        list($day, $month, $year) = explode('/', $dateBR);
        $sqlDate = "$year-$month-$day";
    } else {
        // Já está em formato YYYY-MM-DD
        $sqlDate = $dateBR;
    }
    
    if ($timeBR) {
        return $sqlDate . ' ' . $timeBR . ':00';
    }
    return $sqlDate;
}

// POST: add/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete'])) {
        $pdo->prepare("DELETE FROM events WHERE id=? AND group_id='nosso2026'")->execute([intval($_POST['id'])]);
    } elseif (isset($_POST['update'])) {
        // Combinar data e hora para update também
        $startDate = $_POST['start_date'];
        if (isset($_POST['start_time']) && !empty($_POST['start_time'])) {
            $startDate .= ' ' . $_POST['start_time'] . ':00';
        } else {
            $startDate .= ' 00:00:00';
        }
        $stmt = $pdo->prepare("UPDATE events SET title=?, start_date=?, description=? WHERE id=? AND group_id='nosso2026'");
        $stmt->execute([trim($_POST['title']), $startDate, trim($_POST['description']), intval($_POST['id'])]);
    } else {
        // Combinar data e hora se fornecidos separadamente
        $startDate = $_POST['start_date'];
        if (isset($_POST['start_time']) && !empty($_POST['start_time'])) {
            $startDate .= ' ' . $_POST['start_time'] . ':00';
        } else {
            $startDate .= ' 00:00:00';
        }
        $stmt = $pdo->prepare("INSERT INTO events (user_id, group_id, title, start_date, description) VALUES (1, 'nosso2026', ?, ?, ?)");
        $stmt->execute([trim($_POST['title']), $startDate, trim($_POST['description'])]);
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
    .btn { background:#d4af37; color:#000; padding:0.5rem 1rem; border-radius:0.75rem; font-weight:600; transition:all 0.2s; display:inline-block; text-align:center; border:0; cursor:pointer; }
    .btn:hover { background:#c19b1a; transform: translateY(-1px); }
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
        <button onclick="openModal()" class="btn">📅 + Evento</button>
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
                <?php foreach($eventsByDay[$day] as $e): 
                  $eventTime = date('H:i', strtotime($e['start_date']));
                  $showTime = ($eventTime != '00:00');
                ?>
                  <div class="text-xs bg-white text-black rounded px-1 py-0.5 truncate cursor-pointer" onclick="event.stopPropagation(); openEvent(<?= htmlspecialchars(json_encode($e)) ?>)" title="<?= $showTime ? $eventTime . ' - ' : '' ?><?= htmlspecialchars($e['title']) ?>">
                    <?php if($showTime): ?>
                      <span class="font-bold"><?= $eventTime ?></span> 
                    <?php endif; ?>
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
      <h2 class="text-xl font-bold mb-4">➕ Adicionar Evento Rápido</h2>
      <form method="post" class="space-y-3">
        <div class="grid md:grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-semibold mb-1 text-gray-300">Título *</label>
            <input name="title" class="w-full bg-black border border-[#222] rounded-xl p-3 text-white" placeholder="Ex: Aniversário, Dentista..." required>
          </div>
          <div>
            <label class="block text-sm font-semibold mb-1 text-gray-300">Descrição</label>
            <input name="description" class="w-full bg-black border border-[#222] rounded-xl p-3 text-white" placeholder="Opcional">
          </div>
        </div>
        <div class="grid md:grid-cols-3 gap-3">
          <div>
            <label class="block text-sm font-semibold mb-1 text-gray-300">📅 Data *</label>
            <input name="start_date" type="date" class="w-full bg-black border border-[#222] rounded-xl p-3 text-white" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div>
            <label class="block text-sm font-semibold mb-1 text-gray-300">🕐 Hora</label>
            <input name="start_time" type="time" class="w-full bg-black border border-[#222] rounded-xl p-3 text-white" placeholder="Ex: 14:30">
            <p class="text-xs text-gray-500 mt-1">Deixe vazio para evento de dia inteiro</p>
          </div>
          <div class="flex items-end">
            <button class="btn w-full">💾 Adicionar</button>
          </div>
        </div>
      </form>
    </section>

    <!-- Modal de Evento - VERSÃO ATUALIZADA <?= time() ?> -->
    <div id="eventModal" style="display:none !important; position:fixed; top:0; left:0; right:0; bottom:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:9999; overflow-y:auto; padding:20px;" onclick="closeModal()">
      <div style="margin:40px auto; max-width:500px; background:rgba(20,20,40,0.95); backdrop-filter:blur(10px); border:1px solid #333; border-radius:20px; color:white; padding:32px; position:relative;" onclick="event.stopPropagation()">
        <button type="button" onclick="closeModal()" style="position:absolute; top:16px; right:16px; background:transparent; border:none; cursor:pointer; font-size:24px; color:#999; width:32px; height:32px;">
            ❌
        </button>
        <form method="post" id="eventForm">
          <h3 style="font-size:24px; font-weight:bold; margin-bottom:24px; color:#fff;" id="modalTitle">📝 Novo Evento</h3>
          
          <input type="hidden" id="eventAction" name="add" value="1">
          <input type="hidden" name="id" id="eventId">
          
          <!-- CAMPO TÍTULO -->
          <div style="margin-bottom:20px;">
            <label style="display:block; color:#aaa; font-size:13px; margin-bottom:6px;">Título do Evento</label>
            <input name="title" id="eventTitle" type="text" placeholder="Nome do Evento" style="width:100%; background:#000; border:1px solid #444; border-radius:12px; padding:14px; color:white; font-size:16px;" required>
          </div>
          
          <!-- CAMPOS DATA E HORA -->
          <div style="display:grid; grid-template-columns:60% 40%; gap:12px; margin-bottom:20px;">
            <div>
              <label style="display:block; color:#aaa; font-size:13px; margin-bottom:6px;">📅 Data</label>
              <input name="start_date" id="eventDateOnly" type="date" style="width:100%; background:#000; border:1px solid #444; border-radius:12px; padding:14px; color:white; font-size:16px;" required>
            </div>
            <div>
              <label style="display:block; color:#aaa; font-size:13px; margin-bottom:6px;">🕐 Hora</label>
              <input name="start_time" id="eventTimeOnly" type="time" style="width:100%; background:#000; border:1px solid #444; border-radius:12px; padding:14px; color:white; font-size:16px;">
            </div>
          </div>
          
          <!-- CAMPO DESCRIÇÃO -->
          <div style="margin-bottom:24px;">
            <label style="display:block; color:#aaa; font-size:13px; margin-bottom:6px;">Descrição (opcional)</label>
            <textarea name="description" id="eventDesc" placeholder="Adicione detalhes sobre o evento..." style="width:100%; background:#000; border:1px solid #444; border-radius:12px; padding:14px; color:white; font-size:16px; resize:vertical; min-height:80px;" rows="3"></textarea>
          </div>
          
          <!-- BOTÕES -->
          <div style="display:flex; gap:12px;">
            <button type="submit" style="flex:1; background:linear-gradient(to right, #fbbf24, #f97316); color:#000; padding:14px 24px; border-radius:12px; font-weight:bold; border:none; cursor:pointer; font-size:16px;">💾 Salvar</button>
            <button type="button" id="deleteBtn" style="display:none; background:#dc2626; color:#fff; padding:14px 24px; border-radius:12px; font-weight:bold; border:none; cursor:pointer; font-size:16px;" onclick="deleteEvent()">🗑️</button>
          </div>
        </form>
      </div>
    </div>
  </main>

  <script>
    console.log('✅ Script carregado - Modal disponível');
    
    function openModal() {
      console.log('🔓 Abrindo novo evento modal');
      document.getElementById('modalTitle').textContent = '📝 Novo Evento';
      document.getElementById('eventId').value = '';
      document.getElementById('eventTitle').value = '';
      document.getElementById('eventDesc').value = '';
      const now = new Date();
      const year = now.getFullYear();
      const month = String(now.getMonth() + 1).padStart(2, '0');
      const day = String(now.getDate()).padStart(2, '0');
      const hours = String(now.getHours()).padStart(2, '0');
      const minutes = String(now.getMinutes()).padStart(2, '0');
      document.getElementById('eventDateOnly').value = `${year}-${month}-${day}`;
      document.getElementById('eventTimeOnly').value = `${hours}:${minutes}`;
      document.getElementById('eventAction').name = 'add';
      document.getElementById('deleteBtn').style.display = 'none';
      document.getElementById('eventModal').style.display = 'flex';
      document.getElementById('eventModal').style.alignItems = 'center';
    }
    
    function openEvent(e) {
      document.getElementById('modalTitle').textContent = '✏️ Editar Evento';
      document.getElementById('eventId').value = e.id;
      document.getElementById('eventTitle').value = e.title;
      
      // Separar data e hora
      const dateTimeParts = e.start_date.split(' ');
      const datePart = dateTimeParts[0];
      const timePart = dateTimeParts[1] ? dateTimeParts[1].substring(0, 5) : '';
      
      document.getElementById('eventDateOnly').value = datePart;
      document.getElementById('eventTimeOnly').value = (timePart && timePart !== '00:00') ? timePart : '';
      document.getElementById('eventDesc').value = e.description || '';
      document.getElementById('eventAction').name = 'update';
      document.getElementById('deleteBtn').style.display = 'block';
      document.getElementById('eventModal').style.display = 'flex';
      document.getElementById('eventModal').style.alignItems = 'center';
    }
    
    function openDay(day) {
      document.getElementById('modalTitle').textContent = '📝 Novo Evento';
      const month = String(<?= $currentMonth ?>).padStart(2, '0');
      const year = <?= $currentYear ?>;
      document.getElementById('eventId').value = '';
      document.getElementById('eventTitle').value = '';
      document.getElementById('eventDesc').value = '';
      document.getElementById('eventDateOnly').value = `${year}-${month}-${String(day).padStart(2,'0')}`;
      document.getElementById('eventTimeOnly').value = '09:00';
      document.getElementById('eventAction').name = 'add';
      document.getElementById('deleteBtn').style.display = 'none';
      document.getElementById('eventModal').style.display = 'flex';
      document.getElementById('eventModal').style.alignItems = 'center';
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
  
  <style>
    @media (max-width: 640px) {
      #eventModal > div {
        margin: 20px auto !important;
        max-width: 95% !important;
        padding: 20px !important;
      }
      #eventModal > div > form > div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
      }
    }
    
    /* Garantir que inputs de date e time sejam visíveis */
    input[type="date"], input[type="time"] {
      -webkit-appearance: none;
      -moz-appearance: none;
      appearance: none;
      color-scheme: dark;
    }
    
    input[type="date"]::-webkit-calendar-picker-indicator,
    input[type="time"]::-webkit-calendar-picker-indicator {
      filter: invert(1);
      cursor: pointer;
    }
  </style>

</body>
</html>
