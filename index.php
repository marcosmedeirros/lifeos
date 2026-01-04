<?php
// ARQUIVO: index.php - Dashboard Principal
require_once 'includes/auth.php';
require_login(); // Agora requer login obrigatório

// Define user_id da sessão
$user_id = $_SESSION['user_id'];

function ensureHabitRemovalsTable(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS habit_removals (\n        habit_id INT NOT NULL PRIMARY KEY,\n        removed_from DATE NOT NULL,\n        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n        KEY idx_removed_from (removed_from)\n    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// Roteador da API para o Dashboard
if (isset($_GET['api'])) {
    $action = $_GET['api'];
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) $data = $_POST;
    
    try {
        ensureHabitRemovalsTable($pdo);
        // API do Dashboard
        if ($action === 'dashboard_stats') {
            // Calcula segunda a domingo da semana atual
            $now = new DateTime();
            $dayOfWeek = $now->format('w'); // 0 = Domingo, 1 = Segunda, ..., 6 = Sábado
            
            // Ajusta para começar na segunda-feira
            if ($dayOfWeek == 0) { // Se for domingo
                $daysToMonday = -6;
            } else {
                $daysToMonday = -($dayOfWeek - 1);
            }
            
            $monday = (clone $now)->modify("$daysToMonday days");
            $sunday = (clone $monday)->modify('+6 days');
            
            $startOfWeek = $monday->format('Y-m-d');
            $endOfWeek = $sunday->format('Y-m-d');
            
            // Debug log
            error_log("[DASHBOARD_STATS] Período calculado: $startOfWeek a $endOfWeek");
            
            // Finanças da Semana
            $fin_stmt = $pdo->prepare("
                SELECT type, amount, DATE(created_at) as data
                FROM finances 
                WHERE user_id = ? AND DATE(created_at) BETWEEN ? AND ?
            ");
            $fin_stmt->execute([$user_id, $startOfWeek, $endOfWeek]);
            $fin = $fin_stmt->fetchAll();
            
            $inc = 0; $out = 0; 
            error_log("[DASHBOARD_STATS] Total de registros encontrados: " . count($fin));
            foreach($fin as $f) { 
                $amount = floatval($f['amount']);
                $isIncome = in_array($f['type'], ['income', 'entrada']);
                error_log("[DASHBOARD_STATS] Data: {$f['data']}, Tipo: {$f['type']}, Valor: {$amount}, É entrada? " . ($isIncome ? 'SIM' : 'NÃO'));
                if($isIncome) {
                    $inc += $amount;
                } else {
                    $out += $amount;
                }
            }
            error_log("[DASHBOARD_STATS] Total Entradas: $inc, Total Saídas: $out");
            
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
            
            // Hábitos concluídos na semana
            $habits_week = 0;
            $habitsData = $pdo->query("SELECT checked_dates FROM habits")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($habitsData as $hb) {
                $checks = json_decode($hb['checked_dates'] ?? '[]', true) ?: [];
                foreach ($checks as $d) {
                    if ($d >= $startOfWeek && $d <= $endOfWeek) {
                        $habits_week++;
                    }
                }
            }
            
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
                'habits_week' => $habits_week,
                'activities_count' => $activities_count,
                'strava_count' => $strava_count,
                'events_week' => $events_list,
                'activities_today' => $activities_today
            ]); 
            exit;
        }

        // Chat com Gemini (usa os mesmos dados do dashboard para contexto)
        if ($action === 'gemini_chat') {
            $user_query = trim($data['message'] ?? '');

            if ($user_query === '') {
                echo json_encode(['response' => 'Envie uma pergunta para o Gemini.']);
                exit;
            }

            // Usa o mesmo cálculo de semana do dashboard
            $now = new DateTime();
            $dayOfWeek = $now->format('w');
            if ($dayOfWeek == 0) {
                $daysToMonday = -6;
            } else {
                $daysToMonday = -($dayOfWeek - 1);
            }
            $monday = (clone $now)->modify("$daysToMonday days");
            $sunday = (clone $monday)->modify('+6 days');
            $startOfWeek = $monday->format('Y-m-d');
            $endOfWeek = $sunday->format('Y-m-d');
            
            error_log("[DASHBOARD_STATS] Período calculado: $startOfWeek a $endOfWeek");

            // Finanças da Semana
            $fin_stmt = $pdo->prepare("
                SELECT type, amount, DATE(created_at) as data
                FROM finances 
                WHERE user_id = ? AND DATE(created_at) BETWEEN ? AND ?
            ");
            $fin_stmt->execute([$startOfWeek, $endOfWeek]);
            $fin = $fin_stmt->fetchAll();

            $inc = 0; $out = 0; 
            foreach($fin as $f) { 
                $amount = floatval($f['amount']);
                if(in_array($f['type'], ['income', 'entrada'])) {
                    $inc += $amount;
                } else {
                    $out += $amount;
                }
            }

            // XP Total
            $xp_stmt = $pdo->prepare("SELECT total_xp FROM user_settings WHERE user_id = ?");
            $xp_stmt->execute([$user_id]);
            $xp_total = $xp_stmt->fetchColumn() ?: 0;

            // Atividades de Hoje
            $activities_count = $pdo->query("SELECT COUNT(*) FROM activities WHERE day_date = CURDATE() AND status = 0")->fetchColumn();

            // Treinos do Strava (esta semana)
            $strava_count = $pdo->prepare("
                SELECT COUNT(*) FROM strava_activities 
                WHERE DATE(start_date) BETWEEN ? AND ?
            ");
            $strava_count->execute([$startOfWeek, $endOfWeek]);
            $strava_count = $strava_count->fetchColumn() ?: 0;

            $stats = [
                'xp' => $xp_total,
                'atividades_pendentes' => $activities_count,
                'treinos_semana' => $strava_count,
                'financas' => ['ganhos' => $inc, 'gastos' => $out]
            ];

            // Histórico das últimas 5 mensagens
            $stmt = $pdo->prepare("SELECT role, content FROM chat_messages WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
            $stmt->execute([$user_id]);
            $history = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

            // Prompt de sistema com contexto do dashboard
            $system_prompt = "Você é o assistente pessoal do LifeOS do Marcos. \n" .
                "Dados atuais: XP: {$stats['xp']}, Atividades Pendentes: {$stats['atividades_pendentes']}, \n" .
                "Treinos no Strava: {$stats['treinos_semana']}, Saldo Semanal: R$ " . ($inc - $out) . ".\n" .
                "Responda de forma curta e motivadora.";

            // Chamada para a API do Gemini
            $apiKey = getenv('GOOGLE_API_KEY');
            if (!$apiKey) {
                echo json_encode(['response' => 'Erro: Chave de API não configurada.']);
                exit;
            }
            $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $apiKey;

            $contents = [];
            foreach ($history as $msg) {
                $role = $msg['role'] === 'user' ? 'user' : 'model';
                $contents[] = ["role" => $role, "parts" => [["text" => $msg['content']]]];
            }
            $contents[] = [
                "role" => "user",
                "parts" => [["text" => $system_prompt . "\n\nPergunta do usuário: " . $user_query]]
            ];

            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["contents" => $contents]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            $rawResponse = curl_exec($ch);
            $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            $response = json_decode($rawResponse, true);
            $apiErrorMsg = $response['error']['message'] ?? '';

            if ($curlError) {
                $ai_text = 'Erro ao conectar com Gemini (cURL).';
                echo json_encode(['response' => $ai_text, 'error' => $curlError]);
                exit;
            }

            if ($httpStatus >= 400 || !$response) {
                $ai_text = 'Erro ao conectar com Gemini (HTTP).';
                echo json_encode(['response' => $ai_text, 'error' => $apiErrorMsg ?: 'HTTP ' . $httpStatus]);
                exit;
            }

            $ai_text = $response['candidates'][0]['content']['parts'][0]['text'] ?? 'Sem resposta do modelo.';

            // Salva histórico
            $ins = $pdo->prepare("INSERT INTO chat_messages (user_id, role, content) VALUES (?, ?, ?)");
            $ins->execute([$user_id, 'user', $user_query]);
            $ins->execute([$user_id, 'model', $ai_text]);

            echo json_encode(['response' => $ai_text]);
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
        
        // Toggle atividade (via dashboard)
        if ($action === 'toggle_activity') {
            $stmt = $pdo->prepare("UPDATE activities SET status = 1 - status WHERE id=? AND user_id=?");
            $stmt->execute([$data['id'], $user_id]);
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
            $month = $_GET['month'] ?? date('Y-m');
            $monthStart = $month . '-01';
            $stmt = $pdo->prepare("SELECT h.*, hr.removed_from FROM habits h LEFT JOIN habit_removals hr ON hr.habit_id = h.id WHERE hr.removed_from IS NULL OR hr.removed_from > ? ORDER BY h.id DESC");
            $stmt->execute([$monthStart]);
            echo json_encode($stmt->fetchAll());
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

        // Mensagem diária (baseada no JSON local)
        if ($action === 'get_daily_message') {
            $date = $_GET['date'] ?? date('Y-m-d');
            $file = __DIR__ . '/mensagens_365.json';

            if (!file_exists($file)) {
                echo json_encode(['error' => 'Arquivo mensagens_365.json não encontrado']);
                exit;
            }

            $json = json_decode(file_get_contents($file), true);
            if (!is_array($json)) {
                echo json_encode(['error' => 'Formato inválido do JSON de mensagens']);
                exit;
            }

            $text = null;
            $matchedDate = null;
            $dayOfYear = (int)date('z', strtotime($date));

            // Tenta por correspondência exata de data
            foreach ($json as $item) {
                $itemDate = $item['date'] ?? ($item['dia'] ?? null);
                $itemText = $item['texto'] ?? ($item['mensagem'] ?? ($item['message'] ?? null));
                if ($itemDate && $itemText && $itemDate === $date) {
                    $text = $itemText;
                    $matchedDate = $itemDate;
                    break;
                }
            }

            // Se não achou por data, tenta pelo índice (dia do ano)
            if ($text === null && isset($json[$dayOfYear])) {
                $item = $json[$dayOfYear];
                $text = $item['texto'] ?? ($item['mensagem'] ?? ($item['message'] ?? (is_string($item) ? $item : null)));
                $matchedDate = $item['date'] ?? ($item['dia'] ?? null);
            }

            if ($text === null) {
                echo json_encode(['error' => 'Mensagem não encontrada para a data informada']);
                exit;
            }

            echo json_encode([
                'date' => $date,
                'matched_date' => $matchedDate,
                'text' => $text
            ]);
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
                <h3 class="text-xl font-bold text-yellow-500 mb-4 flex items-center gap-2">
                    <i class="fas fa-sliders-h text-yellow-600"></i> Adicionar Rápido
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Adicionar Atividade -->
                    <div class="glass-card p-4 rounded-xl">
                        <form onsubmit="addActivityQuick(event)" class="space-y-2">
                            <input type="text" id="quick-activity-title" placeholder="Nome da atividade" class="text-sm" required>
                            <button type="submit" class="w-full bg-gradient-to-r from-yellow-600 to-yellow-700 hover:from-yellow-500 hover:to-yellow-600 text-white px-3 py-2 rounded-lg text-sm font-bold transition shadow-lg shadow-yellow-600/30">
                                <i class="fas fa-plus mr-1"></i> Atividade
                            </button>
                        </form>
                    </div>
                    
                    <!-- Adicionar Evento -->
                    <div class="glass-card p-4 rounded-xl">
                        <form onsubmit="openQuickEventModal(event)" class="space-y-2">
                            <input type="text" id="quick-event-title" placeholder="Nome do evento" class="text-sm" required>
                            <input type="datetime-local" id="quick-event-date" class="text-sm" required>
                            <button type="submit" class="w-full bg-gradient-to-r from-yellow-600 to-yellow-700 hover:from-yellow-500 hover:to-yellow-600 text-white px-3 py-2 rounded-lg text-sm font-bold transition shadow-lg shadow-yellow-600/30">
                                <i class="fas fa-plus mr-1"></i> Evento
                            </button>
                        </form>
                    </div>
                    
                    <!-- Adicionar Foto -->
                    <div class="glass-card p-4 rounded-xl">
                        <form onsubmit="addPhotoQuick(event)" enctype="multipart/form-data" class="space-y-2">
                            <input type="file" id="quick-photo-file" accept="image/*" class="text-sm w-full text-white file:mr-2 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-yellow-600 file:text-white hover:file:bg-yellow-500" required>
                            <button type="submit" class="w-full bg-gradient-to-r from-yellow-600 to-yellow-700 hover:from-yellow-500 hover:to-yellow-600 text-white px-3 py-2 rounded-lg text-sm font-bold transition shadow-lg shadow-yellow-600/30">
                                <i class="fas fa-plus mr-1"></i> Foto
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
                            <button type="submit" class="w-full bg-gradient-to-r from-yellow-600 to-yellow-700 hover:from-yellow-500 hover:to-yellow-600 text-white px-3 py-2 rounded-lg text-sm font-bold transition shadow-lg shadow-yellow-600/30">
                                <i class="fas fa-plus mr-1"></i> Lançar Finanças
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- 6 Cards Principais -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <!-- 1. Entradas (Semana) -->
        <div class="glass-card p-6 rounded-2xl border-l-4 border-yellow-600">
            <h3 class="text-yellow-500 text-sm font-bold uppercase tracking-wider mb-1">💰 Entradas (Semana)</h3>
            <p class="text-3xl font-bold text-white" id="dash-income">R$ 0,00</p>
        </div>
        
        <!-- 2. Saídas (Semana) -->
        <div class="glass-card p-6 rounded-2xl border-l-4 border-gray-600">
            <h3 class="text-gray-300 text-sm font-bold uppercase tracking-wider mb-1">📊 Saídas (Semana)</h3>
            <p class="text-3xl font-bold text-white" id="dash-outcome">R$ 0,00</p>
        </div>
        
        <!-- 3. Hábitos concluídos na semana -->
        <div class="glass-card p-6 rounded-2xl border-l-4 border-yellow-600">
            <h3 class="text-yellow-500 text-sm font-bold uppercase tracking-wider mb-1">🔥 Hábitos concluídos (Semana)</h3>
            <p class="text-3xl font-bold text-white" id="dash-habits-week">0</p>
        </div>
        
        <!-- 4. Tarefas Pendentes Hoje -->
        <div class="glass-card p-6 rounded-2xl border-l-4 border-gray-600">
            <h3 class="text-gray-300 text-sm font-bold uppercase tracking-wider mb-1">✓ Tarefas Pendentes Hoje</h3>
            <p class="text-3xl font-bold text-white" id="dash-tasks-count">0</p>
        </div>
        
        <!-- 5. Próximo Evento -->
        <div class="glass-card p-6 rounded-2xl border-l-4 border-yellow-600">
            <h3 class="text-yellow-500 text-sm font-bold uppercase tracking-wider mb-1">📅 Próximo Evento</h3>
            <p class="text-xl font-bold text-white truncate" id="dash-next-event-title">Nenhum evento</p>
            <p class="text-xs text-gray-400" id="dash-next-event-time">Sem data</p>
        </div>
        
        <!-- 6. Card de Motivação -->
        <div class="glass-card p-6 rounded-2xl border-l-4 border-gray-600 flex flex-col justify-center">
            <h3 class="text-gray-300 text-sm font-bold uppercase tracking-wider mb-1">🏋️ Treinos (Semana)</h3>
            <p class="text-3xl font-bold text-white" id="dash-strava-count">0</p>
        </div>
    </div>
    
    <!-- Dias da Semana -->
    <div class="glass-card p-4 rounded-2xl mb-8">
        <h3 class="text-slate-400 text-sm font-semibold mb-3 text-center">Semana Atual</h3>
        <div class="flex gap-3 justify-center flex-wrap">
            <script>
                const today = new Date();
                const dow = today.getDay() === 0 ? 6 : today.getDay() - 1; // 0=Seg, 1=Ter, ..., 6=Dom
                const dayNames = ['Seg','Ter','Qua','Qui','Sex','Sáb','Dom'];
                for(let i=0; i<7; i++) {
                    const d = new Date();
                    d.setDate(today.getDate() - dow + i);
                    const isToday = d.toDateString() === today.toDateString();
                    document.write(`
                        <div class="text-center px-4 py-2 rounded-lg border ${isToday ? 'bg-yellow-600/20 border-yellow-600/50 text-yellow-400' : 'border-slate-700 text-slate-400'}" style="min-width:60px">
                            <div class="text-xs font-semibold mb-1">${dayNames[i]}</div>
                            <div class="text-lg font-bold">${d.getDate()}</div>
                        </div>
                    `);
                }
            </script>
        </div>
    </div>

        <!-- Listas de Atividades, Eventos e Hábitos -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- Atividades de Hoje -->
        <div class="glass-card p-6 rounded-2xl">
            <h3 class="font-bold mb-4 text-yellow-500 flex items-center gap-2">
                <i class="fas fa-check-circle"></i> Hoje
            </h3>
            <div id="dash-activities-list" class="space-y-2"></div>
        </div>
        
        <!-- Eventos desta Semana -->
        <div class="glass-card p-6 rounded-2xl">
            <h3 class="font-bold mb-4 text-yellow-500 flex items-center gap-2">
                <i class="fas fa-calendar-week"></i> Eventos
            </h3>
            <div id="dash-events-list" class="space-y-2"></div>
        </div>
        
        <!-- Hábitos -->
        <div class="glass-card p-6 rounded-2xl">
            <h3 class="font-bold mb-4 text-yellow-500 flex items-center gap-2">
                <i class="fas fa-fire"></i> Hábitos
            </h3>
            <div id="dash-habits-list" class="space-y-2"></div>
        </div>
        
</div>

    <!-- Mensagem do Dia (final do dashboard) -->
    <div class="glass-card p-6 rounded-2xl mb-8 mt-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
            <h3 class="text-xl font-bold text-yellow-500 flex items-center gap-2">
                <i class="fas fa-scroll"></i> Mensagem do Dia
            </h3>
            <div class="flex items-center gap-2 bg-slate-900 rounded-lg p-2 border border-slate-700/70">
                <span id="daily-date-label" class="text-slate-200 font-semibold text-sm min-w-[140px] text-center">...</span>
            </div>
        </div>
        <p id="daily-message" class="text-slate-200 whitespace-pre-line leading-relaxed"></p>
    </div>

<!-- Widget de Chat com Gemini -->
<div id="chat-container" class="fixed bottom-5 right-5 w-80 glass-card p-4 hidden border border-yellow-600/50 shadow-2xl z-50">
    <div class="flex justify-between items-center mb-3">
        <h3 class="text-yellow-500 font-bold text-sm">Gemini</h3>
        <button onclick="toggleChatWidget()" class="text-yellow-500 hover:text-yellow-400"><i class="fas fa-times"></i></button>
    </div>
    <div id="chat-box" class="h-64 overflow-y-auto mb-2 text-xs text-white space-y-2"></div>
    <div class="flex gap-2">
        <input type="text" id="chat-input" class="text-xs bg-slate-900 border-none flex-1 rounded-lg px-2" placeholder="Pergunte algo...">
        <button onclick="sendToGemini()" class="bg-yellow-600 p-2 rounded-lg"><i class="fas fa-paper-plane"></i></button>
    </div>
</div>
<button id="chat-btn" onclick="toggleChatWidget()" class="fixed bottom-5 right-5 bg-yellow-600 w-12 h-12 rounded-full shadow-lg flex items-center justify-center z-50">
    <i class="fas fa-robot text-white"></i>
</button>

<script src="<?php echo BASE_PATH; ?>/assets/js/common.js"></script>
<script>
// Sanitiza texto simples para evitar HTML indesejado no chat
function escapeHtml(str) {
    return str.replace(/[&<>"']/g, function(m) {
        return ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[m]);
    });
}

// Toggle chat widget e botão
function toggleChatWidget() {
    const container = document.getElementById('chat-container');
    const btn = document.getElementById('chat-btn');
    container.classList.toggle('hidden');
    btn.classList.toggle('hidden');
}

async function loadDashboard() { 
    const data = await api('dashboard_stats'); 
    
    // Cards de Finanças e XP
    document.getElementById('dash-income').innerText = formatCurrency(data.income_week || 0);
    document.getElementById('dash-outcome').innerText = formatCurrency(data.outcome_week || 0);
    document.getElementById('dash-tasks-count').innerText = data.activities_count;
    document.getElementById('dash-habits-week').innerText = data.habits_week || 0;
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
    const activitiesList = document.getElementById('dash-activities-list');
    if (data.activities_today.length) {
        activitiesList.innerHTML = data.activities_today.map(t => 
            `<div id="dash-act-${t.id}" class="flex items-center gap-2 p-2 bg-slate-800/50 rounded-lg border-l-2 border-blue-500 hover:bg-slate-800 transition ${t.status == 1 ? 'opacity-50' : ''}" style="cursor:pointer">
                <div class="text-blue-400 hover:text-blue-300 transition flex-shrink-0" data-activity-id="${t.id}">
                    <i class="fas ${t.status == 1 ? 'fa-check-circle' : 'fa-circle'} text-sm"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-medium text-xs text-white ${t.status == 1 ? 'line-through text-slate-400' : ''} truncate">
                        ${t.title}
                    </div>
                </div>
            </div>`
        ).join('');
        
        // Adiciona event listeners para cada atividade
        data.activities_today.forEach(t => {
            const activityDiv = document.getElementById(`dash-act-${t.id}`);
            if (activityDiv) {
                activityDiv.addEventListener('click', (e) => {
                    e.stopPropagation();
                    toggleActivity(t.id);
                });
            }
        });
    } else {
        activitiesList.innerHTML = '<p class="text-slate-500 text-xs italic">Tudo feito!</p>';
    }

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
    
    // Lista de Hábitos com toggle do dia
    try {
        const today = new Date().toISOString().slice(0, 10);
        const currentMonth = today.slice(0, 7);
        const habitsRes = await fetch(`?api=get_habits&month=${currentMonth}`).then(r => r.json());
        const habitsOrdered = Array.isArray(habitsRes)
            ? [...habitsRes].sort((a, b) => (a.id || 0) - (b.id || 0))
            : [];

        document.getElementById('dash-habits-list').innerHTML = habitsOrdered.length ?
            habitsOrdered.map(h => {
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
    console.log('toggleActivity chamado com ID:', id);
    
    try {
        const result = await api('toggle_activity', {id});
        console.log('Resultado da API:', result);
        
        if (result.success) {
            // Atualiza apenas as atividades pendentes no contador
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
                    
                    // Atualiza contador
                    const counter = document.getElementById('dash-tasks-count');
                    if (counter) {
                        const currentCount = parseInt(counter.textContent) || 0;
                        counter.textContent = Math.max(0, currentCount - 1);
                    }
                } else {
                    item.classList.remove('opacity-50');
                    item.classList.remove('border-green-500', 'bg-green-900/20');
                    item.classList.add('border-blue-500');
                    if (icon) { icon.classList.add('fa-circle'); icon.classList.remove('fa-check-circle'); }
                    if (titleDiv) { titleDiv.classList.remove('line-through', 'text-slate-400'); }
                    
                    // Atualiza contador
                    const counter = document.getElementById('dash-tasks-count');
                    if (counter) {
                        const currentCount = parseInt(counter.textContent) || 0;
                        counter.textContent = currentCount + 1;
                    }
                }
            }
        }
    } catch(error) {
        console.error('Erro ao toggle:', error);
    }
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

// Função para obter data/hora local no formato datetime-local (YYYY-MM-DDTHH:mm)
function getLocalDateTimeValue(date = null) {
    if (!date) date = new Date();
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

// Inicializa data padrão do card ao carregar
document.addEventListener('DOMContentLoaded', () => {
    const quickEventDateInput = document.getElementById('quick-event-date');
    if (quickEventDateInput) {
        quickEventDateInput.value = getLocalDateTimeValue();
    }
});

function openQuickEventModal(e) {
    e.preventDefault();
    const title = document.getElementById('quick-event-title').value;
    const dateTime = document.getElementById('quick-event-date').value;
    
    // Abre modal do google_agenda se estiver na página
    if (typeof openEventModal !== 'undefined') {
        document.getElementById('event-id').value = '';
        document.getElementById('event-title').value = title;
        document.getElementById('event-date').value = dateTime;
        document.getElementById('modal-event-title').textContent = 'Novo Evento';
        document.getElementById('btn-delete-event').classList.add('hidden');
        document.getElementById('btn-save-event').textContent = 'Salvar';
        openEventModal();
    } else {
        // Se não estiver na página de google_agenda, redireciona passando os dados
        window.location.href = `<?php echo BASE_PATH; ?>/modules/google_agenda.php?new_event=${encodeURIComponent(title)}&datetime=${encodeURIComponent(dateTime)}`;
    }
    
    document.getElementById('quick-event-title').value = '';
    document.getElementById('quick-event-date').value = getLocalDateTimeValue();
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
    
    // Encontrar o botão clicado para atualização imediata
    const button = event.target.closest('button');
    if (button) {
        const isChecked = button.classList.contains('bg-teal-500');
        
        if (isChecked) {
            // Desmarcar
            button.className = 'w-7 h-7 rounded-lg bg-slate-700/40 hover:bg-slate-700 text-transparent flex items-center justify-center';
            button.innerHTML = '';
        } else {
            // Marcar
            button.className = 'w-7 h-7 rounded-lg bg-teal-500 text-black shadow-[0_0_8px_rgba(20,184,166,0.35)] flex items-center justify-center';
            button.innerHTML = '<i class="fas fa-check text-xs"></i>';
        }
    }
    
    await api('toggle_habit', { id, date: today });
    loadDashboard();
}

async function addPhotoQuick(e) {
    e.preventDefault();
    const fileInput = document.getElementById('quick-photo-file');
    const file = fileInput.files[0];
    
    if (!file) return;
    
    const formData = new FormData();
    formData.append('photo', file);
    formData.append('photo_date', new Date().toISOString().split('T')[0]);
    
    try {
        const response = await fetch(`${BASE_PATH}/modules/board.php?api=upload_photo`, {
            method: 'POST',
            body: formData
        });

        const json = await response.json().catch(() => ({}));

        if (response.ok && json.success) {
            fileInput.value = '';
            alert('✅ Foto adicionada ao Board!');
        } else {
            const message = json.error || 'Erro ao fazer upload da foto';
            alert(`❌ ${message}`);
        }
    } catch (error) {
        console.error('Erro:', error);
        alert('❌ Erro ao fazer upload da foto');
    }
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

// Mensagem do dia
async function loadDailyMessage() {
    const today = new Date();
    const ds = today.toISOString().slice(0, 10);
    try {
        const res = await api(`get_daily_message&date=${ds}`);
        const label = today.toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: 'short' });
        document.getElementById('daily-date-label').innerText = label;
        document.getElementById('daily-message').innerText = res.text || 'Mensagem não encontrada';
    } catch (err) {
        document.getElementById('daily-message').innerText = 'Não foi possível carregar a mensagem do dia.';
    }
}

// Envia mensagem para o Gemini usando a rota PHP
async function sendToGemini() {
    const input = document.getElementById('chat-input');
    const box = document.getElementById('chat-box');
    const msg = input.value.trim();
    if (!msg) return;

    box.innerHTML += `<p class="text-blue-400"><b>Você:</b> ${escapeHtml(msg)}</p>`;
    input.value = '';

    try {
        const res = await fetch('?api=gemini_chat', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ message: msg })
        });
        const data = await res.json();
        const reply = data.response || 'Sem resposta do Gemini.';
        box.innerHTML += `<p class="text-yellow-500"><b>Gemini:</b> ${escapeHtml(reply)}</p>`;
        if (data.error) {
            box.innerHTML += `<p class="text-red-400 text-[11px]">Detalhe: ${escapeHtml(String(data.error))}</p>`;
        }
    } catch (err) {
        box.innerHTML += '<p class="text-red-500">Erro ao chamar o Gemini.</p>';
    }

    box.scrollTop = box.scrollHeight;
}

// Inicialização
document.addEventListener('DOMContentLoaded', () => {
    loadDashboard();
    loadDailyMessage();
});
</script>

<?php include 'includes/footer.php'; ?>
