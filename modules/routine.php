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
    $stmt = $pdo->prepare("SELECT id, log_date, mood, content FROM routine_logs WHERE log_date LIKE ?");
    $stmt->execute(["$month%"]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($action === 'get_routine_day') {
    $date = $_GET['date'];
    $stmt = $pdo->prepare("SELECT id, log_date, mood, content FROM routine_logs WHERE log_date = ?");
    $stmt->execute([$date]);
    echo json_encode($stmt->fetch() ?: null);
    exit;
}

if ($action === 'save_routine') {
    $date = $data['date'] ?? $_POST['date'];
    $mood = $data['mood'] ?? $_POST['mood'] ?? null;
    $content = $data['content'] ?? $_POST['content'] ?? '';
    
    $stmt = $pdo->prepare("SELECT id FROM routine_logs WHERE log_date = ?");
    $stmt->execute([$date]);
    $existing = $stmt->fetch();

    if ($existing) {
        $sql = "UPDATE routine_logs SET mood=?, content=? WHERE log_date=?";
        $pdo->prepare($sql)->execute([$mood, $content, $date]);
    } else {
        $sql = "INSERT INTO routine_logs (user_id, log_date, mood, content) VALUES (?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$user_id, $date, $mood, $content]);
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
                <div class="flex items-center bg-black/40 rounded-lg p-1 border border-gray-600/30">
                    <button onclick="changeRoutineMonth(-1)" class="w-8 h-8 hover:bg-black/60 rounded text-gray-400">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <span id="routine-month-label" class="px-4 font-medium text-sm min-w-[140px] text-center capitalize">...</span>
                    <button onclick="changeRoutineMonth(1)" class="w-8 h-8 hover:bg-black/60 rounded text-gray-400">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
            
            <div class="glass-card p-6 rounded-2xl shadow-2xl">
                <div class="grid grid-cols-7 gap-2 mb-4 text-center text-gray-500 font-bold uppercase text-xs tracking-widest">
                    <div>Dom</div><div>Seg</div><div>Ter</div><div>Qua</div><div>Qui</div><div>Sex</div><div>Sáb</div>
                </div>
                <div class="grid grid-cols-7 gap-2" id="routine-calendar"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Routine -->
<div id="modal-overlay" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4" onclick="closeModal()">
    <div id="modal-content" class="modal-glass rounded-2xl p-8 w-full max-w-lg relative max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
        <button onclick="closeModal()" class="absolute top-4 right-4 text-gray-400 hover:text-white transition w-8 h-8 flex items-center justify-center rounded-full hover:bg-black/40 z-50" type="button">
            <i class="fas fa-times text-xl"></i>
        </button>
        <form id="modal-routine" class="modal-form hidden" onsubmit="submitRoutine(event)">
            <h3 class="text-2xl font-bold mb-6 text-white text-center" id="routine-modal-title">😊 Como foi seu dia?</h3>
            <input type="hidden" name="date" id="routine-date">
            <input type="hidden" name="mood" id="routine-mood">

            <div class="space-y-6">
                <div>
                    <label class="block text-sm font-semibold mb-2 text-gray-300 text-center">😊 Qual o seu humor?</label>
                    <div class="flex gap-4 justify-center py-3">
                        <button type="button" onclick="selectMood('🤩')" data-mood="🤩" class="mood-btn text-5xl opacity-30 hover:opacity-100 transition-all hover:scale-125" title="Muito Bom">🤩</button>
                        <button type="button" onclick="selectMood('😊')" data-mood="😊" class="mood-btn text-5xl opacity-30 hover:opacity-100 transition-all hover:scale-125" title="Bom">😊</button>
                        <button type="button" onclick="selectMood('😐')" data-mood="😐" class="mood-btn text-5xl opacity-30 hover:opacity-100 transition-all hover:scale-125" title="OK">😐</button>
                        <button type="button" onclick="selectMood('😟')" data-mood="😟" class="mood-btn text-5xl opacity-30 hover:opacity-100 transition-all hover:scale-125" title="Ruim">😟</button>
                        <button type="button" onclick="selectMood('😭')" data-mood="😭" class="mood-btn text-5xl opacity-30 hover:opacity-100 transition-all hover:scale-125" title="Muito Ruim">😭</button>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold mb-2 text-gray-300">📝 Descrição do Dia</label>
                    <textarea name="content" id="routine-content" required class="w-full min-h-[200px] bg-black/40 border border-gray-600/30 rounded-xl p-4 text-white" placeholder="Como foi seu dia?"></textarea>
                </div>
                
                <button type="submit" class="w-full bg-gradient-to-r from-yellow-600 to-yellow-700 hover:from-yellow-500 hover:to-yellow-600 text-white font-bold py-3 rounded-xl shadow-lg shadow-yellow-600/30 transition">💾 Salvar Dia</button>
            </div>
        </form>
    </div>
</div>

<script src="<?php echo BASE_PATH; ?>/assets/js/common.js"></script>
<script>
let currentRoutineMonth = new Date();

function selectMood(mood) { 
    document.getElementById('routine-mood').value = mood; 
    document.querySelectorAll('#modal-routine .mood-btn').forEach(b => b.classList.remove('selected')); 
    const btn = document.querySelector(`#modal-routine .mood-btn[data-mood="${mood}"]`);
    if (btn) btn.classList.add('selected'); 
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

    const dim = new Date(currentRoutineMonth.getFullYear(), currentRoutineMonth.getMonth() + 1, 0).getDate(); 
    const pad = new Date(currentRoutineMonth.getFullYear(), currentRoutineMonth.getMonth(), 1).getDay(); 
    
    for (let i = 0; i < pad; i++) {
        cal.innerHTML += '<div class="bg-black/20 h-28 rounded-xl border border-transparent"></div>'; 
    }
    
    for (let i = 1; i <= dim; i++) { 
        const dLocal = new Date(currentRoutineMonth.getFullYear(), currentRoutineMonth.getMonth(), i); 
        const dStr = dLocal.toLocaleDateString('en-CA'); // YYYY-MM-DD em horário local
        const log = logs.find(l => l.log_date === dStr); 
        const todayStr = new Date().toLocaleDateString('en-CA');
        const isToday = todayStr === dStr; 
        
        const emoji = log ? log.mood || '📝' : '+';

        const cellClass = isToday ? 'bg-white/10 border-white/50' : 'bg-black/30 border-gray-700/40 hover:bg-black/50';
        const numClass = isToday ? 'text-gray-100' : 'text-gray-500';

        let html = `<div onclick="openRoutineDay('${dStr}')" class="${cellClass} p-2 h-28 rounded-xl border transition flex flex-col items-center justify-center cursor-pointer relative group">
            <span class="absolute top-2 left-3 text-sm font-bold ${numClass}">${i}</span>
            <div class="text-4xl">${emoji}</div>
            <div class="opacity-0 group-hover:opacity-50 text-2xl text-gray-600 absolute inset-0 flex items-center justify-center">${log ? '' : '+'}</div>
        </div>`; 

        cal.innerHTML += html; 
    } 
}

async function openRoutineDay(date) { 
    resetForm(document.getElementById('modal-routine')); 
    const log = await fetch(`?api=get_routine_day&date=${date}`).then(r => r.json()); 
    
    document.getElementById('routine-date').value = date; 
    const localTitleDate = new Date(`${date}T00:00:00`);
    document.getElementById('routine-modal-title').innerText = `${localTitleDate.toLocaleDateString('pt-BR')}`; 
    
    if (log) { 
        if(log.mood) selectMood(log.mood);
        document.getElementById('routine-content').value = log.content; 
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

// CSS para mood selected
document.head.insertAdjacentHTML('beforeend', `<style>
.mood-btn.selected {
    opacity: 1 !important;
    transform: scale(1.3);
    filter: drop-shadow(0 0 8px rgba(250, 204, 21, 0.6));
}
</style>`);

loadRoutine();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
