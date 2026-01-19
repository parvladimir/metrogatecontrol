const BASE = (window.__BASE__ || '').replace(/\/$/, '');

// ---- helpers injected (hotfix) ----
function escapeHtml(input) {
  const s = (input === null || input === undefined) ? '' : String(input);
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}
function escAttr(input) { return escapeHtml(input); }

// Render single carrier logo (safe: returns '' if no logo known)
function renderCarrierLogo(code) {
  const c = (code || '').toString();
  if (!c) return '';
  const key = c.toUpperCase();
  const url =
    (typeof carrierLogoLocal === 'object' && (carrierLogoLocal[key] || carrierLogoLocal[c] || carrierLogoLocal[key.replace(/\s+/g,'_')])) ||
    '';
  if (!url) return '';
  return `<img class="slogo" src="${escAttr(url)}" alt="${escAttr(key)}">`;
}

// Render list of logos (accepts array or single code)
function renderCarrierLogos(codes) {
  if (Array.isArray(codes)) return codes.map(renderCarrierLogo).join('');
  return renderCarrierLogo(codes);
}
// ---- end helpers ----
function carrierInitials(label){
  const text = String(label || '').trim();
  if (!text) return '?';
  const parts = text.split(/\s+/).filter(Boolean);
  if (parts.length === 1) return parts[0].slice(0, 3).toUpperCase();
  return (parts[0][0] + parts[1][0]).toUpperCase();
}
function carrierBadgeTheme(code){
  const key = carrierKey(code);
  const themes = {
    'DACHSER': { bg: '#facc15', fg: '#1f2937', border: '#fef3c7' },
    'DHL_PARCEL': { bg: '#facc15', fg: '#b91c1c', border: '#fef3c7' },
    'DHL_FREIGHT': { bg: '#facc15', fg: '#b91c1c', border: '#fef3c7' },
    'GLS_DE': { bg: '#1e3a8a', fg: '#f8fafc', border: '#93c5fd' },
    'GLS_BENELUX': { bg: '#1e3a8a', fg: '#f8fafc', border: '#93c5fd' },
  };
  return themes[key] || { bg: '#334155', fg: '#f8fafc', border: '#64748b' };
}
function carrierBadgeLabel(code, name){
  const key = carrierKey(code);
  const labels = {
    'DACHSER': 'DACHSER',
    'DHL_PARCEL': 'DHL',
    'DHL_FREIGHT': 'DHL',
    'GLS_DE': 'GLS',
    'GLS_BENELUX': 'GLS',
  };
  if (labels[key]) return labels[key];
  if (name) return String(name).split(/\s+/)[0].toUpperCase();
  return String(code || '').split(/\s+/)[0].toUpperCase();
}
let carriers = []; // loaded from DB
let carrierOrder = []; // carrier codes in UI order
let carrierLogoLocal = {}; // code -> logo url (relative)

// Normalized lookup to make carrier codes resilient (e.g. "GLS BENELUX" vs "GLS_BENELUX").
function carrierKey(v){
  return String(v||'')
    .trim()
    .toUpperCase()
    .replace(/[^A-Z0-9]+/g,'_')
    .replace(/^_+|_+$/g,'');
}

function buildCarrierMaps(){
  const byKey = {};
  const byName = {};
  carriers.forEach(c => {
    const k = carrierKey(c.code);
    byKey[k] = c;
    if (c.name) byName[carrierKey(c.name)] = k;
  });

  // Map carrier logos by normalized key.
  const logoByKey = {};
  Object.entries(carrierLogoLocal||{}).forEach(([code,logo])=>{
    logoByKey[carrierKey(code)] = logo;
  });

  return {byKey, byName, logoByKey};
}
function resolveCarrierKey(code, name){
  const {byKey, byName} = buildCarrierMaps();
  const codeKey = carrierKey(code);
  if (byKey[codeKey]) return codeKey;
  const nameKey = carrierKey(name);
  if (byKey[nameKey]) return nameKey;
  if (byName[codeKey]) return byName[codeKey];
  if (byName[nameKey]) return byName[nameKey];
  return codeKey || nameKey;
}

