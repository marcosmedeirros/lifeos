import React, { useEffect, useMemo, useState } from 'react';
import { api, fmtDateTime, nowLocal } from './api';

const tabs = [
  { id: 'dashboard', label: 'Dashboard' },
  { id: 'agenda', label: 'Agenda' },
  { id: 'activities', label: 'Atividades' },
  { id: 'habits', label: 'Hábitos' },
];

export default function App() {
  const [tab, setTab] = useState('agenda');
  const [status, setStatus] = useState('Online');

  return (
    <div className="min-h-screen px-3 py-4 max-w-5xl mx-auto text-white font-sans">
      <header className="sticky top-0 z-20 backdrop-blur bg-base/70 border-b border-border -mx-3 px-3 py-4 mb-4 flex items-center justify-between">
        <div className="text-lg font-bold">LifeOS Mobile</div>
        <div className="text-xs text-muted">{status}</div>
      </header>

      <nav className="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4">
        {tabs.map(t => (
          <button
            key={t.id}
            onClick={() => setTab(t.id)}
            className={`nav-link ${tab === t.id ? 'nav-link-active' : ''}`}
          >
            {t.label}
          </button>
        ))}
      </nav>

      {tab === 'dashboard' && <Dashboard setStatus={setStatus} />}
      {tab === 'agenda' && <Agenda setStatus={setStatus} />}
      {tab === 'activities' && <Activities setStatus={setStatus} />}
      {tab === 'habits' && <Habits setStatus={setStatus} />}
    </div>
  );
}

function Dashboard({ setStatus }) {
  const [eventTitle, setEventTitle] = useState('');
  const [eventDt, setEventDt] = useState(nowLocal().toISOString().slice(0, 16));
  const [actTitle, setActTitle] = useState('');
  const [actDate, setActDate] = useState(nowLocal().toISOString().slice(0, 10));
  const [actPeriod, setActPeriod] = useState('morning');

  const createEvent = async () => {
    if (!eventTitle || !eventDt) return;
    const res = await api('../modules/google_agenda.php?api=create_event', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ title: eventTitle, start_date: eventDt })
    });
    if (res && !res.error) { setStatus('Evento criado'); setEventTitle(''); }
    else setStatus(res?.error || 'Erro');
  };

  const createActivity = async () => {
    if (!actTitle || !actDate) return;
    const res = await api('../modules/activities.php?api=save_activity', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ title: actTitle, date: actDate, period: actPeriod })
    });
    if (res && !res.error) { setStatus('Atividade criada'); setActTitle(''); }
    else setStatus(res?.error || 'Erro');
  };

  return (
    <div className="space-y-4">
      <div className="card space-y-3">
        <h2 className="text-lg font-bold">Atalhos</h2>
        <div className="grid md:grid-cols-2 gap-3">
          <div className="space-y-2">
            <label className="text-sm text-muted">Evento rápido</label>
            <input className="input" value={eventTitle} onChange={e => setEventTitle(e.target.value)} placeholder="Título" />
            <input className="input" type="datetime-local" value={eventDt} onChange={e => setEventDt(e.target.value)} />
            <button className="btn" onClick={createEvent}>Adicionar evento</button>
          </div>
          <div className="space-y-2">
            <label className="text-sm text-muted">Atividade rápida</label>
            <input className="input" value={actTitle} onChange={e => setActTitle(e.target.value)} placeholder="Atividade" />
            <input className="input" type="date" value={actDate} onChange={e => setActDate(e.target.value)} />
            <select className="input" value={actPeriod} onChange={e => setActPeriod(e.target.value)}>
              <option value="morning">Manhã</option>
              <option value="afternoon">Tarde</option>
              <option value="night">Noite</option>
            </select>
            <button className="btn" onClick={createActivity}>Adicionar atividade</button>
          </div>
        </div>
      </div>
    </div>
  );
}

