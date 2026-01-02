const tabs = document.querySelectorAll('.tab');
const panels = document.querySelectorAll('.tab-panel');
const syncStatus = document.getElementById('sync-status');

function setActiveTab(tabId) {
  tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === tabId));
  panels.forEach(p => p.classList.toggle('active', p.id === tabId));
}

tabs.forEach(tab => tab.addEventListener('click', () => setActiveTab(tab.dataset.tab)));

document.addEventListener('DOMContentLoaded', () => {
  initDefaults();
  hookQuickActions();
  loadEvents();
  loadActivities();
  loadHabits();
});

function initDefaults() {
  const now = new Date();
  const localDatetime = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0,16);
  const localDate = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0,10);
  document.getElementById('quick-datetime').value = localDatetime;
  document.getElementById('quick-activity-date').value = localDate;
  document.getElementById('events-month').value = now.toISOString().slice(0,7);
  document.getElementById('activities-date').value = localDate;
  document.getElementById('habits-month').value = now.toISOString().slice(0,7);
}

function setStatus(msg) { syncStatus.textContent = msg; }

async function api(url, opts={}) {
  try {
    const res = await fetch(url, { credentials: 'same-origin', ...opts });
    const text = await res.text();

    // Se veio HTML (provável redirect para login), manda para login
    const isHtml = /^\s*<!doctype html/i.test(text) || /^\s*<html/i.test(text);
    if (isHtml) {
      setStatus('Login necessário');
      const redirect = encodeURIComponent(window.location.pathname + window.location.search);
      window.location.href = `../login.php?redirect=${redirect}`;
      return { error: 'login_required', body: text.slice(0,200) };
    }

    if (!res.ok) {
      const snippet = text.slice(0, 200);
      throw new Error(`${res.status} ${res.statusText} | ${snippet}`);
    }

    try { return JSON.parse(text); } catch (err) {
      console.error('Resposta não JSON:', text);
      throw new Error('Resposta inválida');
    }
  } catch (err) {
    setStatus('Erro');
    console.error(err);
    return { error: err.message };
  }
}

// EVENTS (Google Agenda)
async function loadEvents() {
  const ym = document.getElementById('events-month').value || new Date().toISOString().slice(0,7);
  const list = document.getElementById('events-list');
  list.innerHTML = 'Carregando...';
  const data = await api('../modules/google_agenda.php?api=list_events');
  if (!data || data.error) { list.innerHTML = `<div class="item">Erro ao carregar${data?.error ? ': ' + data.error : ''}.</div>`; return; }
  const events = (data.events || []).filter(e => (e.start_date || '').startsWith(ym));
  renderEvents(events);
}

function renderEvents(events) {
  const list = document.getElementById('events-list');
  if (!events.length) { list.innerHTML = '<div class="item">Sem eventos.</div>'; return; }
  list.innerHTML = events.map(ev => {
    const dt = ev.start_date?.replace(' ', 'T');
    return `<div class="item">
      <div class="title">${ev.title || 'Sem título'}</div>
      <div class="meta"><span>${dt ? new Date(dt).toLocaleString('pt-BR') : ''}</span></div>
    </div>`;
  }).join('');
}

document.getElementById('events-month').addEventListener('change', loadEvents);
document.getElementById('sync-events').addEventListener('click', async () => {
  setStatus('Sincronizando...');
  await api('../modules/google_agenda.php?api=sync_from_google');
  await loadEvents();
  setStatus('Online');
});

// Quick add event
async function quickAddEvent() {
  const title = document.getElementById('quick-title').value.trim();
  const dt = document.getElementById('quick-datetime').value;
  if (!title || !dt) return;
  await api('../modules/google_agenda.php?api=create_event', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ title, start_date: dt })
  });
  document.getElementById('quick-title').value = '';
  loadEvents();
  setStatus('Evento criado');
}

document.getElementById('quick-add-event').addEventListener('click', quickAddEvent);

// ACTIVITIES
async function loadActivities() {
  const date = document.getElementById('activities-date').value || new Date().toISOString().slice(0,10);
  const start = date;
  const end = date;
  const data = await api(`../modules/activities.php?api=get_activities&start=${start}&end=${end}`);
  const list = document.getElementById('activities-list');
  if (!data || data.error) { list.innerHTML = `<div class="item">Erro ao carregar${data?.error ? ': ' + data.error : ''}.</div>`; return; }
  if (!data.length) { list.innerHTML = '<div class="item">Sem atividades.</div>'; return; }
  list.innerHTML = data.map(a => `
    <div class="item">
      <div class="title">${a.title}</div>
      <div class="meta">
        <span>${a.day_date}</span>
        <span class="badge">${a.period || 'dia'}</span>
      </div>
    </div>`).join('');
}

document.getElementById('activities-date').addEventListener('change', loadActivities);

document.getElementById('quick-add-activity').addEventListener('click', async () => {
  const title = document.getElementById('quick-activity-title').value.trim();
  const date = document.getElementById('quick-activity-date').value;
  const period = document.getElementById('quick-activity-period').value;
  if (!title || !date) return;
  await api('../modules/activities.php?api=save_activity', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ title, date, period })
  });
  document.getElementById('quick-activity-title').value = '';
  loadActivities();
  setStatus('Atividade criada');
});

// HABITS
async function loadHabits() {
  const ym = document.getElementById('habits-month').value || new Date().toISOString().slice(0,7);
  const data = await api(`../modules/habits.php?api=get_habits&month=${ym}`);
  const list = document.getElementById('habits-list');
  if (!data || data.error) { list.innerHTML = `<div class="item">Erro ao carregar${data?.error ? ': ' + data.error : ''}.</div>`; return; }
  if (!data.length) { list.innerHTML = '<div class="item">Sem hábitos.</div>'; return; }
  list.innerHTML = data.map(h => `
    <div class="item">
      <div class="title">${h.name}</div>
      <div class="meta">Check-ins: ${(h.checked_dates ? JSON.parse(h.checked_dates).length : 0)}</div>
    </div>`).join('');
}

document.getElementById('habits-month').addEventListener('change', loadHabits);

function hookQuickActions() {
  // Already bound; placeholder for future hooks
}