/** Load carriers from backend (DB) */
async function loadCarriers(){
  try{
    const res = await fetch(`${BASE}/api/carriers_get.php`, {credentials:'same-origin'});
    const j = await res.json();
    if(j && j.ok && Array.isArray(j.data)){
      // Normalize carrier codes immediately so that DB codes like "GLS BENELUX"
      // still match data codes like "GLS_BENELUX".
      carriers = j.data
        .filter(c => c.active !== false)
        .map(c => ({
          ...c,
          _key: carrierKey(c.code),
          _sort: Number(c.sort_order ?? c.sortOrder ?? 999),
        }));

      carriers.sort((a,b)=> (a._sort-b._sort) || String(a.name||'').localeCompare(String(b.name||'')));
      carrierOrder = carriers.map(c => c._key);

      carrierLogoLocal = {};
      carriers.forEach(c => { if(c.logo) carrierLogoLocal[c._key] = c.logo; });
    }
  }catch(e){
    // fallback to defaults below
  }
}

const TIME_CORRIDORS = ['06:30','07:30','08:30','09:30','10:30','11:30','12:30','13:30','14:00','14:15','14:30','16:00','16:30','18:30','20:30'];

const carrierGateMap = {
  'DACHSER': ['T136','T137','T138','T139'],
  'DHL_FREIGHT': ['T142','T143'],
  'GLS_DE': ['T153','T154','T155'],
  'GLS_BENELUX': ['T156','T157'],
  'DHL_PARCEL': ['T150','T151','T152'],
};

// Fallback defaults (used only if carriers cannot be loaded from DB)
if (!carrierOrder.length) carrierOrder = ['DACHSER','DHL_FREIGHT','GLS_DE','GLS_BENELUX','DHL_PARCEL'];
if (!Object.keys(carrierLogoLocal).length) carrierLogoLocal = {
  'DACHSER': { svg: `${BASE}/assets/img/carriers/dachser.svg`, alt: 'DACHSER' },
  'DHL_FREIGHT': { svg: `${BASE}/assets/img/carriers/dhl.svg`, alt: 'DHL Freight' },
  'DHL_PARCEL': { svg: `${BASE}/assets/img/carriers/dhl.svg`, alt: 'DHL Parcel' },
  'GLS_DE': { svg: `${BASE}/assets/img/carriers/gls.svg`, alt: 'GLS' },
  'GLS_BENELUX': { svg: `${BASE}/assets/img/carriers/gls.svg`, alt: 'GLS Benelux' },
};



// carrierOrder loaded from DB (see loadCarriers)

// carrierLogoLocal loaded from DB (see loadCarriers)

