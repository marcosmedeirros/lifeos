<?php
require_once __DIR__ . '/../includes/auth.php';
require_login(); // Requer login obrigatório

// Define user_id da sessão
$user_id = $_SESSION['user_id'];
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

        // 3. ADD TRAINING SCHEDULE (Adicionar Treino Semanal)
        if ($action === 'add_training_json') {
            header('Content-Type: application/json');
            $week_date = $_POST['week_date'] ?? null;
            $dias_json = $_POST['dias_json'] ?? null;
            
            if (!$week_date || !$dias_json) {
                echo json_encode(['success' => false, 'error' => 'Dados incompletos']);
                exit;
            }
            
            $training_file = __DIR__ . '/../data/strava_training.json';
            $training_data = file_exists($training_file) ? json_decode(file_get_contents($training_file), true) : [];
            
            $training_data[] = [
                'semana' => $week_date,
                'dias' => json_decode($dias_json, true),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            file_put_contents($training_file, json_encode($training_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo json_encode(['success' => true]);
            exit;
        }
        
        // 4. DELETE TRAINING WEEK (Deletar Semana de Treino)
        if ($action === 'delete_training') {
            header('Content-Type: application/json');
            $week_date = $_POST['week_date'] ?? null;
            
            if (!$week_date) {
                echo json_encode(['success' => false, 'error' => 'Data da semana não fornecida']);
                exit;
            }
            
            $training_file = __DIR__ . '/../data/strava_training.json';
            if (file_exists($training_file)) {
                $training_data = json_decode(file_get_contents($training_file), true);
                $training_data = array_filter($training_data, function($item) use ($week_date) {
                    return $item['semana'] !== $week_date;
                });
                $training_data = array_values($training_data);
                file_put_contents($training_file, json_encode($training_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
            
            echo json_encode(['success' => true]);
            exit;
        }

        // 5. GET DATA (Para o Frontend)
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
                    <i class="fab fa-strava text-[#fc4c02]"></i> Treinos
                </h2>
                <div id="strava-connect-btn" class="hidden"></div>
            </div>
            
            <div id="strava-stats" class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 hidden">
                <div class="glass-card p-6 rounded-2xl border-l-4 border-[#fc4c02]">
                    <p class="text-xs uppercase text-slate-400 font-bold mb-2">Distância Total (Recente)</p>
                    <p class="text-4xl font-bold text-white"><span id="strava-total-km">0</span> <span class="text-lg text-slate-500">km</span></p>
                </div>
                <div class="glass-card p-6 rounded-2xl border-l-4 border-gray-400">
                    <p class="text-xs uppercase text-slate-400 font-bold mb-2">Tempo em Movimento</p>
                    <p class="text-4xl font-bold text-white" id="strava-total-time">0h 0m</p>
                </div>
                <div class="glass-card p-6 rounded-2xl border-l-4 border-gray-400">
                    <p class="text-xs uppercase text-gray-400 font-bold mb-2">Total de Atividades</p>
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

            <div class="glass-card rounded-2xl overflow-hidden shadow-xl border border-gray-700/40">
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[700px]">
                        <thead class="bg-black/60 text-gray-400 uppercase text-xs font-bold tracking-wider">
                            <tr>
                                <th class="p-5">Data</th>
                                <th class="p-5">Atividade</th>
                                <th class="p-5 text-center">Tipo</th>
                                <th class="p-5 text-center">Distância</th>
                                <th class="p-5 text-center">Tempo</th>
                            </tr>
                        </thead>
                        <tbody id="strava-list" class="divide-y divide-gray-700/40 text-sm font-medium">
                            <tr><td colspan="5" class="p-8 text-center text-gray-500">Carregando dados...</td></tr>
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
        document.getElementById('strava-list').innerHTML = '<tr><td colspan="5" class="p-10 text-center text-gray-500 flex flex-col items-center gap-2"><i class="fab fa-strava text-4xl text-gray-700"></i><span>Conecte sua conta para ver suas atividades recentes.</span></td></tr>';
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
                    <div class="glass-card p-5 rounded-xl border border-gray-700/40 hover:border-[#fc4c02]/50 transition">
                        <div class="flex justify-between items-start mb-3">
                            <h4 class="font-bold text-white text-lg">${month.label}</h4>
                            <span class="bg-[#fc4c02]/20 text-[#fc4c02] px-2 py-1 rounded text-sm font-bold">${month.total_dist_fmt} km</span>
                        </div>
                        <p class="text-3xl font-bold text-white mb-3">${month.count} <span class="text-sm text-gray-400">atividades</span></p>
                        <div class="flex flex-wrap gap-1">${typesList}</div>
                    </div>
                `;
            }).join('');
        }
        
        document.getElementById('strava-list').innerHTML = res.list.map(a => `
            <tr class="hover:bg-black/50 transition border-b border-gray-700/30 last:border-0">
                <td class="p-5 text-gray-300 font-mono text-xs">${a.date}</td>
                <td class="p-5 font-bold text-white">${a.name}</td>
                <td class="p-5 text-center"><span class="px-2 py-1 rounded border border-gray-600 text-[10px] uppercase font-bold bg-black/40 text-gray-200">${a.type}</span></td>
                <td class="p-5 text-center font-mono text-[#fc4c02] font-bold">${a.distance} km</td>
                <td class="p-5 text-center text-gray-300 font-mono">${a.time}</td>
            </tr>
        `).join('');
    }
}

loadStrava();
</script>

<style>
    #training-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 20px;
        margin-top: 20px;
    }
    
    .month-details {
        max-height: 1000px;
        overflow-y: auto;
        transition: max-height 0.3s ease;
    }
    
    .month-details.hidden {
        max-height: 0;
        overflow: hidden;
    }
</style>

<!-- SEÇÃO DE TREINO SEMANAL -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 mt-8 border-t border-gray-700/30">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-white flex items-center gap-3">
            <i class="fas fa-dumbbell text-[#fc4c02]"></i> Treino Semanal
        </h2>
        <button onclick="openTrainingModal()" class="bg-[#fc4c02] hover:bg-orange-600 text-white px-4 py-2 rounded-xl font-bold shadow-lg transition flex items-center gap-2">
            <i class="fas fa-plus"></i> Adicionar Semana
        </button>
    </div>

    <div id="training-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- Cards serão inseridos aqui via JavaScript -->
    </div>
</div>

<!-- Modal de Treino -->
<div id="trainingModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm hidden flex items-center justify-center z-50" onclick="if(event.target === this) closeTrainingModal()">
    <div class="bg-[#12182b] rounded-2xl border border-gray-700/50 p-8 max-w-3xl w-full mx-4 shadow-2xl">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-white">Cadastrar Treino da Semana</h3>
            <button onclick="closeTrainingModal()" class="text-gray-400 hover:text-white text-2xl">&times;</button>
        </div>

        <form id="trainingForm" onsubmit="submitTraining(event)">
            <div class="mb-6">
                <label class="block text-sm font-bold text-white mb-2">Segunda-feira da semana:</label>
                <input type="date" id="training_week_date" required 
                    class="w-full bg-[#0c0f1a] border border-gray-700 rounded-xl px-4 py-3 text-white focus:border-[#fc4c02] focus:outline-none">
            </div>

            <div class="mb-6">
                <label class="block text-sm font-bold text-white mb-2">Treinos da Semana (JSON Array de 7 dias):</label>
                <textarea id="training_dias" required rows="16" placeholder='[
  {
    "dia": "Segunda-feira",
    "foco": "Push (Peito/Ombro/Tríceps)",
    "exercicios": [
      {"nome": "Supino Reto", "series": 4, "reps": 10, "peso_sugerido": "18kg"},
      {"nome": "Desenvolvimento", "series": 3, "reps": 12, "peso_sugerido": "10kg"}
    ]
  },
  ...
]'
                    class="w-full bg-[#0c0f1a] border border-gray-700 rounded-xl px-4 py-3 text-white font-mono text-sm focus:border-[#fc4c02] focus:outline-none"></textarea>
                <p class="text-xs text-gray-400 mt-2">Array com 7 dias: cada dia deve ter "dia", "foco" e "exercicios" (array com nome, series, reps, peso_sugerido)</p>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="flex-1 bg-[#fc4c02] hover:bg-orange-600 text-white font-bold py-3 rounded-xl transition">
                    <i class="fas fa-save mr-2"></i> Salvar
                </button>
                <button type="button" onclick="closeTrainingModal()" class="flex-1 bg-gray-700 hover:bg-gray-600 text-white font-bold py-3 rounded-xl transition">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Funções do Modal de Treino
function openTrainingModal() {
    document.getElementById('trainingModal').classList.remove('hidden');
    document.getElementById('trainingForm').reset();
}

function closeTrainingModal() {
    document.getElementById('trainingModal').classList.add('hidden');
}

function editTrainingWeek(semana, dias) {
    openTrainingModal();
    document.getElementById('training_week_date').value = semana;
    document.getElementById('training_dias').value = JSON.stringify(dias, null, 2);
    
    // Adicionar handler para deletar a semana antiga antes de adicionar nova
    const form = document.getElementById('trainingForm');
    form.dataset.editing = semana;
}

async function submitTraining(e) {
    e.preventDefault();
    
    const weekDate = document.getElementById('training_week_date').value;
    const diasText = document.getElementById('training_dias').value;
    
    // Validar JSON
    try {
        const dias = JSON.parse(diasText);
        if (!Array.isArray(dias) || dias.length !== 7) {
            alert('O JSON deve ser um array com exatamente 7 dias!');
            return;
        }
        
        // Validar estrutura de cada dia
        for (let i = 0; i < dias.length; i++) {
            if (!dias[i].dia || !dias[i].foco || !dias[i].exercicios) {
                alert(`Dia ${i + 1} está incompleto! Cada dia deve ter "dia", "foco" e "exercicios".`);
                return;
            }
            if (!Array.isArray(dias[i].exercicios)) {
                alert(`Dia ${i + 1}: "exercicios" deve ser um array!`);
                return;
            }
        }
    } catch (err) {
        alert('JSON inválido: ' + err.message);
        return;
    }
    
    // Se estiver editando, deletar a semana antiga primeiro
    const form = e.target;
    if (form.dataset.editing) {
        await fetch('?api=delete_training', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `week_date=${encodeURIComponent(form.dataset.editing)}`
        });
        delete form.dataset.editing;
    }
    
    // Adicionar nova semana
    const formData = new FormData();
    formData.append('week_date', weekDate);
    formData.append('dias_json', diasText);
    
    const response = await fetch('?api=add_training_json', {
        method: 'POST',
        body: formData
    });
    
    const result = await response.json();
    
    if (result.success) {
        closeTrainingModal();
        loadTrainingData();
    } else {
        alert('Erro ao salvar: ' + (result.error || 'Desconhecido'));
    }
}

async function deleteTraining(semana) {
    if (!confirm(`Deletar treinos da semana de ${formatDate(semana)}?`)) return;
    
    const formData = new FormData();
    formData.append('week_date', semana);
    
    await fetch('?api=delete_training', {
        method: 'POST',
        body: formData
    });
    
    loadTrainingData();
}

function formatDate(dateStr) {
    const [year, month, day] = dateStr.split('-');
    return `${day}/${month}/${year}`;
}

async function loadTrainingData() {
    try {
        const response = await fetch('../data/strava_training.json');
        const data = await response.json();
        
        const grid = document.getElementById('training-grid');
        
        // Filtrar apenas treinos de 2026
        const data2026 = data.filter(item => item.semana.startsWith('2026'));
        
        if (!data2026 || data2026.length === 0) {
            grid.innerHTML = `
                <div class="col-span-full text-center py-12 text-gray-400">
                    <i class="fas fa-dumbbell text-5xl mb-4 opacity-50"></i>
                    <p>Nenhum treino cadastrado ainda.</p>
                </div>
            `;
            return;
        }
        
        // Agrupar por mês
        const monthlyGroups = {};
        data2026.forEach(item => {
            const monthKey = item.semana.slice(0, 7); // YYYY-MM
            if (!monthlyGroups[monthKey]) {
                monthlyGroups[monthKey] = [];
            }
            monthlyGroups[monthKey].push(item);
        });
        
        // Ordenar meses
        const sortedMonths = Object.keys(monthlyGroups).sort().reverse();
        
        // Contar total de treinos
        const totalWorkouts = data2026.reduce((sum, item) => sum + item.dias.length, 0);
        
        // Montar HTML
        grid.innerHTML = sortedMonths.map(monthKey => {
            const weeks = monthlyGroups[monthKey];
            const [year, month] = monthKey.split('-');
            const monthNames = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
            const monthLabel = monthNames[parseInt(month)] + ' de ' + year;
            
            // Contar exercícios deste mês
            const monthWorkoutCount = weeks.reduce((sum, week) => sum + week.dias.length, 0);
            
            return `
                <div class="glass-card p-6 rounded-2xl border border-gray-700/40 hover:border-[#fc4c02]/50 transition cursor-pointer" onclick="toggleMonthDetails('${monthKey}')">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-xl font-bold text-white mb-1">${monthLabel}</h3>
                            <p class="text-sm text-gray-400">${monthWorkoutCount} treinos • ${weeks.length} semana(s)</p>
                        </div>
                        <div class="text-right">
                            <div class="text-3xl font-bold text-[#fc4c02]">${monthWorkoutCount}</div>
                            <div class="text-xs text-gray-400">treinos</div>
                        </div>
                    </div>
                    
                    <div id="month-${monthKey}" class="month-details hidden">
                        ${weeks.map((week, idx) => {
                            const exerciciosHtml = week.dias.map(d => {
                                const exerciciosCount = (d.exercicios || []).length;
                                return `
                                    <div class="flex items-start gap-3 text-sm py-2 border-b border-gray-800/30 last:border-0">
                                        <i class="fas fa-dumbbell text-[#fc4c02] text-xs mt-1"></i>
                                        <div class="flex-1">
                                            <div class="font-semibold text-gray-200">${d.dia}</div>
                                            <div class="text-xs text-gray-400 mt-1">${d.foco}</div>
                                            <div class="text-xs text-[#fc4c02] mt-1">${exerciciosCount} exercícios</div>
                                        </div>
                                    </div>
                                `;
                            }).join('');
                            
                            return `
                                <div class="border border-gray-700/30 rounded-lg p-4 mb-4 bg-black/20">
                                    <div class="flex items-center justify-between mb-3 pb-3 border-b border-gray-700/30">
                                        <div class="font-bold text-white">Semana de ${formatDate(week.semana)}</div>
                                        <div class="flex gap-2">
                                            <button onclick='event.stopPropagation(); editTrainingWeek("${week.semana}", ${JSON.stringify(week.dias).replace(/'/g, "&apos;")})' 
                                                class="text-blue-400 hover:text-blue-300 text-sm" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick='event.stopPropagation(); deleteTraining("${week.semana}")' 
                                                class="text-red-400 hover:text-red-300 text-sm" title="Deletar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div>${exerciciosHtml}</div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;
        }).join('');
        
        // Adicionar informação de total na parte superior
        const header = document.querySelector('h2') || document.getElementById('training-grid')?.previousElementSibling;
        if (header && !document.getElementById('total-workouts-badge')) {
            const badge = document.createElement('span');
            badge.id = 'total-workouts-badge';
            badge.className = 'bg-[#fc4c02] text-white px-3 py-1 rounded-full text-sm font-bold ml-3';
            badge.innerText = `Total: ${totalWorkouts} treinos`;
            header.appendChild(badge);
        }
        
    } catch (err) {
        console.log('Nenhum dado de treino encontrado ou erro ao carregar:', err);
        document.getElementById('training-grid').innerHTML = `
            <div class="col-span-full text-center py-12 text-gray-400">
                <i class="fas fa-dumbbell text-5xl mb-4 opacity-50"></i>
                <p>Nenhum treino cadastrado ainda.</p>
            </div>
        `;
    }
}

function toggleMonthDetails(monthKey) {
    const details = document.getElementById(`month-${monthKey}`);
    if (details) {
        details.classList.toggle('hidden');
    }
}

// Carregar dados ao iniciar
loadTrainingData();
</script>

