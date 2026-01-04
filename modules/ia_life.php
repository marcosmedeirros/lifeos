<?php
// ARQUIVO: modules/ia_life.php - IA Life Assistant
require_once '../includes/auth.php';
require_login();

$user_id = $_SESSION['user_id'];

// Roteador de API para IA Life
if (isset($_GET['api'])) {
    $action = $_GET['api'];
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) $data = $_POST;
    
    try {
        // Obter histórico de conversas
        if ($action === 'get_history') {
            $stmt = $pdo->prepare("
                SELECT role, content, created_at 
                FROM chat_messages 
                WHERE user_id = ? 
                ORDER BY created_at ASC
            ");
            $stmt->execute([$user_id]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        }

        // Enviar mensagem para Gemini (mesmo que no dashboard, mas com histórico completo)
        if ($action === 'chat') {
            $user_query = trim($data['message'] ?? '');

            if ($user_query === '') {
                echo json_encode(['response' => 'Envie uma pergunta para a IA.']);
                exit;
            }

            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week'));

            // Finanças da Semana
            $fin_stmt = $pdo->prepare("
                SELECT type, SUM(amount) as total 
                FROM finances 
                WHERE user_id = ? AND DATE(created_at) BETWEEN ? AND ? 
                GROUP BY type
            ");
            $fin_stmt->execute([$user_id, $startOfWeek, $endOfWeek]);
            $fin = $fin_stmt->fetchAll();

            $inc = 0; $out = 0; 
            foreach($fin as $f) { 
                if(in_array($f['type'], ['income', 'entrada'])) $inc = $f['total']; 
                else $out = $f['total']; 
            }

            // XP Total
            $xp_stmt = $pdo->prepare("SELECT total_xp FROM user_settings WHERE user_id = ?");
            $xp_stmt->execute([$user_id]);
            $xp_total = $xp_stmt->fetchColumn() ?: 0;

            // Atividades de Hoje
            $activities_count = $pdo->query("SELECT COUNT(*) FROM activities WHERE user_id = {$user_id} AND day_date = CURDATE() AND status = 0")->fetchColumn();

            // Treinos do Strava (esta semana)
            $strava_count = $pdo->prepare("
                SELECT COUNT(*) FROM strava_activities 
                WHERE user_id = ? AND DATE(start_date) BETWEEN ? AND ?
            ");
            $strava_count->execute([$user_id, $startOfWeek, $endOfWeek]);
            $strava_count = $strava_count->fetchColumn() ?: 0;

            $stats = [
                'xp' => $xp_total,
                'atividades_pendentes' => $activities_count,
                'treinos_semana' => $strava_count,
                'financas' => ['ganhos' => $inc, 'gastos' => $out]
            ];

            // Histórico completo para contexto
            $stmt = $pdo->prepare("SELECT role, content FROM chat_messages WHERE user_id = ? ORDER BY created_at ASC");
            $stmt->execute([$user_id]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Prompt de sistema
            $system_prompt = "Você é o assistente pessoal de IA do LifeOS do Marcos. \n" .
                "Dados atuais: XP: {$stats['xp']}, Atividades Pendentes: {$stats['atividades_pendentes']}, \n" .
                "Treinos no Strava: {$stats['treinos_semana']}, Saldo Semanal: R$ " . ($inc - $out) . ".\n" .
                "Data: " . date('d/m/Y') . ". Responda de forma motivadora e construtiva.";

            // Chamada para o Gemini
            $apiKey = getenv('GOOGLE_API_KEY');
            if (!$apiKey) {
                echo json_encode(['response' => 'Erro: Chave de API não configurada.']);
                exit;
            }
            $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $apiKey;

            $contents = [];
            // Adiciona histórico completo ao contexto
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

            if ($curlError) {
                echo json_encode(['response' => 'Erro ao conectar com IA.', 'error' => $curlError]);
                exit;
            }

            if ($httpStatus >= 400 || !$response) {
                $apiErrorMsg = $response['error']['message'] ?? '';
                echo json_encode(['response' => 'Erro na IA.', 'error' => $apiErrorMsg ?: 'HTTP ' . $httpStatus]);
                exit;
            }

            $ai_text = $response['candidates'][0]['content']['parts'][0]['text'] ?? 'Sem resposta do modelo.';

            // Salva no histórico
            $ins = $pdo->prepare("INSERT INTO chat_messages (user_id, role, content) VALUES (?, ?, ?)");
            $ins->execute([$user_id, 'user', $user_query]);
            $ins->execute([$user_id, 'model', $ai_text]);

            echo json_encode(['response' => $ai_text, 'timestamp' => date('H:i')]);
            exit;
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// HTML da página
$page = 'ia_life';
$page_title = 'IA Life - LifeOS';
include '../includes/header.php';
?>

<?php include '../includes/sidebar.php'; ?>

<div class="flex min-h-screen w-full">
    <div class="flex-1 p-4 md:p-10 content-wrap transition-all duration-300">
        <div class="main-shell">
            <header class="mb-8">
                <h2 class="text-3xl font-bold text-white">🤖 IA Life</h2>
                <p class="text-slate-400">Seu assistente de IA pessoal com memória contínua</p>
            </header>
            
            <!-- Chat Container -->
            <div class="glass-card p-6 rounded-2xl border border-gray-600/30 h-[70vh] flex flex-col">
                <!-- Messages Area -->
                <div id="chat-messages" class="flex-1 overflow-y-auto mb-6 space-y-4 pr-2">
                    <div class="text-center text-slate-500 mt-8">
                        <i class="fas fa-robot text-4xl text-gray-600/30 mb-2"></i>
                        <p>Carregando histórico...</p>
                    </div>
                </div>

                <!-- Input Area -->
                <div class="flex gap-3 border-t border-slate-700 pt-4">
                    <input 
                        type="text" 
                        id="ia-input" 
                        placeholder="Pergunte algo à IA (pressione Enter)..." 
                        class="flex-1 bg-slate-900 border border-slate-700 rounded-lg px-4 py-3 text-white placeholder-slate-500 focus:outline-none focus:border-white"
                        onkeypress="if(event.key==='Enter') sendMessageIA()"
                    >
                    <button 
                        onclick="sendMessageIA()" 
                        class="bg-white hover:bg-gray-100 text-black px-6 py-3 rounded-lg font-bold transition flex items-center gap-2"
                    >
                        <i class="fas fa-paper-plane"></i> Enviar
                    </button>
                </div>
            </div>

            <!-- Stats Widget -->
            <div class="mt-8 grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="glass-card p-4 rounded-xl border-l-4 border-gray-400">
                    <p class="text-gray-400 text-xs font-bold">XP Total</p>
                    <p id="stat-xp" class="text-2xl font-bold text-white">0 XP</p>
                </div>
                <div class="glass-card p-4 rounded-xl border-l-4 border-blue-600">
                    <p class="text-blue-400 text-xs font-bold">Tarefas Hoje</p>
                    <p id="stat-tasks" class="text-2xl font-bold text-white">0</p>
                </div>
                <div class="glass-card p-4 rounded-xl border-l-4 border-green-600">
                    <p class="text-green-400 text-xs font-bold">Saldo Semanal</p>
                    <p id="stat-balance" class="text-2xl font-bold text-white">R$ 0,00</p>
                </div>
                <div class="glass-card p-4 rounded-xl border-l-4 border-orange-600">
                    <p class="text-orange-400 text-xs font-bold">Treinos Semana</p>
                    <p id="stat-workouts" class="text-2xl font-bold text-white">0</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo BASE_PATH; ?>/assets/js/common.js"></script>
<script>
function escapeHtml(str) {
    return str.replace(/[&<>"']/g, function(m) {
        return ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[m]);
    });
}

async function loadChatHistory() {
    try {
        const response = await fetch('?api=get_history');
        const messages = await response.json();
        const container = document.getElementById('chat-messages');
        
        if (!messages || messages.length === 0) {
            container.innerHTML = '<div class="text-center text-slate-500 mt-8"><p>Comece uma conversa...</p></div>';
            return;
        }

        container.innerHTML = messages.map(msg => {
            const isUser = msg.role === 'user';
            const timestamp = new Date(msg.created_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
            
            return `<div class="flex ${isUser ? 'justify-end' : 'justify-start'}">
                <div class="${isUser ? 'bg-white/20 border-l-4 border-white' : 'bg-slate-800/50 border-l-4 border-slate-600'} rounded-lg p-3 max-w-xs">
                    <p class="text-xs ${isUser ? 'text-gray-300' : 'text-slate-400'} mb-1">${isUser ? 'Você' : 'IA Life'}</p>
                    <p class="text-white text-sm">${escapeHtml(msg.content)}</p>
                    <p class="text-[11px] text-slate-500 mt-1">${timestamp}</p>
                </div>
            </div>`;
        }).join('');

        // Scroll para o final
        container.scrollTop = container.scrollHeight;
    } catch (err) {
        console.error('Erro ao carregar histórico:', err);
    }
}

async function loadStats() {
    try {
        const response = await fetch('../?api=dashboard_stats');
        const stats = await response.json();
        
        document.getElementById('stat-xp').innerText = `${stats.xp_total} XP`;
        document.getElementById('stat-tasks').innerText = stats.activities_count || 0;
        document.getElementById('stat-balance').innerText = formatCurrency((stats.income_week || 0) - (stats.outcome_week || 0));
        document.getElementById('stat-workouts').innerText = stats.strava_count || 0;
    } catch (err) {
        console.error('Erro ao carregar stats:', err);
    }
}

async function sendMessageIA() {
    const input = document.getElementById('ia-input');
    const msg = input.value.trim();
    
    if (!msg) return;

    const container = document.getElementById('chat-messages');
    
    // Exibe mensagem do usuário
    const timestamp = new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    container.innerHTML += `<div class="flex justify-end">
        <div class="bg-yellow-600/20 border-l-4 border-yellow-600 rounded-lg p-3 max-w-xs">
            <p class="text-xs text-yellow-300 mb-1">Você</p>
            <p class="text-white text-sm">${escapeHtml(msg)}</p>
            <p class="text-[11px] text-slate-500 mt-1">${timestamp}</p>
        </div>
    </div>`;

    input.value = '';
    container.scrollTop = container.scrollHeight;

    // Envia para IA
    try {
        const response = await fetch('?api=chat', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: msg })
        });

        const data = await response.json();
        const reply = data.response || 'Erro ao processar resposta.';
        const replyTimestamp = data.timestamp || new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

        container.innerHTML += `<div class="flex justify-start">
            <div class="bg-slate-800/50 border-l-4 border-slate-600 rounded-lg p-3 max-w-xs">
                <p class="text-xs text-slate-400 mb-1">IA Life</p>
                <p class="text-white text-sm">${escapeHtml(reply)}</p>
                <p class="text-[11px] text-slate-500 mt-1">${replyTimestamp}</p>
            </div>
        </div>`;

        if (data.error) {
            container.innerHTML += `<div class="text-center"><p class="text-red-400 text-xs">Detalhe: ${escapeHtml(String(data.error))}</p></div>`;
        }

        container.scrollTop = container.scrollHeight;
        loadStats(); // Atualiza stats após resposta
    } catch (err) {
        container.innerHTML += `<div class="text-center"><p class="text-red-500 text-xs">Erro ao conectar com IA.</p></div>`;
        container.scrollTop = container.scrollHeight;
    }
}

// Inicializa ao carregar
document.addEventListener('DOMContentLoaded', () => {
    loadChatHistory();
    loadStats();
    document.getElementById('ia-input').focus();
});
</script>

<?php include '../includes/footer.php'; ?>
