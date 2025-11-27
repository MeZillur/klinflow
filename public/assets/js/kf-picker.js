// KF.picker — modal search & pick (uses KF.lookup.fetch and KF.writeToHost)
;(function(){
  if (!window.KF) window.KF = {};
  if (window.KF.picker) return;

  const make = (t, cls, attrs) => { const e = document.createElement(t); if (cls) e.className = cls; if (attrs) Object.keys(attrs).forEach(k=>e.setAttribute(k, attrs[k])); return e; };
  const esc = s => String(s==null ? '' : s);

  function createModal(){
    const back = make('div','kf-picker-backdrop');
    const modal = make('div','kf-picker-modal');
    modal.innerHTML = `
      <div class="kf-picker-header" style="display:flex;justify-content:space-between;align-items:center;padding:8px 10px;border-bottom:1px solid #eee">
        <strong class="kf-picker-title">Search</strong>
        <div><button class="kf-picker-close" type="button">Close</button></div>
      </div>
      <div style="padding:10px;">
        <div class="kf-picker-search" style="display:flex;gap:8px;margin-bottom:8px">
          <input class="kf-picker-q" type="search" placeholder="Type to search…" style="flex:1;padding:8px;border:1px solid #ddd;border-radius:6px"/>
          <button class="kf-picker-searchbtn" type="button">Search</button>
        </div>
        <div class="kf-picker-list" role="list" style="max-height:50vh;overflow:auto"></div>
      </div>
    `;
    back.appendChild(modal);
    return { back, modal, q: modal.querySelector('.kf-picker-q'), list: modal.querySelector('.kf-picker-list'), closeBtn: modal.querySelector('.kf-picker-close'), searchBtn: modal.querySelector('.kf-picker-searchbtn') };
  }

  function renderItems(listEl, items){
    listEl.innerHTML = '';
    if (!items || !items.length) {
      const empty = document.createElement('div'); empty.className='kf-picker-empty'; empty.textContent = 'No results'; empty.style.padding='14px'; listEl.appendChild(empty); return;
    }
    items.forEach((it,i) => {
      const row = document.createElement('div');
      row.className = 'kf-picker-row';
      row.tabIndex = 0;
      row.dataset.idx = String(i);
      row.innerHTML = `<div style="font-weight:600">${esc(it.label||it.name||it.code||'')}</div><div style="font-size:12px;color:#475569">${esc(it.sublabel||it.email||it.sku||'')}</div>`;
      listEl.appendChild(row);
    });
  }

  function show(opts = {}){
    // opts: entity, endpoint, host, targets:{id,name,price,code}, title, q, limit, onPick
    const ui = createModal();
    document.body.appendChild(ui.back);
    ui.q.value = opts.q || '';
    ui.modal.querySelector('.kf-picker-title').textContent = opts.title || `Search ${opts.entity||''}`;

    let items = [], active = -1;

    async function doSearch(){
      const q = (ui.q.value||'').trim();
      ui.list.innerHTML = '<div style="padding:14px">Searching…</div>';
      try {
        const rows = await (window.KF && KF.lookup && typeof KF.lookup.fetch === 'function' ? KF.lookup.fetch(opts.entity, q, opts.limit || 30, opts.endpoint) : []);
        items = Array.isArray(rows) ? rows : (Array.isArray(rows?.items) ? rows.items : []);
        renderItems(ui.list, items);
        active = items.length ? 0 : -1;
        setActive(active);
      } catch(e){
        ui.list.innerHTML = '<div style="padding:14px;color:#b00">Search failed</div>';
      }
    }

    function setActive(i){
      Array.from(ui.list.children).forEach((c,idx)=> c.setAttribute('aria-selected', idx===i ? 'true' : 'false'));
      active = i;
      if (active >= 0) {
        const el = ui.list.children[active];
        if (el) el.scrollIntoView({block:'nearest'});
      }
    }

    function pickIndex(i){
      const it = items[i];
      if (!it) return;
      const host = opts.host || document;
      const tg = opts.targets || {};
      // write with host-local helper
      try {
        if (tg.id) KF.writeToHost(host, tg.id, it.id ?? '');
        if (tg.name) KF.writeToHost(host, tg.name, it.label || it.name || '');
        if (tg.price) KF.writeToHost(host, tg.price, it.unit_price ?? it.price ?? '');
        if (tg.code) KF.writeToHost(host, tg.code, it.code ?? '');
      } catch(e){ KF.debug.warn('picker write error', e); }
      // user callback
      try { if (typeof opts.onPick === 'function') opts.onPick(it, host); } catch(e){ KF.debug.warn('picker onPick error', e); }
      close();
    }

    function onKey(e){
      if (e.key === 'Escape') { close(); return; }
      if (e.key === 'ArrowDown'){ e.preventDefault(); if (items.length) setActive(Math.min(active+1, items.length-1)); }
      else if (e.key === 'ArrowUp'){ e.preventDefault(); if (items.length) setActive(Math.max(active-1, 0)); }
      else if (e.key === 'Enter'){ e.preventDefault(); if (active>=0) pickIndex(active); }
    }

    ui.searchBtn.addEventListener('click', doSearch);
    ui.q.addEventListener('keydown', function(e){ if (e.key === 'Enter') doSearch(); });
    ui.list.addEventListener('click', function(e){
      const row = e.target.closest('.kf-picker-row'); if (!row) return;
      pickIndex(Number(row.dataset.idx||0));
    });
    ui.list.addEventListener('keydown', function(e){
      if (e.key === 'Enter') { const row = e.target.closest('.kf-picker-row'); if (!row) return; pickIndex(Number(row.dataset.idx||0)); }
    });

    ui.closeBtn.addEventListener('click', close);
    document.body.addEventListener('keydown', onKey);

    ui.q.focus();
    if (ui.q.value) doSearch();

    function close(){
      try { document.body.removeChild(ui.back); } catch(e){}
      document.body.removeEventListener('keydown', onKey);
    }

    return { close, search: doSearch };
  }

  KF.picker = { show };
})();