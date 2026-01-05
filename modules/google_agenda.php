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
                'timeZone' => 'America/Sao_Paulo'
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
                $google_ids = [];
                foreach ($events_data['items'] as $event) {
                    $google_id = $event['id'];
                    $google_ids[] = $google_id;
                    $title = $event['summary'] ?? 'Sem título';
                    $start = $event['start']['dateTime'] ?? $event['start']['date'];
                    $description = $event['description'] ?? '';
                    
                    // Converter horário para timezone de São Paulo
                    if (strpos($start, 'T') !== false && strlen($start) > 10) {
                        try {
                            $dt = new DateTime($start, new DateTimeZone('UTC'));
                            $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));
                            $start = $dt->format('Y-m-d H:i:s');
                        } catch (Exception $e) {
                            // Se falhar na conversão, tenta converter como está
                            try {
                                $dt = new DateTime($start);
                                $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));
                                $start = $dt->format('Y-m-d H:i:s');
                            } catch (Exception $e2) {
                                // Se ainda falhar, usa como está
                            }
                        }
                    }
                    
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
                
                // Deletar eventos locais que não existem mais no Google
                if (!empty($google_ids)) {
                    $placeholders = implode(',', array_fill(0, count($google_ids), '?'));
                    $deleteStmt = $pdo->prepare("DELETE FROM events WHERE user_id = ? AND google_event_id IS NOT NULL AND google_event_id NOT IN ($placeholders)");
                    $deleteStmt->execute(array_merge([$user_id], $google_ids));
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
            
            // Converter datetime-local do input para formato ISO 8601
            $startDateTime = str_replace('T', ' ', $data['start_date']) . ':00';
            try {
                $dt = new DateTime($startDateTime, new DateTimeZone('America/Sao_Paulo'));
                $startIso = $dt->format('c');
                $endDateTime = clone $dt;
                $endDateTime->add(new DateInterval('PT1H'));
                $endIso = $endDateTime->format('c');
            } catch (Exception $e) {
                echo json_encode(['error' => 'Erro ao processar data: ' . $e->getMessage()]);
                exit;
            }
            
            $event_data = [
                'summary' => $data['title'],
                'description' => $data['description'] ?? '',
                'start' => [
                    'dateTime' => $startIso,
                    'timeZone' => 'America/Sao_Paulo'
                ],
                'end' => [
                    'dateTime' => $endIso,
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
        
        if ($action === 'update_event') {
            // Buscar evento local
            $stmt = $pdo->prepare("SELECT google_event_id FROM events WHERE id = ? AND user_id = ?");
            $stmt->execute([$data['id'], $user_id]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$event) {
                echo json_encode(['error' => 'Evento não encontrado']);
                exit;
            }

            // Atualizar no Google se existir
            if (!empty($event['google_event_id'])) {
                if (!$access_token) {
                    echo json_encode(['error' => 'Não autenticado no Google']);
                    exit;
                }

                $startDateTime = str_replace('T', ' ', $data['start_date']) . ':00';
                try {
                    $dt = new DateTime($startDateTime, new DateTimeZone('America/Sao_Paulo'));
                    $startIso = $dt->format('c');
                    $endDateTime = clone $dt;
                    $endDateTime->add(new DateInterval('PT1H'));
                    $endIso = $endDateTime->format('c');
                } catch (Exception $e) {
                    echo json_encode(['error' => 'Erro ao processar data: ' . $e->getMessage()]);
                    exit;
                }

                $event_data = [
                    'summary' => $data['title'],
                    'description' => $data['description'] ?? '',
                    'start' => [
                        'dateTime' => $startIso,
                        'timeZone' => 'America/Sao_Paulo'
                    ],
                    'end' => [
                        'dateTime' => $endIso,
                        'timeZone' => 'America/Sao_Paulo'
                    ]
                ];

                $google_event_id = $event['google_event_id'];
                $update_url = "https://www.googleapis.com/calendar/v3/calendars/primary/events/{$google_event_id}";

                $ch = curl_init($update_url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Authorization: Bearer {$access_token}",
                    "Content-Type: application/json"
                ]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($event_data));
                $response = curl_exec($ch);
                $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($http_status >= 300) {
                    echo json_encode(['error' => 'Erro ao atualizar no Google Calendar', 'status' => $http_status, 'response' => substr((string)$response, 0, 200)]);
                    exit;
                }
            }

            // Atualizar localmente
            $stmt = $pdo->prepare("UPDATE events SET title = ?, start_date = ?, description = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$data['title'], $data['start_date'], $data['description'] ?? '', $data['id'], $user_id]);
            echo json_encode(['success' => true]);
            exit;
        }
        
        if ($action === 'delete_event') {
            // Buscar o evento para obter o google_event_id
            $stmt = $pdo->prepare("SELECT google_event_id FROM events WHERE id = ? AND user_id = ?");
            $stmt->execute([$data['id'], $user_id]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$event) {
                echo json_encode(['error' => 'Evento não encontrado']);
                exit;
            }

            // Se houver evento no Google, precisamos de token para excluir lá também
            if (!empty($event['google_event_id'])) {
                if (!$access_token) {
                    echo json_encode(['error' => 'Não autenticado no Google para excluir o evento.']);
                    exit;
                }

                $google_event_id = $event['google_event_id'];
                $delete_url = "https://www.googleapis.com/calendar/v3/calendars/primary/events/{$google_event_id}";

                $ch = curl_init($delete_url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$access_token}"]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $resp = curl_exec($ch);
                $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                // 204 = excluído, 404 = já não existe (considerar ok)
                if ($http_status >= 300 && $http_status !== 404) {
                    echo json_encode(['error' => 'Falha ao excluir no Google Calendar.', 'status' => $http_status, 'response' => substr((string)$resp, 0, 200)]);
                    exit;
                }
            }

            // Excluir localmente
            $stmt = $pdo->prepare("DELETE FROM events WHERE id = ? AND user_id = ?");
            $stmt->execute([$data['id'], $user_id]);
            echo json_encode(['success' => true]);
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
            <header class="mb-8 flex items-center justify-between">
                <div>
                    <h2 class="text-3xl font-bold text-white drop-shadow-[0_4px_18px_rgba(255,255,255,0.25)]">
                        📅 Calendário
                    </h2>
                    <p class="text-slate-300">Visual clean para seus eventos conectados</p>
                </div>
                <?php if ($is_connected): ?>
                    <button onclick="createEventModal()" class="bg-white hover:bg-gray-100 text-black px-6 py-3 rounded-xl font-bold shadow-lg transition">
                        <i class="fas fa-plus mr-2"></i> Adicionar
                    </button>
                <?php endif; ?>
            </header>

            <?php if (!$is_connected): ?>
                <!-- Não Conectado -->
                <div class="glass-card p-8 rounded-2xl text-center max-w-2xl mx-auto">
                    <i class="fas fa-calendar-alt text-6xl text-white mb-4"></i>
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
                           class="inline-block bg-white hover:bg-gray-100 text-black px-8 py-4 rounded-xl font-bold text-lg shadow-lg transition">
                            <i class="fab fa-google mr-2"></i> Conectar com Google
                        </a>
                        <p class="text-slate-400 text-xs mt-3">Redirecionamento: <?php echo htmlspecialchars($REDIRECT_URI); ?></p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Conectado -->
                <div class="glass-card p-6 rounded-2xl">
                    <div id="calendar"></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal para criar/editar evento -->
<div id="modal-event-overlay" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
    <div class="modal-glass rounded-2xl p-8 w-full max-w-md">
        <h3 class="text-2xl font-bold mb-6 text-white" id="modal-event-title">Novo Evento</h3>
        <form onsubmit="submitEvent(event)" class="space-y-4">
            <input type="hidden" id="event-id">
            <input type="text" id="event-title" placeholder="Título do evento" required class="w-full">
            <input type="datetime-local" id="event-date" required class="w-full">
            <textarea id="event-desc" class="w-full mt-3 bg-black/40 border border-gray-600/30 rounded-xl p-3 text-white" rows="3" placeholder="Descrição (opcional)"></textarea>
            <div class="flex gap-3">
                <button type="submit" class="flex-1 bg-white hover:bg-gray-100 text-black py-3 rounded-xl font-bold" id="btn-save-event">
                    Salvar
                </button>
                <button type="button" id="btn-delete-event" onclick="deleteEvent()" class="hidden px-6 bg-red-600 hover:bg-red-500 text-white py-3 rounded-xl font-bold">
                    <i class="fas fa-trash mr-1"></i> Excluir
                </button>
                <button type="button" onclick="closeEventModal()" class="px-6 bg-slate-700 hover:bg-slate-600 text-white py-3 rounded-xl">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script src="<?php echo BASE_PATH; ?>/assets/js/common.js"></script>
<script>
let calendarInstance = null;
let isSyncing = false;

async function syncFromGoogle(silent = false) {
    if (isSyncing) return;
    isSyncing = true;
    const syncBtn = document.getElementById('sync-calendar-btn');
    if (syncBtn) syncBtn.classList.add('animate-spin');
    
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
        if (syncBtn) syncBtn.classList.remove('animate-spin');
    }
}

async function loadCalendarEvents() {
    const events = await fetchEvents();
    window.currentEvents = events;
    renderCalendarGrid(events);
}

let currentMonth = new Date();

function renderCalendarGrid(events) {
    const cal = document.getElementById('calendar');
    cal.innerHTML = '';

    // Header com navegação de mês
    const monthNavDiv = document.createElement('div');
    monthNavDiv.className = 'flex items-center justify-between mb-6 gap-3';
    
    const leftDiv = document.createElement('div');
    leftDiv.className = 'flex items-center gap-3';
    
    const prevBtn = document.createElement('button');
    prevBtn.className = 'w-8 h-8 hover:bg-slate-700 rounded text-slate-400 hover:text-white transition';
    prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
    prevBtn.onclick = () => changeMonth(-1);
    leftDiv.appendChild(prevBtn);
    
    const monthLabel = document.createElement('span');
    monthLabel.className = 'px-4 font-medium text-sm min-w-[140px] text-center capitalize text-slate-300';
    monthLabel.id = 'month-label';
    monthLabel.textContent = currentMonth.toLocaleString('pt-BR', { month: 'long', year: 'numeric' });
    leftDiv.appendChild(monthLabel);
    
    const nextBtn = document.createElement('button');
    nextBtn.className = 'w-8 h-8 hover:bg-slate-700 rounded text-slate-400 hover:text-white transition';
    nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
    nextBtn.onclick = () => changeMonth(1);
    leftDiv.appendChild(nextBtn);
    
    monthNavDiv.appendChild(leftDiv);
    
    const syncBtn = document.createElement('button');
    syncBtn.className = 'w-9 h-9 hover:bg-slate-700 rounded text-gray-400 hover:text-gray-300 transition';
    syncBtn.innerHTML = '<i class="fas fa-sync-alt"></i>';
    syncBtn.id = 'sync-calendar-btn';
    syncBtn.onclick = () => syncFromGoogle(false);
    syncBtn.title = 'Sincronizar calendário';
    monthNavDiv.appendChild(syncBtn);
    
    cal.appendChild(monthNavDiv);

    // Header com dias da semana
    const header = document.createElement('div');
    header.className = 'grid grid-cols-7 gap-2 mb-4 text-center text-slate-400 font-bold uppercase text-xs tracking-widest';
    const weekDays = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    weekDays.forEach(day => {
        const div = document.createElement('div');
        div.textContent = day;
        header.appendChild(div);
    });
    cal.appendChild(header);

    // Grid de dias
    const grid = document.createElement('div');
    grid.className = 'grid grid-cols-7 gap-2';

    const year = currentMonth.getFullYear();
    const month = currentMonth.getMonth();
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    // Preencher células vazias no início
    for (let i = 0; i < firstDay; i++) {
        const emptyCell = document.createElement('div');
        emptyCell.className = 'bg-slate-800/10 h-28 rounded-xl border border-transparent';
        grid.appendChild(emptyCell);
    }

    // Adicionar dias do mês
    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const now = new Date();
        const isToday = dateStr === now.toISOString().split('T')[0];
        const dayEvents = events.filter(e => e.start_date.startsWith(dateStr));

        const cell = document.createElement('div');
        const cellClass = isToday 
            ? 'bg-white/10 border-white/50 ring-1 ring-white/30' 
            : 'bg-slate-800/40 border-slate-700/50 hover:bg-slate-800 hover:border-slate-600';
        const numClass = isToday ? 'text-white font-bold' : 'text-gray-400 font-medium';

        cell.className = `${cellClass} h-28 rounded-xl border p-2 cursor-pointer transition group relative flex flex-col`;
        
        const dayNumEl = document.createElement('span');
        dayNumEl.className = `${numClass} text-sm mb-1 ml-1`;
        dayNumEl.textContent = day;
        cell.appendChild(dayNumEl);
        
        const eventsContainer = document.createElement('div');
        eventsContainer.className = 'flex-1 overflow-y-auto no-scrollbar space-y-1';
        
        dayEvents.forEach(ev => {
            const time = new Date(ev.start_date).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
            const eventEl = document.createElement('div');
            eventEl.className = 'cursor-pointer text-xs px-2 py-1 rounded-md bg-white/20 text-gray-100 border-l-2 border-white hover:bg-white/30 transition truncate mb-1';
            const desc = ev.description ? `\n${ev.description}` : '';
            eventEl.title = `${time} - ${ev.title}${desc}`;
            eventEl.innerHTML = `<span class="opacity-70 text-[10px] mr-1">${time}</span>${ev.title}`;
            eventEl.onclick = (e) => {
                e.stopPropagation();
                editEvent(ev);
            };
            eventsContainer.appendChild(eventEl);
        });
        
        cell.appendChild(eventsContainer);
        
        const addBtn = document.createElement('div');
        addBtn.className = 'absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity';
        addBtn.innerHTML = '<i class="fas fa-plus text-xs text-white cursor-pointer"></i>';
        cell.appendChild(addBtn);
        
        grid.appendChild(cell);
    }

    cal.appendChild(grid);
}

async function fetchEvents() {
    const result = await api('list_events');
    if (result?.success) return result.events || [];
    throw new Error(result?.error || 'Erro ao carregar eventos');
}

function renderCalendar(events) {
    renderCalendarGrid(events);
}

function openEventModal() {
    document.getElementById('modal-event-overlay').classList.remove('hidden');
}

function closeEventModal() {
    document.getElementById('modal-event-overlay').classList.add('hidden');
    document.getElementById('event-id').value = '';
    document.getElementById('btn-delete-event').classList.add('hidden');
    document.getElementById('modal-event-title').textContent = 'Novo Evento';
    document.getElementById('btn-save-event').textContent = 'Salvar';
}

function editEvent(ev) {
    document.getElementById('event-id').value = ev.id;
    document.getElementById('event-title').value = ev.title;
    document.getElementById('event-date').value = ev.start_date.replace(' ', 'T');
    document.getElementById('event-desc').value = ev.description || '';
    document.getElementById('modal-event-title').textContent = 'Editar Evento';
    document.getElementById('btn-delete-event').classList.remove('hidden');
    document.getElementById('btn-save-event').textContent = 'Atualizar';
    openEventModal();
}

function changeMonth(dir) {
    currentMonth.setMonth(currentMonth.getMonth() + dir);
    const monthLabel = document.getElementById('month-label');
    if (monthLabel) {
        monthLabel.textContent = currentMonth.toLocaleString('pt-BR', { month: 'long', year: 'numeric' });
    }
    renderCalendarGrid(window.currentEvents || []);
}

function getLocalDateTimeValue(date = null) {
    if (!date) date = new Date();
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

function createEventModal() {
    document.getElementById('event-id').value = '';
    document.getElementById('event-title').value = '';
    document.getElementById('event-date').value = getLocalDateTimeValue();
    document.getElementById('event-desc').value = '';
    document.getElementById('modal-event-title').textContent = 'Novo Evento';
    document.getElementById('btn-delete-event').classList.add('hidden');
    document.getElementById('btn-save-event').textContent = 'Salvar';
    openEventModal();
}

async function submitEvent(e) {
    e.preventDefault();
    
    const eventId = document.getElementById('event-id').value;
    const data = {
        title: document.getElementById('event-title').value,
        start_date: document.getElementById('event-date').value,
        description: document.getElementById('event-desc').value
    };
    
    try {
        if (eventId) {
            data.id = eventId;
            const result = await api('update_event', data);
            if (result.success) {
                closeEventModal();
                await loadCalendarEvents();
            }
        } else {
            const result = await api('create_event', data);
            if (result.success) {
                closeEventModal();
                await loadCalendarEvents();
            }
        }
    } catch (e) {
        alert('❌ Erro: ' + e.message);
    }
}

async function deleteEvent() {
    if (!confirm('Tem certeza que deseja excluir este evento?')) return;
    
    const eventId = document.getElementById('event-id').value;
    try {
        const result = await api('delete_event', { id: eventId });
        if (result.success) {
            closeEventModal();
            await loadCalendarEvents();
        }
    } catch (e) {
        alert('❌ Erro ao excluir: ' + e.message);
    }
}

async function disconnect() {
    if (!confirm('Deseja realmente desconectar do Google Calendar? Você precisará reconectar para sincronizar novamente.')) return;
    
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
    
    // Verifica se vem do dashboard com novo evento
    const urlParams = new URLSearchParams(window.location.search);
    const newEventTitle = urlParams.get('new_event');
    const newEventDate = urlParams.get('datetime');
    
    if (newEventTitle) {
        document.getElementById('event-id').value = '';
        document.getElementById('event-title').value = decodeURIComponent(newEventTitle);
        document.getElementById('event-date').value = decodeURIComponent(newEventDate) || getLocalDateTimeValue();
        document.getElementById('modal-event-title').textContent = 'Novo Evento';
        document.getElementById('btn-delete-event').classList.add('hidden');
        document.getElementById('btn-save-event').textContent = 'Salvar';
        openEventModal();
        
        // Remove os parâmetros da URL
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
