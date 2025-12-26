const path = location.pathname;
const idx = path.indexOf('/app/');
const base = idx >= 0 ? path.slice(0, idx) : path.replace(/\/app\/?$/, '');
const api = (p) => base.replace(/\/$/, '') + p;
function fmtIDR(n){ try { return Number(n||0).toLocaleString('id-ID'); } catch(e){ return n; } }
async function fetchJSON(url, opt){ const r = await fetch(url, opt); const t = await r.text(); try{ return { status:r.status, data: t? JSON.parse(t): null }; } catch(e){ return { status:r.status, data:null, raw:t }; } }
