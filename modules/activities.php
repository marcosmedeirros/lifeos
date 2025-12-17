<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$page = 'activities';

if (isset($_GET['api'])) {
    try {
        require_once __DIR__ . '/../config.php';
        $action = $_GET['api'];
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

if ($action === 'get_activities') { 
    $start = $_GET['start'] ?? date('Y-m-d');
    
    // Se o frontend mandar 'end', usa. Se não, pega +6 dias.
    if (isset($_GET['end'])) {
        $end = $_GET['end'];
    } else {
        $end = date('Y-m-d', strtotime($start.'+6 days')); 
    }

    // QUERY OTIMIZADA:
    // 1. Join com categories para pegar a cor (opcional, se sua tabela existir)
    // 2. Ordenação por DATA e depois por PERÍODO cronológico
    $sql = "
        SELECT a.* FROM activities a 
        WHERE user_id = ? AND day_date BETWEEN ? AND ? 
        ORDER BY 
            day_date ASC, 
            FIELD(period, 'morning', 'afternoon', 'night') ASC, 
            id ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['user_id'], $start, $end]);
    
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit; 
}

if ($action === 'save_activity') { 
    $period = $data['period'] ?? 'morning';
    $repeatDays = $data['repeat_days'] ?? [];

    if (!empty($data['id'])) {
        // UPDATE
        $stmt = $pdo->prepare("UPDATE activities SET title=?, category=?, day_date=?, period=? WHERE id=? AND user_id=?");
        $stmt->execute([$data['title'], $data['category'], $data['date'], $period, $data['id'], $_SESSION['user_id']]);
    } else {
        // INSERT
        $groupId = !empty($repeatDays) ? uniqid('act_') : null;
        $stmt = $pdo->prepare("INSERT INTO activities (user_id, title, category, day_date, period, status, repeat_group) VALUES (?, ?, ?, ?, ?, 0, ?)"); 
        $stmt->execute([$_SESSION['user_id'], $data['title'], $data['category'], $data['date'], $period, $groupId]); 
        
        // Lógica de Repetição (Mantive sua lógica original)
        if(!empty($repeatDays)) { 
            $b = new DateTime($data['date']); 
            $b->modify('+1 day'); 
            for($i=0; $i<60; $i++) { 
                if(in_array($b->format('w'), $repeatDays)) {
                    $stmt->execute([$_SESSION['user_id'], $data['title'], $data['category'], $b->format('Y-m-d'), $period, $groupId]); 
                }
                $b->modify('+1 day'); 
            }
        }
    }
    echo json_encode(['success'=>true]); 
    exit; 
}

if ($action === 'toggle_activity') { 
    // Garante que só altera atividades do próprio usuário
    $pdo->prepare("UPDATE activities SET status = 1 - status WHERE id=? AND user_id=?")->execute([$data['id'], $_SESSION['user_id']]); 
    echo json_encode(['success'=>true]); 
    exit; 
}

if ($action === 'delete_activity') { 
    // Apaga a atividade alvo; se for recorrente, remove todas as futuras da mesma série
    $stmt = $pdo->prepare("SELECT repeat_group, day_date FROM activities WHERE id=? AND user_id=?");
    $stmt->execute([$data['id'], $_SESSION['user_id']]);
    $row = $stmt->fetch();

    if ($row && !empty($row['repeat_group'])) {
        $pdo->prepare("DELETE FROM activities WHERE user_id=? AND repeat_group=? AND day_date >= ?")
            ->execute([$_SESSION['user_id'], $row['repeat_group'], $row['day_date']]);
    } else {
        $pdo->prepare("DELETE FROM activities WHERE id=? AND user_id=?")
            ->execute([$data['id'], $_SESSION['user_id']]);
    }

    echo json_encode(['success'=>true]); 
    exit; 
}

