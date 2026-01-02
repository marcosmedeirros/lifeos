<?php
// ARQUIVO: google_agenda.php - Integração com Google Calendar
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user_id = $_SESSION['user_id'];

// Configurações do Google Calendar API
$GOOGLE_CLIENT_ID = getenv('GOOGLE_CLIENT_ID') ?: '';
$GOOGLE_CLIENT_SECRET = getenv('GOOGLE_CLIENT_SECRET') ?: '';
$GOOGLE_REDIRECT_URI_ENV = getenv('GOOGLE_REDIRECT_URI') ?: '';
$REDIRECT_URI = $GOOGLE_REDIRECT_URI_ENV ?: ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . strtok($_SERVER["REQUEST_URI"], '?') . "?callback=1");

function ensureGoogleCalendarSchema(PDO $pdo) {
    // Tokens table
    $pdo->exec("CREATE TABLE IF NOT EXISTS google_calendar_tokens (\n        id INT AUTO_INCREMENT PRIMARY KEY,\n        user_id INT NOT NULL UNIQUE,\n        access_token TEXT NOT NULL,\n        refresh_token TEXT NULL,\n        expires_at DATETIME NOT NULL,\n        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Ensure events has google_event_id column/index
    $col = $pdo->query("SHOW COLUMNS FROM events LIKE 'google_event_id'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE events ADD COLUMN google_event_id VARCHAR(255) DEFAULT NULL");
        $pdo->exec("CREATE UNIQUE INDEX idx_events_google_event_id ON events (google_event_id)");
    }
}

ensureGoogleCalendarSchema($pdo);

