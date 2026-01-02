function qs(sel){return document.querySelector(sel);}function qsa(sel){return Array.from(document.querySelectorAll(sel));}

function setStatus(msg){const el=qs('#status');if(el)el.textContent=msg;}

async function api(url,opts={}){try{const res=await fetch(url,{credentials:'same-origin',...opts});const text=await res.text();const isHtml=/^\s*<!doctype html/i.test(text)||/^\s*<html/i.test(text);if(isHtml){setStatus('Login necessário');const redirect=encodeURIComponent(window.location.pathname+window.location.search);window.location.href=`../login.php?redirect=${redirect}`;return{error:'login_required',body:text.slice(0,200)};}if(!res.ok){throw new Error(`${res.status} ${res.statusText} | ${text.slice(0,200)}`);}try{return JSON.parse(text);}catch(e){console.error('Resposta não JSON:',text);throw new Error('Resposta inválida');}}catch(err){setStatus('Erro');console.error(err);return{error:err.message};}}

function initNav(active){qsa('.nav a').forEach(a=>{a.classList.toggle('active',a.dataset.page===active);});}

function fmtDate(d){return new Date(d).toLocaleDateString('pt-BR');}
function fmtDateTime(dt){return new Date(dt).toLocaleString('pt-BR');}

function fillDefaultInputs(){const now=new Date();const localDt=new Date(now.getTime()-now.getTimezoneOffset()*60000);qsa('input[type="datetime-local"].auto-now').forEach(i=>i.value=localDt.toISOString().slice(0,16));qsa('input[type="date"].auto-today').forEach(i=>i.value=localDt.toISOString().slice(0,10));qsa('input[type="month"].auto-month').forEach(i=>i.value=localDt.toISOString().slice(0,7));}
