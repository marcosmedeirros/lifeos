<?php
// ARQUIVO: index.php - Dashboard Principal
require_once 'includes/auth.php';
// require_login(); // Comentado - acesso direto

// Definir user_id padrão se não existir sessão
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // ID do usuário padrão (Marcos Medeiros)
    $_SESSION['user_name'] = 'Marcos Medeiros';
}
$user_id = $_SESSION['user_id'];

// Roteador da API para o Dashboard
if (isset($_GET['api'])) {
    $action = $_GET['api'];
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) $data = $_POST;
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) $data = $_POST;
    
    try {
        // API do Dashboard
        if ($action === 'dashboard_stats') {
            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
            
            // Finanças da Semana
            $fin_stmt = $pdo->prepare("
                SELECT type, SUM(amount) as total 
                FROM finances 
                WHERE DATE(created_at) BETWEEN ? AND ? 
                GROUP BY type
            ");
            $fin_stmt->execute([$startOfWeek, $endOfWeek]);
            $fin = $fin_stmt->fetchAll();
            
            $inc = 0; $out = 0; 
            foreach($fin as $f) { 
                if(in_array($f['type'], ['income', 'entrada'])) $inc = $f['total']; 
                else $out = $f['total']; 
            }
            
            // XP Total
            $xp_total = $pdo->query("SELECT total_xp FROM user_settings WHERE user_id = {$user_id}")->fetchColumn() ?: 0;
            
            // Eventos da Semana
            $events_week_stmt = $pdo->prepare("
                SELECT id, title, start_date FROM events 
                WHERE DATE(start_date) BETWEEN ? AND ? 
                ORDER BY start_date LIMIT 5
            ");
            $events_week_stmt->execute([$startOfWeek, $endOfWeek]);
            $events_list = $events_week_stmt->fetchAll();

            // Atividades de Hoje
            $activities_count = $pdo->query("SELECT COUNT(*) FROM activities WHERE day_date = CURDATE() AND status = 0")->fetchColumn();
            $activities_today = $pdo->query("SELECT * FROM activities WHERE day_date = CURDATE() ORDER BY status ASC LIMIT 5")->fetchAll();
            
            // Treinos do Strava (esta semana)
            $strava_count = $pdo->prepare("
                SELECT COUNT(*) FROM strava_activities 
                WHERE DATE(start_date) BETWEEN ? AND ?
            ");
            $strava_count->execute([$startOfWeek, $endOfWeek]);
            $strava_count = $strava_count->fetchColumn() ?: 0;

            echo json_encode([
                'income_week' => $inc, 
                'outcome_week' => $out, 
                'xp_total' => $xp_total,
                'activities_count' => $activities_count,
                'strava_count' => $strava_count,
                'events_week' => $events_list,
                'activities_today' => $activities_today
            ]); 
            exit;
        }
        
        // Toggle de atividade (usado no dashboard)
        if ($action === 'toggle_activity') {
            $pdo->prepare("UPDATE activities SET status=1-status WHERE id=?")->execute([$_POST['id'] ?? $_GET['id']]);
            echo json_encode(['success'=>true]);
            exit;
        }
        
        // Salvar atividade (via dashboard)
        if ($action === 'save_activity') {
            $stmt = $pdo->prepare("INSERT INTO activities (user_id, title, category, day_date, period, status) VALUES (?, ?, ?, ?, ?, 0)");
            $stmt->execute([$user_id, $data['title'] ?? '', $data['category'] ?? '', $data['date'] ?? date('Y-m-d'), $data['period'] ?? 'morning']);
            echo json_encode(['success'=>true]);
            exit;
        }

        // Salvar evento (via dashboard)
        if ($action === 'save_event') {
            $stmt = $pdo->prepare("INSERT INTO events (user_id, title, start_date, description) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $data['title'] ?? '', $data['date'] ?? date('Y-m-d'), $data['desc'] ?? '']);
            echo json_encode(['success'=>true]);
            exit;
        }

        // Salvar finança (via dashboard)
        if ($action === 'save_finance') {
            $type = ($data['type'] === 'entrada' || $data['type'] === 'income') ? 'income' : 'expense';
            $stmt = $pdo->prepare("INSERT INTO finances (user_id, type, amount, description, category_id, created_at, status) VALUES (?, ?, ?, ?, NULL, ?, 0)");
            $stmt->execute([$user_id, $type, $data['amount'] ?? 0, $data['desc'] ?? '', $data['date'] ?? date('Y-m-d')]);
            echo json_encode(['success'=>true]);
            exit;
        }

        // Salvar hábito (via dashboard)
        if ($action === 'save_habit') {
            $pdo->prepare("INSERT INTO habits (name, checked_dates) VALUES (?, '[]')")->execute([$data['name'] ?? '']);
            echo json_encode(['success'=>true]);
            exit;
        }

            // Obter hábitos (via dashboard)
            if ($action === 'get_habits') {
                echo json_encode($pdo->query("SELECT * FROM habits ORDER BY id DESC")->fetchAll());
                exit;
            }

            // Toggle hábito do dia (via dashboard)
            if ($action === 'toggle_habit') {
                $id = $data['id'] ?? ($_POST['id'] ?? null);
                $date = $data['date'] ?? date('Y-m-d');
                $json = $pdo->prepare("SELECT checked_dates FROM habits WHERE id = ?");
                $json->execute([$id]);
                $arr = json_decode($json->fetchColumn() ?: '[]', true) ?: [];
                if (in_array($date, $arr)) {
                    $arr = array_values(array_diff($arr, [$date]));
                } else {
                    $arr[] = $date;
                }
                $upd = $pdo->prepare("UPDATE habits SET checked_dates = ? WHERE id = ?");
                $upd->execute([json_encode($arr), $id]);
                echo json_encode(['success' => true]);
                exit;
            }

        // Salvar meta (via dashboard)
        if ($action === 'save_goal') {
            if (!empty($data['id'])) {
                $stmt = $pdo->prepare("UPDATE goals SET title=?, difficulty=?, goal_type=? WHERE id=? AND user_id=?");
                $stmt->execute([$data['title'] ?? '', $data['difficulty'] ?? 'media', $data['goal_type'] ?? 'geral', $data['id'], $user_id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO goals (user_id, title, difficulty, goal_type, status) VALUES (?, ?, ?, ?, 0)");
                $stmt->execute([$user_id, $data['title'] ?? '', $data['difficulty'] ?? 'media', $data['goal_type'] ?? 'geral']);
            }
            echo json_encode(['success'=>true]);
            exit;
        }

        // Obter metas (via dashboard)
        if ($action === 'get_goals') {
            $type = $_GET['type'] ?? 'geral';
            $stmt = $pdo->prepare("SELECT * FROM goals WHERE user_id = ? AND goal_type = ? ORDER BY status ASC, id DESC");
            $stmt->execute([$user_id, $type]);
            echo json_encode($stmt->fetchAll());
            exit;
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// HTML da página
$page = 'dashboard';
$page_title = 'Dashboard - LifeOS';
include 'includes/header.php';
?>

<?php include 'includes/sidebar.php'; ?>

<div class="flex min-h-screen w-full">
    <div class="flex-1 p-4 md:p-10 content-wrap transition-all duration-300">
        <div class="main-shell">
            <header class="mb-8">
                <h2 class="text-3xl font-bold text-white">Visão Geral</h2>
                <p class="text-slate-400">Central de Controle - Resumo da sua vida digital</p>
            </header>
            
            <!-- SEÇÃO: CONTROLES RÁPIDOS -->
            <div class="mb-8">
                <h3 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-sliders-h text-purple-400"></i> Adicionar Rápido
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Adicionar Atividade -->
                    <div class="glass-card p-4 rounded-xl">
                        <form onsubmit="addActivityQuick(event)" class="space-y-2">
                            <input type="text" id="quick-activity-title" placeholder="Nome da atividade" class="text-sm" required>
                            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white px-3 py-2 rounded-lg text-sm font-bold transition">
                                <i class="fas fa-plus mr-1"></i> Atividade
                            </button>
                        </form>
                    </div>
                    
                    <!-- Adicionar Evento -->
                    <div class="glass-card p-4 rounded-xl">
                        <form onsubmit="addEventQuick(event)" class="space-y-2">
                            <input type="text" id="quick-event-title" placeholder="Nome do evento" class="text-sm" required>
                            <button type="submit" class="w-full bg-yellow-600 hover:bg-yellow-500 text-black px-3 py-2 rounded-lg text-sm font-bold transition">
                                <i class="fas fa-plus mr-1"></i> Evento
                            </button>
                        </form>
                    </div>
                    
                    <!-- Adicionar Hábito -->
                    <div class="glass-card p-4 rounded-xl">
                        <form onsubmit="addHabitQuick(event)" class="space-y-2">
                            <input type="text" id="quick-habit-name" placeholder="Nome do hábito" class="text-sm" required>
                            <button type="submit" class="w-full bg-rose-600 hover:bg-rose-500 text-white px-3 py-2 rounded-lg text-sm font-bold transition">
                                <i class="fas fa-plus mr-1"></i> Hábito
                            </button>
                        </form>
                    </div>
                    
                    <!-- Lançar Finanças -->
                    <div class="glass-card p-4 rounded-xl">
                        <form onsubmit="addFinanceQuick(event)" class="space-y-2">
                            <div class="flex gap-2">
                                <select id="quick-fin-type" class="text-sm">
                                    <option value="entrada">Entrada</option>
                                    <option value="saida">Saída</option>
                                </select>
                                <input type="number" step="0.01" id="quick-fin-amount" placeholder="Valor" class="text-sm" required>
                            </div>
                            <input type="text" id="quick-fin-desc" placeholder="Descrição (opcional)" class="text-sm">
                            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-2 rounded-lg text-sm font-bold transition">
                                <i class="fas fa-plus mr-1"></i> Lançar Finanças
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- 6 Cards Principais -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <!-- 1. Entradas (Semana) -->
        <div class="glass-card p-6 rounded-2xl border-l-4 border-emerald-500">
            <h3 class="text-emerald-400 text-sm font-bold uppercase tracking-wider mb-1">Entradas (Semana)</h3>
            <p class="text-3xl font-bold text-white" id="dash-income">R$ 0,00</p>
        </div>
        
        <!-- 2. Saídas (Semana) -->
        <div class="glass-card p-6 rounded-2xl border-l-4 border-rose-500">
            <h3 class="text-rose-400 text-sm font-bold uppercase tracking-wider mb-1">Saídas (Semana)</h3>
            <p class="text-3xl font-bold text-white" id="dash-outcome">R$ 0,00</p>
        </div>
        
        <!-- 3. Pontos (XP Total) -->
        <div class="glass-card p-6 rounded-2xl border-l-4 border-cyan-500">
            <h3 class="text-cyan-400 text-sm font-bold uppercase tracking-wider mb-1">Pontos (XP Total)</h3>
            <p class="text-3xl font-bold text-white" id="dash-xp-total">0 XP</p>
        </div>
        
        <!-- 4. Tarefas Pendentes Hoje -->
        <div class="glass-card p-6 rounded-2xl border-l-4 border-purple-500">
            <h3 class="text-purple-400 text-sm font-bold uppercase tracking-wider mb-1">Tarefas Pendentes Hoje</h3>
            <p class="text-3xl font-bold text-white" id="dash-tasks-count">0</p>
        </div>
        
        <!-- 5. Próximo Evento -->
        <div class="glass-card p-6 rounded-2xl border-l-4 border-amber-500">
            <h3 class="text-amber-400 text-sm font-bold uppercase tracking-wider mb-1">Próximo Evento</h3>
            <p class="text-xl font-bold text-white truncate" id="dash-next-event-title">Nenhum evento</p>
            <p class="text-xs text-slate-400" id="dash-next-event-time">Sem data</p>
        </div>
        
        <!-- 6. Card de Motivação -->
        <div class="glass-card p-6 rounded-2xl border-l-4 border-indigo-500 flex flex-col justify-center">
            <h3 class="text-indigo-400 text-sm font-bold uppercase tracking-wider mb-1">Treinos (Semana)</h3>
            <p class="text-3xl font-bold text-white" id="dash-strava-count">0</p>
        </div>
    </div>
    
    <!-- Listas de Atividades, Eventos e Hábitos -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- Atividades de Hoje -->
        <div class="glass-card p-6 rounded-2xl">
            <h3 class="font-bold mb-4 text-blue-400 flex items-center gap-2">
                <i class="fas fa-check-circle"></i> Hoje
            </h3>
            <div id="dash-activities-list" class="space-y-2"></div>
        </div>
        
        <!-- Eventos desta Semana -->
        <div class="glass-card p-6 rounded-2xl">
            <h3 class="font-bold mb-4 text-yellow-400 flex items-center gap-2">
                <i class="fas fa-calendar-week"></i> Eventos
            </h3>
            <div id="dash-events-list" class="space-y-2"></div>
        </div>
        
        <!-- Hábitos -->
        <div class="glass-card p-6 rounded-2xl">
            <h3 class="font-bold mb-4 text-rose-400 flex items-center gap-2">
                <i class="fas fa-fire"></i> Hábitos
            </h3>
            <div id="dash-habits-list" class="space-y-2"></div>
        </div>
        
</div>

<script src="<?php echo BASE_PATH; ?>/assets/js/common.js"></script>
<script>
async function loadDashboard() { 
    const data = await api('dashboard_stats'); 
    
    // Cards de Finanças e XP
    document.getElementById('dash-income').innerText = formatCurrency(data.income_week || 0);
    document.getElementById('dash-outcome').innerText = formatCurrency(data.outcome_week || 0);
    document.getElementById('dash-tasks-count').innerText = data.activities_count;
    document.getElementById('dash-xp-total').innerText = `${data.xp_total} XP`;
    document.getElementById('dash-strava-count').innerText = data.strava_count || 0;
    
    // Próximo Evento
    let nextEventTitle = 'Nenhum evento';
    let nextEventTime = 'Sem data';
    if (data.events_week.length > 0) {
        const upcomingEvent = data.events_week[0];
        const eventDate = new Date(upcomingEvent.start_date);
        nextEventTitle = upcomingEvent.title;
        nextEventTime = eventDate.toLocaleDateString('pt-BR', { weekday: 'short' }) + ', ' + 
                       eventDate.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    }
    document.getElementById('dash-next-event-title').innerText = nextEventTitle;
    document.getElementById('dash-next-event-time').innerText = nextEventTime;

    // Atividades do Dia (hoje)
    document.getElementById('dash-activities-list').innerHTML = data.activities_today.length ? 
        data.activities_today.map(t => 
            `<div id="dash-act-${t.id}" class="flex items-center gap-2 p-2 bg-slate-800/50 rounded-lg border-l-2 border-blue-500 hover:bg-slate-800 transition ${t.status == 1 ? 'opacity-50' : ''}">
                <div onclick="event.stopPropagation(); toggleActivity(${t.id})" class="cursor-pointer text-blue-400 hover:text-blue-300 transition flex-shrink-0">
                    <i class="fas ${t.status == 1 ? 'fa-check-circle' : 'fa-circle'} text-sm"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-medium text-xs text-white ${t.status == 1 ? 'line-through text-slate-400' : ''} truncate">
                        ${t.title}
                    </div>
                </div>
            </div>`
        ).join('') : 
        '<p class="text-slate-500 text-xs italic">Tudo feito!</p>';

    // Lista de Eventos da Semana
    document.getElementById('dash-events-list').innerHTML = data.events_week.length ? 
        data.events_week.map(ev => {
            const eventDate = new Date(ev.start_date);
            const day = eventDate.toLocaleDateString('pt-BR', { day: '2-digit' });
            const time = eventDate.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
            
            return `<div class="p-2 bg-slate-800/50 rounded-lg border-l-2 border-yellow-500">
                        <div class="font-medium text-xs text-yellow-300">${day} - ${time}</div>
                        <div class="text-xs text-white truncate">${ev.title}</div>
                    </div>`;
        }).join('') : 
        '<p class="text-slate-500 text-xs italic">Nenhum evento</p>';
    
    // Lista de Hábitos (últimos 5) com toggle do dia
    try {
        const habitsRes = await fetch(`?api=get_habits`).then(r => r.json());
        const today = new Date().toISOString().slice(0, 10);
        document.getElementById('dash-habits-list').innerHTML = habitsRes.length ? 
            habitsRes.slice(0, 5).map(h => {
                const checks = JSON.parse(h.checked_dates || '[]');
                const isChecked = Array.isArray(checks) && checks.includes(today);
                const btnClass = isChecked 
                    ? 'w-7 h-7 rounded-lg bg-teal-500 text-black shadow-[0_0_8px_rgba(20,184,166,0.35)] flex items-center justify-center'
                    : 'w-7 h-7 rounded-lg bg-slate-700/40 hover:bg-slate-700 text-transparent flex items-center justify-center';
                const icon = isChecked ? '<i class="fas fa-check text-xs"></i>' : '';
                return `<div class="flex items-center justify-between p-2 bg-slate-800/50 rounded-lg border-l-2 border-rose-500">
                    <div class="font-medium text-xs text-white truncate pr-2">${h.name}</div>
                    <button onclick="toggleHabitToday(${h.id})" class="${btnClass}">${icon}</button>
                </div>`;
            }).join('') : 
            '<p class="text-slate-500 text-xs italic">Nenhum hábito</p>';
    } catch(e) {
        document.getElementById('dash-habits-list').innerHTML = '<p class="text-slate-500 text-xs italic">Carregando...</p>';
    }
    
    // Removido: Metas no dashboard
}

async function toggleActivity(id) {
    const item = document.getElementById(`dash-act-${id}`);
    if (item) {
        const icon = item.querySelector('i');
        const titleDiv = item.querySelector('.font-medium');
        const isDone = item.classList.contains('opacity-50');

        if (!isDone) {
            item.classList.add('opacity-50');
            item.classList.remove('border-blue-500');
            item.classList.add('border-green-500');
            item.classList.add('bg-green-900/20');
            if (icon) { icon.classList.remove('fa-circle'); icon.classList.add('fa-check-circle'); }
            if (titleDiv) { titleDiv.classList.add('line-through', 'text-slate-400'); }
        } else {
            item.classList.remove('opacity-50');
            item.classList.remove('border-green-500', 'bg-green-900/20');
            item.classList.add('border-blue-500');
            if (icon) { icon.classList.add('fa-circle'); icon.classList.remove('fa-check-circle'); }
            if (titleDiv) { titleDiv.classList.remove('line-through', 'text-slate-400'); }
        }
    }

    await api('toggle_activity', {id});
    loadDashboard();
}

// Funções de Adição Rápida
async function addActivityQuick(e) {
    e.preventDefault();
    const title = document.getElementById('quick-activity-title').value;
    const today = new Date().toISOString().split('T')[0];
    
    await api('save_activity', {
        title,
        category: '',
        date: today,
        period: 'morning'
    });
    
    document.getElementById('quick-activity-title').value = '';
    loadDashboard();
}

async function addEventQuick(e) {
    e.preventDefault();
    const title = document.getElementById('quick-event-title').value;
    const today = new Date().toISOString().split('T')[0];
    
    await api('save_event', {
        title,
        date: today,
        desc: ''
    });
    
    document.getElementById('quick-event-title').value = '';
    loadDashboard();
}

async function addFinanceQuick(e) {
    e.preventDefault();
    const type = document.getElementById('quick-fin-type').value;
    const amount = parseFloat(document.getElementById('quick-fin-amount').value);
    const desc = document.getElementById('quick-fin-desc').value || '';
    const today = new Date().toISOString().split('T')[0];

    await api('save_finance', {
        type,
        amount,
        desc,
        date: today
    });

    document.getElementById('quick-fin-amount').value = '';
    document.getElementById('quick-fin-desc').value = '';
    loadDashboard();
}

async function toggleHabitToday(id) {
    const today = new Date().toISOString().slice(0, 10);
    await api('toggle_habit', { id, date: today });
    loadDashboard();
}

async function addHabitQuick(e) {
    e.preventDefault();
    const name = document.getElementById('quick-habit-name').value;
    
    await api('save_habit', {
        name: name
    });
    
    document.getElementById('quick-habit-name').value = '';
    loadDashboard();
}

async function addGoalQuick(e) {
    e.preventDefault();
    const title = document.getElementById('quick-goal-title').value;
    
    await api('save_goal', {
        title,
        difficulty: 'media',
        goal_type: 'geral'
    });
    
    document.getElementById('quick-goal-title').value = '';
    loadDashboard();
}

// Inicialização
document.addEventListener('DOMContentLoaded', () => {
    loadDashboard();
});
</script>

<?php include 'includes/footer.php'; ?>
