<?php
// ARQUIVO: alimentacao.php - Página de Alimentação & Saúde
require_once __DIR__ . '/../includes/auth.php';
require_login();

$page = 'alimentacao';
$user_id = $_SESSION['user_id'] ?? 1;

// Caminho do arquivo (apenas para alimentação semanal)
$food_file = __DIR__ . '/../data/nutrition_food.json';
if (!file_exists($food_file)) {
    file_put_contents($food_file, json_encode([]));
}

// Tabela no banco para registros diários em JSON
function ensureNutritionEntriesTable(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS nutrition_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        day_date DATE NOT NULL,
        payload LONGTEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_user_date (user_id, day_date),
        KEY idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}
ensureNutritionEntriesTable($pdo);

// Carregar dados
$stmtDaily = $pdo->prepare("SELECT id, day_date, payload, created_at FROM nutrition_entries WHERE user_id = ? ORDER BY day_date DESC, created_at DESC");
$stmtDaily->execute([$user_id]);
$nutrition_data = $stmtDaily->fetchAll(PDO::FETCH_ASSOC);

$food_data = json_decode(file_get_contents($food_file), true) ?? [];
usort($food_data, fn($a, $b) => strtotime($b['semana'] ?? '0') - strtotime($a['semana'] ?? '0'));

