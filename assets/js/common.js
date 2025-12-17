// API Helper
const api = (action, data = null) => {
    const isFormData = data instanceof FormData;
    const options = { method: data ? 'POST' : 'GET' };
    if (data) { 
        if (isFormData) options.body = data; 
        else { 
            options.headers = { 'Content-Type': 'application/json' }; 
            options.body = JSON.stringify(data); 
        } 
    }
    
    return fetch(`?api=${action}`, options).then(async r => {
        const json = await r.json();
        if (!r.ok) {
            console.error("API Error:", json.error || json.message);
            alert("Erro: " + (json.error || json.message || "Erro desconhecido."));
            throw new Error(json.error || json.message || "API error");
        }
        return json;
    }).catch(e => {
        console.error("Fetch Error:", e);
        throw e; 
    });
};

// Format Currency
const formatCurrency = val => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val);

// Modal Functions
function openModal(formId, reset=true) { 
    const overlay = document.getElementById('modal-overlay'); 
    const content = document.getElementById('modal-content'); 
    overlay.classList.remove('hidden'); 
    document.querySelectorAll('.modal-form').forEach(el => el.classList.add('hidden')); 
    const form = document.getElementById(formId); 
    form.classList.remove('hidden'); 
    
    if (formId === 'modal-note') { 
        content.classList.remove('max-w-md'); 
        content.classList.add('max-w-5xl', 'h-[85vh]'); 
    } else if (formId === 'modal-game-admin') { 
        content.classList.remove('max-w-md'); 
        content.classList.add('max-w-4xl'); 
    } else if (formId === 'modal-game-task' || formId === 'modal-game-reward') { 
        content.classList.remove('max-w-4xl'); 
        content.classList.add('max-w-md'); 
    } else { 
        content.classList.remove('max-w-4xl', 'h-[85vh]'); 
        content.classList.add('max-w-md'); 
    } 

    if(reset) resetForm(form); 
}

function closeModal() { 
    document.getElementById('modal-overlay').classList.add('hidden'); 
}

function resetForm(form) { 
    form.reset(); 
    const now = new Date(); 
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset()); 
    form.querySelectorAll('input[type="date"], input[type="datetime-local"]').forEach(input => { 
        input.value = now.toISOString().slice(0, input.type === 'date' ? 10 : 16); 
    }); 
    if(form.querySelector('[name="id"]')) form.querySelector('[name="id"]').value = ''; 
    form.querySelectorAll('button[id^="btn-delete"]').forEach(btn => btn.classList.add('hidden')); 
    
    // Reset specific forms
    if(form.id === 'modal-activity') document.getElementById('activity-modal-title').innerText = "Nova Atividade";
    if(form.id === 'modal-event') document.getElementById('event-modal-title').innerText = "Novo Evento"; 
    if(form.id === 'modal-workout') document.getElementById('workout-modal-title').innerText = "Treino"; 
    if(form.id === 'modal-finance') document.getElementById('finance-modal-title').innerText = "Lançamento";
    if(form.id === 'modal-note') { 
        document.getElementById('note-modal-title').innerText = "Nova Nota"; 
        document.getElementById('btn-delete-note').classList.add('hidden'); 
    }
    if (form.id === 'modal-goal') {
        document.getElementById('goal-modal-title').innerText = "Nova Meta";
        document.getElementById('btn-delete-goal').classList.add('hidden');
    }
}
