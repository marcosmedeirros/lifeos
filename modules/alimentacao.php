<?php
// ARQUIVO: alimentacao.php - Página de Alimentação & Saúde
require_once __DIR__ . '/../includes/auth.php';
require_login(); // Requer login obrigatório

$page = 'alimentacao'; // Para ativar a aba na sidebar

// Caminho dos arquivos de dados
$nutrition_file = __DIR__ . '/../data/nutrition_data.json'; // Diário (score/saúde)
$food_file      = __DIR__ . '/../data/nutrition_food.json'; // Alimentação detalhada

// Criar arquivos se não existirem
if (!file_exists($nutrition_file)) {
    file_put_contents($nutrition_file, json_encode([]));
}
if (!file_exists($food_file)) {
    file_put_contents($food_file, json_encode([]));
}

// Carregar dados
$nutrition_data = json_decode(file_get_contents($nutrition_file), true) ?? [];
$food_data      = json_decode(file_get_contents($food_file), true) ?? [];

// Ordenar por data (mais recente primeiro)
usort($nutrition_data, fn($a, $b) => strtotime($b['data'] ?? '0') - strtotime($a['data'] ?? '0'));
usort($food_data, fn($a, $b) => strtotime($b['data'] ?? '0') - strtotime($a['data'] ?? '0'));