function carrierLabel(v){
  // Prefer DB-provided carrier name; fallback to readable code.
  const {byKey} = buildCarrierMaps();
  const k = carrierKey(v);
  if(byKey[k] && byKey[k].name) return byKey[k].name;
  const raw = String(v||'');
  if(!raw) return '';
  return raw.replace(/_/g,' ').replace(/\b\w/g,m=>m.toUpperCase());
}
function badgeClass(c){
  if(c==='DACHSER') return 'b-dachser';
  if(c==='DHL_FREIGHT' || c==='DHL_PARCEL') return 'b-dhl';
  if(c==='GLS_DE' || c==='GLS_BENELUX') return 'b-gls';
  return '';
}
function esc(s){ return (s||'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])); }
function todayISO(){ return new Date().toISOString().slice(0,10); }
function hhmm(t){ return t ? String(t).slice(0,5) : ''; }
function fmtDT(dt){ return dt ? String(dt).replace('T',' ').slice(0,16) : ''; }

function formPost(url, payload){
  const fd = new URLSearchParams();
  Object.entries(payload).forEach(([k,v])=>fd.append(k, v==null?'':String(v)));
  return fetch(url, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'}, body:fd.toString()});
}

/* ===== DOM ===== */
const workDate = document.getElementById('workDate');
const carrier = document.getElementById('carrier');
const time = document.getElementById('time');
const search = document.getElementById('search');

const scheduleList = document.getElementById('scheduleList');
const gatesGrid = document.getElementById('gatesGrid');
const gatesHint = document.getElementById('gatesHint');

const panelSchedule = document.getElementById('panelSchedule');
const panelToday = document.getElementById('panelToday');
const tabToday = document.getElementById('tabToday');
const tabSchedule = document.getElementById('tabSchedule');

const topActions = document.getElementById('topActions');

const sheetBack = document.getElementById('sheetBack');
const sheet = document.getElementById('sheet');
const sheetClose = document.getElementById('sheetClose');
const sheetTitle = document.getElementById('sheetTitle');
const sheetSub = document.getElementById('sheetSub');

const timeChips = document.getElementById('timeChips');

const fTrailer = document.getElementById('fTrailer');
const fTU = document.getElementById('fTU');
const fEventAt = document.getElementById('fEventAt');
const fPct = document.getElementById('fPct');
const fSeal = document.getElementById('fSeal');
const sealWrap = document.getElementById('sealWrap');
const fPallets = document.getElementById('fPallets');
const fRollis = document.getElementById('fRollis');
const fPackages = document.getElementById('fPackages');
const fRemark = document.getElementById('fRemark');

const btnSave = document.getElementById('btnSave');
const btnClear = document.getElementById('btnClear');

const toast = document.getElementById('toast');

/* ===== Reminders DOM ===== */
const remListEl = document.getElementById('remindersList');
const remId = document.getElementById('remId');
const remActive = document.getElementById('remActive');
const remType = document.getElementById('remType');
const remCarrier = document.getElementById('remCarrier');
const remTime = document.getElementById('remTime');
const remBefore = document.getElementById('remBefore');
const remMessage = document.getElementById('remMessage');

const remFormTitle = document.getElementById('remFormTitle');
const btnRemNew = document.getElementById('btnRemNew');
const btnRemClear = document.getElementById('btnRemClear');
const btnRemSave = document.getElementById('btnRemSave');
const btnRemDelete = document.getElementById('btnRemDelete');

/* ===== State ===== */
let SCHEDULE = [];
let SCHEDULE_META = { time_corridors: [] };
let GATES = [];
let editing = null;

let REMINDERS = [];
let editingRem = null;

/* ===== Toast ===== */
function showToast(msg, ok=true){
  toast.textContent = msg;
  toast.classList.remove('hidden','err');
  if(!ok) toast.classList.add('err');
  clearTimeout(showToast._t);
  showToast._t = setTimeout(()=>toast.classList.add('hidden'), 2400);
}

/* ===== Sheet (slot editor) ===== */
function openSheet(){ sheetBack.classList.remove('hidden'); sheet.classList.remove('hidden'); }
function closeSheet(){ sheetBack.classList.add('hidden'); sheet.classList.add('hidden'); editing=null; }
sheetBack.addEventListener('click', closeSheet);
sheetClose.addEventListener('click', closeSheet);

function setSealVisibility(){
  const p = Number(fPct.value||0);
  sealWrap.style.display = (p >= 100) ? 'block' : 'none';
}
fPct.addEventListener('input', setSealVisibility);

function logoHTMLForCarrier(cf){
  const {logoByKey} = buildCarrierMaps();
  const l = logoByKey[carrierKey(cf)] || carrierLogoLocal[cf];
  if(!l) return '';
  // DB logos are stored as a plain relative path string.
  if(typeof l === 'string'){
    const src = l.startsWith('http') ? l : `${BASE}${l}`;
    return `<img class="gate__logo" src="${src}" alt="${esc(cf)}">`;
  }
  if(l.svg){
    return `<img class="gate__logo" src="${l.svg}" alt="${esc(l.alt||cf)}">`;
  }
  return `<picture>
    <source srcset="${l.webp}" type="image/webp">
    <img class="gate__logo" src="${l.png}" alt="${esc(l.alt||cf)}">
  </picture>`;
}

/* ===== Helpers ===== */
function slotIsFilled(s){
  return !!(
    (s.trailer_number && String(s.trailer_number).trim()) ||
    (s.tu_number && String(s.tu_number).trim()) ||
    Number(s.load_percent||0) > 0 ||
    (s.remark && String(s.remark).trim()) ||
    (s.seal_number && String(s.seal_number).trim()) ||
    (s.pallets != null && String(s.pallets).trim().length) ||
    (s.rollis != null && String(s.rollis).trim().length) ||
    (s.packages != null && String(s.packages).trim().length)
  );
}
function gateHasAnyFilled(g){
  return g.slots.some(slotIsFilled);
}
function carriersWithFilling(){
  const result = [];
  for(const cf of carrierOrder){
    const allowed = carrierGateMap[cf] || [];
    const any = GATES
      .filter(g=>allowed.includes(g.gate_code))
      .some(g=>gateHasAnyFilled(g));
    if(any) result.push(cf);
  }
  return result;
}
function getScheduleTimesForCarrier(cf){
  const times = new Set();
  SCHEDULE.forEach(it=>{
    if(!Number(it.active)) return;
    if(it.carrier !== cf) return;
    const t = hhmm(it.event_time);
    if(t) times.add(t);
  });
  return Array.from(times).sort();
}

/* ===== Filters ===== */
function fillCarrierSelect(){
  carrier.innerHTML =
    `<option value="">All carriers</option>` +
    carrierOrder.map(c=>`<option value="${c}">${carrierLabel(c)}</option>`).join('');
}
function buildTimeOptions(){
  const prev = time.value || '';
  let options = [''].concat(TIME_CORRIDORS.slice());

  time.innerHTML = options.map(t => `<option value="${t}">${t ? t : 'All times'}</option>`).join('');
  time.value = options.includes(prev) ? prev : '';

  // keep enabled; time is a filter, not tied to carrier selection
  time.disabled = false;
}

/* ===== Schedule ===== */
function renderSchedule(){
  const list = document.getElementById('scheduleList');
  if (!list) return;

  const carriers = (SCHEDULE_META && Array.isArray(SCHEDULE_META.carriers)) ? SCHEDULE_META.carriers : [];
  const activeCarrier = carrier.value || '';
  const activateCarrier = (code, name)=>{
    if (code || name) carrier.value = resolveCarrierKey(code, name);
    carrier.dispatchEvent(new Event('change'));
  };

  list.innerHTML = carriers.map(c => {
    const code = c.code;
    const resolvedKey = resolveCarrierKey(c.code, c.name);
    const name = carrierLabel(resolvedKey) || c.name || c.code;
    const initials = carrierBadgeLabel(resolvedKey, name);
    const theme = carrierBadgeTheme(resolvedKey);
    const badge = `
      <div class="sc-logo sc-logo--text" style="--logo-bg:${escAttr(theme.bg)};--logo-fg:${escAttr(theme.fg)};--logo-border:${escAttr(theme.border)}">
        ${escapeHtml(initials)}
      </div>
    `;
    const isActive = activeCarrier && carrierKey(activeCarrier) === carrierKey(resolvedKey);
    return `
      <div class="sc-carrier${isActive ? ' is-active' : ''}" data-code="${escAttr(resolvedKey)}" data-name="${escAttr(name)}">
        <div class="sc-head" role="button" tabindex="0" data-code="${escAttr(resolvedKey)}" data-name="${escAttr(name)}">
          <div class="sc-left">
            ${badge}
            <div class="sc-name">${escapeHtml(name)}</div>
          </div>
          <div class="sc-hint">Tap to select carrier</div>
        </div>
      </div>
    `;
  }).join('');

  list.querySelectorAll('.sc-head').forEach(head => {
    const selectCarrier = ()=>{
      const code = head.getAttribute('data-code') || '';
      const name = head.getAttribute('data-name') || '';
      activateCarrier(code, name);
    };
    head.addEventListener('click', selectCarrier);
    head.addEventListener('keydown', (e)=>{ if (e.key==='Enter' || e.key===' ') { e.preventDefault(); selectCarrier(); } });
  });
}

/* ===== Gates ===== */
function renderGates(){
  const cf = carrier.value;
  const q = (search.value||'').toLowerCase().trim();

  // When "All carriers" is selected, show carriers that either have a schedule on the selected date
  // OR have any filled slots (legacy data can exist even without an explicit schedule row).
  let carriersToShow = [];
  if(cf){
    carriersToShow = [cf];
  } else {
    const carriersWithSchedule = new Set(
      (SCHEDULE||[])
        .filter(it => !it.date || it.date === (workDate.value || todayISO()))
        .map(it => carrierKey(it.carrier))
        .filter(Boolean)
    );
    const carriersWithFill = new Set(
      carrierOrder.filter(c => {
        const allowed = carrierGateMap[c] || [];
        return GATES.some(g => allowed.includes(g.gate_code) && gateHasAnyFilled(g));
      })
    );
    carriersToShow = carrierOrder.filter(c => carriersWithSchedule.has(c) || carriersWithFill.has(c));
  }

  gatesHint.textContent = cf
    ? `Carrier: ${carrierLabel(cf)} · Gates: ${(carrierGateMap[cf]||[]).join(', ')}`
    : `All carriers · Filled/Scheduled: (${carriersToShow.length}) ${carriersToShow.map(carrierLabel).join(', ')}`;

  if(!carriersToShow.length){
    gatesGrid.innerHTML = `<div style="color:#94a3b8;padding:10px">No filled carriers for this date.</div>`;
    return;
  }

  const tiles = carriersToShow.map(c=>{
    const allowed = carrierGateMap[c] || [];
    const logo = logoHTMLForCarrier(c);

    // Always keep carrier isolation: a gate belongs to exactly one carrier in the data.
    let gates = GATES.filter(g=>allowed.includes(g.gate_code) );

    // In "All carriers" mode, we already restrict the carrier list; do not drop empty gates here
    // or the dashboard becomes misleading (scheduled but still empty gates would disappear).

    gates = gates.filter(g=>{
      if(q){
        const inGate = g.gate_code.toLowerCase().includes(q);
        const inAny = g.slots.some(s =>
          (s.trailer_number||'').toLowerCase().includes(q) ||
          (s.tu_number||'').toLowerCase().includes(q)
        );
        if(!inGate && !inAny) return false;
      }
      return true;
    });

    if(!gates.length) return '';

    const gatesHtml = gates.map(g=>{
      return `
        <div class="gate">
          <div class="gate__head">
            <div class="gate__left">${logo}<div class="gate__code">${esc(g.gate_code)}</div></div>
          </div>

          ${g.slots.map(s=>{
            const p = Number(s.load_percent||0);

            const line1 = s.trailer_number ? `${esc(s.trailer_number)}` : `— empty —`;
            const line2 = [
              s.tu_number ? `TU ${esc(s.tu_number)}` : '',
              (p>=100 && s.seal_number) ? `Seal ${esc(s.seal_number)}` : '',
              s.event_at ? `⏱ ${esc(fmtDT(s.event_at))}` : ''
            ].filter(Boolean).join(' · ');

            const tap = slotIsFilled(s) ? '' : `<div class="tapHint">Tap to edit</div>`;

            return `
              <div class="slot" data-gp="${s.gate_position_id}" data-gate="${esc(g.gate_code)}" data-pos="${s.position}" data-carrier="${esc(c)}">
                <div class="slot__top">
                  <div class="slot__name">Slot ${s.position}</div>
                  <div class="slot__pct">${p}%</div>
                </div>
                <div class="bar"><div class="bar__fill" style="width:${p}%"></div></div>
                <div class="slot__line1">${line1}</div>
                <div class="slot__line2">${esc(line2)}</div>
                ${tap}
              </div>
            `;
          }).join('')}
        </div>
      `;
    }).join('');

    return `
      <div class="carrierTile">
        <div class="carrierTile__head">
          ${logo}
          <div class="carrierTile__name">${esc(carrierLabel(c))}</div>
        </div>
        <div class="gatesGrid">
          ${gatesHtml}
        </div>
      </div>
    `;
  }).filter(Boolean).join('');

  gatesGrid.innerHTML = `<div class="carriersGrid">${tiles || ''}</div>`;

  gatesGrid.querySelectorAll('.slot').forEach(el=>{
    el.addEventListener('click', ()=>{
      const c = el.dataset.carrier || '';
      const gp = Number(el.dataset.gp);
      const gateCode = el.dataset.gate;
      const pos = Number(el.dataset.pos);

      carrier.value = c;
      buildTimeOptions();

      const g = GATES.find(x=>x.gate_code===gateCode);
      const s = g ? g.slots.find(x=>Number(x.position)===pos) : null;

      editing = { gate_code: gateCode, position: pos, gate_position_id: gp };

      sheetTitle.textContent = `${gateCode} – Slot ${pos}`;
      sheetSub.textContent = carrierLabel(c);

      fTrailer.value = s?.trailer_number || '';
      fTU.value = s?.tu_number || '';
      fPct.value = Number(s?.load_percent || 0);
      fSeal.value = s?.seal_number || '';
      fPallets.value = (s?.pallets ?? '') === null ? '' : (s?.pallets ?? '');
      fRollis.value = (s?.rollis ?? '') === null ? '' : (s?.rollis ?? '');
      fPackages.value = (s?.packages ?? '') === null ? '' : (s?.packages ?? '');
      fRemark.value = s?.remark || '';

      const d = workDate.value || todayISO();
      if(s?.event_at){
        fEventAt.value = String(s.event_at).replace(' ', 'T').slice(0,16);
      } else {
        const t = time.value || getScheduleTimesForCarrier(c)[0] || '00:00';
        fEventAt.value = `${d}T${t}`;
      }

      setSealVisibility();
      renderTimeChipsForSheet();
      openSheet();
    });
  });
}

/* ===== Save/Clear slot ===== */
btnClear.addEventListener('click', ()=>{
  fTrailer.value=''; fTU.value=''; fPct.value=0; fSeal.value='';
  fPallets.value=''; fRollis.value=''; fPackages.value=''; fRemark.value='';
  setSealVisibility();
});

btnSave.addEventListener('click', async ()=>{
  if(!editing){ showToast('No slot selected', false); return; }

  const d = workDate.value || todayISO();
  let pct = Number(fPct.value||0);
  pct = Math.max(0, Math.min(100, pct));
  pct = Math.round(pct/5)*5;

  btnSave.disabled = true;
  try{
    const res = await formPost(`${BASE}/api/status_save.php`, {
      gate_position_id: editing.gate_position_id,
      work_date: d,
      carrier: carrier.value || '',
      event_at: fEventAt.value || '',
      trailer_number: fTrailer.value.trim(),
      tu_number: fTU.value.trim(),
      seal_number: (pct>=100 ? fSeal.value.trim() : ''),
      load_percent: pct,
      pallets: fPallets.value,
      rollis: fRollis.value,
      packages: fPackages.value,
      remark: fRemark.value.trim()
    });

    const txt = await res.text();
    let j={}; try{ j=JSON.parse(txt);}catch{ j={ok:false,error:'Bad JSON'}; }
    if(!res.ok || !j.ok) throw new Error(j.error || ('HTTP '+res.status));

    showToast('Saved', true);
    closeSheet();
    await loadGates();

  } catch(e){
    showToast('Save failed: '+e.message, false);
  } finally {
    btnSave.disabled = false;
  }
});

/* ===== Data load ===== */
async function loadSchedule(){
  const d = workDate.value || todayISO();
  const res = await fetch(`${BASE}/api/schedule_get.php?date=${encodeURIComponent(d)}`, { cache:'no-store' });
  const j = await res.json();
    SCHEDULE = (j && j.data) ? j.data : (Array.isArray(j) ? j : []);
    SCHEDULE_META = (j && j.meta) ? j.meta : { time_corridors: [] };
    buildTimeOptions();
  renderSchedule();
}
async function loadGates(){
  const d = workDate.value || todayISO();
  const res = await fetch(`${BASE}/api/gates_get.php?date=${encodeURIComponent(d)}`, { cache:'no-store' });
  GATES = await res.json();
  renderGates();
}

/* ===== Mobile tabs ===== */
function isMobile(){
  return window.matchMedia && window.matchMedia('(max-width: 980px)').matches;
}
function setTab(which){
  const todayOn = which === 'today';
  panelToday.style.display = todayOn ? 'block' : 'none';
  panelSchedule.style.display = todayOn ? 'none' : 'block';
  tabToday.classList.toggle('active', todayOn);
  tabSchedule.classList.toggle('active', !todayOn);
  localStorage.setItem('ml_tab', which);
}
tabToday.addEventListener('click', ()=>setTab('today'));
tabSchedule.addEventListener('click', ()=>setTab('schedule'));

window.addEventListener('resize', ()=>{
  if(isMobile()){
    const saved = localStorage.getItem('ml_tab') || 'today';
    setTab(saved);
  } else {
    panelToday.style.display='block';
    panelSchedule.style.display='block';
  }
});

/* ===== Filter events ===== */
carrier.addEventListener('change', ()=>{
  buildTimeOptions();
  renderSchedule();
  renderGates();
});
time.addEventListener('change', ()=>{
  renderSchedule();
  renderGates();
});
search.addEventListener('input', renderGates);

workDate.addEventListener('change', async ()=>{
  await loadSchedule();
  await loadGates();
});

/* =========================
   Pending Alerts (sticky)
   ========================= */
let PENDING = [];
let titleBlinkTimer = null;
let originalTitle = document.title;

function loadPending(){
  try{
    const j = JSON.parse(localStorage.getItem('ml_pending_alerts') || '[]');
    PENDING = Array.isArray(j) ? j : [];
  }catch{
    PENDING = [];
  }
}
function savePending(){
  localStorage.setItem('ml_pending_alerts', JSON.stringify(PENDING));
}
function startTitleBlink(){
  if(titleBlinkTimer) return;
  originalTitle = originalTitle || document.title;
  let on = false;
  titleBlinkTimer = setInterval(()=>{
    on = !on;
    document.title = on ? `⚠ REMINDER (${PENDING.length})` : originalTitle;
  }, 900);
}
function stopTitleBlink(){
  if(titleBlinkTimer){
    clearInterval(titleBlinkTimer);
    titleBlinkTimer = null;
  }
  document.title = originalTitle;
}
function renderAlertBadge(){
  if(!topActions) return;

  const old = topActions.querySelector('[data-alert-badge="1"]');
  if(old) old.remove();

  if(!PENDING.length){
    stopTitleBlink();
    return;
  }

  startTitleBlink();

  const b = document.createElement('div');
  b.className = 'alertBadge';
  b.setAttribute('data-alert-badge','1');
  b.innerHTML = `<span class="alertDot"></span> Alerts: ${PENDING.length}`;
  b.addEventListener('click', ()=>openAlertsSheet());
  topActions.prepend(b);
}
function addPendingAlert(ev){
  const key = `${ev.template_id || ''}|${ev.fired_at || ''}|${ev.message || ''}`;
  if(PENDING.some(x=>x._key===key)) return;
  PENDING.unshift({
    _key: key,
    message: ev.message || 'Reminder',
    fired_at: ev.fired_at || '',
    template_id: ev.template_id || null
  });
  savePending();
  renderAlertBadge();
}
function acknowledgeAlert(key){
  PENDING = PENDING.filter(x=>x._key !== key);
  savePending();
  renderAlertBadge();
}
function acknowledgeAll(){
  PENDING = [];
  savePending();
  renderAlertBadge();
}

function openAlertsSheet(){
  if(!sheet || !sheetBack) return;

  sheetTitle.textContent = 'Pending reminders';
  sheetSub.textContent = 'These stay until you mark them done.';

  const body = sheet.querySelector('.sheet__body');
  if(!body) return;

  const items = PENDING.map(a=>{
    const meta = a.fired_at ? `Fired at: ${a.fired_at}` : '';
    return `
      <div class="alertItem">
        <div>
          <div class="alertItem__msg">${esc(a.message)}</div>
          <div class="alertItem__meta">${esc(meta)}</div>
        </div>
        <button class="btn secondary" type="button" data-ack="${esc(a._key)}" style="height:34px">Done</button>
      </div>
    `;
  }).join('');

  body.innerHTML = `
    <div class="alertPanel">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:10px">
        <div style="font-weight:1000">Unacknowledged</div>
        <button class="btn secondary" type="button" id="ackAll" style="height:34px">Done all</button>
      </div>
      ${items || `<div style="color:var(--muted);padding:10px">No pending alerts.</div>`}
    </div>
  `;

  body.querySelector('#ackAll')?.addEventListener('click', ()=>{
    acknowledgeAll();
    closeSheet();
    window.location.reload();
  });

  body.querySelectorAll('[data-ack]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const k = btn.getAttribute('data-ack');
      acknowledgeAlert(k);
      btn.closest('.alertItem')?.remove();
      if(!PENDING.length){
        closeSheet();
        window.location.reload();
      }
    });
  });

  openSheet();
}

