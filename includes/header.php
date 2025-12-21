<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'LifeOS'; ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'%3E%3Cpath fill='%23a855f7' d='M502.6 329.1c-18.1 7.5-38.7 2.9-52.3-11.7l-41-44.4c-8.5 13.5-18.7 25.9-30.2 37.1-57.3 55.7-132.9 86.2-211.6 86.2-13.1 0-26.1-.8-38.9-2.5l-75 91.3c-12.4 15.1-34.2 19.1-51.1 9.3-16.9-9.8-24.8-30.3-18.6-48.9l32.7-98.2c22.9-7.9 44.7-18.3 65.3-31.1 63-39.1 109.6-102.6 127.3-175.6l-54.5-22.7c-19.2-8-28.4-29.7-20.5-48.5s29.7-28.4 48.5-20.5l129.3 53.9c6.9 2.9 13 7.6 17.7 13.5 26.5-14.4 51.4-32 73.9-52.5C481.4 89.3 512 133.7 512 183c0 33.1-11.1 63.6-30.2 88.2l41 44.4c13.6 14.6 18.2 35.3 11.7 53.4-6.4 18.1-22.5 31.3-41.9 34.1zM117.3 215.1c0 16.6 13.5 30.1 30.1 30.1H242l-33.4-100c-3.5-10.6 1.6-22.2 11.7-26.5 10.1-4.3 21.7-1.3 28.5 7.3l80 100.7c5.8 7.3 6.6 17.4 2 25.4-4.6 8-13.2 12.9-22.5 12.9h-58.1l33.4 100c3.5 10.6-1.6 22.2-11.7 26.5-10.1 4.3-21.7 1.3-28.5-7.3l-80-100.7c-5.8-7.3-6.6-17.4-2-25.4 4.6-8 13.2-12.9 22.5-12.9H147.4c-16.6 0-30.1-13.5-30.1-30.1V215.1z'/%3E%3C/svg%3E">
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
