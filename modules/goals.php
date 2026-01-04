<?php
// ARQUIVO: goals.php - Página completa de Metas
require_once __DIR__ . '/../includes/auth.php';
require_login(); // Requer login obrigatório

$user_id = $_SESSION['user_id'];
// Definir user_id padrão se não existir sessão
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['user_name'] = 'Marcos Medeiros';
}

// Roteador da API
if (isset($_GET['api'])) {
    $action = $_GET['api'];
    header('Content-Type: application/json');
    
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) $data = $_POST;
    
    try {
        // Rota para buscar todas as metas

if ($action === 'get_goals') { 

    $type = $_GET['type'] ?? 'geral';
    echo json_encode($pdo->query("SELECT * FROM goals WHERE user_id = 1 AND goal_type = '$type' ORDER BY status ASC, id DESC")->fetchAll()); 

    exit; 

}



// Rota para salvar ou atualizar uma meta

if ($action === 'save_goal') {

    $title = $data['title'];

    $difficulty = $data['difficulty'];
    
    $goal_type = $data['goal_type'] ?? 'geral';

    $id = $data['id'] ?? null;

    $user_id = 1;



    if ($id) {

        // EDIÇÃO

        $stmt = $pdo->prepare("UPDATE goals SET title = ?, difficulty = ?, goal_type = ? WHERE id = ? AND user_id = ?");

        $stmt->execute([$title, $difficulty, $goal_type, $id, $user_id]);

    } else {

        // CRIAÇÃO

        $stmt = $pdo->prepare("INSERT INTO goals (user_id, title, difficulty, goal_type) VALUES (?, ?, ?, ?)");

        $stmt->execute([$user_id, $title, $difficulty, $goal_type]);

    }

    echo json_encode(['success' => true, 'id' => $id]);

    exit;

}



// Rota para alternar o status (concluído/não concluído)

if ($action === 'toggle_goal') { 

    $pdo->prepare("UPDATE goals SET status=1-status WHERE id=? AND user_id=1")->execute([$data['id']]); 

    exit; 

}



// Rota para DELETAR uma meta
if ($action === 'delete_goal') { 
    $pdo->prepare("DELETE FROM goals WHERE id=? AND user_id={$user_id}")->execute([$data['id']]); 
    echo json_encode(['success' => true]); 
    exit; 
}
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// HTML da página
$page = 'goals';
$page_title = 'Metas - LifeOS';
include __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen w-full">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="flex-1 p-4 md:p-10 content-wrap transition-all duration-300">
        <div class="main-shell">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-4xl font-bold text-white">🎯 Metas</h2>
                <button onclick="openModal('modal-goal')" class="bg-white hover:bg-gray-100 text-black px-6 py-2 rounded-lg font-bold shadow-lg transition transform hover:-translate-y-0.5">
                    <i class="fas fa-plus mr-2"></i> Nova Meta
                </button>
            </div>
            
            <!-- Abas de Navegação -->
            <div class="flex gap-4 mb-8 border-b border-gray-600/20">
                <button onclick="switchGoalType('geral')" id="tab-geral" class="px-6 py-3 font-bold text-white border-b-2 border-white transition">
                    <i class="fas fa-target mr-2"></i>Metas Gerais
                </button>
                <button onclick="switchGoalType('anual')" id="tab-anual" class="px-6 py-3 font-bold text-gray-400 border-b-2 border-transparent hover:text-white transition">
                    <i class="fas fa-calendar mr-2"></i>Metas 2026
                </button>
            </div>
            
            <style>
            #goals-list .goal-card { background: rgba(0,0,0,0.3); }
            </style>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6" id="goals-list"></div>
        </div>
    </div>
</div>

<div id="modal-overlay" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
    <div id="modal-content" class="modal-glass rounded-2xl p-8 w-full max-w-md relative max-h-[90vh] overflow-y-auto">
        <button onclick="closeModal()" class="absolute top-4 right-4 text-gray-400 hover:text-white transition w-8 h-8 flex items-center justify-center rounded-full hover:bg-black/60 z-50">
            <i class="fas fa-times text-xl"></i>
        </button>
        
        <form id="modal-goal" class="modal-form hidden" onsubmit="submitGoal(event)">
            <h3 class="text-2xl font-bold mb-6 text-white" id="goal-modal-title">🎯 Nova Meta</h3>
            <input type="hidden" name="id" id="goal-id">
            <input type="hidden" name="goal_type" id="goal-type" value="geral">
            <div class="space-y-5">
                <input type="text" name="title" id="goal-title" placeholder="Qual seu objetivo?" required class="text-lg">
                <div>
                    <label class="text-sm font-medium text-gray-300 mb-1.5 block">Dificuldade</label>
                    <select name="difficulty" id="goal-difficulty">
                        <option value="facil">Fácil</option>
                        <option value="media" selected>Média</option>
                        <option value="dificil">Difícil</option>
                    </select>
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="submit" class="flex-1 bg-white hover:bg-gray-100 text-black font-bold py-3 rounded-xl shadow-lg transition mt-2">
                        💾 Salvar Meta
                    </button>
                    <button type="button" id="btn-delete-goal" onclick="deleteGoal()" class="hidden bg-rose-500/10 hover:bg-rose-500/20 text-rose-500 px-4 rounded-xl border border-rose-500/30 transition">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="<?php echo BASE_PATH; ?>/assets/js/common.js"></script>
<script>
window.goalsData = [];
window.currentGoalType = 'geral';

async function loadGoals() {
    const goals = await api('get_goals&type=' + window.currentGoalType);
    window.goalsData = goals;
    
    const goalsByDifficulty = { facil: [], media: [], dificil: [] };
    goals.forEach(g => {
        const diff = g.difficulty || 'media';
        if (goalsByDifficulty[diff]) goalsByDifficulty[diff].push(g);
    });

    const diffConfig = { 
        'facil': { title: 'Fácil', color: 'gray', border: 'border-gray-500', text: 'text-gray-400', bg: 'bg-gray-500' }, 
        'media': { title: 'Média', color: 'gray', border: 'border-gray-400', text: 'text-gray-300', bg: 'bg-gray-400' }, 
        'dificil': { title: 'Difícil', color: 'gray', border: 'border-gray-300', text: 'text-gray-200', bg: 'bg-gray-300' } 
    };

    let finalHtml = '';
    Object.keys(goalsByDifficulty).forEach(key => {
        const config = diffConfig[key];
        const columnGoals = goalsByDifficulty[key].sort((a, b) => a.status - b.status);
        
        let goalItemsHtml = columnGoals.map(g => {
            const isDone = g.status == 1;
            return `<div onclick=\"editGoal(${g.id})\" class=\"glass-card p-6 rounded-2xl relative border-l-4 ${config.border} hover:bg-black/50 transition group hover:-translate-y-0.5 duration-300 cursor-pointer\">
                <div class="flex justify-between items-start mb-3">
                    <span class=\"text-[10px] font-bold uppercase tracking-wider ${config.text} bg-black/30 px-2 py-1 rounded border border-gray-600/30\">${config.title}</span>
                    <label class="custom-checkbox relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" onchange="event.stopPropagation(); toggleGoal(${g.id})" class="sr-only peer" ${isDone ? 'checked' : ''}>
                        <div class="w-6 h-6 border-2 border-gray-500 rounded-full peer-checked:bg-white peer-checked:border-white transition flex items-center justify-center">
                            <i class="fas fa-check text-black text-xs opacity-0 peer-checked:opacity-100 transition-opacity"></i>
                        </div>
                    </label>
                </div>
                <h3 class="text-lg font-bold text-white mb-2 ${isDone ? 'line-through text-gray-500' : ''}">${g.title}</h3>
                <div class=\"w-full bg-gray-600/30 h-1.5 rounded-full mt-4 overflow-hidden\">
                    <div class="h-full ${config.bg} transition-all duration-500 shadow-[0_0_10px_currentColor]" style="width: ${isDone ? '100%' : '5%'}"></div>
                </div>
            </div>`;
        }).join('');

        finalHtml += `<div class="space-y-4"><h3 class="text-xl font-bold text-${config.color}-400 border-b border-gray-700/40 pb-2">${config.title}</h3>${goalItemsHtml}${columnGoals.length === 0 ? `<p class="text-gray-500 italic text-sm p-4 bg-black/40 rounded-xl">Nenhuma meta ${config.title}.</p>` : ''}</div>`;
    });

    document.getElementById('goals-list').innerHTML = finalHtml || `<div class="col-span-3 text-center text-gray-500 py-10 italic">Você ainda não definiu nenhuma meta.</div>`;
}

function switchGoalType(type) {
    window.currentGoalType = type;
    document.getElementById('goal-type').value = type;
    
    // Atualizar abas
    document.getElementById('tab-geral').classList.remove('border-blue-500', 'text-white');
    document.getElementById('tab-anual').classList.remove('border-blue-500', 'text-white');
    document.getElementById('tab-geral').classList.add('border-transparent', 'text-slate-400');
    document.getElementById('tab-anual').classList.add('border-transparent', 'text-slate-400');
    
    if (type === 'geral') {
        document.getElementById('tab-geral').classList.add('border-blue-500', 'text-white');
        document.getElementById('tab-geral').classList.remove('border-transparent', 'text-slate-400');
    } else {
        document.getElementById('tab-anual').classList.add('border-blue-500', 'text-white');
        document.getElementById('tab-anual').classList.remove('border-transparent', 'text-slate-400');
    }
    
    loadGoals();
}

function editGoal(id) {
    const goal = window.goalsData.find(g => g.id == id);
    if (!goal) return;
    resetForm(document.getElementById('modal-goal'));
    document.getElementById('goal-modal-title').innerText = "Editar Meta";
    document.getElementById('goal-id').value = goal.id;
    document.getElementById('goal-title').value = goal.title;
    document.getElementById('goal-difficulty').value = goal.difficulty;
    document.getElementById('goal-type').value = goal.goal_type;
    document.getElementById('btn-delete-goal').classList.remove('hidden');
    openModal('modal-goal', false);
}

async function submitGoal(e) { 
    e.preventDefault(); 
    await api('save_goal', Object.fromEntries(new FormData(e.target))); 
    closeModal(); 
    loadGoals(); 
}

async function deleteGoal() {
    const id = document.getElementById('goal-id').value;
    if (confirm('Tem certeza que deseja apagar esta meta?')) {
        await api('delete_goal', { id });
        closeModal();
        loadGoals();
    }
}

async function toggleGoal(id) { 
    await api('toggle_goal', {id}); 
    loadGoals(); 
}

document.addEventListener('DOMContentLoaded', () => {
    loadGoals();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