if ($action === 'save_activity_category') {
    $pdo->prepare("INSERT INTO activity_categories (user_id, name, color) VALUES (?, ?, ?)")
        ->execute([$_SESSION['user_id'], $data['name'], $data['color']]);
    echo json_encode(['success'=>true]); 
    exit;
}

if ($action === 'get_categories') {
    $stmt = $pdo->prepare("SELECT id, name, color FROM activity_categories WHERE user_id = ? ORDER BY name ASC");
    $stmt->execute([$_SESSION['user_id']]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if ($action === 'delete_category') {
    $pdo->prepare("DELETE FROM activity_categories WHERE id = ? AND user_id = ?")
        ->execute([$data['id'], $_SESSION['user_id']]);
    echo json_encode(['success'=>true]); 
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
            <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
                <h2 class="text-3xl font-bold text-white tracking-tight">Agenda Semanal</h2>
                <div class="flex gap-2 bg-slate-900 rounded-lg p-1 border border-slate-700">
                    <button onclick="changeWeek(-1)" class="w-10 h-10 flex items-center justify-center hover:bg-slate-800 rounded text-slate-400 transition">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <span id="activity-week-label" class="px-4 flex items-center text-sm font-mono text-slate-300 min-w-[140px] justify-center">...</span>
                    <button onclick="changeWeek(1)" class="w-10 h-10 flex items-center justify-center hover:bg-slate-800 rounded text-slate-400 transition">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
            
            <div class="flex gap-3 mb-6">
            <button onclick="openActivityModal('modal-activity')" class="bg-primary hover:bg-purple-600 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-purple-500/20 transition transform hover:-translate-y-0.5 flex items-center gap-2">
                    <i class="fas fa-plus"></i> Nova Tarefa
                </button>
                <button onclick="openActivityModal('modal-category')" class="bg-slate-800 hover:bg-slate-700 text-white px-5 py-2.5 rounded-xl border border-slate-700 transition flex items-center gap-2">
                    <i class="fas fa-tags text-slate-400"></i> Categorias
                </button>
            </div>
            
            <div class="board-scroll pb-6 -mx-4 px-4 md:mx-0 md:px-0">
                <div class="board-grid" id="weekly-board"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Activity -->
<div id="activity-modal-overlay" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4" onclick="closeActivityModal()">
    <div class="modal-glass rounded-2xl p-8 w-full max-w-md relative max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
        <button type="button" onclick="closeActivityModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white transition w-8 h-8 flex items-center justify-center rounded-full hover:bg-slate-800 z-50">
            <i class="fas fa-times text-xl"></i>
        </button>
        <form id="modal-activity" class="modal-form" onsubmit="submitActivity(event)">
            <h3 class="text-2xl font-bold mb-6 text-white bg-clip-text text-transparent bg-gradient-to-r from-purple-400 to-pink-400" id="activity-modal-title">Nova Atividade</h3>
            <input type="hidden" name="id" id="activity-id">
            <div class="space-y-5">
                <input type="text" name="title" id="activity-title" placeholder="O que precisa ser feito?" required class="text-lg">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-slate-300 mb-1.5 block">Categoria</label>
                        <select name="category" id="activity-category" class="category-select">
                            <option value="">Geral</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300 mb-1.5 block">Período</label>
                        <select name="period" id="activity-period">
                            <option value="morning">Manhã</option>
                            <option value="afternoon">Tarde</option>
                            <option value="night">Noite</option>
                        </select>
                    </div>
                </div>
                <input type="date" name="date" id="activity-date" required>
                <div class="bg-slate-800/50 p-4 rounded-xl border border-slate-700/50">
                    <label class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3 block">Repetir (Próx. 60 dias)</label>
                    <div class="flex justify-between gap-1">
                        <input type="checkbox" name="repeat_days[]" value="0" id="r0" class="hidden day-checkbox">
                        <label for="r0" class="w-9 h-9 rounded-full border border-slate-600 flex items-center justify-center cursor-pointer text-xs font-bold hover:border-purple-500 transition hover:bg-purple-500/10">D</label>
                        <input type="checkbox" name="repeat_days[]" value="1" id="r1" class="hidden day-checkbox">
                        <label for="r1" class="w-9 h-9 rounded-full border border-slate-600 flex items-center justify-center cursor-pointer text-xs font-bold hover:border-purple-500 transition hover:bg-purple-500/10">S</label>
                        <input type="checkbox" name="repeat_days[]" value="2" id="r2" class="hidden day-checkbox">
                        <label for="r2" class="w-9 h-9 rounded-full border border-slate-600 flex items-center justify-center cursor-pointer text-xs font-bold hover:border-purple-500 transition hover:bg-purple-500/10">T</label>
                        <input type="checkbox" name="repeat_days[]" value="3" id="r3" class="hidden day-checkbox">
                        <label for="r3" class="w-9 h-9 rounded-full border border-slate-600 flex items-center justify-center cursor-pointer text-xs font-bold hover:border-purple-500 transition hover:bg-purple-500/10">Q</label>
                        <input type="checkbox" name="repeat_days[]" value="4" id="r4" class="hidden day-checkbox">
                        <label for="r4" class="w-9 h-9 rounded-full border border-slate-600 flex items-center justify-center cursor-pointer text-xs font-bold hover:border-purple-500 transition hover:bg-purple-500/10">Q</label>
                        <input type="checkbox" name="repeat_days[]" value="5" id="r5" class="hidden day-checkbox">
                        <label for="r5" class="w-9 h-9 rounded-full border border-slate-600 flex items-center justify-center cursor-pointer text-xs font-bold hover:border-purple-500 transition hover:bg-purple-500/10">S</label>
                        <input type="checkbox" name="repeat_days[]" value="6" id="r6" class="hidden day-checkbox">
                        <label for="r6" class="w-9 h-9 rounded-full border border-slate-600 flex items-center justify-center cursor-pointer text-xs font-bold hover:border-purple-500 transition hover:bg-purple-500/10">S</label>
                    </div>
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="submit" class="flex-1 bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-500 hover:to-pink-500 text-white font-bold py-3 rounded-xl shadow-lg transition">Salvar</button>
                    <button type="button" id="btn-delete-activity" onclick="deleteActivity()" class="hidden bg-rose-500/10 hover:bg-rose-500/20 text-rose-500 hover:text-rose-400 px-4 rounded-xl border border-rose-500/30 transition">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </form>
    
        <form id="modal-category" class="modal-form hidden" onsubmit="submitCategory(event)">
            <h3 class="text-xl font-bold mb-6 text-white">Categorias (Atividades)</h3>
            <div class="mb-6 flex gap-3">
                <input type="text" id="category-name" placeholder="Nome" required class="flex-1">
                <input type="color" id="category-color" value="#BB86FC" class="h-12 w-12 p-1 border-0 rounded-lg cursor-pointer bg-slate-800">
                <button type="button" onclick="addNewCategory()" class="bg-primary hover:bg-purple-400 text-white px-4 rounded-lg transition shadow-lg">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
            <div class="space-y-2 max-h-60 overflow-y-auto no-scrollbar pr-1" id="category-list-modal"></div>
        </form>
    </div>
</div>

<script src="../assets/js/common.js"></script>
<script>
function openActivityModal(formId, reset = true) {
    const overlay = document.getElementById('activity-modal-overlay');
    overlay.classList.remove('hidden');
    document.querySelectorAll('#activity-modal-overlay .modal-form').forEach(el => el.classList.add('hidden'));
    const form = document.getElementById(formId);
    form.classList.remove('hidden');
    if (reset) {
        form.reset();
        // Atualizar títulos
        if (formId === 'modal-activity') {
            document.getElementById('activity-modal-title').innerText = 'Nova Atividade';
            document.getElementById('btn-delete-activity').classList.add('hidden');
            // Data atual
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('activity-date').value = today;
        }
    }
}

function closeActivityModal() {
    document.getElementById('activity-modal-overlay').classList.add('hidden');
}

function addNewCategory() {
    const name = document.getElementById('category-name').value;
    const color = document.getElementById('category-color').value;
    if (!name) return;
    
    fetch('?api=save_activity_category', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, color })
    }).then(r => r.json()).then(res => {
        if (res.success) {
            document.getElementById('category-name').value = '';
            loadCategories();
        }
    });
}
let currentWeekStart = new Date();
currentWeekStart.setDate(currentWeekStart.getDate() - currentWeekStart.getDay());
currentWeekStart.setHours(0,0,0,0);
window.activitiesData = [];

