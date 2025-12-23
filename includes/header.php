<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#a855f7">
    <meta name="description" content="LifeOS - Sistema de Gestão de Vida: organize tarefas, finanças, hábitos, metas e mais.">
    <title><?php echo $page_title ?? 'LifeOS'; ?></title>
    <link rel="icon" href="<?php require_once __DIR__ . '/paths.php'; echo BASE_PATH; ?>/icons/icon-192.png">
    <link rel="manifest" href="<?php echo BASE_PATH; ?>/manifest.json">
    <link rel="apple-touch-icon" href="<?php echo BASE_PATH; ?>/icons/apple-touch-icon.png">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={darkMode:'class',theme:{extend:{fontFamily:{sans:['Outfit','sans-serif']},colors:{darkbg:'#0f172a',cardbg:'#1e293b',primary:'#a855f7',secondary:'#14b8a6',strava:'#fc4c02',surface:'#334155'}}}};</script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php require_once __DIR__ . '/paths.php'; echo BASE_PATH; ?>/assets/css/style.css?v=20251220-1">
    <script>const BASE_PATH = '<?php echo BASE_PATH; ?>';</script>
    <style>
        html, body {
            background: radial-gradient(circle at top right, #1e1b4b, #0f172a, #020617) !important;
            min-height: 100vh;
        }
        main {
            position: relative;
            z-index: 1;
        }
    </style>
</head>
<body class="min-h-screen">
