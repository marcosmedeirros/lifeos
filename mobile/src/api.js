export async function api(url, options = {}) {
  try {
    const res = await fetch(url, { credentials: 'same-origin', ...options });
    const text = await res.text();
    if (!res.ok) throw new Error(`${res.status} ${res.statusText} | ${text.slice(0, 200)}`);
    try {
      return JSON.parse(text);
    } catch (e) {
      console.error('Resposta não JSON', text);
      throw new Error('Resposta inválida');
    }
  } catch (err) {
    console.error(err);
    return { error: err.message };
  }
}

export function fmtDateTime(dt) {
  return dt ? new Date(dt).toLocaleString('pt-BR') : '';
}

export function fmtDate(d) {
  return d ? new Date(d).toLocaleDateString('pt-BR') : '';
}

export function nowLocal() {
  const now = new Date();
  return new Date(now.getTime() - now.getTimezoneOffset() * 60000);
}