function changeWeek(dir) { 
    currentWeekStart.setDate(currentWeekStart.getDate() + (dir * 7)); 
    loadActivities(); 
}

async function loadActivities() { 
    const s = currentWeekStart.toISOString().split('T')[0]; 
    const res = await fetch(`?api=get_activities&start=${s}`).then(r => r.json()); 
    window.activitiesData = res;

    const dayNames = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
    const periods = {
        morning: { label: 'Manhã', badge: 'bg-amber-500/15 text-amber-300 border border-amber-500/30' },
        afternoon: { label: 'Tarde', badge: 'bg-orange-500/15 text-orange-300 border border-orange-500/30' },
        night: { label: 'Noite', badge: 'bg-indigo-500/15 text-indigo-300 border border-indigo-500/30' }
    };

    const cards = dayNames.map((d, i) => {
        const dt = new Date(currentWeekStart);
        dt.setDate(dt.getDate() + i);
        const ds = dt.toISOString().split('T')[0];
        const dayNum = String(dt.getDate()).padStart(2, '0');
        const tasks = res.filter(t => t.day_date === ds);
        const isToday = new Date().toISOString().split('T')[0] === ds;

        const periodBlocks = Object.entries(periods).map(([key, cfg]) => {
            const list = tasks.filter(t => (t.period || 'morning') === key);
            return `<div class="period-block">
                <div class="period-header">
                    <span class="period-dot ${key}"></span>
                    <span class="period-label">${cfg.label}</span>
                    <span class="pill ${cfg.badge}">${list.length} tarefas</span>
                </div>
                <div class="space-y-2">
                    ${list.map(t => {
                        const cat = t.category ? `<span class="pill cat" style="border-color:${t.cat_color || '#64748b'}; background:${(t.cat_color || '#64748b')}11; color:${t.cat_color || '#cbd5e1'}">${t.category}</span>` : '';
                        return `<div onclick="editActivityRow(${t.id})" id="act-${t.id}" class="task-card ${t.status == 1 ? 'done' : ''}" style="border-left-color:${t.cat_color || '#64748b'}">
                            <div class="task-main">
                                <button class="check" onclick="event.stopPropagation(); toggleActivity(${t.id},'act-${t.id}')">
                                    <i class="fas ${t.status == 1 ? 'fa-check-circle' : 'fa-circle'}"></i>
                                </button>
                                <div class="task-text">
                                    <div class="task-title" title="${t.title}">${t.title}</div>
                                    <div class="task-meta">${cat}</div>
                                </div>
                            </div>
                            <button class="trash" onclick="event.stopPropagation(); deleteActivity(${t.id})"><i class="fas fa-trash"></i></button>
                        </div>`;
                    }).join('') || '<div class="empty-period">Sem tarefas</div>'}
                </div>
            </div>`;
        }).join('');

        return `<div class="day-card ${isToday ? 'today' : ''}">
            <div class="day-head">
                <div>
                    <div class="day-name">${d}</div>
                    <div class="day-num">${dayNum}</div>
                </div>
                <button class="add" onclick="openActivityModal('modal-activity'); document.getElementById('activity-date').value='${ds}'"><i class="fas fa-plus"></i></button>
            </div>
            <div class="day-body">
                ${periodBlocks}
            </div>
        </div>`;
    }).join('');

    document.getElementById('weekly-board').innerHTML = cards;

    const end = new Date(currentWeekStart);
    end.setDate(end.getDate() + 6);
    document.getElementById('activity-week-label').innerText = `${currentWeekStart.getDate()}/${currentWeekStart.getMonth() + 1} - ${end.getDate()}/${end.getMonth() + 1}`; 
}