/* =========================
   Reminders (DB UI + poll)
   ========================= */
function fillRemCarrierSelect(){
  remCarrier.innerHTML =
    `<option value="">(none)</option>` +
    carrierOrder.map(c=>`<option value="${c}">${carrierLabel(c)}</option>`).join('');
}

function isHHMM(v){
  return /^\d{2}:\d{2}$/.test(v);
}

function renderRemindersList(){
  if(!remListEl) return;

  if(!REMINDERS.length){
    remListEl.innerHTML = `<div style="color:#94a3b8;padding:10px">No reminders yet. Click “+ New”.</div>`;
    return;
  }

  remListEl.innerHTML = REMINDERS.map(r=>{
    const on = Number(r.active) ? '' : 'off';
    const t = hhmm(r.event_time);
    const before = Number(r.minutes_before || 0);
    const typeLabel = (r.type === 'daily') ? 'Daily' : 'Schedule';
    const car = r.carrier ? carrierLabel(r.carrier) : '—';
    const pill1 = `<span class="remPill">${esc(typeLabel)}</span>`;
    const pill2 = `<span class="remPill">${esc(car)}</span>`;
    const pill3 = `<span class="remPill">-${before} min</span>`;

    return `
      <div class="remItem ${on}" data-id="${r.id}">
        <div class="remItem__top">
          <div class="remItem__time">${esc(t)} · ${esc(typeLabel)}</div>
          <div class="remPill">${Number(r.active) ? 'ON' : 'OFF'}</div>
        </div>
        <div class="remItem__meta">
          ${pill1} ${pill2} ${pill3}<br>
          ${esc(r.message)}
        </div>
      </div>
    `;
  }).join('');

  remListEl.querySelectorAll('.remItem').forEach(el=>{
    el.addEventListener('click', ()=>{
      const id = Number(el.dataset.id);
      const r = REMINDERS.find(x=>Number(x.id)===id);
      if(!r) return;
      loadRemToForm(r);
    });
  });
}

