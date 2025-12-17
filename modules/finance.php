<?php
// ARQUIVO: finance.php - Página completa de Finanças
require_once __DIR__ . '/../includes/auth.php';
// require_login();

// Roteador da API
if (isset($_GET['api'])) {
    $action = $_GET['api'];
    header('Content-Type: application/json');
    
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) $data = $_POST;
    
    try {
        // --- CATEGORIAS FINANCEIRAS ---
if ($action === 'get_finance_categories') {
    echo json_encode($pdo->query("SELECT * FROM finance_categories ORDER BY name ASC")->fetchAll());
    exit;
}

if ($action === 'save_finance_category') {
    $pdo->prepare("INSERT INTO finance_categories (user_id, name, color) VALUES (1, ?, ?)")
        ->execute([$data['name'], $data['color']]);
    echo json_encode(['success'=>true]);
    exit;
}

if ($action === 'delete_finance_category') {
    $pdo->prepare("DELETE FROM finance_categories WHERE id = ?")->execute([$data['id']]);
    echo json_encode(['success'=>true]); // <--- CORREÇÃO AQUI
    exit;
}

// --- TRANSAÇÕES ---
if ($action === 'get_finances') {
    $month = $_GET['month'] ?? date('Y-m');
    $catFilter = isset($_GET['category']) && $_GET['category'] !== '' ? $_GET['category'] : null;
    
    // Saldo Geral
    $allTime = $pdo->query("SELECT type, SUM(amount) as total FROM finances GROUP BY type")->fetchAll();
    $totalInc = 0; $totalOut = 0;
    foreach($allTime as $t) {
        if(in_array($t['type'], ['income', 'entrada'])) $totalInc += $t['total'];
        else $totalOut += $t['total'];
    }
    $saldoGeral = $totalInc - $totalOut;

    // Lista do Mês
    $sql = "SELECT f.*, fc.name as cat_name, fc.color as cat_color 
            FROM finances f 
            LEFT JOIN finance_categories fc ON f.category_id = fc.id 
            WHERE f.created_at LIKE ?";
    $params = ["$month%"];
    
    if ($catFilter) {
        $sql .= " AND f.category_id = ?";
        $params[] = $catFilter;
    }
    $sql .= " ORDER BY f.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $list = $stmt->fetchAll();
    
    $inc = 0; $out = 0;
    foreach($list as $i) {
        if(in_array($i['type'], ['income', 'entrada'])) $inc += $i['amount']; 
        else $out += $i['amount']; 
    }
    
    echo json_encode([
        'summary' => ['income'=>$inc, 'outcome'=>$out, 'balance'=>$inc-$out, 'total_balance'=>$saldoGeral],
        'list' => $list
    ]); 
    exit;
}

if ($action === 'save_finance') {
    $catId = !empty($data['category_id']) ? $data['category_id'] : null;
    $type = ($data['type'] === 'entrada' || $data['type'] === 'income') ? 'income' : 'expense';

    if(!empty($data['id'])) {
        $pdo->prepare("UPDATE finances SET type=?, amount=?, description=?, category_id=?, created_at=? WHERE id=?")
            ->execute([$type, $data['amount'], $data['desc'], $catId, $data['date'], $data['id']]);
    } else {
        $pdo->prepare("INSERT INTO finances (user_id, type, amount, description, category_id, created_at, status) VALUES (1, ?, ?, ?, ?, ?, 0)")
            ->execute([$type, $data['amount'], $data['desc'], $catId, $data['date']]);
    }
    echo json_encode(['success'=>true]); 
    exit;
}

if ($action === 'delete_finance') { 
    $pdo->prepare("DELETE FROM finances WHERE id=?")->execute([$data['id']]); 
    echo json_encode(['success'=>true]); // <--- CORREÇÃO AQUI (Faltava isso)
    exit; 
}

if ($action === 'toggle_finance') { 
    $pdo->prepare("UPDATE finances SET status = 1 - status WHERE id=?")->execute([$data['id']]); 
    echo json_encode(['success'=>true]);
    exit; 
}
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// HTML da página
$page = 'finance';
$page_title = 'Finanças - LifeOS';
include __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen w-full">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="flex-1 p-4 md:p-10 content-wrap transition-all duration-300">
        <div class="main-shell">
            <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
                <h2 class="text-3xl font-bold text-white">Controle Financeiro</h2>
                <div class="flex gap-3 items-center flex-wrap justify-end">
                    <select id="finance-filter-category" onchange="loadFinance()" class="bg-slate-800 border-slate-700 text-sm rounded-lg px-4 py-2 focus:ring-2 focus:ring-emerald-500">
                        <option value="">Todas as Categorias</option>
                    </select>
                    <div class="flex items-center bg-slate-800 rounded-lg p-1 border border-slate-700">
                        <button onclick="changeFinanceMonth(-1)" class="w-8 h-8 flex items-center justify-center hover:bg-slate-700 rounded text-slate-400 hover:text-white">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <span id="finance-month-label" class="px-4 font-medium text-sm min-w-[140px] justify-center">...</span>
                        <button onclick="changeFinanceMonth(1)" class="w-8 h-8 flex items-center justify-center hover:bg-slate-700 rounded text-slate-400 hover:text-white">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    <button onclick="openModal('modal-finance')" class="bg-emerald-600 hover:bg-emerald-500 text-white px-6 py-2 rounded-lg font-bold shadow-lg shadow-emerald-900/20 flex items-center gap-2">
                        <i class="fas fa-plus"></i> Lançar
                    </button>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div class="glass-card p-5 rounded-2xl border-b-4 border-blue-500">
                    <p class="text-xs uppercase text-slate-400 font-bold mb-2">Saldo Geral</p>
                    <p class="text-2xl font-bold text-white" id="fin-total-balance">R$ 0,00</p>
                </div>
                <div class="glass-card p-5 rounded-2xl">
                    <p class="text-xs uppercase text-slate-400 font-bold mb-2">Saldo (Mês)</p>
                    <p class="text-2xl font-bold" id="fin-balance">R$ 0,00</p>
                </div>
                <div class="glass-card p-5 rounded-2xl">
                    <div class="flex justify-between mb-2">
                        <p class="text-xs uppercase text-slate-400 font-bold">Entradas</p>
                        <i class="fas fa-arrow-up text-emerald-500"></i>
                    </div>
                    <p class="text-2xl font-bold text-emerald-400" id="fin-income">R$ 0,00</p>
                </div>
                <div class="glass-card p-5 rounded-2xl">
                    <div class="flex justify-between mb-2">
                        <p class="text-xs uppercase text-slate-400 font-bold">Saídas</p>
                        <i class="fas fa-arrow-down text-rose-500"></i>
                    </div>
                    <p class="text-2xl font-bold text-rose-400" id="fin-outcome">R$ 0,00</p>
                </div>
            </div>
            
            <div class="glass-card rounded-2xl overflow-hidden shadow-xl">
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[700px]">
                        <thead class="bg-slate-900/50 text-slate-400 uppercase text-xs font-bold tracking-wider">
                            <tr>
                                <th class="p-5">Data</th>
                                <th class="p-5">Descrição</th>
                                <th class="p-5 text-center">Categoria</th>
                                <th class="p-5 text-center">Tipo</th>
                                <th class="p-5 text-right">Valor</th>
                            </tr>
                        </thead>
                        <tbody id="finance-list" class="divide-y divide-slate-700/50 text-sm font-medium"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<div id="modal-overlay" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
    <div id="modal-content" class="modal-glass rounded-2xl p-8 w-full max-w-md relative max-h-[90vh] overflow-y-auto">
        <button onclick="closeModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white transition w-8 h-8 flex items-center justify-center rounded-full hover:bg-slate-800 z-50">
            <i class="fas fa-times text-xl"></i>
        </button>
        
        <form id="modal-finance" class="modal-form hidden" onsubmit="submitFinance(event)">
            <h3 class="text-2xl font-bold mb-6 text-white bg-clip-text text-transparent bg-gradient-to-r from-emerald-400 to-teal-400" id="finance-modal-title">Lançamento Financeiro</h3>
            <input type="hidden" name="id" id="finance-id">
            <div class="space-y-5">
                <div>
                    <label class="text-sm font-medium text-slate-300 mb-1.5 block">Tipo</label>
                    <select name="type" id="finance-type">
                        <option value="expense">Saída (Gasto)</option>
                        <option value="income">Entrada (Ganho)</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-300 mb-1.5 block">Valor (R$)</label>
                    <input type="number" name="amount" id="finance-amount" step="0.01" placeholder="0,00" required class="text-lg font-mono">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-300 mb-1.5 block">Descrição</label>
                    <input type="text" name="desc" id="finance-desc" placeholder="Ex: Mercado, Salário..." required>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-300 mb-1.5 block">Categoria</label>
                    <div class="flex gap-2">
                        <select name="category_id" id="finance-category-id" class="flex-1">
                            <option value="">Sem Categoria</option>
                        </select>
                        <button type="button" onclick="openModal('modal-finance-category', true)" class="bg-slate-700 hover:bg-slate-600 px-3 rounded-lg text-white transition">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-300 mb-1.5 block">Data e Hora</label>
                    <input type="datetime-local" name="date" id="finance-date" required>
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="submit" class="flex-1 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold py-3 rounded-xl shadow-lg transition">Salvar</button>
                    <button type="button" id="btn-delete-finance" onclick="deleteFinance()" class="hidden bg-rose-500/10 hover:bg-rose-500/20 text-rose-500 hover:text-rose-400 px-4 rounded-xl border border-rose-500/30 transition">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </form>
        
        <div id="modal-finance-category" class="modal-form hidden">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-white">Categorias (Finanças)</h3>
                <button onclick="openModal('modal-finance', false)" class="text-sm text-blue-400 hover:text-blue-300 transition">Voltar</button>
            </div>
            <form onsubmit="submitFinanceCategory(event)" class="mb-6 flex gap-3">
                <input type="text" name="name" placeholder="Nova Categoria" required class="flex-1">
                <input type="color" name="color" value="#3B82F6" class="h-12 w-12 p-1 border-0 rounded-lg cursor-pointer bg-slate-800">
                <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white px-4 rounded-lg transition shadow-lg">
                    <i class="fas fa-plus"></i>
                </button>
            </form>
            <div class="space-y-2 max-h-60 overflow-y-auto no-scrollbar pr-1" id="finance-category-list-modal"></div>
        </div>
    </div>
</div>

<script src="../assets/js/common.js"></script>
<script>
let currentFinanceDate = new Date();
window.financeData = [];

async function loadFinanceCategoriesForFilter() {
    const cats = await api('get_finance_categories');
    document.getElementById('finance-filter-category').innerHTML = '<option value="">Todas as Categorias</option>' + 
        cats.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
}

function changeFinanceMonth(dir) {
    currentFinanceDate.setMonth(currentFinanceDate.getMonth() + dir);
    loadFinance();
}

async function loadFinance() {
    document.getElementById('finance-month-label').innerText = currentFinanceDate.toLocaleString('pt-BR', { month: 'long', year: 'numeric' });
    const ym = `${currentFinanceDate.getFullYear()}-${String(currentFinanceDate.getMonth() + 1).padStart(2, '0')}`;
    const catId = document.getElementById('finance-filter-category').value;
    const res = await fetch(`?api=get_finances&month=${ym}&category=${catId}`).then(r => r.json());
    
    window.financeData = res.list;
    document.getElementById('fin-income').innerText = formatCurrency(res.summary.income);
    document.getElementById('fin-outcome').innerText = formatCurrency(res.summary.outcome);
    
    const bal = document.getElementById('fin-balance');
    bal.innerText = formatCurrency(res.summary.balance);
    bal.className = `text-2xl font-bold ${res.summary.balance >= 0 ? 'text-blue-400' : 'text-red-400'}`;
    
    document.getElementById('fin-total-balance').innerText = formatCurrency(res.summary.total_balance);
    
    document.getElementById('finance-list').innerHTML = res.list.map(f => {
        const isEntry = f.type === 'income' || f.type === 'entrada';
        const catName = f.cat_name || '-';
        const catStyle = f.cat_color ? `border-color:${f.cat_color}; color:${f.cat_color}; background-color:${f.cat_color}10` : 'border-color:#334155; color:#94a3b8';
        const typeLabel = isEntry ? 'Entrada' : 'Saída';
        const typeBadge = isEntry ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border-rose-500/20';
        
        return `<tr onclick="editFinanceRow(${f.id})" class="hover:bg-slate-700/30 transition cursor-pointer group ${f.status==1 ? 'opacity-40 grayscale' : ''}">
            <td class="p-5 text-slate-300 group-hover:text-white transition">${new Date(f.created_at).toLocaleDateString('pt-BR')}</td>
            <td class="p-5 font-medium text-white group-hover:text-purple-400 transition ${f.status==1 ? 'line-through' : ''}">${f.description}</td>
            <td class="p-5 text-center"><span class="text-xs px-2 py-1 rounded border font-bold uppercase tracking-wider" style="${catStyle}">${catName}</span></td>
            <td class="p-5 text-center"><span class="px-2 py-1 rounded text-xs font-bold uppercase border ${typeBadge}">${typeLabel}</span></td>
            <td class="p-5 text-right font-mono font-bold ${isEntry ? 'text-emerald-400' : 'text-slate-200'}">${!isEntry ? '- ' : ''}${formatCurrency(f.amount)}</td>
        </tr>`;
    }).join('') || '<tr><td colspan="5" class="p-8 text-center text-slate-500 italic">Nenhum lançamento encontrado.</td></tr>';
}

async function loadFinanceCategories() {
    const cats = await api('get_finance_categories');
    document.getElementById('finance-category-list-modal').innerHTML = cats.map(c => 
        `<div class="flex justify-between items-center bg-slate-800 p-3 rounded-lg border-l-4 mb-2" style="border-color:${c.color}">
            <span>${c.name}</span>
            <button onclick="deleteFinanceCategory(${c.id})" class="text-rose-500 hover:text-rose-400 transition">
                <i class="fas fa-trash"></i>
            </button>
        </div>`
    ).join('');
    
    document.getElementById('finance-category-id').innerHTML = '<option value="">Sem Categoria</option>' + 
        cats.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
}

async function submitFinanceCategory(e) {
    e.preventDefault();
    await api('save_finance_category', Object.fromEntries(new FormData(e.target)));
    e.target.reset();
    loadFinanceCategories();
    loadFinanceCategoriesForFilter();
}

async function deleteFinanceCategory(id) {
    if(confirm('Apagar categoria?')) {
        await api('delete_finance_category', {id});
        loadFinanceCategories();
        loadFinanceCategoriesForFilter();
    }
}

function editFinanceRow(id) {
    const f = window.financeData.find(i => i.id == id);
    if(!f) return;
    
    resetForm(document.getElementById('modal-finance'));
    document.getElementById('finance-id').value = f.id;
    document.getElementById('finance-type').value = f.type;
    document.getElementById('finance-amount').value = f.amount;
    document.getElementById('finance-desc').value = f.description;
    document.getElementById('finance-category-id').value = f.category_id || "";
    document.getElementById('finance-date').value = f.created_at.replace(' ', 'T').slice(0, 16);
    document.getElementById('finance-modal-title').innerText = "Editar Finança";
    document.getElementById('btn-delete-finance').classList.remove('hidden');
    
    openModal('modal-finance', false);
}

async function submitFinance(e) {
    e.preventDefault();
    await api('save_finance', Object.fromEntries(new FormData(e.target)));
    closeModal();
    loadFinance();
}

async function deleteFinance() {
    if(confirm('Excluir?')) {
        await api('delete_finance', {id: document.getElementById('finance-id').value});
        closeModal();
        loadFinance();
    }
}

const originalOpenModal = window.openModal;
window.openModal = function(formId, reset=true) {
    originalOpenModal(formId, reset);
    if(formId === 'modal-finance' || formId === 'modal-finance-category') {
        loadFinanceCategories();
    }
};

document.addEventListener('DOMContentLoaded', () => {
    loadFinance();
    loadFinanceCategoriesForFilter();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
