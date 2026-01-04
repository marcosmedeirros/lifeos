<?php
// ARQUIVO: modules/chat_life.php - Chat Life (Conversas Gerais)
require_once '../includes/auth.php';
require_login();

// Desabilitar cache para garantir que histórico é recarregado
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$user_id = $_SESSION['user_id'];

// Roteador de API para Chat Life
if (isset($_GET['api'])) {
    $action = $_GET['api'];
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) $data = $_POST;
    
    try {
        // Obter histórico de Chat Life
        if ($action === 'get_history') {
            $stmt = $pdo->prepare("
                SELECT role, content, created_at 
                FROM chat_life_messages 
                WHERE user_id = ? 
                ORDER BY created_at ASC
            ");
            $stmt->execute([$user_id]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Corrige caminhos antigos de imagens se necessário
            foreach ($messages as &$msg) {
                if (strpos($msg['content'], '[IMAGEM:') !== false) {
                    // Se o caminho ainda tem /modules/, remove
                    $msg['content'] = str_replace('[IMAGEM: /modules/uploads/', '[IMAGEM: /uploads/', $msg['content']);
                    $msg['content'] = str_replace('[IMAGEM: modules/uploads/', '[IMAGEM: /uploads/', $msg['content']);
                }
            }
            
            echo json_encode($messages);
            exit;
        }

        // Enviar mensagem para Gemini (Chat Life - sem contexto de stats)
        if ($action === 'chat') {
            $user_query = trim($data['message'] ?? '');

            if ($user_query === '') {
                echo json_encode(['response' => 'Envie uma mensagem para conversar.']);
                exit;
            }

            // Histórico completo do Chat Life
            $stmt = $pdo->prepare("SELECT role, content FROM chat_life_messages WHERE user_id = ? ORDER BY created_at ASC");
            $stmt->execute([$user_id]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Prompt de sistema simples - sem contexto de stats
            date_default_timezone_set('America/Sao_Paulo');
            $system_prompt = "Você é um assistente conversável amigável e atencioso. " .
                "Converse naturalmente sobre qualquer assunto. " .
                "Data e hora atual: " . date('d/m/Y H:i') . " (29 de dezembro de 2025, 21:53). " .
                "Sempre use essa data/hora como referência atual.";

            // Chamada para o Gemini
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
                "parts" => [["text" => $system_prompt . "\n\nMensagem: " . $user_query]]
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
                echo json_encode(['response' => 'Erro ao conectar.', 'error' => $curlError]);
                exit;
            }

            if ($httpStatus >= 400 || !$response) {
                $apiErrorMsg = $response['error']['message'] ?? '';
                echo json_encode(['response' => 'Erro na resposta.', 'error' => $apiErrorMsg ?: 'HTTP ' . $httpStatus]);
                exit;
            }

            $ai_text = $response['candidates'][0]['content']['parts'][0]['text'] ?? 'Sem resposta.';

            // Salva no histórico de Chat Life
            $ins = $pdo->prepare("INSERT INTO chat_life_messages (user_id, role, content) VALUES (?, ?, ?)");
            $ins->execute([$user_id, 'user', $user_query]);
            $ins->execute([$user_id, 'model', $ai_text]);

            echo json_encode(['response' => $ai_text, 'timestamp' => date('H:i')]);
            exit;
        }

        // Upload de foto no chat
        if ($action === 'upload_photo') {
            if (!isset($_FILES['photo'])) {
                echo json_encode(['error' => 'Nenhuma foto enviada']);
                exit;
            }

            $file = $_FILES['photo'];
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                echo json_encode(['error' => 'Formato não permitido']);
                exit;
            }

            // Cria pasta se não existir
            $uploadDir = '../uploads/chat_life/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Salva com nome único
            $filename = uniqid() . '_' . time() . '.' . $ext;
            $filepath = $uploadDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Caminho público - relativo à raiz do projeto
                $publicPath = '/uploads/chat_life/' . $filename;
                $ins = $pdo->prepare("INSERT INTO chat_life_messages (user_id, role, content) VALUES (?, ?, ?)");
                $ins->execute([$user_id, 'user', '[IMAGEM: ' . $publicPath . ']']);

                echo json_encode(['success' => true, 'path' => $publicPath, 'filename' => $filename]);
                exit;
            } else {
                echo json_encode(['error' => 'Erro ao salvar arquivo']);
                exit;
            }
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// HTML da página
$page = 'chat_life';
$page_title = 'Chat Life - LifeOS';
include '../includes/header.php';
?>

<?php include '../includes/sidebar.php'; ?>

<div class="flex min-h-screen w-full">
    <div class="flex-1 p-4 md:p-10 content-wrap transition-all duration-300">
        <div class="main-shell">
            <header class="mb-8">
                <h2 class="text-3xl font-bold text-white">💬 Chat Life</h2>
                <p class="text-slate-400">Converse livremente sobre qualquer assunto</p>
            </header>
            
            <!-- Chat Container -->
            <div class="glass-card p-6 rounded-2xl border border-gray-600/30 h-[70vh] flex flex-col">
                <!-- Messages Area -->
                <div id="chat-messages" class="flex-1 overflow-y-auto mb-6 space-y-4 pr-2">
                    <div class="text-center text-slate-500 mt-8">
                        <i class="fas fa-comments text-4xl text-gray-600/30 mb-2"></i>
                        <p>Carregando histórico...</p>
                    </div>
                </div>

                <!-- Input Area -->
                <div class="flex gap-3 border-t border-slate-700 pt-4">
                    <input 
                        type="text" 
                        id="chat-input" 
                        placeholder="Converse sobre qualquer coisa (pressione Enter)..." 
                        class="flex-1 bg-black border border-gray-600/30 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-white"
                        onkeypress="if(event.key==='Enter') sendMessage()"
                    >
                    <button 
                        onclick="document.getElementById('photo-input').click()" 
                        class="bg-black/40 hover:bg-black/60 text-gray-400 px-4 py-3 rounded-lg font-bold transition flex items-center gap-2 border border-gray-600/30"
                    >
                        <i class="fas fa-image"></i> Foto
                    </button>
                    <input type="file" id="photo-input" accept="image/*" style="display: none;" onchange="uploadPhoto()">
                    <button 
                        onclick="sendMessage()" 
                        class="bg-white hover:bg-gray-100 text-black px-6 py-3 rounded-lg font-bold transition flex items-center gap-2"
                    >
                        <i class="fas fa-paper-plane"></i> Enviar
                    </button>
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

// Processa markdown básico para HTML
function parseMarkdown(text) {
    text = escapeHtml(text);
    
    // **negrito** → <strong>negrito</strong>
    text = text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    
    // * item → quebra com bullet
    text = text.replace(/\n\* /g, '<br>• ');
    text = text.replace(/^\* /gm, '• ');
    
    // Quebras de linha simples
    text = text.replace(/\n/g, '<br>');
    
    return text;
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
            
            // Detecta se é uma imagem
            const isImage = msg.content.includes('[IMAGEM:');
            let imagePath = isImage ? msg.content.match(/\[IMAGEM: (.+?)\]/)[1] : null;
            
            // Garante que o caminho é absoluto desde a raiz
            if (imagePath && !imagePath.startsWith('http')) {
                imagePath = imagePath.startsWith('/') ? imagePath : '/' + imagePath;
            }
            
            let contentHtml = '';
            if (isImage) {
                contentHtml = `<img src="${imagePath}" class="rounded-lg max-w-xs h-auto mb-2">`;
            } else {
                // Processa markdown da resposta da IA
                const displayContent = isUser ? escapeHtml(msg.content) : parseMarkdown(msg.content);
                contentHtml = `<div class="text-white text-sm leading-relaxed">${displayContent}</div>`;
            }
            
            return `<div class="flex ${isUser ? 'justify-end' : 'justify-start'}">
                <div class="${isUser ? 'bg-white/20 border-l-4 border-white' : 'bg-black/40 border-l-4 border-gray-600'} rounded-lg p-3 max-w-2xl">
                    <p class="text-xs ${isUser ? 'text-gray-300' : 'text-slate-400'} mb-1">${isUser ? 'Você' : 'Chat Life'}</p>
                    ${contentHtml}
                    <p class="text-[11px] text-slate-500 mt-1">${timestamp}</p>
                </div>
            </div>`;
        }).join('');

        container.scrollTop = container.scrollHeight;
    } catch (err) {
        console.error('Erro ao carregar histórico:', err);
    }
}