// Callback do OAuth2
if (isset($_GET['callback']) && isset($_GET['code'])) {
    $token_url = "https://oauth2.googleapis.com/token";
    $post_data = [
        'code' => $_GET['code'],
        'client_id' => $GOOGLE_CLIENT_ID,
        'client_secret' => $GOOGLE_CLIENT_SECRET,
        'redirect_uri' => $REDIRECT_URI,
        'grant_type' => 'authorization_code'
    ];

    $ch = curl_init($token_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    $response = curl_exec($ch);
    curl_close($ch);

    $token_data = json_decode($response, true);
    
    if (isset($token_data['access_token'])) {
        // Salvar tokens no banco
        $stmt = $pdo->prepare("INSERT INTO google_calendar_tokens (user_id, access_token, refresh_token, expires_at) 
                               VALUES (?, ?, ?, ?) 
                               ON DUPLICATE KEY UPDATE 
                               access_token = VALUES(access_token), 
                               refresh_token = VALUES(refresh_token), 
                               expires_at = VALUES(expires_at)");
        $expires_at = date('Y-m-d H:i:s', time() + $token_data['expires_in']);
        $stmt->execute([
            $user_id, 
            $token_data['access_token'], 
            $token_data['refresh_token'] ?? null, 
            $expires_at
        ]);
        
        header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
    }
}

// Função para obter access token válido
function getValidAccessToken($pdo, $user_id, $client_id, $client_secret) {
    $stmt = $pdo->prepare("SELECT * FROM google_calendar_tokens WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $token = $stmt->fetch();
    
    if (!$token) return null;
    
    // Se expirou, renovar
    if (strtotime($token['expires_at']) < time()) {
        $token_url = "https://oauth2.googleapis.com/token";
        $post_data = [
            'refresh_token' => $token['refresh_token'],
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'grant_type' => 'refresh_token'
        ];

        $ch = curl_init($token_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        $response = curl_exec($ch);
        curl_close($ch);

        $new_token = json_decode($response, true);
        
        if (isset($new_token['access_token'])) {
            $expires_at = date('Y-m-d H:i:s', time() + $new_token['expires_in']);
            $stmt = $pdo->prepare("UPDATE google_calendar_tokens SET access_token = ?, expires_at = ? WHERE user_id = ?");
            $stmt->execute([$new_token['access_token'], $expires_at, $user_id]);
            return $new_token['access_token'];
        }
        return null;
    }
    
    return $token['access_token'];
}

// API Routes
if (isset($_GET['api'])) {
    $action = $_GET['api'];
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) $data = $_POST;
    
    try {
        $access_token = getValidAccessToken($pdo, $user_id, $GOOGLE_CLIENT_ID, $GOOGLE_CLIENT_SECRET);
        
        if ($action === 'sync_from_google') {
            if (!$access_token) {
                echo json_encode(['error' => 'Não autenticado']);
                exit;
            }
            
            // Buscar eventos do Google Calendar
            $time_min = date('c', strtotime('-30 days'));
            $time_max = date('c', strtotime('+90 days'));
            $calendar_url = "https://www.googleapis.com/calendar/v3/calendars/primary/events?timeMin={$time_min}&timeMax={$time_max}&singleEvents=true&orderBy=startTime";
            
            $ch = curl_init($calendar_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$access_token}"]);
            $response = curl_exec($ch);
            curl_close($ch);
            
            $events_data = json_decode($response, true);
            
            if (isset($events_data['items'])) {
                foreach ($events_data['items'] as $event) {
                    $google_id = $event['id'];
                    $title = $event['summary'] ?? 'Sem título';
                    $start = $event['start']['dateTime'] ?? $event['start']['date'];
                    $description = $event['description'] ?? '';
                    
                    // Verificar se já existe
                    $check = $pdo->prepare("SELECT id FROM events WHERE google_event_id = ?");
                    $check->execute([$google_id]);
                    
                    if ($check->fetch()) {
                        // Atualizar
                        $stmt = $pdo->prepare("UPDATE events SET title = ?, start_date = ?, description = ? WHERE google_event_id = ?");
                        $stmt->execute([$title, $start, $description, $google_id]);
                    } else {
                        // Inserir
                        $stmt = $pdo->prepare("INSERT INTO events (user_id, title, start_date, description, google_event_id) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$user_id, $title, $start, $description, $google_id]);
                    }
                }
                echo json_encode(['success' => true, 'count' => count($events_data['items'])]);
            } else {
                echo json_encode(['error' => 'Erro ao buscar eventos']);
            }
            exit;
        }
        
        if ($action === 'create_event') {
            if (!$access_token) {
                echo json_encode(['error' => 'Não autenticado']);
                exit;
            }
            
            $event_data = [
                'summary' => $data['title'],
                'description' => $data['description'] ?? '',
                'start' => [
                    'dateTime' => date('c', strtotime($data['start_date'])),
                    'timeZone' => 'America/Sao_Paulo'
                ],
                'end' => [
                    'dateTime' => date('c', strtotime($data['start_date']) + 3600),
                    'timeZone' => 'America/Sao_Paulo'
                ]
            ];
            
            $ch = curl_init("https://www.googleapis.com/calendar/v3/calendars/primary/events");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($event_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$access_token}",
                "Content-Type: application/json"
            ]);
            $response = curl_exec($ch);
            curl_close($ch);
            
            $created_event = json_decode($response, true);
            
            if (isset($created_event['id'])) {
                // Salvar localmente
                $stmt = $pdo->prepare("INSERT INTO events (user_id, title, start_date, description, google_event_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $user_id,
                    $data['title'],
                    $data['start_date'],
                    $data['description'] ?? '',
                    $created_event['id']
                ]);
                echo json_encode(['success' => true, 'id' => $created_event['id']]);
            } else {
                echo json_encode(['error' => 'Erro ao criar evento']);
            }
            exit;
        }
        
        if ($action === 'disconnect') {
            $stmt = $pdo->prepare("DELETE FROM google_calendar_tokens WHERE user_id = ?");
            $stmt->execute([$user_id]);
            echo json_encode(['success' => true]);
            exit;
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Verificar se está conectado
$stmt = $pdo->prepare("SELECT * FROM google_calendar_tokens WHERE user_id = ?");
$stmt->execute([$user_id]);
$google_token = $stmt->fetch();
$is_connected = $google_token && !empty($google_token['access_token']);

$page = 'google_agenda';
$page_title = 'Google Agenda - LifeOS';
include __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen w-full">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="flex-1 p-4 md:p-10 content-wrap transition-all duration-300">
        <div class="main-shell">
            <header class="mb-8">
                <h2 class="text-3xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-yellow-400 to-yellow-500">
                    📅 Google Agenda
                </h2>
                <p class="text-slate-400">Sincronização com Google Calendar</p>
            </header>

            <?php if (!$is_connected): ?>
                <!-- Não Conectado -->
                <div class="glass-card p-8 rounded-2xl text-center max-w-2xl mx-auto">
                    <i class="fas fa-calendar-alt text-6xl text-yellow-500 mb-4"></i>
                    <h3 class="text-2xl font-bold text-white mb-4">Conecte sua Google Agenda</h3>
                    <p class="text-slate-300 mb-6">Sincronize seus eventos automaticamente entre o LifeOS e o Google Calendar</p>
                    
                    <?php if (empty($GOOGLE_CLIENT_ID) || empty($GOOGLE_CLIENT_SECRET)): ?>
                        <div class="bg-red-900/30 border border-red-600 rounded-lg p-4 mb-6">
                            <p class="text-red-200 text-sm">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                Configure as variáveis GOOGLE_CLIENT_ID e GOOGLE_CLIENT_SECRET no arquivo .env
                            </p>
                        </div>
                    <?php else: ?>
                        <a href="https://accounts.google.com/o/oauth2/v2/auth?client_id=<?php echo urlencode($GOOGLE_CLIENT_ID); ?>&redirect_uri=<?php echo urlencode($REDIRECT_URI); ?>&response_type=code&scope=https://www.googleapis.com/auth/calendar&access_type=offline&prompt=consent" 
                           class="inline-block bg-gradient-to-r from-yellow-600 to-yellow-700 hover:from-yellow-500 hover:to-yellow-600 text-white px-8 py-4 rounded-xl font-bold text-lg shadow-lg shadow-yellow-600/30 transition">
                            <i class="fab fa-google mr-2"></i> Conectar com Google
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Conectado -->
                <div class="mb-6 flex flex-wrap gap-3 items-center">
                    <button onclick="syncFromGoogle()" class="bg-gradient-to-r from-yellow-600 to-yellow-700 hover:from-yellow-500 hover:to-yellow-600 text-white px-6 py-3 rounded-xl font-bold shadow-lg shadow-yellow-600/30 transition">
                        <i class="fas fa-sync-alt mr-2"></i> Sincronizar do Google
                    </button>
                    <button onclick="createEventModal()" class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white px-6 py-3 rounded-xl font-bold shadow-lg transition">
                        <i class="fas fa-plus mr-2"></i> Novo Evento
                    </button>
                    <button onclick="disconnect()" class="bg-gradient-to-r from-red-600 to-red-700 hover:from-red-500 hover:to-red-600 text-white px-6 py-3 rounded-xl font-bold shadow-lg transition">
                        <i class="fas fa-unlink mr-2"></i> Desconectar
                    </button>
                    <div class="text-green-300 text-sm font-semibold ml-2 flex items-center gap-2">
                        <i class="fas fa-check-circle"></i>
                        <span>Conectado ao Google Calendar<?php if (!empty($google_token['updated_at'])) { echo ' • token atualizado em ' . $google_token['updated_at']; } ?></span>
                    </div>
                </div>

                <div class="glass-card p-6 rounded-2xl">
                    <h3 class="text-xl font-bold text-yellow-500 mb-4">Seus Eventos Sincronizados</h3>
                    <div id="events-list" class="space-y-3"></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal para criar evento -->
<div id="modal-create-event" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
    <div class="modal-glass rounded-2xl p-8 w-full max-w-md">
        <h3 class="text-2xl font-bold mb-6 text-yellow-500">Novo Evento</h3>
        <form onsubmit="submitEvent(event)" class="space-y-4">
            <input type="text" id="event-title" placeholder="Título do evento" required class="w-full">
            <input type="datetime-local" id="event-date" required class="w-full">
            <textarea id="event-description" placeholder="Descrição (opcional)" rows="3" class="w-full"></textarea>
            <div class="flex gap-3">
                <button type="submit" class="flex-1 bg-yellow-600 hover:bg-yellow-500 text-white py-3 rounded-xl font-bold">
                    Criar no Google Calendar
                </button>
                <button type="button" onclick="closeCreateModal()" class="px-6 bg-slate-700 hover:bg-slate-600 text-white py-3 rounded-xl">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script src="<?php echo BASE_PATH; ?>/assets/js/common.js"></script>
<script>
async function syncFromGoogle() {
    try {
        const result = await api('sync_from_google');
        if (result.success) {
            alert(`✅ ${result.count} eventos sincronizados com sucesso!`);
            loadEvents();
        }
    } catch (e) {
        alert('❌ Erro ao sincronizar: ' + e.message);
    }
}

async function loadEvents() {
    // Buscar eventos locais que vieram do Google
    const events = <?php 
        $stmt = $pdo->prepare("SELECT * FROM events WHERE google_event_id IS NOT NULL AND user_id = ? ORDER BY start_date DESC LIMIT 50");
        $stmt->execute([$user_id]);
        echo json_encode($stmt->fetchAll());
    ?>;
    
    const list = document.getElementById('events-list');
    if (events.length === 0) {
        list.innerHTML = '<p class="text-slate-500 text-center italic">Nenhum evento sincronizado. Clique em "Sincronizar do Google"</p>';
        return;
    }
    
    list.innerHTML = events.map(ev => {
        const date = new Date(ev.start_date);
        const formatted = date.toLocaleString('pt-BR', { 
            day: '2-digit', 
            month: 'short', 
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
        
        return `<div class="bg-slate-800/50 p-4 rounded-xl border-l-4 border-yellow-500">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <h4 class="text-white font-bold">${ev.title}</h4>
                    <p class="text-slate-400 text-sm mt-1">${formatted}</p>
                    ${ev.description ? `<p class="text-slate-300 text-sm mt-2">${ev.description}</p>` : ''}
                </div>
                <span class="text-green-400 text-xs">
                    <i class="fab fa-google mr-1"></i>Sincronizado
                </span>
            </div>
        </div>`;
    }).join('');
}

function createEventModal() {
    document.getElementById('modal-create-event').classList.remove('hidden');
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    document.getElementById('event-date').value = now.toISOString().slice(0, 16);
}

function closeCreateModal() {
    document.getElementById('modal-create-event').classList.add('hidden');
}

async function submitEvent(e) {
    e.preventDefault();
    
    const data = {
        title: document.getElementById('event-title').value,
        start_date: document.getElementById('event-date').value,
        description: document.getElementById('event-description').value
    };
    
    try {
        const result = await api('create_event', data);
        if (result.success) {
            alert('✅ Evento criado no Google Calendar!');
            closeCreateModal();
            location.reload();
        }
    } catch (e) {
        alert('❌ Erro ao criar evento: ' + e.message);
    }
}

async function disconnect() {
    if (!confirm('Deseja realmente desconectar do Google Calendar?')) return;
    
    try {
        await api('disconnect');
        location.reload();
    } catch (e) {
        alert('❌ Erro ao desconectar: ' + e.message);
    }
}

<?php if ($is_connected): ?>
document.addEventListener('DOMContentLoaded', loadEvents);
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
