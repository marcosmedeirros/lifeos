<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user_id = $_SESSION['user_id'] ?? 1;
$page = 'diary';

if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    $action = $_GET['api'];
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    try {
        if ($action === 'get_entries') {
            $stmt = $pdo->prepare("SELECT * FROM diary_entries WHERE user_id=? ORDER BY entry_date DESC");
            $stmt->execute([$user_id]);
            echo json_encode($stmt->fetchAll());
            exit;
        }
        
        if ($action === 'save_entry') {
            if (!empty($data['id'])) {
                $stmt = $pdo->prepare("UPDATE diary_entries SET entry_date=?, mood=?, content=? WHERE id=? AND user_id=?");
                $stmt->execute([$data['date'], $data['mood'], $data['content'], $data['id'], $user_id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO diary_entries (user_id, entry_date, mood, content) VALUES (?,?,?,?)");
                $stmt->execute([$user_id, $data['date'], $data['mood'], $data['content']]);
            }
            echo json_encode(['success' => true]);
            exit;
        }
        
        if ($action === 'delete_entry') {
            $pdo->prepare("DELETE FROM diary_entries WHERE id=? AND user_id=?")->execute([$data['id'], $user_id]);
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
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-3xl font-bold text-white">📔 Meu Diário</h2>
                <button onclick="openDiaryModal()" class="bg-yellow-500 hover:bg-yellow-600 text-black px-5 py-2 rounded-lg font-bold shadow-lg transition">
                    <i class="fas fa-plus mr-1"></i> Nova Entrada
                </button>
            </div>
            
            <div class="space-y-6" id="diary-list"></div>
        </div>
    </div>
</div>

<!-- Modal Diário -->
<div id="diary-modal-overlay" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4" onclick="closeDiaryModal()">
    <div class="modal-glass rounded-2xl p-8 w-full max-w-2xl relative max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
        <button type="button" onclick="closeDiaryModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white transition">
            <i class="fas fa-times text-xl"></i>
        </button>
        <form id="diary-form" onsubmit="submitDiary(event)">
            <h3 class="text-2xl font-bold mb-6 text-transparent bg-clip-text bg-gradient-to-r from-yellow-400 to-orange-400" id="diary-modal-title">Como foi seu dia?</h3>
            <input type="hidden" name="id" id="diary-id">
            <input type="hidden" name="date" id="diary-date">
            
            <div class="space-y-5">
                <div>
                    <label class="block text-sm font-semibold mb-2 text-slate-300">😊 Qual o seu humor?</label>
                    <div class="flex gap-4 justify-center py-3">
                        <button type="button" onclick="selectMood('😭')" data-mood="😭" class="mood-btn text-5xl opacity-30 hover:opacity-100 transition-all hover:scale-125">😭</button>
                        <button type="button" onclick="selectMood('🙂')" data-mood="🙂" class="mood-btn text-5xl opacity-30 hover:opacity-100 transition-all hover:scale-125">🙂</button>
                        <button type="button" onclick="selectMood('😐')" data-mood="😐" class="mood-btn text-5xl opacity-30 hover:opacity-100 transition-all hover:scale-125">😐</button>
                        <button type="button" onclick="selectMood('😟')" data-mood="😟" class="mood-btn text-5xl opacity-30 hover:opacity-100 transition-all hover:scale-125">😟</button>
                        <button type="button" onclick="selectMood('😭')" data-mood="😭" class="mood-btn text-5xl opacity-30 hover:opacity-100 transition-all hover:scale-125">😭</button>
                    </div>
                    <input type="hidden" name="mood" id="diary-mood" value="🙂">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold mb-2 text-slate-300">📝 Resumo do Dia</label>
                    <textarea name="content" id="diary-content" required class="w-full min-h-[200px]" placeholder="Como foi seu dia?"></textarea>
                </div>
                
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 bg-gradient-to-r from-yellow-500 to-orange-500 hover:from-yellow-400 hover:to-orange-400 text-black font-bold py-3 rounded-xl shadow-lg transition">Salvar Diário</button>
                    <button type="button" id="btn-delete-diary" onclick="deleteDiary()" class="hidden bg-rose-500/10 hover:bg-rose-500/20 text-rose-500 px-4 rounded-xl border border-rose-500/30 transition">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="<?php echo BASE_PATH; ?>/assets/js/common.js"></script>
<script>
let diaryEntries = [];

function selectMood(mood) {
    document.querySelectorAll('.mood-btn').forEach(btn => {
        btn.style.opacity = btn.dataset.mood === mood ? '1' : '0.3';
    });
    document.getElementById('diary-mood').value = mood;
}

function openDiaryModal(entry = null) {
    const overlay = document.getElementById('diary-modal-overlay');
    overlay.classList.remove('hidden');
    
    const today = new Date().toISOString().split('T')[0];
    
    if (entry) {
        document.getElementById('diary-id').value = entry.id;
        document.getElementById('diary-date').value = entry.entry_date;
        document.getElementById('diary-content').value = entry.content;
        selectMood(entry.mood);
        document.getElementById('diary-modal-title').textContent = 'Diário - ' + new Date(entry.entry_date + 'T00:00:00').toLocaleDateString('pt-BR');
        document.getElementById('btn-delete-diary').classList.remove('hidden');
    } else {
        document.getElementById('diary-form').reset();
        document.getElementById('diary-date').value = today;
        selectMood('🙂');
        document.getElementById('diary-modal-title').textContent = 'Diário - ' + new Date().toLocaleDateString('pt-BR');
        document.getElementById('btn-delete-diary').classList.add('hidden');
    }
}

function closeDiaryModal() {
    document.getElementById('diary-modal-overlay').classList.add('hidden');
}

async function submitDiary(e) {
    e.preventDefault();
    const fd = new FormData(e.target);
    const data = Object.fromEntries(fd);
    await api('save_entry', data);
    closeDiaryModal();
    loadDiary();
}

async function deleteDiary() {
    if (confirm('Apagar esta entrada do diário?')) {
        await api('delete_entry', {id: document.getElementById('diary-id').value});
        closeDiaryModal();
        loadDiary();
    }
}

async function loadDiary() {
    diaryEntries = await fetch('?api=get_entries').then(r => r.json());
    const list = document.getElementById('diary-list');
    
    if (diaryEntries.length === 0) {
        list.innerHTML = `<div class="glass-card p-12 text-center text-slate-400">
            <p class="text-6xl mb-4">📔</p>
            <p class="text-xl">Nenhuma entrada ainda. Comece a escrever!</p>
        </div>`;
        return;
    }
    
    list.innerHTML = diaryEntries.map(entry => {
        const date = new Date(entry.entry_date + 'T00:00:00');
        const dateStr = date.toLocaleDateString('pt-BR', {weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'});
        return `
            <article class="glass-card p-6 hover:border-slate-600 transition cursor-pointer" onclick='openDiaryModal(${JSON.stringify(entry)})'>
                <div class="flex items-start gap-4 mb-3">
                    <span class="text-5xl">${entry.mood}</span>
                    <div class="flex-1">
                        <h3 class="font-bold text-xl text-white capitalize">${dateStr}</h3>
                        <p class="text-sm text-slate-400">${entry.created_at ? new Date(entry.created_at).toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'}) : ''}</p>
                    </div>
                </div>
                <p class="text-slate-200 whitespace-pre-wrap leading-relaxed">${entry.content}</p>
            </article>
        `;
    }).join('');
}

loadDiary();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
