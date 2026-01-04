<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config.php';
require_login();

$user_id = $_SESSION['user_id'] ?? 1;
$page = 'board';

if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    $action = $_GET['api'];
    if ($action === 'upload') {
        $action = 'upload_photo';
    }
    
    try {
        if ($action === 'get_photos') {
            $stmt = $pdo->prepare("SELECT * FROM board_photos WHERE user_id=? ORDER BY photo_date DESC, id DESC");
            $stmt->execute([$user_id]);
            echo json_encode($stmt->fetchAll());
            exit;
        }
        
        if ($action === 'upload_photo') {
            if (empty($_FILES['photo']['name'])) {
                throw new Exception('Nenhum arquivo enviado');
            }
            
            if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Erro no upload: ' . $_FILES['photo']['error']);
            }
            
            $uploadDir = __DIR__ . '/../uploads';
            $boardDir = $uploadDir . '/board';
            
            // Criar diretórios se não existirem
            if (!is_dir($uploadDir)) {
                if (!@mkdir($uploadDir, 0755, true)) {
                    throw new Exception('Não foi possível criar diretório uploads');
                }
            }
            if (!is_dir($boardDir)) {
                if (!@mkdir($boardDir, 0755, true)) {
                    throw new Exception('Não foi possível criar diretório board');
                }
            }
            
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($ext, $allowedExts)) {
                throw new Exception('Formato de arquivo não permitido');
            }
            
            $safe = 'board_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
            $target = $boardDir . '/' . $safe;
            
            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $target)) {
                throw new Exception('Falha ao mover arquivo');
            }
            
            $photoDate = $_POST['photo_date'] ?? date('Y-m-d');
            $rel = BASE_PATH . '/uploads/board/' . $safe;
            $stmt = $pdo->prepare("INSERT INTO board_photos (user_id, photo_date, image_path) VALUES (?,?,?)");
            $stmt->execute([$user_id, $photoDate, $rel]);
            
            echo json_encode(['success' => true]);
            exit;
        }
        
        if ($action === 'delete_photo') {
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $pdo->prepare("SELECT image_path FROM board_photos WHERE id=? AND user_id=?");
            $stmt->execute([$data['id'], $user_id]);
            $photo = $stmt->fetch();
            
            if ($photo && $photo['image_path']) {
                $filePath = __DIR__ . '/..' . str_replace(BASE_PATH, '', $photo['image_path']);
                if (file_exists($filePath)) @unlink($filePath);
            }
            
            $pdo->prepare("DELETE FROM board_photos WHERE id=? AND user_id=?")->execute([$data['id'], $user_id]);
            echo json_encode(['success' => true]);
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
            <div class="text-center mb-10">
                <h2 class="text-3xl font-bold mb-3 text-white">📸 Memory Board</h2>
                <p class="text-gray-400">Uma foto por dia, um ano de memórias</p>
            </div>
            
            <!-- Upload -->
            <div class="glass-card p-6 mb-10">
                <h3 class="text-xl font-bold mb-4 text-white">📷 Adicionar Foto do Dia</h3>
                <form id="upload-form" onsubmit="uploadPhoto(event)" enctype="multipart/form-data">
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold mb-2 text-slate-300">📅 Data</label>
                            <input type="date" name="photo_date" id="photoDate" required class="w-full">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-2 text-slate-300">🖼️ Foto</label>
                            <input type="file" name="photo" accept="image/*" required class="w-full">
                        </div>
                    </div>
                    <button type="submit" class="w-full mt-4 bg-white hover:bg-gray-100 text-black font-bold py-3 rounded-xl shadow-lg transition">📤 Upload Foto</button>
                </form>
            </div>
            
            <!-- Grid Polaroid -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6" id="board-grid"></div>
            
            <div id="board-empty" class="hidden glass-card p-12 text-center text-slate-400">
                <p class="text-6xl mb-4">📷</p>
                <p class="text-xl">Nenhuma foto ainda. Comece hoje!</p>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo BASE_PATH; ?>/assets/js/common.js"></script>
<style>
.polaroid {
    background: #fff;
    padding: 12px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.4);
    transition: transform 0.3s, box-shadow 0.3s;
    position: relative;
}
.polaroid:hover {
    transform: translateY(-5px) rotate(1deg);
    box-shadow: 0 10px 20px rgba(0,0,0,0.5);
}
.polaroid img {
    width: 100%;
    aspect-ratio: 1;
    object-fit: cover;
    display: block;
}
.polaroid-caption {
    color: #000;
    text-align: center;
    padding: 10px 0 5px;
    font-family: 'Courier New', monospace;
    min-height: 50px;
}
.delete-btn {
    position: absolute;
    top: 6px;
    right: 6px;
    background: rgba(220, 38, 38, 0.9);
    color: white;
    border: none;
    border-radius: 50%;
    width: 32px;
    height: 32px;
    cursor: pointer;
    opacity: 0;
    transition: opacity 0.2s;
}
.polaroid:hover .delete-btn {
    opacity: 1;
}
</style>
<script>
let boardPhotos = [];

document.getElementById('photoDate').value = new Date().toISOString().split('T')[0];

async function uploadPhoto(e) {
    e.preventDefault();
    const fd = new FormData(e.target);
    
    const response = await fetch('?api=upload_photo', {
        method: 'POST',
        body: fd
    });
    
    const result = await response.json();
    if (result.success) {
        e.target.reset();
        document.getElementById('photoDate').value = new Date().toISOString().split('T')[0];
        loadBoard();
    } else {
        alert('Erro ao fazer upload');
    }
}

async function deletePhoto(id) {
    if (confirm('Apagar esta foto?')) {
        await api('delete_photo', {id});
        loadBoard();
    }
}

async function loadBoard() {
    boardPhotos = await fetch('?api=get_photos').then(r => r.json());
    const grid = document.getElementById('board-grid');
    const empty = document.getElementById('board-empty');
    
    if (boardPhotos.length === 0) {
        grid.classList.add('hidden');
        empty.classList.remove('hidden');
        return;
    }
    
    grid.classList.remove('hidden');
    empty.classList.add('hidden');
    
    grid.innerHTML = boardPhotos.map(photo => {
        const date = new Date(photo.photo_date + 'T00:00:00');
        const dateStr = date.toLocaleDateString('pt-BR');
        return `
            <div class="polaroid">
                <button onclick="deletePhoto(${photo.id})" class="delete-btn">🗑️</button>
                <img src="${photo.image_path}" alt="Foto do dia ${dateStr}">
                <div class="polaroid-caption">
                    <p class="text-sm text-gray-600">${dateStr}</p>
                </div>
            </div>
        `;
    }).join('');
}

loadBoard();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
