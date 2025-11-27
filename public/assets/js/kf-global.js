// Minimal but robust KF core runtime (final version)
// - Single drop-in file for public/assets/js/kf-global.js
// - Provides: KF.moduleBase, KF.api, KF.LRU, KF.rescan, KF.lookup.fetch,
//   host-local write helper, cache, and safe ready event.
// - Small, defensive, and compatible with existing module-level lookup fallbacks.
;(function(){
  "use strict";

  // Ensure global namespace
  window.KF = window.KF || {};
  const KF = window.KF;

  // Module base (meta tag in shell) with fallback to existing value
  try {
    const m = document.querySelector('meta[name="kf-module-base"]')?.content;
    if (m && typeof m === 'string') KF.moduleBase = m.replace(/\/+$/,'');
    KF.moduleBase = KF.moduleBase || '';
  } catch(e) {
    KF.moduleBase = KF.moduleBase || '';
  }

  // -------------------------
  // LRU cache (tiny)
  // -------------------------
  KF.LRU = function(max = 500){
    const map = new Map();
    return {
      get(key){
        if (!map.has(key)) return undefined;
        const v = map.get(key);
        map.delete(key);
        map.set(key, v);
        return v;
      },
      set(key, value){
        if (map.has(key)) map.delete(key);
        map.set(key, value);
        while (map.size > max) {
          map.delete(map.keys().next().value);
        }
      },
      has(key){ return map.has(key); },
      clear(){ map.clear(); }
    };
  };

  // Shared cache instance for lookups
  KF._cache = KF._cache || new KF.LRU(800);

  // -------------------------
  // Debug helper
  // -------------------------
  KF.debug = KF.debug || {
    enabled: false,
    log(...args){ if (this.enabled) console.log('[KF]', ...args); },
    warn(...args){ if (this.enabled) console.warn('[KF]', ...args); }
  };

  // -------------------------
  // Helper: safe querySelector within host, fallback document
  // -------------------------
  function _safeQuery(host, selector) {
    if (!selector) return null;
    try {
      if (host && typeof host.querySelector === 'function') {
        try {
          const node = host.querySelector(selector);
          if (node) return node;
        } catch(_) { /* ignore host query errors */ }
      }
      return document.querySelector(selector);
    } catch(e) {
      KF.debug.warn('safeQuery error', e);
      return null;
    }
  }

  // -------------------------
  // write target prefer host-local
  // -------------------------
  function writeTargetWithinHost(hostEl, selector, value) {
    if (!selector) return;
    try {
      const node = _safeQuery(hostEl, selector);
      if (!node) return;
      // If element supports value or textContent, set appropriately
      if ('value' in node && (node.tagName.toLowerCase() !== 'div' || node.isContentEditable === false)) {
        node.value = (value == null ? '' : String(value));
      } else if (node.isContentEditable) {
        node.textContent = (value == null ? '' : String(value));
      } else {
        node.textContent = (value == null ? '' : String(value));
      }
      node.dispatchEvent(new Event('change', { bubbles: true }));
    } catch (e) {
      KF.debug.warn('writeTargetWithinHost failed', e);
    }
  }
  KF.writeTargetWithinHost = writeTargetWithinHost;

  // Alias (backwards compatibility)
  KF.writeTarget = KF.writeTarget || function(selector, value){
    // global-style write when host unknown (keeps old behavior)
    try {
      const node = document.querySelector(selector);
      if (!node) return;
      if ('value' in node && (node.tagName.toLowerCase() !== 'div' || node.isContentEditable === false)) node.value = (value==null?'':String(value));
      else if (node.isContentEditable) node.textContent = (value==null?'':String(value));
      else node.textContent = (value==null?'':String(value));
      node.dispatchEvent(new Event('change', { bubbles: true }));
    } catch(e){ KF.debug.warn('KF.writeTarget global failed', e); }
  };

  // -------------------------
  // Fetch / API wrapper
  // -------------------------
  async function api(url, opts = {}) {
    const controller = new AbortController();
    const timeout = (opts && typeof opts.timeout === 'number') ? opts.timeout : 15000; // 15s default
    const timer = setTimeout(()=>controller.abort(), timeout);

    const baseOpts = {
      method: opts.method || 'GET',
      credentials: opts.credentials || 'include',
      headers: Object.assign({}, opts.headers || {}, {'X-Requested-With':'fetch'}),
      signal: controller.signal
    };

    // Handle body content
    if (opts.body != null) {
      if (opts.body instanceof FormData) {
        baseOpts.body = opts.body;
        // leave content-type unset for FormData
      } else if (typeof opts.body === 'object') {
        baseOpts.headers['Content-Type'] = baseOpts.headers['Content-Type'] || 'application/json';
        baseOpts.body = JSON.stringify(opts.body);
      } else {
        baseOpts.body = opts.body;
      }
    }

    try {
      const res = await fetch(url, baseOpts);
      clearTimeout(timer);
      const ct = res.headers.get('content-type') || '';
      if (ct.indexOf('json') !== -1) {
        const js = await res.json().catch(()=>null);
        if (!res.ok) throw Object.assign(new Error('HTTP '+res.status), { status: res.status, data: js });
        return js;
      } else {
        const text = await res.text().catch(()=>null);
        if (!res.ok) throw Object.assign(new Error('HTTP '+res.status), { status: res.status, data: text });
        return text;
      }
    } catch (err) {
      clearTimeout(timer);
      if (err.name === 'AbortError') throw Object.assign(new Error('Timeout'), { name: 'TimeoutError' });
      throw err;
    }
  }
  KF.api = KF.api || api;

  // -------------------------
  // Normalized lookup fetch helper
  // - returns array of items ([] if none)
  // - uses cache
  // -------------------------
  async function lookupFetch(entity, q, limit = 30, endpoint) {
    const epBase = endpoint || (KF.moduleBase ? (KF.moduleBase + '/api/lookup/' + encodeURIComponent(entity)) : ('/api/lookup/' + encodeURIComponent(entity)));
    const u = new URL(epBase, location.origin);
    if (q) u.searchParams.set('q', q);
    if (limit) u.searchParams.set('limit', String(limit));
    const key = `${u.toString()}`;

    // cache hit
    const cached = KF._cache.get(key);
    if (cached) return cached;

    try {
      const js = await KF.api(u.toString(), { method: 'GET' });
      const arr = Array.isArray(js) ? js : (Array.isArray(js?.items) ? js.items : []);
      KF._cache.set(key, arr);
      return arr;
    } catch (e) {
      KF.debug.warn('lookupFetch failed', e);
      return [];
    }
  }
  KF.lookup = KF.lookup || {};
  KF.lookup.fetch = lookupFetch;

  // -------------------------
  // Rescan: plugin hook for adapters to bind dynamic nodes
  // -------------------------
  (function(){
    const prev = KF.rescan && typeof KF.rescan === 'function' ? KF.rescan : null;
    KF.rescan = function(root){
      // preserve previous behaviour
      try { if (prev) prev(root); } catch(e){ KF.debug.warn('prev rescan error', e); }
      // emit event so adapters can listen
      try { document.dispatchEvent(new CustomEvent('kf:rescan', { detail: { root: root || document } })); } catch(e){}
    };
  })();

  // -------------------------
  // Default lookup binder (only if not provided)
  // This is intentionally conservative and will not override an existing bind function.
  // -------------------------
  if (!KF.lookup.bind || typeof KF.lookup.bind !== 'function') {
    KF.lookup.bind = function(opts){
      // opts: { el, entity, onPick }
      if (!opts || !opts.el || !opts.entity) return;
      const el = opts.el;
      if (el.dataset.kfBound === '1') return;
      el.dataset.kfBound = '1';
      el.setAttribute('autocomplete', 'off');

      // create or reuse suggestion container
      let wrap = el.closest('.relative');
      if (!wrap) {
        wrap = document.createElement('div'); wrap.className = 'relative'; el.parentNode.insertBefore(wrap, el); wrap.appendChild(el);
      }
      const list = document.createElement('div');
      list.className = 'kf-suggest';
      wrap.appendChild(list);

      let items = [], idx = -1, inflight = 0, lastQ = '';

      function close() {
        list.classList.remove('open');
        list.innerHTML = '';
        items = []; idx = -1;
      }
      function open() {
        if (list.children.length) list.classList.add('open');
      }
      function renderRows(arr) {
        list.innerHTML = '';
        if (!arr.length) {
          const empty = document.createElement('div'); empty.className='kf-item text-slate-400'; empty.textContent='No matches';
          list.appendChild(empty); open(); return;
        }
        arr.forEach((it,i) => {
          const row = document.createElement('div'); row.className='kf-item';
          const title = document.createElement('div'); title.className='kf-title'; title.textContent = it.label || it.name || it.code || ('ID ' + (it.id ?? ''));
          const sub = document.createElement('div'); sub.className='kf-sub'; sub.textContent = [it.code, it.sku, it.barcode].filter(Boolean).join(' · ');
          row.appendChild(title); row.appendChild(sub);
          row.addEventListener('mouseenter', ()=> highlight(i));
          row.addEventListener('mousedown', (ev)=> { ev.preventDefault(); pick(i); });
          list.appendChild(row);
        });
        open();
      }
      function highlight(i){
        const rows = Array.from(list.querySelectorAll('.kf-item'));
        rows.forEach(r=>r.removeAttribute('aria-selected'));
        const r = rows[i]; if (r) r.setAttribute('aria-selected','true');
        idx = i;
      }
      function pick(i) {
        const it = items[i]; if (!it) return;
        // set input label
        if (el.isContentEditable) el.textContent = it.label || it.name || it.code || '';
        else el.value = it.label || it.name || it.code || '';
        // call onPick so page can set hidden fields
        try { if (typeof opts.onPick === 'function') opts.onPick(it, el); } catch(e){ KF.debug.warn('onPick handler error', e); }
        el.dispatchEvent(new Event('change', { bubbles:true }));
        close();
      }

      async function search(q) {
        const qq = String(q||'').trim();
        if (!qq) { close(); return; }
        if (qq === lastQ) { open(); return; }
        lastQ = qq;
        const my = ++inflight;
        const arr = await lookupFetch(opts.entity, qq, opts.limit || 20, opts.endpoint).catch(()=>[]);
        if (my !== inflight) return;
        items = Array.isArray(arr) ? arr : [];
        renderRows(items.slice(0, 50));
        idx = items.length ? 0 : -1;
        highlight(idx);
      }

      function onKey(e) {
        if (!list.classList.contains('open')) return;
        const rows = Array.from(list.querySelectorAll('.kf-item')).filter(r=>!r.classList.contains('text-slate-400'));
        if (e.key === 'ArrowDown') { e.preventDefault(); highlight(Math.min(idx+1, rows.length-1)); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(Math.max(idx-1, 0)); }
        else if (e.key === 'Enter') { e.preventDefault(); if (idx>=0) pick(idx); }
        else if (e.key === 'Escape') { close(); }
      }

      el.addEventListener('input', function(e){ search(e.target.value || ''); });
      el.addEventListener('keydown', onKey);
      document.addEventListener('click', function(e){ if (!wrap.contains(e.target)) close(); });
    };
  }

  // -------------------------
  // Utility: write values to targets using host-local resolution
  // Useful for implementing onPick handlers in pickers/adapters.
  // Example: KF.writeToHost(hostEl, '.pid', item.id)
  // -------------------------
  KF.writeToHost = KF.writeToHost || function(hostEl, selector, value){
    writeTargetWithinHost(hostEl, selector, value);
  };

  // Dispatch ready event for other scripts (picker/adapter) to consume
  try { document.dispatchEvent(new CustomEvent('kf:ready', { detail: { KF: KF } })); } catch(e){ /* ignore */ }

})();