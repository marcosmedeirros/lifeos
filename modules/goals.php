<?php
// ARQUIVO: goals.php - Página completa de Metas
require_once __DIR__ . '/../includes/auth.php';
// require_login();

// Roteador da API
if (isset($_GET['api'])) {
    $action = $_GET['api'];
    header('Content-Type: application/json');
    
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) $data = $_POST;
    
    try {
        // Rota para buscar todas as metas

if ($action === 'get_goals') { 

    echo json_encode($pdo->query("SELECT * FROM goals WHERE user_id = 1 ORDER BY status ASC, id DESC")->fetchAll()); 

    exit; 

}



// Rota para salvar ou atualizar uma meta

if ($action === 'save_goal') {

    $title = $data['title'];

    $difficulty = $data['difficulty'];

    $id = $data['id'] ?? null;

    $user_id = 1;



    if ($id) {

        // EDIÇÃO

        $stmt = $pdo->prepare("UPDATE goals SET title = ?, difficulty = ? WHERE id = ? AND user_id = ?");

        $stmt->execute([$title, $difficulty, $id, $user_id]);

    } else {

        // CRIAÇÃO

        $stmt = $pdo->prepare("INSERT INTO goals (user_id, title, difficulty) VALUES (?, ?, ?)");

        $stmt->execute([$user_id, $title, $difficulty]);

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
                <h2 class="text-3xl font-bold text-white">Metas</h2>
                <button onclick="openModal('modal-goal')" class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white px-6 py-2 rounded-lg font-bold shadow-lg shadow-blue-500/20 transition transform hover:-translate-y-0.5">
                    <i class="fas fa-plus mr-2"></i> Nova Meta
                </button>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6" id="goals-list"></div>
        </div>
    </div>
</div>

<div id="modal-overlay" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
    <div id="modal-content" class="modal-glass rounded-2xl p-8 w-full max-w-md relative max-h-[90vh] overflow-y-auto">
        <button onclick="closeModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white transition w-8 h-8 flex items-center justify-center rounded-full hover:bg-slate-800 z-50">
            <i class="fas fa-times text-xl"></i>
        </button>
        
        <form id="modal-goal" class="modal-form hidden" onsubmit="submitGoal(event)">
            <h3 class="text-2xl font-bold mb-6 text-white bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-cyan-400" id="goal-modal-title">Nova Meta</h3>
            <input type="hidden" name="id" id="goal-id">
            <div class="space-y-5">
                <input type="text" name="title" id="goal-title" placeholder="Qual seu objetivo?" required class="text-lg">
                <div>
                    <label class="text-sm font-medium text-slate-300 mb-1.5 block">Dificuldade</label>
                    <select name="difficulty" id="goal-difficulty">
                        <option value="facil">Fácil</option>
                        <option value="media" selected>Média</option>
                        <option value="dificil">Difícil</option>
                    </select>
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="submit" class="flex-1 bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-500 hover:to-cyan-500 text-white font-bold py-3 rounded-xl shadow-lg transition mt-2">
                        Salvar Meta
                    </button>
                    <button type="button" id="btn-delete-goal" onclick="deleteGoal()" class="hidden bg-rose-500/10 hover:bg-rose-500/20 text-rose-500 px-4 rounded-xl border border-rose-500/30 transition">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="/assets/js/common.js"></script>
<script>
window.goalsData = [];

async function loadGoals() {
    const goals = await api('get_goals');
    window.goalsData = goals;
    
    const goalsByDifficulty = { facil: [], media: [], dificil: [] };
    goals.forEach(g => {
        const diff = g.difficulty || 'media';
        if (goalsByDifficulty[diff]) goalsByDifficulty[diff].push(g);
    });

    const diffConfig = { 
        'facil': { title: 'Fácil', color: 'green', border: 'border-emerald-500', text: 'text-emerald-400', bg: 'bg-emerald-500' }, 
        'media': { title: 'Média', color: 'yellow', border: 'border-yellow-500', text: 'text-yellow-400', bg: 'bg-yellow-500' }, 
        'dificil': { title: 'Difícil', color: 'red', border: 'border-rose-500', text: 'text-rose-400', bg: 'bg-rose-500' } 
    };

    let finalHtml = '';
    Object.keys(goalsByDifficulty).forEach(key => {
        const config = diffConfig[key];
        const columnGoals = goalsByDifficulty[key].sort((a, b) => a.status - b.status);
        
        let goalItemsHtml = columnGoals.map(g => {
            const isDone = g.status == 1;
            return `<div onclick="editGoal(${g.id})" class="glass-card p-6 rounded-2xl relative border-l-4 ${config.border} hover:bg-slate-800/50 transition group hover:-translate-y-0.5 duration-300 cursor-pointer">
                <div class="flex justify-between items-start mb-3">
                    <span class="text-[10px] font-bold uppercase tracking-wider ${config.text} bg-slate-900/50 px-2 py-1 rounded border border-slate-700/50">${config.title}</span>
                    <label class="custom-checkbox relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" onchange="event.stopPropagation(); toggleGoal(${g.id})" class="sr-only peer" ${isDone ? 'checked' : ''}>
                        <div class="w-6 h-6 border-2 border-slate-500 rounded-full peer-checked:bg-purple-600 peer-checked:border-purple-600 transition flex items-center justify-center">
                            <i class="fas fa-check text-white text-xs opacity-0 peer-checked:opacity-100 transition-opacity"></i>
                        </div>
                    </label>
                </div>
                <h3 class="text-lg font-bold text-white mb-2 ${isDone ? 'line-through text-slate-500' : ''}">${g.title}</h3>
                <div class="w-full bg-slate-700/50 h-1.5 rounded-full mt-4 overflow-hidden">
                    <div class="h-full ${config.bg} transition-all duration-500 shadow-[0_0_10px_currentColor]" style="width: ${isDone ? '100%' : '5%'}"></div>
                </div>
            </div>`;
        }).join('');

        finalHtml += `<div class="space-y-4"><h3 class="text-xl font-bold text-${config.color}-400 border-b border-slate-700/50 pb-2">${config.title}</h3>${goalItemsHtml}${columnGoals.length === 0 ? `<p class="text-slate-500 italic text-sm p-4 bg-slate-800/30 rounded-xl">Nenhuma meta ${config.title}.</p>` : ''}</div>`;
    });

    document.getElementById('goals-list').innerHTML = finalHtml || `<div class="col-span-3 text-center text-slate-500 py-10 italic">Você ainda não definiu nenhuma meta.</div>`;
}

function editGoal(id) {
    const goal = window.goalsData.find(g => g.id == id);
    if (!goal) return;
    resetForm(document.getElementById('modal-goal'));
    document.getElementById('goal-modal-title').innerText = "Editar Meta";
    document.getElementById('goal-id').value = goal.id;
    document.getElementById('goal-title').value = goal.title;
    document.getElementById('goal-difficulty').value = goal.difficulty;
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
