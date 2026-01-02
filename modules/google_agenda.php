<?php
// ARQUIVO: google_agenda.php - Integração com Google Calendar
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user_id = $_SESSION['user_id'];
$callback_error = null;

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
    if (empty($GOOGLE_CLIENT_ID) || empty($GOOGLE_CLIENT_SECRET)) {
        $callback_error = 'CLIENT_ID ou CLIENT_SECRET não configurados.';
    } else {
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
        $curl_error = curl_error($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
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
        } else {
            $raw_error = $token_data['error_description'] ?? $token_data['error'] ?? ($curl_error ?: 'Falha ao trocar o código por token.');
            $callback_error = "HTTP {$http_status}: {$raw_error}";
            if ($response) {
                $callback_error .= "\nResposta: " . substr($response, 0, 500);
            }
        }
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
            $params = [
                'timeMin' => $time_min,
                'timeMax' => $time_max,
                'singleEvents' => 'true',
                'orderBy' => 'startTime',
                'timeZone' => 'UTC'
            ];
            $calendar_url = 'https://www.googleapis.com/calendar/v3/calendars/primary/events?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
            
            $ch = curl_init($calendar_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$access_token}"]);
            $response = curl_exec($ch);
            $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
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
                $google_error = $events_data['error']['errors'][0]['message'] ?? ($events_data['error']['message'] ?? '');
                echo json_encode([
                    'error' => 'Erro ao buscar eventos',
                    'http_status' => $http_status,
                    'response' => substr($response ?: '', 0, 1000),
                    'curl_error' => $curl_error,
                    'request_url' => $calendar_url,
                    'google_error' => $google_error
                ]);
            }
            exit;
        }

        if ($action === 'list_events') {
            $stmt = $pdo->prepare("SELECT * FROM events WHERE google_event_id IS NOT NULL AND user_id = ? ORDER BY start_date DESC LIMIT 200");
            $stmt->execute([$user_id]);
            echo json_encode(['success' => true, 'events' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
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
$page_title = 'Calendário - LifeOS';
include __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen w-full">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="flex-1 p-4 md:p-10 content-wrap transition-all duration-300">
        <div class="main-shell">
            <header class="mb-8">
                <h2 class="text-3xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-yellow-400 via-amber-400 to-yellow-500 drop-shadow-[0_4px_18px_rgba(250,204,21,0.25)]">
                    📅 Calendário
                </h2>
                <p class="text-slate-300">Visual clean para seus eventos conectados</p>
            </header>

            <?php if (!$is_connected): ?>
                <!-- Não Conectado -->
                <div class="glass-card p-8 rounded-2xl text-center max-w-2xl mx-auto">
                    <i class="fas fa-calendar-alt text-6xl text-yellow-500 mb-4"></i>
                    <h3 class="text-2xl font-bold text-white mb-4">Conecte sua Google Agenda</h3>
                    <p class="text-slate-300 mb-6">Sincronize seus eventos automaticamente entre o LifeOS e o Google Calendar</p>

                    <?php if (!empty($callback_error)): ?>
                        <div class="bg-red-900/40 border border-red-600 rounded-lg p-4 mb-4 text-left">
                            <p class="text-red-200 text-sm font-semibold mb-1">Erro ao conectar:</p>
                            <p class="text-red-100 text-xs whitespace-pre-line"><?php echo htmlspecialchars($callback_error); ?></p>
                        </div>
                    <?php endif; ?>
                    
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
                        <p class="text-slate-400 text-xs mt-3">Redirecionamento: <?php echo htmlspecialchars($REDIRECT_URI); ?></p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Conectado -->
                <div class="mb-6 flex flex-wrap gap-3 items-center">
                    <button onclick="createEventModal()" class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white px-6 py-3 rounded-xl font-bold shadow-lg transition">
                        <i class="fas fa-plus mr-2"></i> Novo Evento
                    </button>
                </div>

                <div class="glass-card p-6 rounded-2xl mt-6 bg-gradient-to-br from-slate-900/70 via-slate-900 to-slate-950 border border-slate-800 shadow-2xl shadow-yellow-600/20">
                    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                        <div>
                            <h3 class="text-2xl font-bold text-yellow-300 drop-shadow">Calendário</h3>
                            <p class="text-slate-400 text-sm">Visão mensal, semanal e lista em um lugar só</p>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-slate-200 bg-slate-800/80 px-3 py-2 rounded-full border border-slate-700 shadow">
                            <span class="h-3 w-3 rounded-full bg-yellow-400 border border-yellow-200 shadow-sm"></span>
                            <span>Atualizado automaticamente</span>
                        </div>
                    </div>
                    <div id="calendar" class="bg-slate-950/70 rounded-2xl p-4 border border-slate-800 shadow-inner"></div>
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

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
<script src="<?php echo BASE_PATH; ?>/assets/js/common.js"></script>
<style>
/* Harmoniza o FullCalendar com o tema escuro/dourado do site */
#calendar {
    font-family: 'Outfit', sans-serif;
}
#calendar .fc {
    --fc-border-color: rgba(255, 255, 255, 0.06);
    --fc-button-text-color: #0f172a;
    --fc-button-bg-color: #facc15;
    --fc-button-border-color: #eab308;
    --fc-button-hover-bg-color: #fde047;
    --fc-button-hover-border-color: #facc15;
    --fc-button-active-bg-color: #f59e0b;
    --fc-button-active-border-color: #d97706;
    --fc-page-bg-color: transparent;
    --fc-neutral-bg-color: rgba(255, 255, 255, 0.02);
    --fc-list-event-hover-bg-color: rgba(250, 204, 21, 0.12);
}
#calendar .fc-toolbar.fc-header-toolbar {
    margin-bottom: 1.25rem;
}
#calendar .fc-toolbar-title {
    color: #facc15;
    text-shadow: 0 4px 16px rgba(250, 204, 21, 0.25);
    letter-spacing: 0.02em;
}
#calendar .fc-button {
    border-radius: 12px;
    padding: 0.55rem 0.9rem;
    font-weight: 700;
    box-shadow: 0 10px 25px -12px rgba(250, 204, 21, 0.6);
}
#calendar .fc-button-primary:disabled {
    background: #1f2937;
    color: #9ca3af;
    border-color: #1f2937;
    box-shadow: none;
}
#calendar .fc-scrollgrid {
    border-radius: 18px;
    overflow: hidden;
    border-color: rgba(255, 255, 255, 0.05);
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.04);
}
#calendar .fc-col-header-cell {
    background: rgba(255, 255, 255, 0.02);
}
#calendar .fc-col-header-cell-cushion {
    color: #cbd5f5;
    font-weight: 700;
    letter-spacing: 0.02em;
    text-transform: uppercase;
}
#calendar .fc-daygrid-day-number {
    color: #e2e8f0;
    font-weight: 600;
}
#calendar .fc-day-today {
    background: linear-gradient(135deg, rgba(250, 204, 21, 0.08), rgba(234, 179, 8, 0.06));
}
#calendar .fc-daygrid-day-frame {
    background: rgba(255, 255, 255, 0.01);
}
#calendar .fc-event {
    background: linear-gradient(135deg, #fde047, #fbbf24);
    border: none;
    color: #0f172a;
    font-weight: 700;
    border-radius: 10px;
    padding: 4px 8px;
    box-shadow: 0 12px 30px -14px rgba(250, 204, 21, 0.8);
}
#calendar .fc-event-title { white-space: normal; }
#calendar .fc-list-day-cushion {
    background: rgba(255, 255, 255, 0.03);
    color: #e2e8f0;
}
#calendar .fc-list-event-title a { color: #0f172a; }
#calendar .fc-highlight { background: rgba(250, 204, 21, 0.12); }
</style>
<script>
let calendarInstance = null;
let isSyncing = false;

