<?php
// ARQUIVO: habits.php - Página completa de Hábitos
require_once __DIR__ . '/../includes/auth.php';
require_login(); // Requer login obrigatório

$user_id = $_SESSION['user_id'];
// Definir user_id padrão se não existir sessão
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['user_name'] = 'Marcos Medeiros';
}

if (isset($_GET['api'])) {
    $action = $_GET['api'];
    header('Content-Type: application/json');
    
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) $data = $_POST;
    
    try {
        if ($action === 'get_habits') { 
            echo json_encode($pdo->query("SELECT * FROM habits")->fetchAll()); 
            exit; 
        }
        
        if ($action === 'save_habit') { 
            $pdo->prepare("INSERT INTO habits (name, checked_dates) VALUES (?, '[]')")->execute([$data['name']]); 
            echo json_encode(['success'=>true]); 
            exit; 
        }
        
        if ($action === 'toggle_habit') { 
            $id=$data['id']; $d=$data['date']; 
            $c=json_decode($pdo->query("SELECT checked_dates FROM habits WHERE id=$id")->fetchColumn(),true)??[]; 
            if(in_array($d,$c)) $c=array_diff($c,[$d]); 
            else $c[]=$d; 
            $pdo->prepare("UPDATE habits SET checked_dates=? WHERE id=?")->execute([json_encode(array_values($c)),$id]); 
            echo json_encode(['success'=>true]);
            exit; 
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

$page = 'habits';
$page_title = 'Hábitos - LifeOS';
include __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen w-full">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="flex-1 p-4 md:p-10 content-wrap transition-all duration-300">
        <div class="main-shell">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-3xl font-bold text-white">Rastreador de Hábitos</h2>
                <div class="flex gap-3 items-center">
                    <div class="flex items-center bg-slate-800 rounded-lg p-1">
                        <button onclick="changeHabitMonth(-1)" class="w-8 h-8 hover:bg-slate-700 rounded text-slate-400">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <span id="habit-month-label" class="px-4 font-medium text-sm min-w-[140px] text-center capitalize">...</span>
                        <button onclick="changeHabitMonth(1)" class="w-8 h-8 hover:bg-slate-700 rounded text-slate-400">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    <button onclick="openModal('modal-habit')" class="bg-teal-500 hover:bg-teal-600 text-black px-5 py-2 rounded-lg font-bold shadow-lg shadow-teal-500/20 transition">
                        <i class="fas fa-plus mr-1"></i> Novo
                    </button>
                </div>
            </div>
            
            <div class="glass-card rounded-2xl p-6 overflow-x-auto shadow-xl">
                <table class="w-full border-collapse" id="habits-table">
                    <thead>
                        <tr id="habits-header-row"></tr>
                    </thead>
                    <tbody id="habits-list"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="modal-overlay" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
    <div id="modal-content" class="modal-glass rounded-2xl p-8 w-full max-w-md relative max-h-[90vh] overflow-y-auto">
        <button onclick="closeModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white transition w-8 h-8 flex items-center justify-center rounded-full hover:bg-slate-800 z-50">
            <i class="fas fa-times text-xl"></i>
        </button>
        
        <form id="modal-habit" class="modal-form hidden" onsubmit="submitHabit(event)">
            <h3 class="text-2xl font-bold mb-6 text-white bg-clip-text text-transparent bg-gradient-to-r from-teal-400 to-emerald-400">Novo Hábito</h3>
            <div class="space-y-5">
                <input type="text" name="name" placeholder="Ex: Ler 10 páginas" required class="text-lg">
                <button type="submit" class="w-full bg-gradient-to-r from-teal-500 to-emerald-500 hover:from-teal-400 hover:to-emerald-400 text-white font-bold py-3 rounded-xl shadow-lg transition mt-2">
                    Criar Hábito
                </button>
            </div>
        </form>
    </div>
</div>

<script src="<?php echo BASE_PATH; ?>/assets/js/common.js"></script>
<script>
let currentHabitMonth = new Date();

function changeHabitMonth(dir) { 
    currentHabitMonth.setMonth(currentHabitMonth.getMonth() + dir); 
    loadHabits(); 
}

async function loadHabits() { 
    const habits = await api('get_habits'); 
    document.getElementById('habit-month-label').innerText = currentHabitMonth.toLocaleString('pt-BR', { month: 'long', year: 'numeric' }); 
    
    const dim = new Date(currentHabitMonth.getFullYear(), currentHabitMonth.getMonth() + 1, 0).getDate(); 
    const ym = currentHabitMonth.toISOString().slice(0, 7); 
    
    let h = '<th class="p-3 text-left text-slate-400 font-medium bg-slate-900/50 sticky left-0 z-10 min-w-[150px]">Hábito</th>'; 
    for(let i=1; i<=dim; i++) h += `<th class="p-2 text-center text-xs text-slate-500 w-10 min-w-[40px]">${i}</th>`; 
    document.getElementById('habits-header-row').innerHTML = h; 
    
    document.getElementById('habits-list').innerHTML = habits.map(hb => { 
        const checks = JSON.parse(hb.checked_dates || '[]'); 
        let cells = `<td class="p-3 border-b border-slate-700/50 font-bold text-slate-200 sticky left-0 bg-slate-800 z-10 shadow-[4px_0_10px_rgba(0,0,0,0.2)]">${hb.name}</td>`; 
        for(let i=1; i<=dim; i++) { 
            const d = `${ym}-${String(i).padStart(2, '0')}`; 
            const isChecked = checks.includes(d); 
            cells += `<td class="border-b border-slate-700/50 text-center p-1">
                <button onclick="toggleHabitInstant(event, ${hb.id}, '${d}')" class="w-8 h-8 rounded-lg text-[10px] transition-all transform hover:scale-110 ${isChecked ? 'bg-teal-500 text-black shadow-[0_0_10px_rgba(20,184,166,0.4)]' : 'bg-slate-700/30 hover:bg-slate-700 text-transparent'}">
                    ${isChecked ? '<i class="fas fa-check"></i>' : ''}
                </button>
            </td>`; 
        } 
        return `<tr class="hover:bg-slate-800/30 transition">${cells}</tr>`; 
    }).join(''); 
}

async function toggleHabitInstant(event, id, date) { 
    const btn = event.currentTarget; 
    const isChecked = btn.classList.contains('bg-teal-500'); 
    if (isChecked) { 
        btn.className = "w-8 h-8 rounded-lg text-[10px] transition-all transform hover:scale-110 bg-slate-700/30 hover:bg-slate-700 text-transparent"; 
        btn.innerHTML = ""; 
    } else { 
        btn.className = "w-8 h-8 rounded-lg text-[10px] transition-all transform hover:scale-110 bg-teal-500 text-black shadow-[0_0_10px_rgba(20,184,166,0.4)]"; 
        btn.innerHTML = '<i class="fas fa-check"></i>'; 
    } 
    await api('toggle_habit', {id, date}); 
}

async function submitHabit(e) { 
    e.preventDefault(); 
    await api('save_habit', Object.fromEntries(new FormData(e.target))); 
    closeModal(); 
    loadHabits(); 
}

document.addEventListener('DOMContentLoaded', () => {
    loadHabits();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
