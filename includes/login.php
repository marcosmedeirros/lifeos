<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - LifeOS</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={darkMode:'class',theme:{extend:{fontFamily:{sans:['Outfit','sans-serif']}}}};</script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: radial-gradient(circle at top right, #1e1b4b, #0f172a, #020617); color: #e2e8f0; font-family: 'Outfit', sans-serif; min-height: 100vh; }
        .modal-glass { background: rgba(15, 23, 42, 0.98); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.1); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7); }
        input { background-color: #1e293b; border: 1px solid #334155; color: white; padding: 12px; border-radius: 10px; width: 100%; transition: all 0.2s; }
        input:focus { outline: none; border-color: #a855f7; box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.2); background-color: #0f172a; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="modal-glass p-8 w-full max-w-sm rounded-2xl shadow-2xl">
        <div class="mb-6 text-center">
            <div class="w-12 h-12 mx-auto rounded-xl bg-gradient-to-tr from-purple-600 to-blue-500 flex items-center justify-center mb-3 shadow-lg shadow-purple-500/20">
                <i class="fas fa-lock text-white text-xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-white">Login LifeOS</h1>
            <p class="text-slate-400 text-sm">Acesso restrito. Use suas credenciais.</p>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="login" value="1">
            <?php if (isset($GLOBALS['login_error']) && $GLOBALS['login_error']): ?>
                <div class="bg-rose-500/20 text-rose-300 border border-rose-500 rounded-lg p-3 mb-4 text-sm font-medium">
                    <?php echo htmlspecialchars($GLOBALS['login_error']); ?>
                </div>
            <?php endif; ?>
            
            <div class="space-y-4">
                <input type="text" name="username" placeholder="Usuário" required autocomplete="username">
                <input type="password" name="password" placeholder="Senha" required autocomplete="current-password">
                <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white px-5 py-3 rounded-xl font-bold shadow-lg shadow-purple-500/30 transition transform hover:-translate-y-0.5">
                    Acessar Dashboard
                </button>
            </div>
        </form>
    </div>
</body>
</html>