function Agenda({ setStatus }) {
  const [ym, setYm] = useState(nowLocal().toISOString().slice(0, 7));
  const [events, setEvents] = useState([]);
  const [eventTitle, setEventTitle] = useState('');
  const [eventDt, setEventDt] = useState(nowLocal().toISOString().slice(0, 16));
  const filtered = useMemo(() => events.filter(e => (e.start_date || '').startsWith(ym)), [events, ym]);

  useEffect(() => { load(); }, []);

  const load = async () => {
    setStatus('Carregando...');
    const data = await api('../modules/google_agenda.php?api=list_events');
    if (data && !data.error) {
      setEvents(data.events || []);
      setStatus('Online');
    } else {
      setStatus(data?.error || 'Erro');
    }
  };

  const sync = async () => {
    setStatus('Sincronizando...');
    await api('../modules/google_agenda.php?api=sync_from_google');
    await load();
  };

  const createEvent = async () => {
    if (!eventTitle || !eventDt) return;
    const res = await api('../modules/google_agenda.php?api=create_event', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ title: eventTitle, start_date: eventDt })
    });
    if (res && !res.error) { setStatus('Evento criado'); setEventTitle(''); load(); }
    else setStatus(res?.error || 'Erro');
  };

  return (
    <div className="space-y-4">
      <div className="card space-y-3">
        <div className="flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
          <div>
            <h2 className="text-lg font-bold">Eventos</h2>
            <p className="text-xs text-muted">Sincronizado com Google Agenda</p>
          </div>
          <div className="flex flex-wrap gap-2 w-full sm:w-auto">
            <input className="input sm:w-40" type="month" value={ym} onChange={e => setYm(e.target.value)} />
            <button className="btn sm:w-auto" onClick={sync}>Sincronizar</button>
          </div>
        </div>
        <div className="grid md:grid-cols-2 gap-3">
          <div className="space-y-2">
            <label className="text-sm text-muted">Novo evento</label>
            <input className="input" value={eventTitle} onChange={e => setEventTitle(e.target.value)} placeholder="Título" />
            <input className="input" type="datetime-local" value={eventDt} onChange={e => setEventDt(e.target.value)} />
            <button className="btn" onClick={createEvent}>Adicionar</button>
          </div>
        </div>
        <div className="list space-y-2 max-h-[60vh] overflow-auto pr-1">
          {!filtered.length && <div className="card bg-[#0f0f0f] border-border text-sm">Sem eventos.</div>}
          {filtered.map(ev => {
            const dt = ev.start_date?.replace(' ', 'T');
            return (
              <div key={ev.id || ev.google_event_id} className="card bg-[#0f0f0f] border-border">
                <div className="font-semibold text-sm">{ev.title || 'Sem título'}</div>
                <div className="text-xs text-muted">{fmtDateTime(dt)}</div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}

function Activities({ setStatus }) {
  const [date, setDate] = useState(nowLocal().toISOString().slice(0, 10));
  const [items, setItems] = useState([]);
  const [title, setTitle] = useState('');
  const [period, setPeriod] = useState('morning');

  useEffect(() => { load(); }, [date]);

  const load = async () => {
    setStatus('Carregando...');
    const data = await api(`../modules/activities.php?api=get_activities&start=${date}&end=${date}`);
    if (data && !data.error) { setItems(data || []); setStatus('Online'); }
    else setStatus(data?.error || 'Erro');
  };

  const create = async () => {
    if (!title || !date) return;
    const res = await api('../modules/activities.php?api=save_activity', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ title, date, period })
    });
    if (res && !res.error) { setStatus('Atividade criada'); setTitle(''); load(); }
    else setStatus(res?.error || 'Erro');
  };

  return (
    <div className="space-y-4">
      <div className="card space-y-3">
        <div className="flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
          <div>
            <h2 className="text-lg font-bold">Atividades</h2>
            <p className="text-xs text-muted">Planejamento do dia</p>
          </div>
          <input className="input sm:w-40" type="date" value={date} onChange={e => setDate(e.target.value)} />
        </div>
        <div className="grid md:grid-cols-2 gap-3">
          <div className="space-y-2">
            <label className="text-sm text-muted">Nova atividade</label>
            <input className="input" value={title} onChange={e => setTitle(e.target.value)} placeholder="Título" />
            <select className="input" value={period} onChange={e => setPeriod(e.target.value)}>
              <option value="morning">Manhã</option>
              <option value="afternoon">Tarde</option>
              <option value="night">Noite</option>
            </select>
            <button className="btn" onClick={create}>Adicionar</button>
          </div>
        </div>
        <div className="list space-y-2 max-h-[60vh] overflow-auto pr-1">
          {!items.length && <div className="card bg-[#0f0f0f] border-border text-sm">Sem atividades.</div>}
          {items.map(a => (
            <div key={a.id} className="card bg-[#0f0f0f] border-border">
              <div className="font-semibold text-sm">{a.title}</div>
              <div className="text-xs text-muted flex gap-2 items-center">
                <span>{a.day_date}</span>
                <span className="badge">{a.period || 'dia'}</span>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

function Habits({ setStatus }) {
  const [ym, setYm] = useState(nowLocal().toISOString().slice(0, 7));
  const [items, setItems] = useState([]);

  useEffect(() => { load(); }, [ym]);

  const load = async () => {
    setStatus('Carregando...');
    const data = await api(`../modules/habits.php?api=get_habits&month=${ym}`);
    if (data && !data.error) { setItems(data || []); setStatus('Online'); }
    else setStatus(data?.error || 'Erro');
  };

  return (
    <div className="space-y-4">
      <div className="card space-y-3">
        <div className="flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
          <div>
            <h2 className="text-lg font-bold">Hábitos</h2>
            <p className="text-xs text-muted">Resumo mensal</p>
          </div>
          <input className="input sm:w-40" type="month" value={ym} onChange={e => setYm(e.target.value)} />
        </div>
        <div className="grid sm:grid-cols-2 gap-2">
          {!items.length && <div className="card bg-[#0f0f0f] border-border text-sm">Sem hábitos.</div>}
          {items.map(h => {
            const checks = h.checked_dates ? JSON.parse(h.checked_dates).length : 0;
            return (
              <div key={h.id} className="card bg-[#0f0f0f] border-border">
                <div className="font-semibold text-sm">{h.name}</div>
                <div className="text-xs text-muted">Check-ins: {checks}</div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
