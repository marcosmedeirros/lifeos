<?php
require_once __DIR__ . '/../includes/auth.php';
require_login(); // Requer login obrigatório

// Define user_id da sessão
$user_id = $_SESSION['user_id'];

$page = 'routine';

if (isset($_GET['api'])) {
    try {
        require_once __DIR__ . '/../config.php';
        $action = $_GET['api'];
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

if ($action === 'get_routine_month') {
    $month = $_GET['month'] ?? date('Y-m');
    $stmt = $pdo->prepare("SELECT id, log_date, mood, sleep_hours, day_status, content, photo_path FROM routine_logs WHERE log_date LIKE ?");
    $stmt->execute(["$month%"]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($action === 'get_routine_day') {
    $date = $_GET['date'];
    // Ajustado para selecionar apenas as colunas que agora existem (day_status e não mais gratitude)
    $stmt = $pdo->prepare("SELECT id, log_date, mood, sleep_hours, day_status, content, photo_path FROM routine_logs WHERE log_date = ?");
    $stmt->execute([$date]);
    echo json_encode($stmt->fetch() ?: null);
    exit;
}

if ($action === 'save_routine') {
    $date = $data['date'] ?? $_POST['date'];
    $mood = $data['mood'] ?? $_POST['mood'] ?? null;
    $sleep_hours = $data['sleep_hours'] ?? $_POST['sleep_hours'] ?? 0;
    $day_status = $data['day_status'] ?? $_POST['day_status'] ?? null; // NOVO CAMPO
    $content = $data['content'] ?? $_POST['content'] ?? '';
    
    // Lógica de Upload de Imagem
    $photoPath = null;
    
    // Verifica se já existe um registro para pegar a foto antiga caso não envie nova
    $stmt = $pdo->prepare("SELECT id, photo_path FROM routine_logs WHERE log_date = ?");
    $stmt->execute([$date]);
    $existing = $stmt->fetch();

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $filename = "routine_" . $date . "_" . time() . "." . $ext;
        
        // Cria pasta se não existir
        if (!is_dir('uploads')) mkdir('uploads', 0777, true);
        
        if (move_uploaded_file($_FILES['photo']['tmp_name'], "uploads/" . $filename)) {
            $photoPath = "uploads/" . $filename;
            // Apaga foto antiga se existir
            if ($existing && !empty($existing['photo_path']) && file_exists($existing['photo_path'])) {
                unlink($existing['photo_path']);
            }
        }
    } else {
        // Mantém a foto antiga se não enviou nova
        $photoPath = $existing ? $existing['photo_path'] : null;
    }

    if ($existing) {
        // UPDATE: Removida a coluna 'gratitude' e adicionada 'day_status'
        $sql = "UPDATE routine_logs SET mood=?, sleep_hours=?, day_status=?, content=?, photo_path=? WHERE log_date=?";
        $pdo->prepare($sql)->execute([$mood, $sleep_hours, $day_status, $content, $photoPath, $date]);
    } else {
        // INSERT: Removida a coluna 'gratitude' e adicionada 'day_status'
        $sql = "INSERT INTO routine_logs (user_id, log_date, mood, sleep_hours, day_status, content, photo_path) VALUES (1, ?, ?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$date, $mood, $sleep_hours, $day_status, $content, $photoPath]);
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
                <h2 class="text-3xl font-bold text-white">Diário & Rotina</h2>
                <div class="flex items-center bg-slate-800 rounded-lg p-1 border border-slate-700">
                    <button onclick="changeRoutineMonth(-1)" class="w-8 h-8 hover:bg-slate-700 rounded text-slate-400">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <span id="routine-month-label" class="px-4 font-medium text-sm min-w-[140px] text-center capitalize">...</span>
                    <button onclick="changeRoutineMonth(1)" class="w-8 h-8 hover:bg-slate-700 rounded text-slate-400">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
            
            <div class="glass-card p-6 rounded-2xl shadow-2xl">
                <div class="grid grid-cols-7 gap-2 mb-4 text-center text-slate-500 font-bold uppercase text-xs tracking-widest">
                    <div>Dom</div><div>Seg</div><div>Ter</div><div>Qua</div><div>Qui</div><div>Sex</div><div>Sáb</div>
                </div>
                <div class="grid grid-cols-7 gap-2" id="routine-calendar"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Routine -->
<div id="modal-overlay" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4" onclick="closeModal()">
    <div id="modal-content" class="modal-glass rounded-2xl p-8 w-full max-w-md relative max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
        <button onclick="closeModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white transition w-8 h-8 flex items-center justify-center rounded-full hover:bg-slate-800 z-50" type="button">
            <i class="fas fa-times text-xl"></i>
        </button>
        <form id="modal-routine" class="modal-form hidden h-full flex flex-col" onsubmit="submitRoutine(event)">
        <h3 class="text-2xl font-bold mb-6 text-white text-center" id="routine-modal-title">Diário</h3>
        <input type="hidden" name="date" id="routine-date">
        <input type="hidden" name="mood" id="routine-mood">
        <input type="hidden" name="day_status" id="routine-day-status">

        <div class="space-y-6">
            <div class="text-center">
                <label class="block text-sm font-medium text-slate-300 mb-3">Qual o seu humor?</label>
                <div class="flex justify-center gap-3 text-3xl">
                    <button type="button" onclick="selectMood('otimo')" class="mood-btn transition hover:scale-125" id="mood-otimo" title="Ótimo">🤩</button>
                    <button type="button" onclick="selectMood('bom')" class="mood-btn transition hover:scale-125" id="mood-bom" title="Bom">😊</button>
                    <button type="button" onclick="selectMood('ok')" class="mood-btn transition hover:scale-125" id="mood-ok" title="OK">😐</button>
                    <button type="button" onclick="selectMood('ruim')" class="mood-btn transition hover:scale-125" id="mood-ruim" title="Ruim">😟</button>
                    <button type="button" onclick="selectMood('pessimo')" class="mood-btn transition hover:scale-125" id="mood-pessimo" title="Péssimo">😭</button>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-medium text-slate-300 block mb-1">Sono (Horas)</label>
                    <input type="number" step="0.5" name="sleep_hours" id="routine-sleep" class="text-center font-bold">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-300 block mb-1">Dia Bom?</label>
                    <div class="flex h-12 gap-2 p-1 bg-slate-800 rounded-xl border border-slate-700">
                        <button type="button" onclick="selectDayStatus('bom')" id="day-status-bom" class="flex-1 bg-slate-700/50 hover:bg-slate-700 text-white font-bold py-2 rounded-lg transition">Sim</button>
                        <button type="button" onclick="selectDayStatus('ruim')" id="day-status-ruim" class="flex-1 bg-slate-700/50 hover:bg-slate-700 text-white font-bold py-2 rounded-lg transition">Não</button>
                    </div>
                </div>
            </div>
            
            <div>
                <label class="text-sm font-medium text-slate-300 block mb-1">Resumo do Dia</label>
                <textarea name="content" id="routine-content" rows="4" class="bg-slate-800/50" placeholder="Como foi seu dia?"></textarea>
            </div>
            
            <div>
                <label class="text-sm font-medium text-slate-300 block mb-1">Foto do Dia</label>
                <input type="file" name="photo" id="routine-photo" accept="image/*" class="file:bg-slate-700 file:text-white file:border-0 file:rounded-lg file:px-4 file:py-2 hover:file:bg-slate-600">
            </div>
            <div id="routine-photo-preview" class="hidden rounded-xl overflow-hidden h-40 bg-cover bg-center"></div>
            <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-bold py-3 rounded-xl shadow-lg">Salvar Diário</button>
        </div>
    </form>
</div>

<script src="<?php echo BASE_PATH; ?>/assets/js/common.js"></script>
<script>
let currentRoutineMonth = new Date();

function selectMood(mood) { 
    document.getElementById('routine-mood').value = mood; 
    document.querySelectorAll('#modal-routine .mood-btn').forEach(b => b.classList.remove('selected')); 
    document.getElementById('mood-' + mood).classList.add('selected'); 
}

function selectDayStatus(status) {
    document.getElementById('routine-day-status').value = status;
    document.getElementById('day-status-bom').classList.remove('selected');
    document.getElementById('day-status-ruim').classList.remove('selected');
    document.getElementById(`day-status-${status}`).classList.add('selected');
}

function changeRoutineMonth(dir) { 
    currentRoutineMonth.setMonth(currentRoutineMonth.getMonth() + dir); 
    loadRoutine(); 
}

async function loadRoutine() { 
    const ym = currentRoutineMonth.toISOString().slice(0, 7); 
    document.getElementById('routine-month-label').innerText = currentRoutineMonth.toLocaleString('pt-BR', { month: 'long', year: 'numeric' }); 
    const logs = await fetch(`?api=get_routine_month&month=${ym}`).then(r => r.json()); 
    const cal = document.getElementById('routine-calendar'); 
    cal.innerHTML = ''; 
    
    const moodMap = {
        'otimo': '🤩', 'bom': '😊', 'ok': '😐', 'ruim': '😟', 'pessimo': '😭'
    };
    const dayStatusMap = {
        'bom': '✅', 'ruim': '❌'
    };

    const dim = new Date(currentRoutineMonth.getFullYear(), currentRoutineMonth.getMonth() + 1, 0).getDate(); 
    const pad = new Date(currentRoutineMonth.getFullYear(), currentRoutineMonth.getMonth(), 1).getDay(); 
    
    for (let i = 0; i < pad; i++) {
        cal.innerHTML += '<div class="bg-slate-800/10 h-28 rounded-xl border border-transparent"></div>'; 
    }
    
    for (let i = 1; i <= dim; i++) { 
        const dLocal = new Date(currentRoutineMonth.getFullYear(), currentRoutineMonth.getMonth(), i); 
        const dStr = dLocal.toLocaleDateString('en-CA'); // YYYY-MM-DD em horário local
        const log = logs.find(l => l.log_date === dStr); 
        const todayStr = new Date().toLocaleDateString('en-CA');
        const isToday = todayStr === dStr; 
        
        const emojiPrimary = log ? moodMap[log.mood] || '📝' : '+';
        const emojiSecondary = log && log.day_status ? dayStatusMap[log.day_status] : '';

        const cellClass = isToday ? 'bg-blue-500/10 border-blue-500/40' : 'bg-slate-800/30 border-slate-700/40 hover:bg-slate-800/50';
        const numClass = isToday ? 'text-blue-400' : 'text-slate-500';

        let html = `<div onclick="openRoutineDay('${dStr}')" class="${cellClass} p-2 h-28 rounded-xl border transition flex flex-col items-center justify-center cursor-pointer relative group">
            <span class="absolute top-2 left-3 text-sm font-bold ${numClass}">${i}</span>
            <div class="text-4xl">
                ${emojiPrimary}
                ${emojiSecondary ? `<span class="absolute top-1 right-1 text-xs">${emojiSecondary}</span>` : ''}
            </div>
            ${log && log.photo_path ? '<div class="absolute bottom-2 right-2 w-2 h-2 bg-emerald-400 rounded-full"></div>' : ''}
            <div class="opacity-0 group-hover:opacity-50 text-2xl text-slate-600 absolute inset-0 flex items-center justify-center">${log ? '' : '+'}</div>
        </div>`; 

        cal.innerHTML += html; 
    } 
}

async function openRoutineDay(date) { 
    resetForm(document.getElementById('modal-routine')); 
    const log = await fetch(`?api=get_routine_day&date=${date}`).then(r => r.json()); 
    
    document.getElementById('routine-date').value = date; 
    const localTitleDate = new Date(`${date}T00:00:00`);
    document.getElementById('routine-modal-title').innerText = `Diário - ${localTitleDate.toLocaleDateString('pt-BR')}`; 
    
    if (log) { 
        if(log.mood) selectMood(log.mood); 
        if(log.day_status) selectDayStatus(log.day_status);

        document.getElementById('routine-sleep').value = log.sleep_hours; 
        document.getElementById('routine-content').value = log.content; 
        
        if (log.photo_path) { 
            const preview = document.getElementById('routine-photo-preview'); 
            preview.classList.remove('hidden'); 
            preview.style.backgroundImage = `url('${log.photo_path}')`; 
        } 
    } 
    openModal('modal-routine', false); 
}

async function submitRoutine(e) { 
    e.preventDefault(); 
    const fd = new FormData(e.target); 
    await fetch('?api=save_routine', { method: 'POST', body: fd }).then(r => r.json()); 
    closeModal(); 
    loadRoutine(); 
}

// CSS para mood/day-status selected
document.head.insertAdjacentHTML('beforeend', `<style>
.mood-btn.selected {
    transform: scale(1.3);
    filter: drop-shadow(0 0 8px rgba(168, 85, 247, 0.6));
}
#day-status-bom.selected {
    background: linear-gradient(135deg, rgb(16, 185, 129), rgb(5, 150, 105));
}
#day-status-ruim.selected {
    background: linear-gradient(135deg, rgb(239, 68, 68), rgb(220, 38, 38));
}
</style>`);

loadRoutine();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