// Processar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'delete') {
        $date_to_delete = $_POST['date'] ?? '';
        $nutrition_data = array_filter($nutrition_data, fn($item) => $item['data'] !== $date_to_delete);
        file_put_contents($nutrition_file, json_encode(array_values($nutrition_data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        usort($nutrition_data, fn($a, $b) => strtotime($b['data'] ?? '0') - strtotime($a['data'] ?? '0'));
        $_SESSION['msg_success'] = "Registro removido com sucesso!";
        header('Location: alimentacao.php');
        exit;
    } elseif ($_POST['action'] === 'add_json') {
        $json_input = $_POST['json_data'] ?? '';
        try {
            $new_entry = json_decode($json_input, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $_SESSION['msg_error'] = "JSON inválido: " . json_last_error_msg();
            } else {
                if (!isset($new_entry['data'])) {
                    $_SESSION['msg_error'] = "O JSON deve conter o campo 'data'";
                } else {
                    // Adicionar novo registro (NÃO substituir existentes do mesmo dia)
                    $nutrition_data[] = $new_entry;
                    file_put_contents($nutrition_file, json_encode(array_values($nutrition_data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    usort($nutrition_data, fn($a, $b) => strtotime($b['data'] ?? '0') - strtotime($a['data'] ?? '0'));
                    $_SESSION['msg_success'] = "Novo registro adicionado com sucesso!";
                }
            }
        } catch (Exception $e) {
            $_SESSION['msg_error'] = "Erro ao processar JSON: " . $e->getMessage();
        }
        header('Location: alimentacao.php');
        exit;
    } elseif ($_POST['action'] === 'add_food_json') {
        $json_input = $_POST['json_food'] ?? '';
        $week_date = $_POST['week_date'] ?? ''; // Data da semana (segunda-feira)
        try {
            $new_entry = json_decode($json_input, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $_SESSION['msg_error'] = "JSON inválido: " . json_last_error_msg();
            } else {
                if (empty($week_date)) {
                    $_SESSION['msg_error'] = "Informe a data da semana (segunda-feira)";
                } else {
                    // Remover entrada com mesma semana se existir
                    $food_data = array_filter($food_data, fn($item) => ($item['semana'] ?? '') !== $week_date);
                    $food_data[] = ['semana' => $week_date, 'dias' => $new_entry];
                    file_put_contents($food_file, json_encode(array_values($food_data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    usort($food_data, fn($a, $b) => strtotime($b['semana'] ?? '0') - strtotime($a['semana'] ?? '0'));
                    $_SESSION['msg_success'] = "Registro de alimentação semanal salvo!";
                }
            }
        } catch (Exception $e) {
            $_SESSION['msg_error'] = "Erro ao processar JSON: " . $e->getMessage();
        }
        header('Location: alimentacao.php');
        exit;
    } elseif ($_POST['action'] === 'delete_food') {
        $week_to_delete = $_POST['week'] ?? '';
        $food_data = array_filter($food_data, fn($item) => ($item['semana'] ?? '') !== $week_to_delete);
        file_put_contents($food_file, json_encode(array_values($food_data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        usort($food_data, fn($a, $b) => strtotime($b['semana'] ?? '0') - strtotime($a['semana'] ?? '0'));
        $_SESSION['msg_success'] = "Registro de alimentação removido!";
        header('Location: alimentacao.php');
        exit;
    }
}

// Formatar data legível
function format_date($date_str) {
    $date = DateTime::createFromFormat('Y-m-d', $date_str);
    if ($date) {
        setlocale(LC_TIME, 'pt_BR.UTF-8');
        return strftime('%d/%m/%Y', $date->getTimestamp());
    }
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
        /* Estilos específicos para cards de nutrição */
        .nutrition-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        @media (min-width: 1200px) {
            .nutrition-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        .tab-buttons {display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;}
        .tab-buttons button {background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.12);color:#fff;padding:10px 14px;border-radius:10px;font-weight:700;cursor:pointer;transition:all .2s ease;}
        .tab-buttons button.active {background:rgba(255,255,255,0.12);border-color:rgba(255,255,255,0.22);}
        .tab-content {display:none;}
        .tab-content.active {display:block;}

        .nutrition-card-small {
            background: rgba(30, 30, 30, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        .nutrition-card-small:hover {
            border-color: rgba(255, 255, 255, 0.3);
            background: rgba(30, 30, 30, 0.95);
        }

        .card-date {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 16px;
            color: #ffffff;
        }

        .card-date-small {
            font-size: 12px;
            color: #999999;
            margin-top: 4px;
        }

        .card-section {
            margin-bottom: 16px;
        }

        .card-section-title {
            font-size: 12px;
            color: #cccccc;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .card-stat {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 6px;
            color: #ddd;
        }

        .card-stat-label {
            color: #999999;
        }

        .card-stat-value {
            font-weight: 500;
            color: #ffffff;
        }

        .badge-small {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
        }

        .badge-true {
            background: rgba(100, 100, 100, 0.2);
            color: #cccccc;
        }

        .badge-false {
            background: rgba(100, 100, 100, 0.2);
            color: #999999;
        }

        .card-coach {
            background: rgba(50, 50, 50, 0.5);
            padding: 10px;
            border-radius: 6px;
            border-left: 3px solid #ffffff;
            font-size: 13px;
            color: #ddd;
            line-height: 1.5;
            margin-top: 12px;
        }

        .btn-delete {
            background: rgba(80, 80, 80, 0.2);
            color: #cccccc;
            border: 1px solid rgba(80, 80, 80, 0.3);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            margin-top: 12px;
            width: 100%;
            transition: all 0.3s ease;
        }

        .btn-delete:hover {
            background: rgba(80, 80, 80, 0.3);
            border-color: rgba(80, 80, 80, 0.5);
            color: #ffffff;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999999;
        }

        .empty-state p {
            font-size: 16px;
        }

        .success-banner {
            background: rgba(100, 100, 100, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #ffffff;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .error-banner {
            background: rgba(100, 100, 100, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #ffffff;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: rgba(20, 20, 20, 0.98);
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 40px;
            border-radius: 16px;
            max-width: 650px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.7);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 20px;
        }

        .modal-header h2 {
            margin: 0;
            color: #ffffff;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .close-modal {
            color: #999999;
            font-size: 32px;
            font-weight: bold;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            line-height: 1;
            transition: all 0.2s ease;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
        }

        .close-modal:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.1);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 12px;
            color: #cccccc;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-weight: 700;
        }

        #json_data {
            min-height: 240px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            resize: vertical;
            background: rgba(15, 15, 15, 0.9);
            border: 2px solid rgba(255, 255, 255, 0.15);
            color: #ffffff;
            padding: 16px;
            border-radius: 10px;
            transition: all 0.3s ease;
            line-height: 1.6;
        }

        #json_data::placeholder {
            color: #666666;
        }

        #json_data:focus {
            outline: none;
            border-color: #ffffff;
            background: rgba(15, 15, 15, 0.95);
            box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.05), 0 0 20px rgba(255, 255, 255, 0.1);
        }

        .btn-add-json {
            background: #ffffff;
            color: #000000;
            border: 2px solid transparent;
            padding: 14px 28px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
            margin-top: 20px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .btn-add-json:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(255, 255, 255, 0.2);
            border-color: #ffffff;
        }

        .btn-add-json:active {
            transform: translateY(-1px);
        }

        .btn-open-modal {
            background: #ffffff;
            color: #000000;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }

        .btn-open-modal:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255, 255, 255, 0.2);
        }

        .help-text {
            color: #999999;
            font-size: 12px;
            margin-top: 10px;
            line-height: 1.6;
        }

        .example-json {
            background: rgba(30, 30, 30, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 14px;
            border-radius: 8px;
            font-size: 12px;
            color: #cccccc;
            font-family: 'Courier New', monospace;
            margin-top: 12px;
            overflow-x: auto;
            line-height: 1.5;
        }
    </style>
    <script>
        // Modal functions - Definidas globalmente para serem acessíveis no onclick
        function openModal() {
            const modal = document.getElementById('addJsonModal');
            if (modal) {
                modal.classList.add('show');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeModal() {
            const modal = document.getElementById('addJsonModal');
            if (modal) {
                modal.classList.remove('show');
                document.body.style.overflow = '';
                const form = document.getElementById('jsonForm');
                if (form) form.reset();
            }
        }
    </script>
</head>
<body class="dark">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="flex-1 p-2 md:p-4 content-wrap transition-all duration-300">
        <div class="main-shell">
            <!-- Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                <div>
                    <h2 class="text-2xl md:text-3xl font-bold text-white">🥗 Alimentação & Saúde</h2>
                    <p class="text-gray-400 text-sm mt-1">Acompanhe seus registros diários</p>
                </div>
                <button class="btn-open-modal" onclick="openModal()">
                    <span>➕</span> Adicionar Registro via JSON
                </button>
            </div>

            <!-- Success Banner -->
            <?php if (isset($_SESSION['msg_success'])): ?>
                <div class="success-banner">
                    ✓ <?= htmlspecialchars($_SESSION['msg_success']) ?>
                </div>
                <?php unset($_SESSION['msg_success']); ?>
            <?php endif; ?>

            <!-- Error Banner -->
            <?php if (isset($_SESSION['msg_error'])): ?>
                <div class="error-banner">
                    ✕ <?= htmlspecialchars($_SESSION['msg_error']) ?>
                </div>
                <?php unset($_SESSION['msg_error']); ?>
            <?php endif; ?>

            <div class="tab-buttons">
                <button class="tab-btn active" data-tab="tab-daily">Registro diário</button>
                <button class="tab-btn" data-tab="tab-food">Alimentação</button>
            </div>

            <div id="tab-daily" class="tab-content active">
            <!-- Modal -->
            <div id="addJsonModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Adicionar Novo Registro</h2>
                        <button class="close-modal" onclick="closeModal()">&times;</button>
                    </div>
                    
                    <form method="POST" id="jsonForm">
                        <input type="hidden" name="action" value="add_json">
                        
                        <div class="form-group">
                            <label for="json_data">Cole seu JSON aqui:</label>
                            <textarea 
                                id="json_data" 
                                name="json_data" 
                                class="form-input" 
                                placeholder='{"data":"2026-01-04","perfil":"Marcos","score_do_dia":8.8,"status":"Update de Hidratação","saude_hormonal":{"tsh_base":2.31,"levoide_38mcg_status":"OK","centrum_status":"Aguardando"},"performance_fisica":{"creatina_scoops":1,"agua_total_litros":2,"meta_agua_litros":3,"treinos":[{"tipo":"Corrida","distancia":"3.9km","kcal":184}]},"nutricao":{"proteina_total_estimada_g":125,"meta_diaria_g":150,"refeicoes":{"cafe":"ovos","almoco":"frango"}},"coach_feedback":"Volume de treino ótimo"}'
                                required></textarea>
                            <div class="help-text">
                                O JSON deve conter pelo menos o campo "data" no formato YYYY-MM-DD.
                            </div>
                            <div class="example-json">
{
  "data": "2026-01-04",
  "perfil": "Marcos Crestani Medeiros",
  "score_do_dia": 8.8,
  "status": "Update de Hidratação",
  "saude_hormonal": {
    "tsh_base": 2.311,
    "levoide_38mcg_status": "OK",
    "b12_status_pg_ml": 318,
    "centrum_status": "Aguardando entrega (07/01)"
  },
  "performance_fisica": {
    "creatina_scoops": 1,
    "agua_total_litros": 2.0,
    "meta_agua_litros": 3.0,
    "treinos": [
      { "tipo": "Funcional Força", "duracao": "27min", "kcal": 173 },
      { "tipo": "Corrida", "distancia": "3.9km", "kcal": 184 }
    ]
  },
  "nutricao": {
    "proteina_total_estimada_g": 125,
    "meta_diaria_g": 150,
    "refeicoes": {
      "cafe": "2 ovos, 2 paes de queijo",
      "almoco": "Risoto (frango + tomate)",
      "tarde": "Bolinho whey (2 scups) + 2 ovos",
      "janta": "Arroz, carne bovina, salsichao"
    }
  },
  "coach_feedback": "Volume de treino excepcional. Esforço extra na agua."
}
                            </div>
                        </div>

                        <button type="submit" class="btn-add-json">Adicionar Registro</button>
                    </form>
                </div>
            </div>

            <!-- Nutrition Grid -->
            <div class="nutrition-grid">
                <?php if (empty($nutrition_data)): ?>
                    <div class="empty-state" style="grid-column: 1/-1;">
                        <p style="font-size: 24px; margin-bottom: 12px;">📭</p>
                        <p>Nenhum registro ainda.</p>
                        <p style="font-size: 13px; margin-top: 8px; color: #6b7280;">Clique em "Adicionar Registro via JSON" para começar</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($nutrition_data as $entry): ?>
                    <div class="nutrition-card-small">
                        <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;">
                            <div class="card-date" style="margin-bottom:0;">
                                <?= htmlspecialchars(format_date($entry['data'] ?? '')) ?>
                                <div class="card-date-small"><?= htmlspecialchars($entry['data'] ?? '') ?></div>
                            </div>
                            <?php if (isset($entry['score_do_dia'])): ?>
                                <div style="text-align:right;">
                                    <div style="font-size:26px;font-weight:800;line-height:1;color:#fff;"><?= htmlspecialchars($entry['score_do_dia']) ?></div>
                                    <div class="card-date-small" style="text-transform:uppercase;letter-spacing:0.6px;">Nota do dia</div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($entry['justificativa_nota'])): ?>
                            <div class="card-coach" style="margin-top:10px;">🧠 <?= htmlspecialchars($entry['justificativa_nota']) ?></div>
                        <?php endif; ?>

                        <?php if (!empty($entry['perfil']) || !empty($entry['status'])): ?>
                            <div class="card-date-small" style="margin-top:6px;color:#b3b3b3;display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
                                <?php if (!empty($entry['perfil'])): ?>
                                    <span>Perfil: <?= htmlspecialchars($entry['perfil']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($entry['status'])): ?>
                                    <span style="padding:4px 10px;border-radius:999px;border:1px solid rgba(255,255,255,0.2);color:#fff;">Status: <?= htmlspecialchars($entry['status']) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Saúde Hormonal -->
                        <?php if (!empty($entry['saude_hormonal']) && is_array($entry['saude_hormonal'])): ?>
                        <div class="card-section">
                            <div class="card-section-title">🩺 Saúde Hormonal</div>
                            <?php foreach ($entry['saude_hormonal'] as $key => $value): ?>
                                <div class="card-stat">
                                    <span class="card-stat-label"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($key))) ?>:</span>
                                    <span class="card-stat-value">
                                        <?php if (is_bool($value)): ?>
                                            <span class="badge-small <?= $value ? 'badge-true' : 'badge-false' ?>"><?= $value ? '✓' : '✗' ?></span>
                                        <?php elseif (is_numeric($value)): ?>
                                            <?= htmlspecialchars($value) ?>
                                        <?php else: ?>
                                            <?= htmlspecialchars($value) ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Micronutrientes -->
                        <?php if (!empty($entry['micronutrientes']) && is_array($entry['micronutrientes'])): ?>
                        <div class="card-section">
                            <div class="card-section-title">💊 Micronutrientes</div>
                            <?php foreach ($entry['micronutrientes'] as $key => $value): ?>
                                <div class="card-stat">
                                    <span class="card-stat-label"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($key))) ?>:</span>
                                    <span class="card-stat-value"><?= is_bool($value) ? ($value ? '✓' : '✗') : htmlspecialchars($value) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Performance Física -->
                        <?php if (!empty($entry['performance_fisica']) && is_array($entry['performance_fisica'])): ?>
                        <div class="card-section">
                            <div class="card-section-title">⚡ Performance Física</div>
                            <?php foreach ($entry['performance_fisica'] as $key => $value): ?>
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

                        <!-- Nutrição -->
                        <?php if (!empty($entry['nutricao']) && is_array($entry['nutricao'])): ?>
                        <div class="card-section">
                            <div class="card-section-title">🥗 Nutrição</div>
                            <?php foreach ($entry['nutricao'] as $key => $value): ?>
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

                        <!-- Coach Feedback -->
                        <?php if (!empty($entry['coach_feedback'])): ?>
                            <div class="card-coach" style="margin-top:10px;">🏅 <?= htmlspecialchars($entry['coach_feedback']) ?></div>
                        <?php endif; ?>

                        <!-- Disciplina Mental -->
                        <?php if (!empty($entry['disciplina_mental']) && is_array($entry['disciplina_mental'])): ?>
                        <div class="card-section">
                            <div class="card-section-title">🧘 Disciplina Mental</div>
                            <?php foreach ($entry['disciplina_mental'] as $key => $value): ?>
                                <div class="card-stat">
                                    <span class="card-stat-label"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($key))) ?>:</span>
                                    <span class="card-stat-value"><?= is_bool($value) ? ($value ? '✓' : '✗') : htmlspecialchars($value) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Análise do Coach -->
                        <?php if (isset($entry['analise_coach'])): ?>
                        <div class="card-coach">
                            👨‍🏫 <?= htmlspecialchars(substr($entry['analise_coach'], 0, 100)) ?><?= strlen($entry['analise_coach']) > 100 ? '...' : '' ?>
                        </div>
                        <?php endif; ?>

                        <!-- Botão Delete -->
                        <form method="POST" style="display:inline; width:100%;" onsubmit="return confirm('Remover este registro?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="date" value="<?= htmlspecialchars($entry['data'] ?? '') ?>">
                            <button type="submit" class="btn-delete">🗑️ Remover</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            </div><!-- tab-daily -->

            <div id="tab-food" class="tab-content">
                <button class="btn-open-modal" onclick="openFoodModal()">
                    <span>➕</span> Adicionar Semana de Alimentação
                </button>

                <!-- Modal Food -->
                <div id="addFoodModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2>Alimentação Semanal</h2>
                            <button class="close-modal" onclick="closeFoodModal()">&times;</button>
                        </div>
                        
                        <form method="POST" id="foodForm">
                            <input type="hidden" name="action" value="add_food_json">
                            <input type="hidden" name="week_date" id="food_week_date">
                            
                            <div class="form-group">
                                <label for="food_week_input">Data da semana (segunda-feira):</label>
                                <input type="date" id="food_week_input" class="form-input" required>
                            </div>

                            <div class="form-group">
                                <label for="json_food">Cole o JSON da semana (array com 7 dias):</label>
                                <textarea 
                                    id="json_food" 
                                    name="json_food" 
                                    class="form-input" 
                                    style="min-height:320px;"
                                    required></textarea>
                                <div class="help-text">
                                    Array com 7 objetos (Segunda a Domingo), cada um com: dia, cafe, almoco, lanche, janta.
                                </div>
                                <div class="example-json" style="font-size:11px;max-height:200px;overflow-y:auto;">
[
  {"dia":"Segunda-feira","cafe":"Omelete de 2 ovos","almoco":"Strogonoff...","lanche":"Sanduíche...","janta":"Strogonoff..."},
  {"dia":"Terça-feira","cafe":"Omelete...","almoco":"Frango...","lanche":"Bolo...","janta":"Frango..."},
  {"dia":"Quarta-feira","cafe":"...","almoco":"...","lanche":"...","janta":"..."},
  {"dia":"Quinta-feira","cafe":"...","almoco":"...","lanche":"...","janta":"..."},
  {"dia":"Sexta-feira","cafe":"...","almoco":"...","lanche":"...","janta":"..."},
  {"dia":"Sábado","cafe":"...","almoco":"...","lanche":"...","janta":"..."},
  {"dia":"Domingo","cafe":"...","almoco":"...","lanche":"...","janta":"..."}
]
                                </div>
                            </div>

                            <button type="submit" class="btn-add-json">Salvar Semana</button>
                        </form>
                    </div>
                </div>

                <div class="nutrition-grid">
                    <?php if (empty($food_data)): ?>
                        <div class="empty-state" style="grid-column: 1/-1;">
                            <p style="font-size: 24px; margin-bottom: 12px;">📭</p>
                            <p>Nenhum registro de alimentação semanal.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($food_data as $weekEntry): ?>
                            <?php 
                                $semana = $weekEntry['semana'] ?? '';
                                $dias = $weekEntry['dias'] ?? [];
                            ?>
                            <!-- Card da semana -->
                            <div class="nutrition-card-small" style="grid-column:1/-1;max-width:100%;">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                                    <div class="card-date">
                                        Semana de <?= htmlspecialchars(format_date($semana)) ?>
                                        <div class="card-date-small"><?= htmlspecialchars($semana) ?></div>
                                    </div>
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
                                                <div><strong style="color:#ccc;">☕ Café:</strong> <?= htmlspecialchars($dia['cafe'] ?? '') ?></div>
                                                <div><strong style="color:#ccc;">🍽️ Almoço:</strong> <?= htmlspecialchars($dia['almoco'] ?? '') ?></div>
                                                <div><strong style="color:#ccc;">🥤 Lanche:</strong> <?= htmlspecialchars($dia['lanche'] ?? '') ?></div>
                                                <div><strong style="color:#ccc;">🌙 Janta:</strong> <?= htmlspecialchars($dia['janta'] ?? '') ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div><!-- tab-food -->
        </div>
    </div>

    <script>
        // Inicializar abas ANTES do DOMContentLoaded
        document.addEventListener('DOMContentLoaded', function() {
            // Tabs - Colocar aqui garante que funcione
            const tabButtons = document.querySelectorAll('.tab-btn');
            const tabs = document.querySelectorAll('.tab-content');
            
            tabButtons.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Remove active de todos
                    tabButtons.forEach(b => b.classList.remove('active'));
                    tabs.forEach(t => t.classList.remove('active'));
                    
                    // Add active ao clicado
                    btn.classList.add('active');
                    const tabId = btn.getAttribute('data-tab');
                    const tabContent = document.getElementById(tabId);
                    if (tabContent) {
                        tabContent.classList.add('active');
                    }
                });
            });
        });
        
        // Modal event listeners
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('addJsonModal');
            
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    closeModal();
                }
            });

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    closeModal();
                }
            });

            const jsonForm = document.getElementById('jsonForm');
            if (jsonForm) {
                jsonForm.addEventListener('submit', function(e) {
                    const jsonInput = document.getElementById('json_data').value.trim();
                    try {
                        JSON.parse(jsonInput);
                    } catch (error) {
                        e.preventDefault();
                        alert('JSON inválido: ' + error.message);
                        return false;
                    }
                });
            }
        });

        // Food modal functions
        function openFoodModal() {
            const modal = document.getElementById('addFoodModal');
            if (modal) {
                modal.classList.add('show');
                document.body.style.overflow = 'hidden';
                // Set current Monday
                const today = new Date();
                const day = today.getDay();
                const diff = today.getDate() - day + (day === 0 ? -6 : 1);
                const monday = new Date(today.setDate(diff));
                const mondayStr = monday.toISOString().split('T')[0];
                document.getElementById('food_week_input').value = mondayStr;
                document.getElementById('food_week_date').value = mondayStr;
            }
        }

        function closeFoodModal() {
            const modal = document.getElementById('addFoodModal');
            if (modal) {
                modal.classList.remove('show');
                document.body.style.overflow = '';
                document.getElementById('foodForm').reset();
            }
        }

        function editFoodWeek(semana, dias) {
            openFoodModal();
            document.getElementById('food_week_input').value = semana;
            document.getElementById('food_week_date').value = semana;
            document.getElementById('json_food').value = JSON.stringify(dias, null, 2);
        }

        // Update hidden field when date changes
        document.addEventListener('DOMContentLoaded', function() {
            const weekInput = document.getElementById('food_week_input');
            if (weekInput) {
                weekInput.addEventListener('change', function() {
                    document.getElementById('food_week_date').value = this.value;
                });
            }

            // Food form validation
            const foodForm = document.getElementById('foodForm');
            if (foodForm) {
                foodForm.addEventListener('submit', function(e) {
                    const jsonInput = document.getElementById('json_food').value.trim();
                    try {
                        const parsed = JSON.parse(jsonInput);
                        if (!Array.isArray(parsed) || parsed.length !== 7) {
                            e.preventDefault();
                            alert('O JSON deve ser um array com exatamente 7 dias.');
                            return false;
                        }
                    } catch (error) {
                        e.preventDefault();
                        alert('JSON inválido: ' + error.message);
                        return false;
                    }
                });
            }

            // Close food modal on escape or outside click
            window.addEventListener('click', function(event) {
                const foodModal = document.getElementById('addFoodModal');
                if (event.target === foodModal) {
                    closeFoodModal();
                }
            });
        });
    </script>
</body>
</html>