function editActivityRow(id) { 
    const act = window.activitiesData.find(i => i.id == id); 
    if(!act) return; 
    const form = document.getElementById('modal-activity');
    form.reset();
    document.getElementById('activity-id').value = act.id; 
    document.getElementById('activity-title').value = act.title; 
    document.getElementById('activity-date').value = act.day_date; 
    document.getElementById('activity-period').value = act.period || 'morning'; 
    const catSelect = document.getElementById('activity-category'); 
    for(let i=0; i<catSelect.options.length; i++) { 
        if(catSelect.options[i].text === act.category) { 
            catSelect.selectedIndex = i; 
            break; 
        } 
    } 
    document.getElementById('activity-modal-title').innerText = "Editar Atividade"; 
    document.getElementById('btn-delete-activity').classList.remove('hidden'); 
    openActivityModal('modal-activity', false); 
}

async function submitActivity(e) { 
    e.preventDefault(); 
    const fd=new FormData(e.target); 
    const d=Object.fromEntries(fd); 
    d.repeat_days=[]; 
    document.querySelectorAll('#modal-activity input[name="repeat_days[]"]:checked').forEach(cb=>d.repeat_days.push(cb.value)); 
    await api('save_activity',d); 
    closeActivityModal(); 
    loadActivities(); 
}