function clearRemForm(){
  editingRem = null;
  remId.value = '';
  remActive.value = '1';
  remType.value = 'daily';
  remCarrier.value = '';
  remTime.value = '';
  remBefore.value = '0';
  remMessage.value = '';
  remFormTitle.textContent = 'New reminder';
  btnRemDelete.style.display = 'none';
  applyRemTypeRules();
}

function loadRemToForm(r){
  editingRem = r;
  remId.value = String(r.id);
  remActive.value = String(Number(r.active) ? 1 : 0);
  remType.value = r.type || 'daily';
  remCarrier.value = r.carrier || '';
  remTime.value = hhmm(r.event_time) || '';
  remBefore.value = String(r.minutes_before ?? 0);
  remMessage.value = r.message || '';
  remFormTitle.textContent = `Edit reminder #${r.id}`;
  btnRemDelete.style.display = 'inline-flex';
  applyRemTypeRules();
}

function applyRemTypeRules(){
  if(remType.value === 'daily'){
    remCarrier.value = '';
    remCarrier.disabled = true;
    remCarrier.style.opacity = '0.65';
  } else {
    remCarrier.disabled = false;
    remCarrier.style.opacity = '1';
  }
}

async function loadReminders(){
  try{
    const res = await fetch(`${BASE}/api/reminders_list.php`, { cache:'no-store' });
    REMINDERS = await res.json();
    renderRemindersList();
  }catch(e){
    showToast('Reminders load failed', false);
  }
}