// Processar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'delete') {
            $entry_id = intval($_POST['entry_id'] ?? 0);
            if ($entry_id > 0) {
                $delStmt = $pdo->prepare("DELETE FROM nutrition_entries WHERE id = ? AND user_id = ?");
                $delStmt->execute([$entry_id, $user_id]);
                $_SESSION['msg_success'] = 'Registro removido com sucesso!';
            } else {
                $_SESSION['msg_error'] = 'ID inválido para exclusão.';
            }
        } elseif ($_POST['action'] === 'add_json') {
            $entry_date = $_POST['entry_date'] ?? '';
            $json_input = $_POST['json_data'] ?? '';
            if (empty($entry_date)) {
                $_SESSION['msg_error'] = 'Selecione a data do registro.';
            } else {
                $decoded = json_decode($json_input, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $_SESSION['msg_error'] = 'JSON inválido: ' . json_last_error_msg();
                } else {
                    if (empty($decoded['data'])) {
                        $decoded['data'] = $entry_date;
                    }
                    $payload = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $ins = $pdo->prepare("INSERT INTO nutrition_entries (user_id, day_date, payload) VALUES (?, ?, ?)");
                    $ins->execute([$user_id, $entry_date, $payload]);
                    $_SESSION['msg_success'] = 'Novo registro salvo!';
                }
            }
        } elseif ($_POST['action'] === 'add_food_json') {
            $json_input = $_POST['json_food'] ?? '';
            $week_date = $_POST['week_date'] ?? '';
            $parsed = json_decode($json_input, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $_SESSION['msg_error'] = 'JSON inválido: ' . json_last_error_msg();
            } elseif (empty($week_date)) {
                $_SESSION['msg_error'] = 'Informe a data da semana (segunda-feira).';
            } else {
                $food_data = array_filter($food_data, fn($item) => ($item['semana'] ?? '') !== $week_date);
                $food_data[] = ['semana' => $week_date, 'dias' => $parsed];
                file_put_contents($food_file, json_encode(array_values($food_data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $_SESSION['msg_success'] = 'Registro de alimentação semanal salvo!';
            }
        } elseif ($_POST['action'] === 'delete_food') {
            $week_to_delete = $_POST['week'] ?? '';
            $food_data = array_filter($food_data, fn($item) => ($item['semana'] ?? '') !== $week_to_delete);
            file_put_contents($food_file, json_encode(array_values($food_data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $_SESSION['msg_success'] = 'Registro de alimentação removido!';
        }
    } catch (Throwable $e) {
        $_SESSION['msg_error'] = 'Erro ao processar requisição: ' . $e->getMessage();
        error_log('[alimentacao] ' . $e->getMessage());
    }

    header('Location: alimentacao.php');
    exit;
}

function format_date($date_str) {
    $date = DateTime::createFromFormat('Y-m-d', $date_str);
    if ($date) return $date->format('d/m/Y');
    return $date_str;
}

$page_title = 'Alimentação • LifeOS';
include __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen w-full">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="flex-1 p-2 md:p-4 content-wrap transition-all duration-300">
        <div class="main-shell">
            <style>
                .nutrition-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(320px,1fr)); gap:20px; margin-top:30px; }
                .tab-buttons {display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;}
                .tab-buttons button {background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.12);color:#fff;padding:10px 14px;border-radius:10px;font-weight:700;cursor:pointer;transition:all .2s ease;}
                .tab-buttons button.active {background:rgba(255,255,255,0.12);border-color:rgba(255,255,255,0.22);}
                .tab-content {display:none;}
                .tab-content.active {display:block;}
                .nutrition-card-small {background:rgba(30,30,30,0.8);border:1px solid rgba(255,255,255,0.15);border-radius:12px;padding:20px;}
                .card-date {font-size:18px;font-weight:bold;margin-bottom:16px;color:#fff;}
                .card-date-small {font-size:12px;color:#999;margin-top:4px;}
                .card-section-title {font-size:12px;color:#ccc;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;margin-bottom:10px;}
                .card-stat {display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;color:#ddd;}
                .card-stat-label {color:#999;}
                .card-stat-value {color:#fff;font-weight:500;}
                .badge-small {display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:500;}
                .badge-true {background:rgba(100,100,100,0.2);color:#ccc;}
                .badge-false {background:rgba(100,100,100,0.2);color:#999;}
                .card-coach {background:rgba(50,50,50,0.5);padding:10px;border-radius:6px;border-left:3px solid #fff;font-size:13px;color:#ddd;line-height:1.5;margin-top:12px;}
                .btn-delete {background:rgba(80,80,80,0.2);color:#ccc;border:1px solid rgba(80,80,80,0.3);padding:6px 12px;border-radius:6px;font-size:12px;cursor:pointer;margin-top:12px;width:100%;}
                .empty-state {text-align:center;padding:60px 20px;color:#999;}
                .success-banner,.error-banner {background:rgba(100,100,100,0.15);border:1px solid rgba(255,255,255,0.2);color:#fff;padding:12px 16px;border-radius:8px;margin-bottom:20px;}
                .modal {display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;background-color:rgba(0,0,0,0.5);} .modal.show {display:flex;align-items:center;justify-content:center;}
                .modal-content {background:rgba(20,20,20,0.98);border:1px solid rgba(255,255,255,0.15);padding:32px;border-radius:16px;max-width:650px;width:90%;max-height:85vh;overflow-y:auto;}
                .modal-header {display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border-bottom:1px solid rgba(255,255,255,0.1);padding-bottom:12px;}
                .close-modal {color:#999;font-size:26px;font-weight:bold;cursor:pointer;background:none;border:none;}
                #json_data {min-height:220px;font-family:'Courier New',monospace;font-size:13px;background:rgba(15,15,15,0.9);border:2px solid rgba(255,255,255,0.15);color:#fff;padding:16px;border-radius:10px;}
                .btn-add-json,.btn-open-modal {background:#fff;color:#000;border:none;padding:12px 24px;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;}
            </style>

            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap:4 mb:6 mb-6">
                <div>
                    <h2 class="text-2xl md:text-3xl font-bold text-white">Alimentação & Saúde</h2>
                    <p class="text-gray-400 text-sm mt-1">Selecione o dia, salve o JSON e visualize.</p>
                </div>
                <button class="btn-open-modal" onclick="openModal()"><span>+</span> Adicionar Registro via JSON</button>
            </div>

            <?php if (isset($_SESSION['msg_success'])): ?><div class="success-banner">✓ <?= htmlspecialchars($_SESSION['msg_success']) ?></div><?php unset($_SESSION['msg_success']); endif; ?>
            <?php if (isset($_SESSION['msg_error'])): ?><div class="error-banner">✕ <?= htmlspecialchars($_SESSION['msg_error']) ?></div><?php unset($_SESSION['msg_error']); endif; ?>

            <div class="tab-buttons">
                <button type="button" class="tab-btn active" data-tab="tab-daily">Registro diário</button>
                <button type="button" class="tab-btn" data-tab="tab-food">Alimentação semanal</button>
            </div>

            <div id="tab-daily" class="tab-content active">
                <div id="addJsonModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2>Adicionar Novo Registro</h2>
                            <button class="close-modal" onclick="closeModal()">&times;</button>
                        </div>
                        <form method="POST" id="jsonForm">
                            <input type="hidden" name="action" value="add_json">
                            <div class="form-group">
                                <label for="entry_date">Data do registro</label>
                                <input type="date" id="entry_date" name="entry_date" class="form-input" required>
                                <div class="help-text">Selecione o dia para o qual o JSON se aplica.</div>
                            </div>
                            <div class="form-group">
                                <label for="json_data">Cole seu JSON aqui:</label>
                                <textarea id="json_data" name="json_data" class="form-input" required></textarea>
                            </div>
                            <button type="submit" class="btn-add-json">Salvar</button>
                        </form>
                    </div>
                </div>

                <div class="nutrition-grid">
                    <?php if (empty($nutrition_data)): ?>
                        <div class="empty-state" style="grid-column:1/-1;">
                            <p style="font-size:24px; margin-bottom:12px;">📭</p>
                            <p>Nenhum registro ainda.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($nutrition_data as $entry): ?>
                            <?php $payload = json_decode($entry['payload'] ?? '[]', true) ?: []; $displayDate = $entry['day_date'] ?? ($payload['data'] ?? ''); ?>
                            <div class="nutrition-card-small">
                                <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;">
                                    <div class="card-date" style="margin-bottom:0;">
                                        <?= htmlspecialchars(format_date($displayDate)) ?>
                                        <div class="card-date-small"><?= htmlspecialchars($displayDate) ?></div>
                                    </div>
                                    <?php if (isset($payload['score_do_dia'])): ?>
                                        <div style="text-align:right;">
                                            <div style="font-size:26px;font-weight:800;line-height:1;color:#fff;">
                                                <?= htmlspecialchars($payload['score_do_dia']) ?></div>
                                            <div class="card-date-small" style="text-transform:uppercase;letter-spacing:0.6px;">Nota do dia</div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($payload['perfil']) || !empty($payload['status'])): ?>
                                    <div class="card-date-small" style="margin-top:6px;color:#b3b3b3;display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
                                        <?php if (!empty($payload['perfil'])): ?><span>Perfil: <?= htmlspecialchars($payload['perfil']) ?></span><?php endif; ?>
                                        <?php if (!empty($payload['status'])): ?><span style="padding:4px 10px;border-radius:999px;border:1px solid rgba(255,255,255,0.2);color:#fff;">Status: <?= htmlspecialchars($payload['status']) ?></span><?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($payload['saude_hormonal']) && is_array($payload['saude_hormonal'])): ?>
                                    <div class="card-section">
                                        <div class="card-section-title">Saúde Hormonal</div>
                                        <?php foreach ($payload['saude_hormonal'] as $key => $value): ?>
                                            <div class="card-stat">
                                                <span class="card-stat-label"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($key))) ?>:</span>
                                                <span class="card-stat-value"><?= is_bool($value) ? ($value ? '✓' : '✗') : htmlspecialchars($value) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($payload['micronutrientes']) && is_array($payload['micronutrientes'])): ?>
                                    <div class="card-section">
                                        <div class="card-section-title">Micronutrientes</div>
                                        <?php foreach ($payload['micronutrientes'] as $key => $value): ?>
                                            <div class="card-stat">
                                                <span class="card-stat-label"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($key))) ?>:</span>
                                                <span class="card-stat-value"><?= is_bool($value) ? ($value ? '✓' : '✗') : htmlspecialchars($value) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($payload['performance_fisica']) && is_array($payload['performance_fisica'])): ?>
                                    <div class="card-section">
                                        <div class="card-section-title">Performance Física</div>
                                        <?php foreach ($payload['performance_fisica'] as $key => $value): ?>
                                            <?php if ($key === 'treinos' && is_array($value)): ?>
                                                <div class="card-stat" style="flex-direction:column;align-items:flex-start;gap:6px;">
                                                    <span class="card-stat-label">Treinos:</span>
                                                    <div style="display:flex;flex-direction:column;gap:6px;width:100%;">
                                                        <?php foreach ($value as $t): ?>
                                                            <div class="item" style="padding:10px;border-radius:10px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);">
                                                                <div class="title" style="font-size:13px;margin-bottom:4px;"><?= htmlspecialchars($t['tipo'] ?? 'Treino') ?></div>
                                                                <div class="meta" style="gap:12px;font-size:12px;">
                                                                    <?php if (!empty($t['duracao_min'])): ?><span>⏱ <?= htmlspecialchars($t['duracao_min']) ?> min</span><?php endif; ?>
                                                                    <?php if (!empty($t['duracao'])): ?><span>⏱ <?= htmlspecialchars($t['duracao']) ?></span><?php endif; ?>
                                                                    <?php if (!empty($t['distancia_km'])): ?><span>📍 <?= htmlspecialchars($t['distancia_km']) ?> km</span><?php endif; ?>
                                                                    <?php if (!empty($t['distancia'])): ?><span>📍 <?= htmlspecialchars($t['distancia']) ?></span><?php endif; ?>
                                                                    <?php if (!empty($t['kcal_total'])): ?><span>🔥 <?= htmlspecialchars($t['kcal_total']) ?> kcal</span><?php endif; ?>
                                                                    <?php if (!empty($t['kcal'])): ?><span>🔥 <?= htmlspecialchars($t['kcal']) ?> kcal</span><?php endif; ?>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="card-stat">
                                                    <span class="card-stat-label"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($key))) ?>:</span>
                                                    <span class="card-stat-value"><?= is_bool($value) ? ($value ? '✓' : '✗') : htmlspecialchars($value) ?></span>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($payload['nutricao']) && is_array($payload['nutricao'])): ?>
                                    <div class="card-section">
                                        <div class="card-section-title">Nutrição</div>
                                        <?php foreach ($payload['nutricao'] as $key => $value): ?>
                                            <?php if ($key === 'refeicoes' && is_array($value)): ?>
                                                <div class="card-stat" style="flex-direction:column;align-items:flex-start;gap:6px;">
                                                    <span class="card-stat-label">Refeições:</span>
                                                    <div style="display:flex;flex-direction:column;gap:4px;width:100%;">
                                                        <?php foreach ($value as $refKey => $refVal): ?>
                                                            <div style="font-size:13px;color:#ddd;display:flex;gap:6px;"><strong style="min-width:60px;text-transform:capitalize;"><?= htmlspecialchars($refKey) ?>:</strong> <span><?= htmlspecialchars($refVal) ?></span></div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="card-stat">
                                                    <span class="card-stat-label"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($key))) ?>:</span>
                                                    <span class="card-stat-value"><?= is_bool($value) ? ($value ? '✓' : '✗') : htmlspecialchars($value) ?></span>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($payload['coach_feedback'])): ?><div class="card-coach" style="margin-top:10px;">🏅 <?= htmlspecialchars($payload['coach_feedback']) ?></div><?php endif; ?>
                                <?php if (!empty($payload['disciplina_mental']) && is_array($payload['disciplina_mental'])): ?>
                                    <div class="card-section">
                                        <div class="card-section-title">Disciplina Mental</div>
                                        <?php foreach ($payload['disciplina_mental'] as $key => $value): ?>
                                            <div class="card-stat">
                                                <span class="card-stat-label"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($key))) ?>:</span>
                                                <span class="card-stat-value"><?= is_bool($value) ? ($value ? '✓' : '✗') : htmlspecialchars($value) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" style="display:inline; width:100%;" onsubmit="return confirm('Remover este registro?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="entry_id" value="<?= htmlspecialchars($entry['id']) ?>">
                                    <button type="submit" class="btn-delete">🗑️ Remover</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div id="tab-food" class="tab-content">
                <button class="btn-open-modal" onclick="openFoodModal()"><span>+</span> Adicionar Semana de Alimentação</button>
                <div id="addFoodModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header"><h2>Alimentação Semanal</h2><button class="close-modal" onclick="closeFoodModal()">&times;</button></div>
                        <form method="POST" id="foodForm">
                            <input type="hidden" name="action" value="add_food_json">
                            <input type="hidden" name="week_date" id="food_week_date">
                            <div class="form-group"><label for="food_week_input">Data da semana (segunda-feira):</label><input type="date" id="food_week_input" class="form-input" required></div>
                            <div class="form-group"><label for="json_food">Cole o JSON da semana (array com 7 dias):</label><textarea id="json_food" name="json_food" class="form-input" style="min-height:260px;" required></textarea></div>
                            <button type="submit" class="btn-add-json">Salvar Semana</button>
                        </form>
                    </div>
                </div>

                <div class="nutrition-grid">
                    <?php if (empty($food_data)): ?>
                        <div class="empty-state" style="grid-column:1/-1;"><p style="font-size:24px; margin-bottom:12px;">📭</p><p>Nenhum registro de alimentação semanal.</p></div>
                    <?php else: ?>
                        <?php foreach ($food_data as $weekEntry): $semana = $weekEntry['semana'] ?? ''; $dias = $weekEntry['dias'] ?? []; ?>
                            <div class="nutrition-card-small" style="grid-column:1/-1;max-width:100%;">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                                    <div class="card-date">Semana de <?= htmlspecialchars(format_date($semana)) ?><div class="card-date-small"><?= htmlspecialchars($semana) ?></div></div>
                                    <div style="display:flex;gap:8px;">
                                        <button class="btn-open-modal" style="padding:8px 16px;margin:0;font-size:13px;" onclick='editFoodWeek(<?= json_encode($semana) ?>, <?= json_encode($dias, JSON_UNESCAPED_UNICODE) ?>)'>✏️ Editar</button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Remover esta semana?')">
                                            <input type="hidden" name="action" value="delete_food">
                                            <input type="hidden" name="week" value="<?= htmlspecialchars($semana) ?>">
                                            <button type="submit" class="btn-delete" style="width:auto;margin:0;padding:8px 16px;">🗑️</button>
                                        </form>
                                    </div>
                                </div>
                                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px;">
                                    <?php foreach ($dias as $dia): ?>
                                        <div class="item" style="padding:14px;border-radius:12px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);">
                                            <div class="title" style="font-size:14px;margin-bottom:10px;color:#fff;"><?= htmlspecialchars($dia['dia'] ?? '') ?></div>
                                            <div style="display:flex;flex-direction:column;gap:6px;font-size:12px;color:#ddd;">
                                                <div><strong style="color:#ccc;">Cafe:</strong> <?= htmlspecialchars($dia['cafe'] ?? '') ?></div>
                                                <div><strong style="color:#ccc;">Almoco:</strong> <?= htmlspecialchars($dia['almoco'] ?? '') ?></div>
                                                <div><strong style="color:#ccc;">Lanche:</strong> <?= htmlspecialchars($dia['lanche'] ?? '') ?></div>
                                                <div><strong style="color:#ccc;">Janta:</strong> <?= htmlspecialchars($dia['janta'] ?? '') ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openModal(){const m=document.getElementById('addJsonModal');if(m){m.classList.add('show');document.body.style.overflow='hidden';}}
function closeModal(){const m=document.getElementById('addJsonModal');if(m){m.classList.remove('show');document.body.style.overflow='';const f=document.getElementById('jsonForm');if(f)f.reset();}}
function openFoodModal(){const m=document.getElementById('addFoodModal');if(m){m.classList.add('show');document.body.style.overflow='hidden';const today=new Date();const day=today.getDay();const diff=today.getDate()-day+(day===0?-6:1);const monday=new Date(today.setDate(diff));const mondayStr=monday.toISOString().split('T')[0];document.getElementById('food_week_input').value=mondayStr;document.getElementById('food_week_date').value=mondayStr;}}
function closeFoodModal(){const m=document.getElementById('addFoodModal');if(m){m.classList.remove('show');document.body.style.overflow='';const f=document.getElementById('foodForm');if(f)f.reset();}}
function editFoodWeek(semana,dias){openFoodModal();document.getElementById('food_week_input').value=semana;document.getElementById('food_week_date').value=semana;document.getElementById('json_food').value=JSON.stringify(dias,null,2);}

document.addEventListener('DOMContentLoaded',()=>{
    const tabButtons=document.querySelectorAll('.tab-btn');
    const tabs=document.querySelectorAll('.tab-content');
    tabButtons.forEach(btn=>btn.addEventListener('click',e=>{e.preventDefault();tabButtons.forEach(b=>b.classList.remove('active'));tabs.forEach(t=>t.classList.remove('active'));btn.classList.add('active');const tabId=btn.getAttribute('data-tab');const tab=document.getElementById(tabId);if(tab){tab.classList.add('active');}}));

    const modal=document.getElementById('addJsonModal');
    window.addEventListener('click',e=>{if(e.target===modal) closeModal();});
    document.addEventListener('keydown',e=>{if(e.key==='Escape') closeModal();});
    const jsonForm=document.getElementById('jsonForm');
    if(jsonForm){jsonForm.addEventListener('submit',e=>{try{JSON.parse(document.getElementById('json_data').value.trim());}catch(err){e.preventDefault();alert('JSON inválido: '+err.message);}});}

    const weekInput=document.getElementById('food_week_input');
    if(weekInput){weekInput.addEventListener('change',function(){document.getElementById('food_week_date').value=this.value;});}
    const foodForm=document.getElementById('foodForm');
    if(foodForm){foodForm.addEventListener('submit',e=>{try{const parsed=JSON.parse(document.getElementById('json_food').value.trim());if(!Array.isArray(parsed)||parsed.length!==7){e.preventDefault();alert('O JSON deve ser um array com exatamente 7 dias.');}}catch(err){e.preventDefault();alert('JSON inválido: '+err.message);}});}
    const foodModal=document.getElementById('addFoodModal');
    window.addEventListener('click',e=>{if(e.target===foodModal) closeFoodModal();});
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
