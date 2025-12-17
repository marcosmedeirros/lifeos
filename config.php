<?php
// ARQUIVO: config.php

// ===== CONFIGURAÇÃO LOCAL (XAMPP) =====
//$db_host = 'localhost';
//$db_name = 'bancolifestyle'; // Altere para o nome do seu banco local
//$db_user = 'root';
//$db_pass = ''; // XAMPP usa senha vazia por padrão


$db_host = 'localhost';
$db_name = 'u289267434_bancolifestyle';
$db_user = 'u289267434_marcosmedeiros';
$db_pass = 'Zonete@13';


try {
    // Conectar sem o banco para criá-lo se não existir
    $pdo_temp = new PDO("mysql:host=$db_host;charset=utf8mb4", $db_user, $db_pass);
    $pdo_temp->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo_temp->exec("CREATE DATABASE IF NOT EXISTS $db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    unset($pdo_temp);
    
    // Agora conectar ao banco
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // 1. Criação das Tabelas
    $sql_setup = "
    CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100), email VARCHAR(100), password_hash VARCHAR(255));
    CREATE TABLE IF NOT EXISTS activities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT 1,
        title VARCHAR(255),
        category VARCHAR(100),
        day_date DATE,
        period ENUM('morning','afternoon','night') DEFAULT 'morning',
        status TINYINT DEFAULT 0,
        repeat_group VARCHAR(50) DEFAULT NULL
    );
    CREATE TABLE IF NOT EXISTS categories (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100), color VARCHAR(20));
    CREATE TABLE IF NOT EXISTS activity_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT 1,
        name VARCHAR(100),
        color VARCHAR(20) DEFAULT '#3B82F6'
    );
    CREATE TABLE IF NOT EXISTS events (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT DEFAULT 1, group_id VARCHAR(50) DEFAULT NULL, title VARCHAR(255), start_date DATETIME, description TEXT);
    CREATE TABLE IF NOT EXISTS finances (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT DEFAULT 1, type ENUM('income','expense'), amount DECIMAL(10,2), description VARCHAR(255), category_id INT DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, status TINYINT DEFAULT 0);
    CREATE TABLE IF NOT EXISTS finance_categories (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT DEFAULT 1, name VARCHAR(100), color VARCHAR(20) DEFAULT '#3B82F6');
    CREATE TABLE IF NOT EXISTS habits (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), checked_dates TEXT);
    CREATE TABLE IF NOT EXISTS workouts (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT DEFAULT 1, name VARCHAR(255), workout_date DATE, done TINYINT DEFAULT 0);
    CREATE TABLE IF NOT EXISTS goals (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT DEFAULT 1, title VARCHAR(255), difficulty ENUM('facil','media','dificil'), status TINYINT DEFAULT 0);
    CREATE TABLE IF NOT EXISTS notes (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT DEFAULT 1, content TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
    CREATE TABLE IF NOT EXISTS routine_logs (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT DEFAULT 1, log_date DATE UNIQUE, mood VARCHAR(20), sleep_hours DECIMAL(3,1), day_status ENUM('bom','ruim') DEFAULT NULL, content TEXT, photo_path VARCHAR(255), created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
    
    -- TABELAS PARA GAMIFICAÇÃO (PONTOS XP E REGRAS)
    CREATE TABLE IF NOT EXISTS user_settings (
        user_id INT PRIMARY KEY,
        total_xp INT DEFAULT 0
    );
    CREATE TABLE IF NOT EXISTS game_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT 1,
        name VARCHAR(255),
        xp INT,
        icon VARCHAR(50),
        color VARCHAR(20)
    );
    CREATE TABLE IF NOT EXISTS game_rewards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT 1,
        name VARCHAR(255),
        cost INT,
        icon VARCHAR(50),
        color VARCHAR(20)
    );

    -- TABELAS DO STRAVA
    CREATE TABLE IF NOT EXISTS strava_auth (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT 1,
        access_token TEXT,
        refresh_token TEXT,
        expires_at INT,
        athlete_id BIGINT
    );
    
    CREATE TABLE IF NOT EXISTS strava_activities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        strava_id BIGINT UNIQUE,
        name VARCHAR(255),
        type VARCHAR(50),
        distance DECIMAL(10,2), -- metros
        moving_time INT, -- segundos
        start_date DATETIME,
        kudos INT DEFAULT 0
    );
    ";
    $pdo->exec($sql_setup);

    // AUTO-REPAIR (Garante colunas antigas)
    try { $pdo->query("SELECT user_id FROM workouts LIMIT 1"); } catch (Exception $e) { $pdo->exec("ALTER TABLE workouts ADD COLUMN user_id INT DEFAULT 1"); }
    try { $pdo->query("SELECT period FROM activities LIMIT 1"); } catch (Exception $e) { $pdo->exec("ALTER TABLE activities ADD COLUMN period ENUM('morning','afternoon','night') DEFAULT 'morning'"); }
    try { $pdo->query("SELECT repeat_group FROM activities LIMIT 1"); } catch (Exception $e) { $pdo->exec("ALTER TABLE activities ADD COLUMN repeat_group VARCHAR(50) DEFAULT NULL"); }
    try { $pdo->query("SELECT group_id FROM events LIMIT 1"); } catch (Exception $e) { $pdo->exec("ALTER TABLE events ADD COLUMN group_id VARCHAR(50) DEFAULT NULL"); }
    try { $pdo->query("SELECT user_id FROM finance_categories LIMIT 1"); } catch (Exception $e) { $pdo->exec("ALTER TABLE finance_categories ADD COLUMN user_id INT DEFAULT 1"); }
    
    // REPARO DE METAS: Adiciona user_id à tabela goals
    try { $pdo->query("SELECT user_id FROM goals LIMIT 1"); } catch (Exception $e) { $pdo->exec("ALTER TABLE goals ADD COLUMN user_id INT DEFAULT 1"); }
    
    // REPARO DE ROTINA: Remove a coluna gratitude e Adiciona/Verifica day_status
    try { $pdo->query("SELECT gratitude FROM routine_logs LIMIT 1"); $pdo->exec("ALTER TABLE routine_logs DROP COLUMN gratitude"); } catch (Exception $e) { /* Coluna já deletada ou nunca existiu */ }
    try { $pdo->query("SELECT day_status FROM routine_logs LIMIT 1"); } catch (Exception $e) { $pdo->exec("ALTER TABLE routine_logs ADD COLUMN day_status ENUM('bom','ruim') DEFAULT NULL"); }


    // --- AUTENTICAÇÃO: INSERE O USUÁRIO PADRÃO ---
    // Hash para '123456'. IMPORTANTE: O login simples é feito pela comparação '123456' no index.php. 
    // Este hash está aqui apenas por boa prática de DB.
    $hashed_password = password_hash('123456', PASSWORD_BCRYPT); 
    $pdo->prepare("
        INSERT INTO users (id, name, email, password_hash) 
        VALUES (1, 'Marcos Medeiros', 'marcosmedeirros', ?) 
        ON DUPLICATE KEY UPDATE name=VALUES(name), email=VALUES(email), password_hash=VALUES(password_hash)
    ")->execute([$hashed_password]);

    // Garante que a linha de XP exista para o usuário 1
    $pdo->exec("INSERT INTO user_settings (user_id, total_xp) VALUES (1, 0) ON DUPLICATE KEY UPDATE user_id=1");

    // NOTA: O POPULAMENTO INICIAL (INSERT INTO game_tasks / game_rewards) FOI REMOVIDO DAQUI
    
} catch (PDOException $e) {
    // Se o erro for de conexão, vai mostrar esta mensagem
    http_response_code(500);
    die(json_encode(['error' => 'Erro Banco: ' . $e->getMessage()]));
}
?>