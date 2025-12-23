<?php
require_once __DIR__ . '/../includes/auth.php';
require_login(); // Requer login obrigatório

// Define user_id da sessão
$user_id = $_SESSION['user_id'];
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['user_name'] = 'Marcos Medeiros';
}

$page = 'events';

if (isset($_GET['api'])) {
    try {
        require_once __DIR__ . '/../config.php';
        $action = $_GET['api'];
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

if ($action === 'get_events') {
    $month = $_GET['month'] ?? date('Y-m');
    $stmt = $pdo->prepare("SELECT * FROM events WHERE user_id = ? AND start_date LIKE ? ORDER BY start_date ASC");
    $stmt->execute([$_SESSION['user_id'], "$month%"]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($action === 'save_event') {
    if (!empty($data['id'])) {
        // --- EDIÇÃO (Atualiza apenas este evento) ---
        $sql = "UPDATE events SET title=?, start_date=?, description=? WHERE id=? AND user_id=?";
        $pdo->prepare($sql)->execute([$data['title'], $data['date'], $data['desc'], $data['id'], $_SESSION['user_id']]);
    } else {
        // --- NOVO EVENTO (Com Repetição) ---
        $groupId = uniqid('evt_'); // Gera um ID único para o grupo
        
        // Insere o evento original
        $sql = "INSERT INTO events (user_id, group_id, title, start_date, description) VALUES (?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$_SESSION['user_id'], $groupId, $data['title'], $data['date'], $data['desc']]);

        // Se houver repetição selecionada
        if (!empty($data['repeat_days'])) {
            $baseDate = new DateTime($data['date']);
            // Preserva a hora original
            $timePart = $baseDate->format('H:i:s');
            
            // Avança para o dia seguinte para começar o loop
            $baseDate->modify('+1 day');

            // Gera eventos para os próximos 90 dias (aprox 3 meses)
            for ($i = 0; $i < 90; $i++) {
                // Se o dia da semana bater com o escolhido
                if (in_array($baseDate->format('w'), $data['repeat_days'])) {
                    $newDateTime = $baseDate->format('Y-m-d') . ' ' . $timePart;
                    $pdo->prepare($sql)->execute([$_SESSION['user_id'], $groupId, $data['title'], $newDateTime, $data['desc']]);
                }
                $baseDate->modify('+1 day');
            }
        }
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'delete_event') {
    // 1. Descobre se o evento faz parte de um grupo
    $stmt = $pdo->prepare("SELECT group_id FROM events WHERE id = ?");
    $stmt->execute([$data['id']]);
    $event = $stmt->fetch();

    if ($event && !empty($event['group_id'])) {
        // SE TIVER GRUPO: Apaga TODOS do mesmo grupo (Série inteira)
        $pdo->prepare("DELETE FROM events WHERE group_id = ?")->execute([$event['group_id']]);
    } else {
        // SE NÃO TIVER GRUPO: Apaga só ele
        $pdo->prepare("DELETE FROM events WHERE id = ?")->execute([$data['id']]);
    }
    
    echo json_encode(['success' => true]);
    exit;
}

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen w-full">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="flex-1 p-4 md:p-8 content-wrap transition-all duration-300">
        <div class="main-shell calendar-shell">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-3xl font-bold text-white">Calendário</h2>
                <div class="flex gap-3 items-center">
                    <div class="flex items-center bg-slate-800 rounded-lg p-1 border border-slate-700">
                        <button onclick="changeEventMonth(-1)" class="w-8 h-8 hover:bg-slate-700 rounded text-slate-400">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <span id="event-month-label" class="px-4 font-medium text-sm min-w-[140px] text-center capitalize">...</span>
                        <button onclick="changeEventMonth(1)" class="w-8 h-8 hover:bg-slate-700 rounded text-slate-400">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    <button onclick="openEventModal('modal-event')" class="bg-yellow-500 hover:bg-yellow-600 text-black px-5 py-2 rounded-lg font-bold shadow-lg shadow-yellow-500/20 transition">
                        <i class="fas fa-plus mr-1"></i> Novo
                    </button>
                </div>
            </div>
            
            <div class="glass-card p-6 rounded-2xl shadow-2xl">
                <div class="grid grid-cols-7 gap-2 mb-4 text-center text-slate-500 font-bold uppercase text-xs tracking-widest">
                    <div>Dom</div><div>Seg</div><div>Ter</div><div>Qua</div><div>Qui</div><div>Sex</div><div>Sáb</div>
                </div>
                <div class="grid grid-cols-7 gap-2" id="events-calendar"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Event -->
<!-- Modal Event -->
<div id="event-modal-overlay" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4" onclick="closeEventModal()">
    <div class="modal-glass rounded-2xl p-8 w-full max-w-md relative max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
        <button type="button" onclick="closeEventModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white transition w-8 h-8 flex items-center justify-center rounded-full hover:bg-slate-800 z-50">
            <i class="fas fa-times text-xl"></i>
        </button>
        <form id="modal-event" class="modal-form" onsubmit="submitEvent(event)">
            <h3 class="text-2xl font-bold mb-6 text-white bg-clip-text text-transparent bg-gradient-to-r from-yellow-400 to-orange-400" id="event-modal-title">Novo Evento</h3>
            <input type="hidden" name="id" id="event-id">
            <div class="space-y-5">
                <input type="text" name="title" id="event-title" placeholder="Nome do Evento" required class="text-lg">
                <div class="grid grid-cols-2 gap-3">
                    <input type="date" name="date" id="event-date" required>
                    <input type="time" name="time" id="event-time" placeholder="Hora (opcional)">
                </div>
                <textarea name="desc" id="event-desc" placeholder="Descrição (opcional)" class="text-lg" rows="3"></textarea>
                <div class="flex gap-3 pt-4">
                    <button type="submit" class="flex-1 bg-gradient-to-r from-yellow-500 to-orange-500 hover:from-yellow-400 hover:to-orange-400 text-black font-bold py-3 rounded-xl shadow-lg transition">Salvar</button>
                    <button type="button" id="btn-delete-event" onclick="deleteEvent()" class="hidden bg-rose-500/10 hover:bg-rose-500/20 text-rose-500 hover:text-rose-400 px-4 rounded-xl border border-rose-500/30 transition">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="<?php echo BASE_PATH; ?>/assets/js/common.js"></script>
<script>
function openEventModal(formId, reset = true) {
    const overlay = document.getElementById('event-modal-overlay');
    overlay.classList.remove('hidden');
    document.querySelectorAll('#event-modal-overlay .modal-form').forEach(el => el.classList.add('hidden'));
    const form = document.getElementById(formId);
    form.classList.remove('hidden');
    if (reset) {
        form.reset();
        // Data atual
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('event-date').value = today;
        document.getElementById('event-modal-title').innerText = 'Novo Evento';
        document.getElementById('btn-delete-event').classList.add('hidden');
    }
}

function closeEventModal() {
    document.getElementById('event-modal-overlay').classList.add('hidden');
}
let currentEventMonth = new Date();
window.eventsData = [];

function changeEventMonth(dir) { 
    currentEventMonth.setMonth(currentEventMonth.getMonth() + dir); 
    loadEvents(); 
}

async function loadEvents() { 
    const ym = currentEventMonth.toISOString().slice(0, 7); 
    document.getElementById('event-month-label').innerText = currentEventMonth.toLocaleString('pt-BR', { month: 'long', year: 'numeric' }); 
    window.eventsData = await fetch(`?api=get_events&month=${ym}`).then(r => r.json()); 
    
    const cal = document.getElementById('events-calendar'); 
    cal.innerHTML = ''; 
    const dim = new Date(currentEventMonth.getFullYear(), currentEventMonth.getMonth() + 1, 0).getDate(); 
    const pad = new Date(currentEventMonth.getFullYear(), currentEventMonth.getMonth(), 1).getDay(); 
    
    for (let i = 0; i < pad; i++) {
        cal.innerHTML += '<div class="bg-slate-800/10 h-28 rounded-xl border border-transparent"></div>'; 
    }
    
    for (let i = 1; i <= dim; i++) { 
        const dStr = `${ym}-${String(i).padStart(2, '0')}`; 
        const evs = window.eventsData.filter(e => e.start_date.startsWith(dStr)); 
        const isToday = new Date().toISOString().slice(0,10) === dStr; 
        const cellClass = isToday ? 'bg-purple-500/10 border-purple-500/50 ring-1 ring-purple-500/30' : 'bg-slate-800/40 border-slate-700/50 hover:bg-slate-800 hover:border-slate-600'; 
        const numClass = isToday ? 'text-purple-400 font-bold' : 'text-slate-400 font-medium'; 
        
        let html = `<div class="${cellClass} h-28 rounded-xl border p-2 cursor-pointer transition group relative flex flex-col" onclick="openEventModal('modal-event'); document.getElementById('event-date').value='${dStr}'">
            <span class="${numClass} text-sm mb-1 ml-1">${i}</span>
            <div class="flex-1 overflow-y-auto no-scrollbar space-y-1">`;
        
        evs.forEach(ev => { 
            const time = new Date(ev.start_date).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }); 
            html += `<div onclick="event.stopPropagation(); editEventRow(${ev.id})" class="cursor-pointer text-xs px-2 py-1 rounded-md bg-yellow-500/10 text-yellow-200 border-l-2 border-yellow-500 hover:bg-yellow-500/20 transition truncate mb-1" title="${time} - ${ev.title}">
                <span class="opacity-70 text-[10px] mr-1">${time}</span>${ev.title}
            </div>`; 
        });
        
        html += `</div><div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity"><i class="fas fa-plus text-xs text-slate-500 hover:text-white cursor-pointer"></i></div></div>`; 
        cal.innerHTML += html; 
    } 
}

function editEventRow(id) { 
    const ev = window.eventsData.find(i => i.id == id); 
    if(!ev) return; 
    const form = document.getElementById('modal-event'); 
    form.reset(); 
    document.getElementById('event-id').value = ev.id; 
    document.getElementById('event-title').value = ev.title; 
    const dateTimeParts = ev.start_date.split(' ');
    document.getElementById('event-date').value = dateTimeParts[0]; 
    if (dateTimeParts[1]) {
        document.getElementById('event-time').value = dateTimeParts[1].substring(0, 5);
    }
    document.getElementById('event-desc').value = ev.description || ''; 
    document.getElementById('event-modal-title').innerText = 'Editar Evento'; 
    document.getElementById('btn-delete-event').classList.remove('hidden'); 
    openEventModal('modal-event', false); 
}

async function submitEvent(e) { 
    e.preventDefault(); 
    const fd = new FormData(e.target); 
    const data = Object.fromEntries(fd); 
    // Combinar data e hora
    if (data.time) {
        data.date = data.date + ' ' + data.time + ':00';
    }
    await api('save_event', data); 
    closeEventModal(); 
    loadEvents(); 
}

async function deleteEvent() { 
    if(confirm('Excluir este evento (e repetições)?')) { 
        await api('delete_event', {id: document.getElementById('event-id').value}); 
        closeEventModal(); 
        loadEvents(); 
    } 
}

// CSS para day-checkbox
document.head.insertAdjacentHTML('beforeend', `<style>
.day-checkbox:checked + label {
    background-color: rgba(234, 179, 8, 0.2);
    border-color: rgb(234, 179, 8);
    color: rgb(234, 179, 8);
}
</style>`);

loadEvents();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