async function saveReminder(){
  const payload = {
    id: remId.value || 0,
    active: remActive.value,
    type: remType.value,
    carrier: remType.value === 'daily' ? '' : remCarrier.value,
    event_time: remTime.value.trim(),
    minutes_before: remBefore.value,
    message: remMessage.value.trim(),
  };

  if(!isHHMM(payload.event_time)){
    showToast('Time must be HH:MM', false);
    return;
  }
  if(payload.type === 'schedule' && !payload.carrier){
    showToast('Carrier required for Schedule', false);
    return;
  }
  if(!payload.message){
    showToast('Message required', false);
    return;
  }

  btnRemSave.disabled = true;
  try{
    const res = await formPost(`${BASE}/api/reminders_save.php`, payload);
    const txt = await res.text();
    let j={}; try{ j=JSON.parse(txt);}catch{ j={ok:false,error:'Bad JSON'}; }
    if(!res.ok || !j.ok) throw new Error(j.error || ('HTTP '+res.status));

    showToast('Reminder saved', true);
    await loadReminders();
    clearRemForm();
  }catch(e){
    showToast('Save failed: '+e.message, false);
  }finally{
    btnRemSave.disabled = false;
  }
}

async function deleteReminder(){
  const id = Number(remId.value || 0);
  if(!id) return;
  if(!confirm(`Delete reminder #${id}?`)) return;

  btnRemDelete.disabled = true;
  try{
    const res = await formPost(`${BASE}/api/reminders_delete.php`, { id });
    const txt = await res.text();
    let j={}; try{ j=JSON.parse(txt);}catch{ j={ok:false,error:'Bad JSON'}; }
    if(!res.ok || !j.ok) throw new Error(j.error || ('HTTP '+res.status));

    showToast('Reminder deleted', true);
    await loadReminders();
    clearRemForm();
  }catch(e){
    showToast('Delete failed: '+e.message, false);
  }finally{
    btnRemDelete.disabled = false;
  }
}

