<?php
// ARQUIVO: general.php

// --- Funções Auxiliares ---

if ($action === 'dashboard_stats') {
    // Calcula o início da semana (Domingo) e o final (Sábado)
    $startOfWeek = date('Y-m-d', strtotime('monday this week'));
    $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
    
    // --- 1. Finanças da Semana ---
    $fin_stmt = $pdo->prepare("
        SELECT type, SUM(amount) as total 
        FROM finances 
        WHERE DATE(created_at) BETWEEN ? AND ? 
        GROUP BY type
    ");
    $fin_stmt->execute([$startOfWeek, $endOfWeek]);
    $fin = $fin_stmt->fetchAll();
    
    $inc = 0; $out = 0; 
    foreach($fin as $f) { 
        if(in_array($f['type'], ['income', 'entrada'])) $inc = $f['total']; 
        else $out = $f['total']; 
    }
    
    // --- 2. Pontos Ganhos (XP Total) ---
    // XP total acumulado (usado na Dashboard principal)
    $xp_total = $pdo->query("SELECT total_xp FROM user_settings WHERE user_id = 1")->fetchColumn() ?: 0;
    
    // --- 3. Atividades e Eventos da Semana (para listas) ---
    $events_week_stmt = $pdo->prepare("
        SELECT id, title, start_date FROM events 
        WHERE DATE(start_date) BETWEEN ? AND ? 
        ORDER BY start_date
    ");
    $events_week_stmt->execute([$startOfWeek, $endOfWeek]);
    $events_list = $events_week_stmt->fetchAll();

    $activities_count = $pdo->query("SELECT COUNT(*) FROM activities WHERE day_date = CURDATE() AND status = 0")->fetchColumn();
    $activities_today = $pdo->query("SELECT * FROM activities WHERE day_date = CURDATE() ORDER BY status ASC")->fetchAll();

    
    echo json_encode([
        'income_week' => $inc, 
        'outcome_week' => $out, 
        'xp_total' => $xp_total,
        'activities_count' => $activities_count,
        'events_week' => $events_list,
        'activities_today' => $activities_today
    ]); 
    exit;
}

// Manter o restante (Categorias)
if ($action === 'get_categories') { echo json_encode($pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll()); exit; }
if ($action === 'save_category') { $pdo->prepare("INSERT INTO categories (name, color) VALUES (?, ?)")->execute([$data['name'], $data['color']]); echo json_encode(['success'=>true]); exit; }
if ($action === 'delete_category') { $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$data['id']]); echo json_encode(['success'=>true]); exit; }
?>