async function sendMessage() {
    const input = document.getElementById('chat-input');
    const msg = input.value.trim();
    
    if (!msg) return;

    const container = document.getElementById('chat-messages');
    
    // Exibe mensagem do usuário
    const timestamp = new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    container.innerHTML += `<div class="flex justify-end">
        <div class="bg-white/20 border-l-4 border-white rounded-lg p-3 max-w-xs">
            <p class="text-xs text-gray-300 mb-1">Você</p>
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

        const formattedReply = parseMarkdown(reply);
        container.innerHTML += `<div class="flex justify-start">
            <div class="bg-black/40 border-l-4 border-gray-600 rounded-lg p-3 max-w-2xl">
                <p class="text-xs text-slate-400 mb-1">Chat Life</p>
                <div class="text-white text-sm leading-relaxed">${formattedReply}</div>
                <p class="text-[11px] text-slate-500 mt-1">${replyTimestamp}</p>
            </div>
        </div>`;

        if (data.error) {
            container.innerHTML += `<div class="text-center"><p class="text-red-400 text-xs">Detalhe: ${escapeHtml(String(data.error))}</p></div>`;
        }

        container.scrollTop = container.scrollHeight;
    } catch (err) {
        container.innerHTML += `<div class="text-center"><p class="text-red-500 text-xs">Erro ao conectar.</p></div>`;
        container.scrollTop = container.scrollHeight;
    }
}

// Inicializa ao carregar
document.addEventListener('DOMContentLoaded', () => {
    loadChatHistory();
    document.getElementById('chat-input').focus();
});

// Upload de foto
async function uploadPhoto() {
    const fileInput = document.getElementById('photo-input');
    const file = fileInput.files[0];
    
    if (!file) return;

    const formData = new FormData();
    formData.append('photo', file);

    try {
        const response = await fetch('?api=upload_photo', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        if (data.success) {
            // Exibe a imagem no chat
            const container = document.getElementById('chat-messages');
            const timestamp = new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
            container.innerHTML += `<div class="flex justify-end">
                <div class="bg-white/20 border-l-4 border-white rounded-lg p-3 max-w-xs">
                    <p class="text-xs text-gray-300 mb-1">Você</p>
                    <img src="${data.path}" class="rounded-lg max-w-xs h-auto mb-2">
                    <p class="text-[11px] text-slate-500 mt-1">${timestamp}</p>
                </div>
            </div>`;
            container.scrollTop = container.scrollHeight;
            fileInput.value = '';
        } else {
            alert('❌ Erro ao enviar foto: ' + (data.error || 'Desconhecido'));
        }
    } catch (err) {
        alert('❌ Erro ao enviar foto');
        console.error(err);
    }
}
</script>

<?php include '../includes/footer.php'; ?>
