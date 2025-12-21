<?php
// ARQUIVO: notes.php - Página completa de Notas
require_once __DIR__ . '/../includes/auth.php';
// require_login();

// Definir user_id padrão se não existir sessão
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['user_name'] = 'Marcos Medeiros';
}

if (isset($_GET['api'])) {
    $action = $_GET['api'];
    header('Content-Type: application/json');
    
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) $data = $_POST;
    
    try {
        if ($action === 'get_notes') { 
    echo json_encode($pdo->query("SELECT * FROM notes ORDER BY id DESC")->fetchAll()); 
    exit; 
}

if ($action === 'save_note') { 
    if (!empty($data['id'])) {
        // UPDATE (Editar)
        $pdo->prepare("UPDATE notes SET content=? WHERE id=?")->execute([$data['content'], $data['id']]);
    } else {
        // INSERT (Criar)
        $pdo->prepare("INSERT INTO notes (user_id, content) VALUES (1, ?)")->execute([$data['content']]); 
    }
    echo json_encode(['success'=>true]); 
    exit; 
}

if ($action === 'delete_note') { 
    $pdo->prepare("DELETE FROM notes WHERE id=?")->execute([$data['id']]); 
    echo json_encode(['success'=>true]); 
    exit; 
}
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

$page = 'notes';
$page_title = 'Notas - LifeOS';
include __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen w-full">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="flex-1 p-4 md:p-10 content-wrap transition-all duration-300">
        <div class="main-shell">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-3xl font-bold text-white">Notas</h2>
                <button onclick="openModal('modal-note')" class="bg-yellow-500 hover:bg-yellow-400 text-black px-6 py-2 rounded-lg font-bold shadow-lg shadow-yellow-500/20 transition transform hover:-translate-y-0.5">
                    <i class="fas fa-sticky-note mr-2"></i> Criar Nota
                </button>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="notes-list"></div>
        </div>
    </div>
</div>

<div id="modal-overlay" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
    <div id="modal-content" class="modal-glass rounded-2xl p-8 w-full max-w-5xl relative max-h-[90vh] overflow-y-auto h-[85vh] flex flex-col">
        <button onclick="closeModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white transition w-8 h-8 flex items-center justify-center rounded-full hover:bg-slate-800 z-50">
            <i class="fas fa-times text-xl"></i>
        </button>
        
        <form id="modal-note" class="modal-form hidden h-full flex flex-col" onsubmit="submitNote(event)">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-2xl font-bold text-yellow-400" id="note-modal-title">Nota</h3>
                <button type="button" id="btn-delete-note" onclick="deleteNote()" class="hidden text-rose-400 hover:text-rose-300 transition">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <input type="hidden" name="id" id="note-id">
            <div class="flex-1 flex flex-col space-y-4">
                <textarea name="content" id="note-content" class="flex-1 bg-slate-800/50 p-6 rounded-xl border border-slate-700/50 font-medium text-lg leading-relaxed focus:bg-slate-800 transition resize-none" placeholder="Comece a escrever..." required></textarea>
                <button type="submit" class="w-full bg-yellow-500 hover:bg-yellow-400 text-black font-bold py-3 rounded-xl shadow-lg transition">
                    Salvar Nota
                </button>
            </div>
        </form>
    </div>
</div>

<script src="<?php echo BASE_PATH; ?>/assets/js/common.js"></script>
<script>
window.notesData = [];

async function loadNotes() {
    window.notesData = await api('get_notes');
    const c = document.getElementById('notes-list');
    
    if(window.notesData.length === 0) {
        c.innerHTML = '<p class="text-slate-500 col-span-3 text-center py-10 italic">Nenhuma nota por enquanto.</p>';
        return;
    }
    
    c.innerHTML = window.notesData.map(n => 
        `<div onclick="editNote(${n.id})" class="glass-card p-6 rounded-2xl hover:bg-slate-800/50 transition group flex flex-col justify-between min-h-[180px] border border-slate-700/50 cursor-pointer">
            <p class="whitespace-pre-wrap font-medium text-slate-300 text-sm leading-relaxed mb-4 flex-1 line-clamp-custom">${n.content}</p>
            <div class="flex justify-between items-center mt-auto pt-4 border-t border-slate-700/30">
                <span class="text-[10px] text-slate-500 font-mono">${new Date(n.created_at).toLocaleDateString('pt-BR')}</span>
                <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                    <i class="fas fa-pen text-xs text-purple-400 hover:text-white"></i>
                    <button onclick="event.stopPropagation(); deleteNote(${n.id})" class="text-rose-500 hover:text-white transition">
                        <i class="fas fa-trash text-xs"></i>
                    </button>
                </div>
            </div>
        </div>`
    ).join('');
}

function editNote(id) {
    const n = window.notesData.find(i => i.id == id);
    if (!n) return;
    
    resetForm(document.getElementById('modal-note'));
    document.getElementById('note-id').value = n.id;
    document.getElementById('note-content').value = n.content;
    document.getElementById('note-modal-title').innerText = "Editar Nota";
    document.getElementById('btn-delete-note').classList.remove('hidden');
    openModal('modal-note', false);
}

async function submitNote(e) {
    e.preventDefault();
    await api('save_note', Object.fromEntries(new FormData(e.target)));
    closeModal();
    loadNotes();
}

async function deleteNote(id) {
    const noteId = id || document.getElementById('note-id').value;
    if(confirm('Apagar nota?')) {
        await api('delete_note', {id: noteId});
        closeModal();
        loadNotes();
    }
}

const originalOpenModal = window.openModal;
window.openModal = function(formId, reset=true) {
    const overlay = document.getElementById('modal-overlay');
    const content = document.getElementById('modal-content');
    overlay.classList.remove('hidden');
    document.querySelectorAll('.modal-form').forEach(el => el.classList.add('hidden'));
    const form = document.getElementById(formId);
    form.classList.remove('hidden');
    
    if (formId === 'modal-note') {
        content.classList.add('max-w-5xl', 'h-[85vh]');
    }
    
    if(reset) resetForm(form);
};

document.addEventListener('DOMContentLoaded', () => {
    loadNotes();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
