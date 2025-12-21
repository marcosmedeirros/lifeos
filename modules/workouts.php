<?php
require_once __DIR__ . '/../includes/auth.php';
// require_login(); // Comentado - acesso direto

// Definir user_id padrão se não existir sessão
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['user_name'] = 'Marcos Medeiros';
}

$page = 'workouts';

if (isset($_GET['api'])) {
    try {
        require_once __DIR__ . '/../config.php';
        $action = $_GET['api'];
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

if ($action === 'get_workouts') {
    $month = $_GET['month'] ?? date('Y-m');
    $stmt = $pdo->prepare("SELECT * FROM workouts WHERE workout_date LIKE ? ORDER BY workout_date ASC");
    $stmt->execute(["$month%"]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($action === 'save_workout') {
    if (!empty($data['id'])) {
        // Editar
        $sql = "UPDATE workouts SET name=?, workout_date=? WHERE id=?";
        $pdo->prepare($sql)->execute([$data['name'], $data['date'], $data['id']]);
    } else {
        // Novo (Com user_id)
        $sql = "INSERT INTO workouts (user_id, name, workout_date, done) VALUES (1, ?, ?, 0)";
        $pdo->prepare($sql)->execute([$data['name'], $data['date']]);
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'toggle_workout') {
    $pdo->prepare("UPDATE workouts SET done = 1 - done WHERE id=?")->execute([$data['id']]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'delete_workout') {
    $pdo->prepare("DELETE FROM workouts WHERE id=?")->execute([$data['id']]);
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
                <h2 class="text-3xl font-bold text-white">Treinos</h2>
                <div class="flex gap-3 items-center">
                    <div class="flex items-center bg-slate-800 rounded-lg p-1 border border-slate-700">
                        <button onclick="changeWorkoutMonth(-1)" class="w-8 h-8 hover:bg-slate-700 rounded text-slate-400">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <span id="workout-month-label" class="px-4 font-medium text-sm min-w-[140px] text-center capitalize">...</span>
                        <button onclick="changeWorkoutMonth(1)" class="w-8 h-8 hover:bg-slate-700 rounded text-slate-400">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    <button onclick="openModal('modal-workout')" class="bg-purple-600 hover:bg-purple-700 text-white px-5 py-2 rounded-lg font-bold shadow-lg shadow-purple-600/20 transition">
                        <i class="fas fa-dumbbell mr-2"></i> Registrar
                    </button>
                </div>
            </div>
            
            <div class="glass-card p-6 rounded-2xl shadow-2xl">
                <div class="grid grid-cols-7 gap-2 mb-4 text-center text-slate-500 font-bold uppercase text-xs tracking-widest">
                    <div>Dom</div><div>Seg</div><div>Ter</div><div>Qua</div><div>Qui</div><div>Sex</div><div>Sáb</div>
                </div>
                <div class="grid grid-cols-7 gap-2" id="workout-calendar"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Workout -->
<div id="modal-overlay" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4" onclick="closeModal()">
    <div id="modal-content" class="modal-glass rounded-2xl p-8 w-full max-w-md relative max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
        <button type="button" onclick="closeModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white transition w-8 h-8 flex items-center justify-center rounded-full hover:bg-slate-800 z-50">
            <i class="fas fa-times text-xl"></i>
        </button>
        <form id="modal-workout" class="modal-form hidden" onsubmit="submitWorkout(event)">
        <h3 class="text-2xl font-bold mb-6 text-white bg-clip-text text-transparent bg-gradient-to-r from-purple-400 to-indigo-400" id="workout-modal-title">Treino</h3>
        <input type="hidden" name="id" id="workout-id">
        <div class="space-y-5">
            <input type="text" name="name" id="workout-name" placeholder="Nome do Treino (Ex: Perna A)" required class="text-lg">
            <input type="date" name="date" id="workout-date" required>
            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-bold py-3 rounded-xl shadow-lg transition">Salvar</button>
                <button type="button" id="btn-delete-workout" onclick="deleteWorkout()" class="hidden bg-rose-500/10 hover:bg-rose-500/20 text-rose-500 hover:text-rose-400 px-4 rounded-xl border border-rose-500/30 transition">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
        </form>
    </div>
</div>

<script src="/lifeos/assets/js/common.js"></script>
<script>
let currentWorkoutMonth = new Date();
window.workoutsData = [];

function changeWorkoutMonth(dir) { 
    currentWorkoutMonth.setMonth(currentWorkoutMonth.getMonth() + dir); 
    loadWorkouts(); 
}

async function loadWorkouts() {
    const ym = currentWorkoutMonth.toISOString().slice(0, 7);
    document.getElementById('workout-month-label').innerText = currentWorkoutMonth.toLocaleString('pt-BR', { month: 'long', year: 'numeric' });
    window.workoutsData = await fetch(`?api=get_workouts&month=${ym}`).then(r => r.json());
    
    const cal = document.getElementById('workout-calendar'); 
    cal.innerHTML = '';
    const dim = new Date(currentWorkoutMonth.getFullYear(), currentWorkoutMonth.getMonth() + 1, 0).getDate();
    const pad = new Date(currentWorkoutMonth.getFullYear(), currentWorkoutMonth.getMonth(), 1).getDay();
    
    for (let i = 0; i < pad; i++) {
        cal.innerHTML += '<div class="bg-slate-800/10 h-28 rounded-xl border border-transparent"></div>';
    }
    
    for (let i = 1; i <= dim; i++) {
        const dStr = `${ym}-${String(i).padStart(2, '0')}`;
        const w = window.workoutsData.find(w => w.workout_date === dStr);
        const isToday = new Date().toISOString().slice(0,10) === dStr;
        const cellClass = isToday ? 'bg-purple-500/10 border-purple-500/50 ring-1 ring-purple-500/30' : 'bg-slate-800/40 border-slate-700/50 hover:bg-slate-800 hover:border-slate-600';
        const numClass = isToday ? 'text-purple-400 font-bold' : 'text-slate-400 font-medium';
        
        let html = `<div onclick="editWorkoutRow(${w?.id||0}, '${dStr}')" class="${cellClass} p-2 h-28 rounded-xl border transition flex flex-col group relative overflow-hidden cursor-pointer">
            <span class="${numClass} text-sm mb-1 ml-1">${i}</span>
            <div class="flex-1 overflow-y-auto no-scrollbar space-y-1">`;
        
        if (w) { 
            html += `<div class="text-xs px-2 py-1 rounded-md border bg-emerald-500/20 text-emerald-300 border-emerald-500/30 truncate flex items-center gap-1">${w.name}</div>`; 
        }
        
        html += `</div><div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity"><i class="fas fa-plus text-xs text-slate-500 hover:text-white"></i></div></div>`;
        cal.innerHTML += html;
    }
}

function editWorkoutRow(id, date) { 
    const w = window.workoutsData.find(i => i.id == id); 
    resetForm(document.getElementById('modal-workout')); 
    document.getElementById('workout-date').value = date; 
    
    if(w) { 
        document.getElementById('workout-id').value = w.id; 
        document.getElementById('workout-name').value = w.name; 
        document.getElementById('btn-delete-workout').classList.remove('hidden'); 
    } else { 
        document.getElementById('btn-delete-workout').classList.add('hidden'); 
    } 
    openModal('modal-workout', false); 
}

async function submitWorkout(e) { 
    e.preventDefault(); 
    const fd=new FormData(e.target); 
    const d=Object.fromEntries(fd); 
    await api('save_workout',d); 
    closeModal(); 
    loadWorkouts(); 
}

async function deleteWorkout() {
    if(confirm('Excluir?')) {
        await api('delete_workout',{id:document.getElementById('workout-id').value}); 
        closeModal(); 
        loadWorkouts();
    }
}

loadWorkouts();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
