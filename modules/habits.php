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

function ensureHabitRemovalsTable(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS habit_removals (\n        habit_id INT NOT NULL PRIMARY KEY,\n        removed_from DATE NOT NULL,\n        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n        KEY idx_removed_from (removed_from)\n    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

if (isset($_GET['api'])) {
    $action = $_GET['api'];
    header('Content-Type: application/json');
    
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) $data = $_POST;
    
    try {
        ensureHabitRemovalsTable($pdo);

        if ($action === 'get_habits') {
            $month = $_GET['month'] ?? date('Y-m');
            $monthStart = $month . '-01';

            $stmt = $pdo->prepare("SELECT h.*, hr.removed_from FROM habits h LEFT JOIN habit_removals hr ON hr.habit_id = h.id WHERE hr.removed_from IS NULL OR hr.removed_from > ? ORDER BY h.id DESC");
            $stmt->execute([$monthStart]);
            echo json_encode($stmt->fetchAll());
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

        if ($action === 'delete_habit_month') {
            $id = $data['id'] ?? null;
            $month = $data['month'] ?? date('Y-m');
            if (!$id) {
                echo json_encode(['error' => 'ID do hábito não informado']);
                exit;
            }

            $monthStart = $month . '-01';
            $stmt = $pdo->prepare("INSERT INTO habit_removals (habit_id, removed_from) VALUES (?, ?) ON DUPLICATE KEY UPDATE removed_from = LEAST(removed_from, VALUES(removed_from))");
            $stmt->execute([$id, $monthStart]);
            echo json_encode(['success' => true]);
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
    
    <div class="flex-1 p-2 md:p-4 content-wrap transition-all duration-300">
        <div class="main-shell">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-2 mb-4">
                <h2 class="text-2xl md:text-3xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-yellow-400 to-yellow-500">🔥 Hábitos</h2>
                <div class="flex flex-wrap gap-1 items-center">
                    <div class="flex items-center bg-slate-800 rounded-lg p-0.5 border border-yellow-600/30">
                        <button onclick="changeHabitMonth(-1)" class="w-6 h-6 hover:bg-slate-700 rounded text-yellow-500 text-xs">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <span id="habit-month-label" class="px-1 font-medium text-[10px] md:text-xs min-w-[80px] md:min-w-[120px] text-center capitalize text-white">...</span>
                        <button onclick="changeHabitMonth(1)" class="w-6 h-6 hover:bg-slate-700 rounded text-yellow-500 text-xs">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    <button onclick="openModal('modal-habit')" class="bg-gradient-to-r from-yellow-600 to-yellow-700 hover:from-yellow-500 hover:to-yellow-600 text-white px-2 md:px-4 py-1.5 rounded-lg font-bold text-xs md:text-sm shadow-lg shadow-yellow-600/30 transition">
                        <i class="fas fa-plus mr-1"></i>Novo
                    </button>
                </div>
            </div>
            
            <div class="glass-card rounded-2xl p-2 md:p-4 overflow-x-auto shadow-xl max-h-[calc(100vh-280px)]">
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
            <h3 class="text-2xl font-bold mb-6 text-transparent bg-clip-text bg-gradient-to-r from-yellow-400 to-yellow-500">🔥 Novo Hábito</h3>
            <div class="space-y-5">
                <input type="text" name="name" placeholder="Ex: Ler 10 páginas" required class="text-lg">
                <button type="submit" class="w-full bg-gradient-to-r from-yellow-600 to-yellow-700 hover:from-yellow-500 hover:to-yellow-600 text-white font-bold py-3 rounded-xl shadow-lg shadow-yellow-600/30 transition mt-2">
                    💾 Criar Hábito
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
    const ym = currentHabitMonth.toISOString().slice(0, 7);
    const habits = await api(`get_habits&month=${ym}`);
    document.getElementById('habit-month-label').innerText = currentHabitMonth.toLocaleString('pt-BR', { month: 'long', year: 'numeric' });

    const dim = new Date(currentHabitMonth.getFullYear(), currentHabitMonth.getMonth() + 1, 0).getDate();

    let h = '<th class="p-1 text-left text-yellow-500 font-bold text-xs bg-slate-900/50 sticky left-0 z-10 min-w-[90px] md:min-w-[130px] border-b border-yellow-600/30">Hábito</th>';
    for (let i = 1; i <= dim; i++) {
        h += `<th class="p-0.5 text-center text-[7px] md:text-[8px] text-yellow-600 w-5 md:w-8 min-w-[20px] md:min-w-[32px] border-b border-yellow-600/30">${i}</th>`;
    }
    h += '<th class="p-1 text-center text-yellow-500 font-bold text-[10px] md:text-xs bg-slate-900/50 min-w-[50px] border-b border-yellow-600/30"> </th>';
    document.getElementById('habits-header-row').innerHTML = h;

    document.getElementById('habits-list').innerHTML = habits.map(hb => {
        const checks = JSON.parse(hb.checked_dates || '[]');
        let cells = `<td class="p-1 border-b border-yellow-600/20 font-bold text-white text-xs md:text-sm sticky left-0 bg-slate-800 z-10 shadow-[4px_0_10px_rgba(0,0,0,0.2)]">✓ ${hb.name}</td>`;
        for (let i = 1; i <= dim; i++) {
            const d = `${ym}-${String(i).padStart(2, '0')}`;
            const isChecked = checks.includes(d);
            cells += `<td class="border-b border-yellow-600/20 text-center p-0.5"><button onclick="toggleHabitInstant(event, ${hb.id}, '${d}')" class="w-5 h-5 md:w-6 md:h-6 rounded text-[6px] md:text-[8px] transition-all transform hover:scale-110 ${isChecked ? 'bg-gradient-to-r from-yellow-600 to-yellow-700 text-white shadow-[0_0_8px_rgba(212,175,55,0.4)]' : 'bg-slate-700/30 hover:bg-slate-700 text-transparent'}">${isChecked ? '<i class="fas fa-check"></i>' : ''}</button></td>`;
        }
        cells += `<td class="border-b border-yellow-600/20 text-center p-1"><button onclick="deleteHabitForMonth(${hb.id})" class="w-8 h-8 md:w-9 md:h-9 rounded-lg text-red-400 hover:text-red-200 hover:bg-red-900/30 transition"><i class="fas fa-trash"></i></button></td>`;
        return `<tr class="hover:bg-slate-800/30 transition">${cells}</tr>`;
    }).join('');
}

async function toggleHabitInstant(event, id, date) {
    const btn = event.currentTarget;
    const isChecked = btn.classList.contains('bg-gradient-to-r');
    if (isChecked) {
        btn.className = "w-5 h-5 md:w-6 md:h-6 rounded text-[6px] md:text-[8px] transition-all transform hover:scale-110 bg-slate-700/30 hover:bg-slate-700 text-transparent";
        btn.innerHTML = "";
    } else {
        btn.className = "w-5 h-5 md:w-6 md:h-6 rounded text-[6px] md:text-[8px] transition-all transform hover:scale-110 bg-gradient-to-r from-yellow-600 to-yellow-700 text-white shadow-[0_0_8px_rgba(212,175,55,0.4)]";
        btn.innerHTML = '<i class="fas fa-check"></i>';
    }
    await api('toggle_habit', { id, date });
}

async function deleteHabitForMonth(id) {
    const ym = currentHabitMonth.toISOString().slice(0, 7);
    const label = currentHabitMonth.toLocaleString('pt-BR', { month: 'long', year: 'numeric' });
    const confirmDelete = confirm(`Remover este hábito a partir de ${label}? Meses anteriores permanecerão salvos.`);
    if (!confirmDelete) return;
    await api('delete_habit_month', { id, month: ym });
    loadHabits();
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
