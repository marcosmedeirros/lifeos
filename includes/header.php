<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#d4af37">
    <meta name="description" content="LifeOS - Sistema de Gestão de Vida: organize tarefas, finanças, hábitos, metas e mais.">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="LifeOS">
    <title><?php echo $page_title ?? 'LifeOS'; ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none'%3E%3Crect x='3' y='4' width='18' height='18' rx='2' stroke='%23d4af37' stroke-width='2'/%3E%3Cpath d='M3 10h18' stroke='%23d4af37' stroke-width='2'/%3E%3Cpath d='M7 2v4M17 2v4' stroke='%23d4af37' stroke-width='2' stroke-linecap='round'/%3E%3Cpath d='M7 14l1.5 1.5L11 13' stroke='%23d4af37' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3Cpath d='M13 14l1.5 1.5L17 13' stroke='%23d4af37' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3Cpath d='M7 18l1.5 1.5L11 17' stroke='%23d4af37' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E">
    <link rel="manifest" href="<?php echo BASE_PATH; ?>/manifest.json">
    <link rel="apple-touch-icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 180 180'%3E%3Crect fill='%230a0a0a' width='180' height='180'/%3E%3Crect x='30' y='40' width='120' height='120' rx='14' stroke='%23d4af37' stroke-width='6' fill='none'/%3E%3Cpath d='M48 52v28M132 52v28' stroke='%23d4af37' stroke-width='6' stroke-linecap='round'/%3E%3Cpath d='M48 92l10 10L84 80M92 92l10 10L128 80' stroke='%23d4af37' stroke-width='6' stroke-linecap='round' stroke-linejoin='round'/%3E%3Cpath d='M48 122l10 10L84 112' stroke='%23d4af37' stroke-width='6' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={darkMode:'class',theme:{extend:{fontFamily:{sans:['Outfit','sans-serif']},colors:{darkbg:'#0a0a0a',cardbg:'#1a1a1a',primary:'#d4af37',secondary:'#ffffff',strava:'#fc4c02',surface:'#333333'}}}};</script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php require_once __DIR__ . '/paths.php'; echo BASE_PATH; ?>/assets/css/style.css?v=20251220-1">
    <script>const BASE_PATH = '<?php echo BASE_PATH; ?>';</script>
    <style>
        html, body {
            background: #0a0a0a !important;
            color: #ffffff;
            min-height: 100vh;
        }
        main {
            position: relative;
            z-index: 1;
        }
        /* Gradiente dourado para títulos e destaques */
        h1, h2, h3, h4, h5, h6 {
            color: #ffffff;
        }
        .text-transparent.bg-clip-text.bg-gradient-to-r.from-yellow-400.to-orange-400,
        .text-transparent.bg-clip-text.bg-gradient-to-r.from-purple-400.to-pink-400 {
            background: linear-gradient(135deg, #d4af37, #f5deb3) !important;
            -webkit-background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
        }
        button, a.btn, input[type="submit"] {
            transition: all 0.3s ease;
        }
        button:hover, a.btn:hover, input[type="submit"]:hover {
            box-shadow: 0 0 20px rgba(212, 175, 55, 0.4);
        }
    </style>
</head>
<body class="min-h-screen">