async function toggleActivity(id,eid) { 
    const el=document.getElementById(eid); 
    if(el){
        el.classList.toggle('opacity-50'); 
        el.querySelector('.font-bold').classList.toggle('line-through'); 
        el.querySelector('i').classList.toggle('fa-check-circle'); 
        el.querySelector('i').classList.toggle('fa-circle');
    } 
    await api('toggle_activity',{id}); 
}

async function deleteActivity(id) { 
    const actId = id || document.getElementById('activity-id').value; 
    if(confirm('Apagar atividade?')) { 
        await api('delete_activity',{id: actId}); 
        closeActivityModal(); 
        loadActivities(); 
    } 
}

async function loadCategories() { 
    const cats=await api('get_categories'); 
    document.getElementById('category-list-modal').innerHTML=cats.map(c=>`<div class="flex justify-between items-center bg-slate-800 p-3 rounded-lg border-l-4 mb-2" style="border-color:${c.color}">
        <span>${c.name}</span>
        <button onclick="deleteCategory(${c.id})" class="text-red-500 hover:text-white transition">
            <i class="fas fa-trash"></i>
        </button>
    </div>`).join(''); 
    
    document.querySelectorAll('.category-select').forEach(el=>el.innerHTML='<option value="">Sem categoria</option>'+cats.map(c=>`<option value="${c.name}">${c.name}</option>`).join('')); 
}

async function submitCategory(e) { 
    e.preventDefault(); 
    const name = document.getElementById('category-name').value;
    const color = document.getElementById('category-color').value;
    await api('save_activity_category', { name, color }); 
    document.getElementById('category-name').value = '';
    loadCategories(); 
}

async function deleteCategory(id) { 
    if(confirm('Apagar?')) { 
        await api('delete_category', {id}); 
        loadCategories(); 
    } 
}

