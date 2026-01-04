<?php
require_once __DIR__ . '/../includes/auth.php';
require_login(); // Requer login obrigatório

// Define user_id da sessão
$user_id = $_SESSION['user_id'];
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['user_name'] = 'Marcos Medeiros';
}

$page = 'game';

if (isset($_GET['api'])) {
    try {
        require_once __DIR__ . '/../config.php';
        $action = $_GET['api'];
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

if ($action === 'get_game_data') {
    // 1. Definições de Tarefas que dão XP (As "missões") - Lendo do DB
    $xp_tasks = $pdo->query("SELECT id, name, xp, icon, color FROM game_tasks WHERE user_id = 1")->fetchAll(PDO::FETCH_ASSOC);

    // 2. Definições de Recompensas (O que pode ser resgatado) - Lendo do DB
    $rewards = $pdo->query("SELECT id, name, cost, icon, color FROM game_rewards WHERE user_id = 1")->fetchAll(PDO::FETCH_ASSOC);

    // 3. Pontos Atuais do Usuário
    $user_xp = 0;
    try {
        $xp_data = $pdo->query("SELECT total_xp FROM user_settings WHERE user_id = 1")->fetch(PDO::FETCH_ASSOC);
        if ($xp_data) {
            $user_xp = (int)$xp_data['total_xp'];
        }
    } catch (PDOException $e) {
        // Ignora, assume 0 XP se a tabela não estiver pronta.
    }

    echo json_encode([
        'xp_tasks' => $xp_tasks,
        'rewards' => $rewards,
        'user_xp' => $user_xp
    ]);
    exit;
}

// NOVO: CRUD para Missões (Tasks)
if ($action === 'save_game_task') {
    $is_edit = isset($data['id']) && $data['id'];
    if ($is_edit) {
        $stmt = $pdo->prepare("UPDATE game_tasks SET name=?, xp=?, icon=?, color=? WHERE id=? AND user_id=1");
        $stmt->execute([$data['name'], $data['xp'], $data['icon'], $data['color'], $data['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO game_tasks (user_id, name, xp, icon, color) VALUES (1, ?, ?, ?, ?)");
        $stmt->execute([$data['name'], $data['xp'], $data['icon'], $data['color']]);
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'delete_game_task') {
    $pdo->prepare("DELETE FROM game_tasks WHERE id=? AND user_id=1")->execute([$data['id']]);
    echo json_encode(['success' => true]);
    exit;
}

// NOVO: CRUD para Recompensas (Rewards)
if ($action === 'save_game_reward') {
    $is_edit = isset($data['id']) && $data['id'];
    if ($is_edit) {
        $stmt = $pdo->prepare("UPDATE game_rewards SET name=?, cost=?, icon=?, color=? WHERE id=? AND user_id=1");
        $stmt->execute([$data['name'], $data['cost'], $data['icon'], $data['color'], $data['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO game_rewards (user_id, name, cost, icon, color) VALUES (1, ?, ?, ?, ?)");
        $stmt->execute([$data['name'], $data['cost'], $data['icon'], $data['color']]);
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'delete_game_reward') {
    $pdo->prepare("DELETE FROM game_rewards WHERE id=? AND user_id=1")->execute([$data['id']]);
    echo json_encode(['success' => true]);
    exit;
}

// Função para registrar pontos (o usuário clica em "fiz essa tarefa")
if ($action === 'earn_xp' && isset($data['xp']) && isset($data['task_id'])) {
    $xp = (int)$data['xp'];
    $taskId = (int)$data['task_id'];
    
    try {
        $stmt = $pdo->prepare("UPDATE user_settings SET total_xp = total_xp + ? WHERE user_id = 1");
        $stmt->execute([$xp]);
        
        echo json_encode(['success' => true, 'message' => "Ganhou +$xp XP!"]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => "Erro ao registrar XP: " . $e->getMessage()]);
    }
    exit;
}

// Função para resgatar recompensa
if ($action === 'redeem_reward' && isset($data['cost']) && isset($data['reward_id'])) {
    $cost = (int)$data['cost'];
    $rewardId = (int)$data['reward_id'];

    try {
        // 1. Verifica os pontos atuais
        $xp_data = $pdo->query("SELECT total_xp FROM user_settings WHERE user_id = 1")->fetch(PDO::FETCH_ASSOC);
        $user_xp = (int)$xp_data['total_xp'];

        if ($user_xp < $cost) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Pontos insuficientes. Você precisa de $cost XP."]);
            exit;
        }

        // 2. Decrementa os pontos
        $stmt = $pdo->prepare("UPDATE user_settings SET total_xp = total_xp - ? WHERE user_id = 1");
        $stmt->execute([$cost]);
        
        echo json_encode(['success' => true, 'new_xp' => $user_xp - $cost, 'message' => "Recompensa resgatada! -$cost XP."]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => "Erro ao resgatar recompensa: " . $e->getMessage()]);
    }
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
        <div class="main-shell">
            <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
                <h2 class="text-3xl font-bold text-white">Gamificação</h2>
                <div class="flex gap-3 items-center">
                    <div class="glass-card px-6 py-3 rounded-xl border border-white/30 bg-white/10">
                        <span class="text-sm font-medium text-slate-300 mr-2">Seu XP:</span>
                        <span class="text-2xl font-bold text-white" id="user-xp-display">0 XP</span>
                    </div>
                    <button onclick="openModal('modal-game-task', false); openMissionModal(0);" class="bg-white hover:bg-gray-100 text-black px-5 py-2 rounded-lg font-bold shadow-lg transition">
                        <i class="fas fa-plus mr-1"></i> 🎉 Missão
                    </button>
                    <button onclick="openModal('modal-game-reward', false); openRewardModal(0);" class="bg-white hover:bg-gray-100 text-black px-5 py-2 rounded-lg font-bold shadow-lg transition">
                        <i class="fas fa-plus mr-1"></i> 🎁 Recompensa
                    </button>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Missões (Ganhar XP) -->
                <div class="glass-card p-6 rounded-2xl shadow-2xl">
                    <h3 class="text-2xl font-bold text-emerald-400 mb-6 flex items-center gap-2">
                        <i class="fas fa-trophy"></i> Missões
                    </h3>
                    <div id="xp-tasks-list" class="space-y-4">
                        <p class="text-slate-500 italic">Carregando missões...</p>
                    </div>
                </div>
                
                <!-- Recompensas (Gastar XP) -->
                <div class="glass-card p-6 rounded-2xl shadow-2xl">
                    <h3 class="text-2xl font-bold text-white mb-6 flex items-center gap-2">
                        <i class="fas fa-gift"></i> Recompensas
                    </h3>
                    <div id="rewards-list" class="space-y-4">
                        <p class="text-slate-500 italic">Carregando recompensas...</p>
                    </div>
                </div>
            </div>
            
            <!-- Seção Admin (Gerenciar Missões e Recompensas) -->
            <div class="mt-8 glass-card p-6 rounded-2xl shadow-2xl">
                <h3 class="text-2xl font-bold text-white mb-6 flex items-center gap-2">
                    <i class="fas fa-cog"></i> Central de Gamificação
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h4 class="text-lg font-bold text-emerald-400 mb-4">Suas Missões</h4>
                        <div id="admin-missions-list" class="space-y-2 max-h-96 overflow-y-auto no-scrollbar"></div>
                    </div>
                    <div>
                        <h4 class="text-lg font-bold text-gray-300 mb-4">Suas Recompensas</h4>
                        <div id="admin-rewards-list" class="space-y-2 max-h-96 overflow-y-auto no-scrollbar"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Game Task -->
<div id="modal-overlay" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4" onclick="closeModal()">
    <div id="modal-content" class="modal-glass rounded-2xl p-8 w-full max-w-md relative max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
        <button type="button" onclick="closeModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white transition w-8 h-8 flex items-center justify-center rounded-full hover:bg-slate-800 z-50">
            <i class="fas fa-times text-xl"></i>
        </button>
        <form id="modal-game-task" class="modal-form hidden" onsubmit="submitGameTask(event)">
        <h3 class="text-2xl font-bold mb-6 text-white" id="task-modal-title">🎉 Nova Missão</h3>
        <input type="hidden" name="id" id="task-id">
        <div class="space-y-5">
            <input type="text" name="name" id="task-name" placeholder="Nome da Missão (Ex: Correr 5km)" required>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-medium text-slate-300 mb-1.5 block">XP Ganho</label>
                    <input type="number" name="xp" id="task-xp" placeholder="20" required>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-300 mb-1.5 block">Cor (Tailwind)</label>
                    <select name="color" id="task-color">
                        <option value="emerald">Verde (emerald)</option>
                        <option value="purple">Roxo (purple)</option>
                        <option value="blue">Azul (blue)</option>
                        <option value="teal">Teal</option>
                        <option value="yellow">Amarelo</option>
                        <option value="rose">Rosa</option>
                        <option value="sky">Azul Céu</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300 mb-1.5 block">Ícone (FontAwesome)</label>
                <input type="text" name="icon" id="task-icon" placeholder="Ex: fa-running" required>
            </div>
            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 bg-white hover:bg-gray-100 text-black font-bold py-3 rounded-xl shadow-lg transition">💾 Salvar Missão</button>
                <button type="button" id="btn-delete-task" onclick="deleteGameTask()" class="hidden bg-rose-500/10 hover:bg-rose-500/20 text-rose-500 px-4 rounded-xl border border-rose-500/30 transition">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    </form>
    
    <form id="modal-game-reward" class="modal-form hidden" onsubmit="submitGameReward(event)">
        <h3 class="text-2xl font-bold mb-6 text-white" id="reward-modal-title">🎁 Nova Recompensa</h3>
        <input type="hidden" name="id" id="reward-id">
        <div class="space-y-5">
            <input type="text" name="name" id="reward-name" placeholder="Nome da Recompensa (Ex: Rodízio de Pizza)" required>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-medium text-slate-300 mb-1.5 block">Custo (XP)</label>
                    <input type="number" name="cost" id="reward-cost" placeholder="200" required>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-300 mb-1.5 block">Cor (Tailwind)</label>
                    <select name="color" id="reward-color">
                        <option value="emerald">Verde (emerald)</option>
                        <option value="purple" selected>Roxo (purple)</option>
                        <option value="blue">Azul (blue)</option>
                        <option value="teal">Teal</option>
                        <option value="yellow">Amarelo</option>
                        <option value="rose">Rosa</option>
                        <option value="sky">Azul Céu</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300 mb-1.5 block">Ícone (FontAwesome)</label>
                <input type="text" name="icon" id="reward-icon" placeholder="Ex: fa-pizza-slice" required>
            </div>
            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 bg-white hover:bg-gray-100 text-black font-bold py-3 rounded-xl shadow-lg transition">💾 Salvar Recompensa</button>
                <button type="button" id="btn-delete-reward" onclick="deleteGameReward()" class="hidden bg-rose-500/10 hover:bg-rose-500/20 text-rose-500 px-4 rounded-xl border border-rose-500/30 transition">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
        </form>
    </div>
</div>

<script src="<?php echo BASE_PATH; ?>/assets/js/common.js"></script>
<script>
window.gameData = { xp_tasks: [], rewards: [], user_xp: 0 };

async function loadGame() {
    try {
        const data = await api('get_game_data');
        window.gameData = data;
        renderGame();
        renderAdminLists();
    } catch (error) {
        document.getElementById('xp-tasks-list').innerHTML = `<p class="text-rose-400">Erro ao carregar dados do Game.</p>`;
        document.getElementById('rewards-list').innerHTML = `<p class="text-rose-400">Erro ao carregar recompensas.</p>`;
    }
}

function renderAdminLists() {
    const { xp_tasks, rewards } = window.gameData;
    const tasksList = document.getElementById('admin-missions-list');
    const rewardsList = document.getElementById('admin-rewards-list');

    const taskHtml = xp_tasks.map(t => `
        <div onclick="openMissionModal(${t.id})" class="flex justify-between items-center bg-slate-800 p-3 rounded-lg border-l-4 mb-2 border-${t.color}-500 hover:bg-slate-700 transition cursor-pointer">
            <span class="font-medium">${t.name} <span class="text-emerald-400 text-xs font-bold">(+${t.xp} XP)</span></span>
            <i class="fas fa-pen text-slate-400 text-sm"></i>
        </div>
    `).join('');

    const rewardHtml = rewards.map(r => `
        <div onclick="openRewardModal(${r.id})" class="flex justify-between items-center bg-slate-800 p-3 rounded-lg border-l-4 mb-2 border-${r.color}-500 hover:bg-slate-700 transition cursor-pointer">
            <span class="font-medium">${r.name} <span class="text-gray-400 text-xs font-bold">(${r.cost} XP)</span></span>
            <i class="fas fa-pen text-slate-400 text-sm"></i>
        </div>
    `).join('');

    tasksList.innerHTML = taskHtml || '<p class="text-slate-500 italic text-sm">Nenhuma missão cadastrada.</p>';
    rewardsList.innerHTML = rewardHtml || '<p class="text-slate-500 italic text-sm">Nenhuma recompensa cadastrada.</p>';
}

function openMissionModal(id) {
    resetForm(document.getElementById('modal-game-task'));
    if (id > 0) {
        const task = window.gameData.xp_tasks.find(t => t.id === id);
        if (task) {
            document.getElementById('task-modal-title').innerText = "Editar Missão";
            document.getElementById('task-id').value = task.id;
            document.getElementById('task-name').value = task.name;
            document.getElementById('task-xp').value = task.xp;
            document.getElementById('task-icon').value = task.icon;
            document.getElementById('task-color').value = task.color;
            document.getElementById('btn-delete-task').classList.remove('hidden');
        }
    } else {
        document.getElementById('task-modal-title').innerText = "Nova Missão";
    }
    openModal('modal-game-task', false); 
}

async function submitGameTask(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target));
    await api('save_game_task', data);
    closeModal();
    loadGame();
}

async function deleteGameTask() {
    const id = document.getElementById('task-id').value;
    if (confirm('Tem certeza que deseja apagar esta missão?')) {
        await api('delete_game_task', { id });
        closeModal();
        loadGame(); 
    }
}

function openRewardModal(id) {
    resetForm(document.getElementById('modal-game-reward'));
    if (id > 0) {
        const reward = window.gameData.rewards.find(r => r.id === id);
        if (reward) {
            document.getElementById('reward-modal-title').innerText = "Editar Recompensa";
            document.getElementById('reward-id').value = reward.id;
            document.getElementById('reward-name').value = reward.name;
            document.getElementById('reward-cost').value = reward.cost;
            document.getElementById('reward-icon').value = reward.icon;
            document.getElementById('reward-color').value = reward.color;
            document.getElementById('btn-delete-reward').classList.remove('hidden');
        }
    } else {
        document.getElementById('reward-modal-title').innerText = "Nova Recompensa";
    }
    openModal('modal-game-reward', false); 
}

async function submitGameReward(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target));
    await api('save_game_reward', data);
    closeModal();
    loadGame();
}

async function deleteGameReward() {
    const id = document.getElementById('reward-id').value;
    if (confirm('Tem certeza que deseja apagar esta recompensa?')) {
        await api('delete_game_reward', { id });
        closeModal();
        loadGame(); 
    }
}

function renderGame() {
    const { user_xp, xp_tasks, rewards } = window.gameData;
    document.getElementById('user-xp-display').innerText = `${user_xp} XP`;

    const tasksHtml = xp_tasks.map(task => `
        <div class="flex items-center p-3 bg-slate-800/50 rounded-xl border border-slate-700/50 hover:bg-slate-800 transition">
            <div class="w-10 h-10 rounded-full flex items-center justify-center bg-${task.color}-500/20 text-${task.color}-400 mr-4">
                <i class="fas ${task.icon}"></i>
            </div>
            <div class="flex-1">
                <p class="font-bold text-white">${task.name}</p>
                <span class="text-sm text-emerald-400 font-medium">+${task.xp} XP</span>
            </div>
            <button onclick="earnXP(${task.xp}, ${task.id})" class="bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-1.5 rounded-lg text-sm font-bold transition shadow-md shadow-emerald-900/30">
                Ganhar
            </button>
        </div>
    `).join('');
    document.getElementById('xp-tasks-list').innerHTML = tasksHtml || '<p class="text-slate-500 italic">Nenhuma missão disponível. Adicione uma na Central de Gamificação.</p>';

    const rewardsHtml = rewards.map(reward => {
        const canRedeem = user_xp >= reward.cost;
        const buttonClass = canRedeem 
            ? 'bg-white hover:bg-gray-100 shadow-gray-900/30' 
            : 'bg-slate-700 cursor-not-allowed opacity-50';
        
        return `
            <div class="flex items-center p-3 bg-slate-800/50 rounded-xl border border-slate-700/50 hover:bg-slate-800 transition">
                <div class="w-10 h-10 rounded-full flex items-center justify-center bg-white/20 text-white mr-4">
                    <i class="fas ${reward.icon}"></i>
                </div>
                <div class="flex-1">
                    <p class="font-bold text-white">${reward.name}</p>
                    <span class="text-sm text-gray-400 font-mono">${reward.cost} XP</span>
                </div>
                <button 
                    onclick="${canRedeem ? `redeemReward(${reward.cost}, ${reward.id}, '${reward.name}')` : ''}" 
                    class="text-black px-3 py-1.5 rounded-lg text-sm font-bold transition shadow-md ${buttonClass}"
                    ${!canRedeem ? 'disabled' : ''}
                >
                    Resgatar
                </button>
            </div>
        `;
    }).join('');
    document.getElementById('rewards-list').innerHTML = rewardsHtml || '<p class="text-slate-500 italic">Nenhuma recompensa definida. Adicione uma na Central de Gamificação.</p>';
}

async function earnXP(xp, taskId) {
    if (confirm(`Confirmar conclusão da tarefa e ganhar ${xp} XP?`)) {
        const result = await api('earn_xp', { xp, task_id: taskId });
        if (result.success) {
            alert(result.message);
            loadGame();
        }
    }
}

async function redeemReward(cost, rewardId, rewardName) {
    if (confirm(`Tem certeza que deseja resgatar "${rewardName}" por ${cost} XP?`)) {
        try {
            const result = await api('redeem_reward', { cost, reward_id: rewardId });
            if (result.success) {
                alert(`Parabéns! ${rewardName} resgatado com sucesso!`);
                loadGame();
            }
        } catch (error) {
            // Erro tratado pelo wrapper api()
        }
    }
}

loadGame();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
