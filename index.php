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

            echo json_encode([
                'income_week' => $inc, 
                'outcome_week' => $out, 
                'xp_total' => $xp_total,
                'activities_count' => $activities_count,
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
                <p class="text-slate-400">Resumo da sua vida digital</p>
            </header>
            
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
            <h3 class="text-indigo-400 text-sm font-bold uppercase tracking-wider mb-1">Foco</h3>
            <p class="text-lg font-bold text-white">Não desista, mesmo cansado!</p>
        </div>
    </div>
    
    <!-- Listas de Atividades e Eventos -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Eventos da Semana -->
        <div class="glass-card p-6 rounded-2xl">
            <h3 class="font-bold mb-6 text-yellow-400 flex items-center gap-2">
                <i class="fas fa-calendar-week"></i> Eventos desta Semana
            </h3>
            <div id="dash-events-list" class="space-y-3"></div>
        </div>
        
        <!-- Prioridades do Dia -->
        <div class="glass-card p-6 rounded-2xl">
            <h3 class="font-bold mb-6 text-purple-400 flex items-center gap-2">
                <i class="fas fa-tasks"></i> Prioridades do Dia
            </h3>
            <div id="dash-activities-list" class="space-y-3"></div>
        </div>
        </div>
    </div>
</div>

<script src="/lifeos/assets/js/common.js"></script>
<script>
async function loadDashboard() { 
    const data = await api('dashboard_stats'); 
    
    // Cards de Finanças e XP
    document.getElementById('dash-income').innerText = formatCurrency(data.income_week || 0);
    document.getElementById('dash-outcome').innerText = formatCurrency(data.outcome_week || 0);
    document.getElementById('dash-tasks-count').innerText = data.activities_count;
    document.getElementById('dash-xp-total').innerText = `${data.xp_total} XP`;
    
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

    // Lista de Eventos da Semana
    document.getElementById('dash-events-list').innerHTML = data.events_week.length ? 
        data.events_week.map(ev => {
            const eventDate = new Date(ev.start_date);
            const day = eventDate.toLocaleDateString('pt-BR', { day: 'numeric', month: 'short' }).replace('.', '');
            const time = eventDate.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
            
            return `<div class="flex items-start gap-3 p-3 bg-slate-800/50 rounded-xl border-l-4 border-yellow-500">
                        <div class="text-xs font-bold bg-slate-900 p-2 rounded-lg text-center min-w-[70px] text-yellow-500">
                            ${day.toUpperCase()}<br>${time}
                        </div>
                        <div class="font-medium text-sm text-white">${ev.title}</div>
                    </div>`;
        }).join('') : 
        '<p class="text-slate-500 text-sm italic">Nenhum evento agendado para esta semana.</p>';
    
    // Prioridades do Dia
    document.getElementById('dash-activities-list').innerHTML = data.activities_today.length ? 
        data.activities_today.map(t => 
            `<div id="dash-act-${t.id}" class="flex items-center gap-3 p-3 bg-slate-800/50 rounded-xl border-l-4 border-purple-500 ${t.status == 1 ? 'opacity-50' : ''}">
                <div onclick="toggleActivity(${t.id})" class="cursor-pointer text-purple-400 text-xl hover:text-purple-300 transition">
                    <i class="fas ${t.status == 1 ? 'fa-check-circle' : 'fa-circle'}"></i>
                </div>
                <div class="flex-1">
                    <div class="font-medium text-sm text-white ${t.status == 1 ? 'line-through text-slate-400' : ''}">
                        ${t.title}
                    </div>
                    <div class="text-xs text-slate-400">${t.category || ''}</div>
                </div>
            </div>`
        ).join('') : 
        '<p class="text-slate-500 text-sm italic">Tudo feito!</p>'; 
}

async function toggleActivity(id) {
    const el = document.getElementById(`dash-act-${id}`);
    if(el) {
        el.classList.toggle('opacity-50');
        el.querySelector('.font-medium').classList.toggle('line-through');
        el.querySelector('.font-medium').classList.toggle('text-slate-400');
        el.querySelector('i').classList.toggle('fa-check-circle');
        el.querySelector('i').classList.toggle('fa-circle');
    }
    await api('toggle_activity', {id});
}

// Inicialização
document.addEventListener('DOMContentLoaded', () => {
    loadDashboard();
});
</script>

<?php include 'includes/footer.php'; ?>