// CSS para day-checkbox
document.head.insertAdjacentHTML('beforeend', `<style>
.day-checkbox:checked + label {
    background-color: rgba(168, 85, 247, 0.2);
    border-color: rgb(168, 85, 247);
    color: rgb(168, 85, 247);
}

/* New agenda styling */
.day-card {background: linear-gradient(135deg, rgba(15,23,42,0.85), rgba(15,23,42,0.65)); border:1px solid rgba(148,163,184,0.12); border-radius:18px; padding:16px; min-width:280px; box-shadow: 0 10px 40px rgba(0,0,0,0.35); display:flex; flex-direction:column; gap:14px; backdrop-filter: blur(8px); transition:all .2s ease;}
.day-card.today {box-shadow: 0 10px 50px rgba(139,92,246,0.25); border-color: rgba(139,92,246,0.4);}
.day-card:hover {transform: translateY(-3px); border-color: rgba(148,163,184,0.2);}
.day-head {display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid rgba(148,163,184,0.15); padding-bottom:10px;}
.day-name {text-transform:uppercase; letter-spacing:0.08em; font-size:11px; color:#94a3b8; font-weight:700;}
.day-num {font-size:28px; font-weight:800; color:#e2e8f0; line-height:1;}
.day-card.today .day-num {color:#c084fc;}
.day-card.today .day-name {color:#c084fc;}
.day-body {display:flex; flex-direction:column; gap:12px;}
.period-block {background: rgba(15,23,42,0.65); border:1px solid rgba(148,163,184,0.12); border-radius:12px; padding:12px;}
.period-header {display:flex; align-items:center; gap:8px; margin-bottom:10px;}
.period-dot {width:8px; height:8px; border-radius:999px; display:inline-block;}
.period-dot.morning {background:#f59e0b;}
.period-dot.afternoon {background:#fb923c;}
.period-dot.night {background:#6366f1;}
.period-label {font-weight:700; color:#e2e8f0; font-size:12px; text-transform:uppercase; letter-spacing:0.05em;}
.pill {display:inline-flex; align-items:center; gap:6px; padding:4px 8px; border-radius:999px; font-size:11px; font-weight:700;}
.pill.cat {border-width:1px; border-style:solid; background:rgba(148,163,184,0.08); padding:2px 8px; color:#cbd5e1;}
.task-card {position:relative; border:1px solid rgba(148,163,184,0.12); border-left-width:4px; border-radius:12px; padding:10px 12px; background:rgba(15,23,42,0.55); display:flex; align-items:center; gap:10px; justify-content:space-between; transition:all .2s ease;}
.task-card:hover {background:rgba(30,41,59,0.7); border-color:rgba(148,163,184,0.25);}
.task-card.done {opacity:0.45; filter:grayscale(0.3);}
.task-main {display:flex; align-items:flex-start; gap:10px; flex:1; min-width:0;}
.task-text {flex:1; min-width:0;}
.task-title {color:#e2e8f0; font-weight:700; font-size:14px; line-height:1.4; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
.task-card.done .task-title {text-decoration:line-through; color:#cbd5e150;}
.task-meta {display:flex; gap:6px; flex-wrap:wrap; margin-top:4px;}
.check {width:24px; height:24px; border:1px solid rgba(148,163,184,0.25); border-radius:999px; color:#cbd5e1; background:transparent; display:inline-flex; align-items:center; justify-content:center; transition:all .2s ease;}
.check:hover {border-color:#c084fc; color:#c084fc;}
.trash {width:28px; height:28px; border-radius:10px; border:1px solid transparent; color:#94a3b8; background:transparent; display:inline-flex; align-items:center; justify-content:center; opacity:0; transition:all .2s ease;}
.task-card:hover .trash {opacity:1; border-color:rgba(148,163,184,0.2);} 
.trash:hover {color:#f87171; border-color:rgba(248,113,113,0.4); background:rgba(248,113,113,0.08);} 
.add {width:34px; height:34px; border-radius:10px; background:rgba(99,102,241,0.15); color:#c084fc; border:1px solid rgba(99,102,241,0.35); display:inline-flex; align-items:center; justify-content:center; transition:all .2s ease;}
.add:hover {background:rgba(99,102,241,0.25);} 
.empty-period {font-size:12px; color:#94a3b8; opacity:0.7; padding:8px 6px; border:1px dashed rgba(148,163,184,0.3); border-radius:10px; text-align:center;}
</style>`);

loadActivities();
loadCategories();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