async function syncFromGoogle(silent = false) {
    if (isSyncing) return;
    isSyncing = true;
    try {
        const result = await api('sync_from_google');
        if (result.success) {
            if (!silent) alert(`✅ ${result.count} eventos sincronizados com sucesso!`);
            await loadCalendarEvents();
        } else if (!silent) {
            alert('❌ Erro ao sincronizar.');
            console.error('sync_from_google response (no success flag):', result);
        }
    } catch (e) {
        console.error('sync_from_google error:', e);
        if (!silent) alert('❌ Erro ao sincronizar: ' + (e?.message || e));
    } finally {
        isSyncing = false;
    }
}

async function loadCalendarEvents() {
    const events = await fetchEvents();
    renderCalendar(events);
}

async function fetchEvents() {
    const result = await api('list_events');
    if (result?.success) return result.events || [];
    throw new Error(result?.error || 'Erro ao carregar eventos');
}

function renderCalendar(events) {
    const calendarEl = document.getElementById('calendar');
    if (!calendarEl) return;

    const fcEvents = Array.isArray(events) ? events.map(ev => ({
        title: ev.title || 'Sem título',
        start: ev.start_date,
        allDay: (ev.start_date && ev.start_date.length === 10)
    })) : [];

    if (!calendarInstance) {
        calendarInstance = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: 'pt-br',
            height: 'auto',
            themeSystem: 'standard',
            expandRows: true,
            firstDay: 1,
            headerToolbar: {
                start: 'prev,next today',
                center: 'title',
                end: 'dayGridMonth,timeGridWeek,listWeek'
            },
            dayMaxEvents: true,
            nowIndicator: true,
            eventDisplay: 'block',
            dayMaxEventRows: 3,
            eventColor: '#facc15',
            eventBorderColor: '#f59e0b',
            eventTextColor: '#0f172a',
            titleFormat: { year: 'numeric', month: 'long' },
            buttonText: {
                today: 'Hoje',
                month: 'Mês',
                week: 'Semana',
                list: 'Lista'
            },
            events: fcEvents
        });
        calendarInstance.render();
    } else {
        calendarInstance.removeAllEvents();
        calendarInstance.addEventSource(fcEvents);
    }
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
document.addEventListener('DOMContentLoaded', async () => {
    await syncFromGoogle(true);
    await loadCalendarEvents();
    setInterval(() => syncFromGoogle(true), 10 * 60 * 1000); // auto a cada 10min
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
