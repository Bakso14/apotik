function detectRoute(){
  const file = location.pathname.split('/').pop();
  if(!file || file==='index.html') return 'obat';
  const map = {
    'dashboard.html':'dashboard',
    'pembelian.html':'pembelian',
    'penjualan.html':'penjualan',
    'pelanggan.html':'pelanggan',
    'dokter.html':'dokter',
    'supplier.html':'supplier',
    'karyawan.html':'karyawan',
    'formula.html':'formula',
    'interaksi.html':'interaksi',
    'laporan.html':'laporan'
  };
  return map[file] || null;
}
function ensureFA(){
  if(window.__fa_loaded) return; window.__fa_loaded = true;
  const id = 'fa-css-cdn-link';
  if(document.getElementById(id)) return;
  const link = document.createElement('link');
  link.id = id;
  link.rel = 'stylesheet';
  link.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css';
  link.referrerPolicy = 'no-referrer';
  link.onerror = () => {
    // Fallback to jsDelivr if cdnjs fails
    const altId = 'fa-css-cdn-fallback';
    if(document.getElementById(altId)) return;
    const alt = document.createElement('link');
    alt.id = altId; alt.rel = 'stylesheet';
    alt.href = 'https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css';
    document.head.appendChild(alt);
  };
  document.head.appendChild(link);
}
async function loadSidebar(){
  const el = document.querySelector('aside.sidebar[data-include="sidebar"]');
  if(!el) return;
  try{
    const r = await fetch('./partials/sidebar.html');
    const html = await r.text();
    el.innerHTML = html;
    ensureFA();
  // If FA font not loaded (offline/blocked), replace icons with emoji fallbacks
  applyIconFallbacks(el);
    const key = window.SIDEBAR_ACTIVE || detectRoute();
    if(key){
      const a = el.querySelector(`[data-route="${key}"]`);
      if(a) a.classList.add('active');
    } else {
      const file = location.pathname.split('/').pop() || 'index.html';
      el.querySelectorAll('a').forEach(a=>{
        const href=a.getAttribute('href');
        if((file==='index.html' && href==='./') || (href && href.endsWith(file))){ a.classList.add('active'); }
      });
    }
  }catch(e){ /* noop */ }
}
async function loadHeader(){
  const el = document.querySelector('[data-include="header"]');
  if(!el) return;
  try{
    const r = await fetch('./partials/header.html');
    const html = await r.text();
    el.innerHTML = html;
    ensureFA();
  applyIconFallbacks(el);
    const logout = el.querySelector('#logoutBtn');
    if(logout){
      logout.addEventListener('click', async () => {
        try{ await fetchJSON(api('/api/logout'), { method:'POST' }); }catch(err){}
        location.href = './login.html';
      });
    }
    const changeUser = el.querySelector('#changeUserBtn');
    if(changeUser){ changeUser.addEventListener('click', ()=>{ location.href='./login.html'; }); }
    const editName = el.querySelector('#editNameBtn');
    if(editName){ editName.addEventListener('click', async ()=>{
      try{
        const me = await fetchJSON(api('/api/me'));
        const u = me && me.data && me.data.user || {};
        const cur = u.nama || localStorage.getItem('ui_display_name') || '';
        const v = prompt('Ubah nama tampilan:', cur||'');
        if(v===null) return;
        localStorage.setItem('ui_display_name', v);
        const ui = el.querySelector('#userInfo'); if(ui){ ui.textContent = v + (u.role? ' ('+u.role+')':'' ); }
      }catch(e){
        const v = prompt('Ubah nama tampilan:'); if(v!=null){ localStorage.setItem('ui_display_name', v); const ui=el.querySelector('#userInfo'); if(ui) ui.textContent=v; }
      }
    }); }
    // Set user info if available
    try{
      const me = await fetchJSON(api('/api/me'));
      const u = me && me.data && me.data.user;
      if(u){
        const ui = el.querySelector('#userInfo');
        const override = localStorage.getItem('ui_display_name');
        if(ui) ui.textContent = `${override||u.nama||'Pengguna'}${u.role? ' ('+u.role+')':''}`;
      }
    }catch(err){}
  }catch(e){ /* noop */ }
}
function bootIncludes(){ loadSidebar(); loadHeader(); }
if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', bootIncludes); else bootIncludes();

// --- Helpers: Detect FA and fallback to emoji if unavailable ---
function faLoaded(){
  try{
    if(!('fonts' in document)) return false;
    // Check for Font Awesome 6 Free (solid)
    return document.fonts.check('1em "Font Awesome 6 Free"');
  }catch(e){ return false; }
}
/*
function applyIconFallbacks(scope){
  // try a short delay to give link time to attach
  const run = () => {
    if(faLoaded()) return; // FA available, no fallback needed
    const map = {
      'fa-gauge':'⏱️','fa-chart-line':'📈','fa-pills':'💊','fa-cart-shopping':'🛒','fa-user-group':'👥','fa-user-doctor':'🧑‍⚕️','fa-truck':'🚚','fa-people-group':'👥','fa-flask':'⚗️','fa-notes-medical':'📝','fa-cash-register':'💵','fa-right-from-bracket':'↪️','fa-prescription-bottle-medical':'🏥'
    };
    scope.querySelectorAll('i.fa-solid').forEach(i=>{
      const name = [...i.classList].find(c=>c.startsWith('fa-') && c!=='fa-solid');
      const span = document.createElement('span');
      span.textContent = (name && map[name]) || '•';
      span.style.width='16px'; span.style.display='inline-block'; span.style.textAlign='center';
      i.replaceWith(span);
    });
  };
  // run now and again soon (in case CSS finishes later)
  run();
  setTimeout(run, 1200);
}
*/