/* server-driven firing (pseudo cron) */
async function pollReminders(){
  try{
    const res = await fetch(`${BASE}/api/reminders_fire.php`, { cache:'no-store' });
    const j = await res.json();
    if(!j || !j.ok) return;

    (j.fired || []).forEach(ev=>{
      if(ev && ev.message){
        showToast(ev.message, true);
        addPendingAlert(ev);
      }
    });

    renderAlertBadge();
  }catch(e){}
}

/* ===== Reminders events ===== */
btnRemNew?.addEventListener('click', clearRemForm);
btnRemClear?.addEventListener('click', clearRemForm);
btnRemSave?.addEventListener('click', saveReminder);
btnRemDelete?.addEventListener('click', deleteReminder);
remType?.addEventListener('change', applyRemTypeRules);

/* ===== Boot ===== */
workDate.value = todayISO();
fillCarrierSelect();
carrier.value = '';
buildTimeOptions();

fillRemCarrierSelect();
clearRemForm();

loadPending();
renderAlertBadge();

(async ()=>{
  await loadCarriers();
  fillCarrierSelect();
  buildTimeOptions();
  await loadSchedule();
  await loadGates();

  await loadReminders();
  await pollReminders();
  setInterval(pollReminders, 60*1000);

  if(isMobile()){
    const saved = localStorage.getItem('ml_tab') || 'today';
    setTab(saved);
  }
})();
