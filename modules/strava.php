<?php
require_once __DIR__ . '/../includes/auth.php';
// require_login(); // Comentado - acesso direto

// Definir user_id padrão se não existir sessão
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['user_name'] = 'Marcos Medeiros';
}

$page = 'strava';

// --- CREDENCIAIS STRAVA ---
$client_id = '189490'; 
$client_secret = '7d027375a7114dcfc69af1f6b2ef2f955f339834'; 
$redirect_uri = 'https://marcosmedeiros.io/?api=strava_callback';

if (isset($_GET['api'])) {
    try {
        require_once __DIR__ . '/../config.php';
        $action = $_GET['api'];
        
        // --- FUNÇÕES AUXILIARES ---
        function makeStravaRequest($url, $postFields = null, $headers = []) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            if ($postFields) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
            }
            if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $response = curl_exec($ch);
            curl_close($ch);
            return json_decode($response, true);
        }

        // 1. CALLBACK (Retorno do Login)
        if ($action === 'strava_callback') {
            if (!isset($_GET['code'])) die("Erro: Código não recebido.");
            
            $tokenData = makeStravaRequest('https://www.strava.com/oauth/token', [
                'client_id' => $client_id, 
                'client_secret' => $client_secret,
                'code' => $_GET['code'], 
                'grant_type' => 'authorization_code'
            ]);

            if (isset($tokenData['access_token'])) {
                $pdo->prepare("DELETE FROM strava_auth WHERE user_id = 1")->execute(); 
                $stmt = $pdo->prepare("INSERT INTO strava_auth (user_id, access_token, refresh_token, expires_at, athlete_id) VALUES (1, ?, ?, ?, ?)");
                $stmt->execute([$tokenData['access_token'], $tokenData['refresh_token'], $tokenData['expires_at'], $tokenData['athlete']['id']]);
                header("Location: /modules/strava.php"); exit;
            } else {
                die("Erro Strava: " . json_encode($tokenData));
            }
        }

        // 2. TOGGLE DONE (Marcar/Desmarcar como Feito)
        if ($action === 'toggle_strava_done') {
            $strava_id = $_POST['strava_id'] ?? null;
            if (!$strava_id) {
                echo json_encode(['success' => false, 'error' => 'ID inválido']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT done FROM strava_activities WHERE strava_id = ?");
            $stmt->execute([$strava_id]);
            $current = $stmt->fetch();
            
            $newStatus = $current ? !$current['done'] : true;
            
            $updateStmt = $pdo->prepare("UPDATE strava_activities SET done = ? WHERE strava_id = ?");
            $updateStmt->execute([$newStatus, $strava_id]);
            
            echo json_encode(['success' => true, 'done' => $newStatus]);
            exit;
        }

        // 3. GET DATA (Para o Frontend)
        if ($action === 'get_strava_data') {
            $auth = $pdo->query("SELECT * FROM strava_auth WHERE user_id = 1")->fetch();
            $authUrl = "https://www.strava.com/oauth/authorize?client_id=$client_id&response_type=code&redirect_uri=$redirect_uri&approval_prompt=force&scope=activity:read_all";

            if (!$auth) { 
                echo json_encode(['connected' => false, 'auth_url' => $authUrl]); 
                exit; 
            }

            // Refresh Token se necessário
            if (time() > $auth['expires_at']) {
                $refresh = makeStravaRequest('https://www.strava.com/oauth/token', [
                    'client_id' => $client_id, 
                    'client_secret' => $client_secret,
                    'grant_type' => 'refresh_token', 
                    'refresh_token' => $auth['refresh_token']
                ]);
                if (isset($refresh['access_token'])) {
                    $auth['access_token'] = $refresh['access_token'];
                    $pdo->prepare("UPDATE strava_auth SET access_token=?, refresh_token=?, expires_at=? WHERE id=?")
                        ->execute([$refresh['access_token'], $refresh['refresh_token'], $refresh['expires_at'], $auth['id']]);
                }
            }

            // Buscando as últimas 30 atividades
            $activities = makeStravaRequest('https://www.strava.com/api/v3/athlete/activities?per_page=30', null, ["Authorization: Bearer " . $auth['access_token']]);
            
            // Salvar/atualizar atividades no banco local
            if (is_array($activities)) {
                foreach($activities as $act) {
                    $stmt = $pdo->prepare("INSERT INTO strava_activities (strava_id, name, type, distance, moving_time, start_date, kudos) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), kudos=VALUES(kudos)");
                    $stmt->execute([$act['id'], $act['name'], $act['type'], $act['distance'], $act['moving_time'], $act['start_date'], $act['kudos_count'] ?? 0]);
                }
            }
            
            $list = []; 
            $monthlyStats = [];
            $totalKm = 0;
            $totalTime = 0;

            $meses = [
                '01'=>'Janeiro', '02'=>'Fevereiro', '03'=>'Março', '04'=>'Abril',
                '05'=>'Maio', '06'=>'Junho', '07'=>'Julho', '08'=>'Agosto',
                '09'=>'Setembro', '10'=>'Outubro', '11'=>'Novembro', '12'=>'Dezembro'
            ];

            if (is_array($activities)) {
                foreach($activities as $act) {
                    $distKm = $act['distance'] / 1000;
                    $totalKm += $distKm;
                    $totalTime += $act['moving_time'];
                    
                    // Buscar status 'done' do banco local
                    $doneStmt = $pdo->prepare("SELECT done FROM strava_activities WHERE strava_id = ?");
                    $doneStmt->execute([$act['id']]);
                    $doneData = $doneStmt->fetch();
                    $isDone = $doneData ? (bool)$doneData['done'] : false;
                    
                    $list[] = [
                        'strava_id' => $act['id'],
                        'name' => $act['name'], 
                        'type' => $act['type'],
                        'distance' => number_format($distKm, 2, ',', '.'),
                        'time' => gmdate("H:i:s", $act['moving_time']),
                        'date' => date('d/m/Y', strtotime($act['start_date'])),
                        'kudos' => $act['kudos_count'] ?? 0,
                        'done' => $isDone
                    ];

                    $monthKey = date('Y-m', strtotime($act['start_date']));
                    $monthNum = date('m', strtotime($act['start_date']));
                    $yearNum = date('Y', strtotime($act['start_date']));

                    if (!isset($monthlyStats[$monthKey])) {
                        $monthlyStats[$monthKey] = [
                            'label' => $meses[$monthNum] . '/' . $yearNum,
                            'total_dist' => 0,
                            'count' => 0,
                            'types' => []
                        ];
                    }

                    $monthlyStats[$monthKey]['total_dist'] += $distKm;
                    $monthlyStats[$monthKey]['count']++;
                    
                    $type = $act['type'];
                    if(!isset($monthlyStats[$monthKey]['types'][$type])) $monthlyStats[$monthKey]['types'][$type] = 0;
                    $monthlyStats[$monthKey]['types'][$type]++;
                }
            }

            $formattedStats = [];
            foreach($monthlyStats as $key => $data) {
                $data['total_dist_fmt'] = number_format($data['total_dist'], 1, ',', '.');
                $formattedStats[] = $data;
            }

            $hours = floor($totalTime / 3600);
            $minutes = floor(($totalTime % 3600) / 60);

            echo json_encode([
                'connected' => true, 
                'summary' => [
                    'total_km' => number_format($totalKm, 1, ',', '.'), 
                    'total_time' => $hours . 'h ' . $minutes . 'm',
                    'total_activities' => count($activities)
                ],
                'monthly' => array_values($formattedStats),
                'list' => array_slice($list, 0, 10)
            ]);
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
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-3xl font-bold text-white flex items-center gap-3">
                    <i class="fab fa-strava text-[#fc4c02]"></i> Atividades Strava
                </h2>
                <div id="strava-connect-btn" class="hidden"></div>
            </div>
            
            <div id="strava-stats" class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 hidden">
                <div class="glass-card p-6 rounded-2xl border-l-4 border-[#fc4c02]">
                    <p class="text-xs uppercase text-slate-400 font-bold mb-2">Distância Total (Recente)</p>
                    <p class="text-4xl font-bold text-white"><span id="strava-total-km">0</span> <span class="text-lg text-slate-500">km</span></p>
                </div>
                <div class="glass-card p-6 rounded-2xl border-l-4 border-yellow-500">
                    <p class="text-xs uppercase text-slate-400 font-bold mb-2">Tempo em Movimento</p>
                    <p class="text-4xl font-bold text-white" id="strava-total-time">0h 0m</p>
                </div>
                <div class="glass-card p-6 rounded-2xl border-l-4 border-green-500">
                    <p class="text-xs uppercase text-slate-400 font-bold mb-2">Total de Atividades</p>
                    <p class="text-4xl font-bold text-white" id="strava-total-activities">0</p>
                </div>
            </div>

            <!-- Estatísticas Mensais -->
            <div id="strava-monthly" class="mb-8 hidden">
                <h3 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-calendar-alt text-[#fc4c02]"></i> Atividades por Mês
                </h3>
                <div id="monthly-stats-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Cards mensais serão inseridos aqui -->
                </div>
            </div>

            <div class="glass-card rounded-2xl overflow-hidden shadow-xl border border-slate-700/50">
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[700px]">
                        <thead class="bg-slate-900/50 text-slate-400 uppercase text-xs font-bold tracking-wider">
                            <tr>
                                <th class="p-5">Data</th>
                                <th class="p-5">Atividade</th>
                                <th class="p-5 text-center">Tipo</th>
                                <th class="p-5 text-center">Distância</th>
                                <th class="p-5 text-center">Tempo</th>
                                <th class="p-5 text-center">Kudos</th>
                                <th class="p-5 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody id="strava-list" class="divide-y divide-slate-700/50 text-sm font-medium">
                            <tr><td colspan="7" class="p-8 text-center text-slate-500">Carregando dados...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo BASE_PATH; ?>/assets/js/common.js"></script>
<script>
async function toggleDone(stravaId, button) {
    const formData = new FormData();
    formData.append('strava_id', stravaId);
    
    try {
        const res = await fetch('?api=toggle_strava_done', {
            method: 'POST',
            body: formData
        }).then(r => r.json());
        
        if (res.success) {
            const icon = button.querySelector('i');
            if (res.done) {
                button.classList.remove('bg-slate-700', 'hover:bg-green-600');
                button.classList.add('bg-green-600', 'hover:bg-green-700');
                icon.classList.remove('fa-circle');
                icon.classList.add('fa-check-circle');
            } else {
                button.classList.remove('bg-green-600', 'hover:bg-green-700');
                button.classList.add('bg-slate-700', 'hover:bg-green-600');
                icon.classList.remove('fa-check-circle');
                icon.classList.add('fa-circle');
            }
        }
    } catch (error) {
        console.error('Erro ao atualizar status:', error);
    }
}

async function loadStrava() {
    const res = await api('get_strava_data');
    
    if (!res.connected) {
        const btnContainer = document.getElementById('strava-connect-btn');
        btnContainer.innerHTML = `<a href="${res.auth_url}" class="bg-[#fc4c02] hover:bg-orange-600 text-white px-6 py-2.5 rounded-xl font-bold shadow-lg transition flex items-center gap-2 transform hover:-translate-y-0.5"><i class="fab fa-strava"></i> Conectar com Strava</a>`;
        btnContainer.classList.remove('hidden');
        
        document.getElementById('strava-stats').classList.add('hidden');
        document.getElementById('strava-list').innerHTML = '<tr><td colspan="7" class="p-10 text-center text-slate-500 flex flex-col items-center gap-2"><i class="fab fa-strava text-4xl text-slate-700"></i><span>Conecte sua conta para ver suas atividades recentes.</span></td></tr>';
    } else {
        document.getElementById('strava-connect-btn').classList.add('hidden');
        document.getElementById('strava-stats').classList.remove('hidden');
        
        document.getElementById('strava-total-km').innerText = res.summary.total_km;
        document.getElementById('strava-total-time').innerText = res.summary.total_time;
        document.getElementById('strava-total-activities').innerText = res.summary.total_activities;
        
        // Renderizar estatísticas mensais
        if (res.monthly && res.monthly.length > 0) {
            document.getElementById('strava-monthly').classList.remove('hidden');
            const monthlyGrid = document.getElementById('monthly-stats-grid');
            monthlyGrid.innerHTML = res.monthly.map(month => {
                const typesList = Object.entries(month.types).map(([type, count]) => 
                    `<span class="text-xs px-2 py-0.5 bg-slate-700/50 rounded text-slate-300">${type}: ${count}</span>`
                ).join(' ');
                
                return `
                    <div class="glass-card p-5 rounded-xl border border-slate-700/50 hover:border-[#fc4c02]/50 transition">
                        <div class="flex justify-between items-start mb-3">
                            <h4 class="font-bold text-white text-lg">${month.label}</h4>
                            <span class="bg-[#fc4c02]/20 text-[#fc4c02] px-2 py-1 rounded text-xs font-bold">${month.count} atividades</span>
                        </div>
                        <p class="text-3xl font-bold text-white mb-3">${month.total_dist_fmt} <span class="text-sm text-slate-400">km</span></p>
                        <div class="flex flex-wrap gap-1">${typesList}</div>
                    </div>
                `;
            }).join('');
        }
        
        document.getElementById('strava-list').innerHTML = res.list.map(a => `
            <tr class="hover:bg-slate-800/30 transition border-b border-slate-700/30 last:border-0">
                <td class="p-5 text-slate-300 font-mono text-xs">${a.date}</td>
                <td class="p-5 font-bold text-white">${a.name}</td>
                <td class="p-5 text-center"><span class="px-2 py-1 rounded border border-slate-600 text-[10px] uppercase font-bold bg-slate-800 text-slate-300">${a.type}</span></td>
                <td class="p-5 text-center font-mono text-[#fc4c02] font-bold">${a.distance} km</td>
                <td class="p-5 text-center text-slate-300 font-mono">${a.time}</td>
                <td class="p-5 text-center text-slate-400"><i class="fas fa-thumbs-up text-[#fc4c02] mr-1"></i> ${a.kudos}</td>
                <td class="p-5 text-center">
                    <button onclick="toggleDone(${a.strava_id}, this)" class="px-3 py-1.5 rounded-lg transition font-bold text-xs ${a.done ? 'bg-green-600 hover:bg-green-700' : 'bg-slate-700 hover:bg-green-600'} text-white">
                        <i class="fas ${a.done ? 'fa-check-circle' : 'fa-circle'} mr-1"></i> ${a.done ? 'Feito' : 'Fazer'}
                    </button>
                </td>
            </tr>
        `).join('');
    }
}

loadStrava();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

