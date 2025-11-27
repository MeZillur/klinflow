// app/Support/kf-lookup.js — binder with debug & fallback (overwrite existing binder with this)
(function(){
  window.KF = window.KF || {};
  if (window.KF.__kfLookupBinder) return;
  window.KF.__kfLookupBinder = true;

  function moduleBase(){
    try {
      if (window.KF && window.KF.moduleBase) return window.KF.moduleBase;
      var meta = document.querySelector('meta[name="kf-module-base"]');
      return meta ? meta.content : '';
    } catch(e){ return ''; }
  }

  function bindOne(inp){
    if (!inp || inp.dataset.kfBound === '1') return;
    inp.dataset.kfBound = '1';
    var entity = (inp.dataset.kfLookup || '').trim();
    if (!entity) return;
    var timer = 0, lastQ = '';
    var wrap = inp.closest('.relative') || (function(){ var r=document.createElement('div'); r.className='relative'; inp.parentNode.insertBefore(r,inp); r.appendChild(inp); return r; })();
    var list = document.createElement('div'); list.className='kf-suggest'; wrap.appendChild(list);

    function close(){ list.classList.remove('open'); list.innerHTML=''; }
    function open(){ if (list.children.length) list.classList.add('open'); }

    function render(items){
      list.innerHTML='';
      if (!items || !items.length){ var el=document.createElement('div'); el.className='kf-item text-slate-400'; el.textContent = (window.KF && window.KF.t)?window.KF.t('No matches'):'No matches'; list.appendChild(el); open(); return; }
      items.forEach(function(it, i){
        var row = document.createElement('div'); row.className='kf-item';
        var title = document.createElement('div'); title.className='kf-title'; title.textContent = it.label || it.name || it.code || ('ID '+(it.id||''));
        var sub = document.createElement('div'); sub.className='kf-sub'; sub.textContent = it.code || it.sku || (it.sublabel||'');
        row.appendChild(title); row.appendChild(sub);
        row.addEventListener('mousedown', function(ev){ ev.preventDefault(); pick(i); });
        row.addEventListener('mouseenter', function(){ highlight(i); });
        list.appendChild(row);
      });
      open();
    }

    var items = [], idx=-1;
    function highlight(i){ var rows = list.querySelectorAll('.kf-item'); rows.forEach(function(r){ r.removeAttribute('aria-selected'); }); var r=rows[i]; if (r){ r.setAttribute('aria-selected','true'); idx=i; } }
    function pick(i){
      var it = items[i]; if(!it) return;
      inp.value = it.label || it.name || it.code || '';
      var idSel = inp.dataset.kfTargetId ? document.querySelector(inp.dataset.kfTargetId) : null;
      var nameSel = inp.dataset.kfTargetName ? document.querySelector(inp.dataset.kfTargetName) : null;
      if (idSel) idSel.value = it.id || '';
      if (nameSel) nameSel.value = it.name || it.label || '';
      inp.dispatchEvent(new Event('change', { bubbles: true }));
      close();
      // Also emit a small custom event for other listeners
      try { inp.dispatchEvent(new CustomEvent('kf:select', { detail: it })); } catch(e){}
    }

    function doSearch(q){
      var base = (moduleBase().replace(/\/+$/,'') || '');
      var url = (base || '') + '/api/lookup/' + encodeURIComponent(entity) + '?q=' + encodeURIComponent(q) + '&limit=' + (parseInt(inp.dataset.kfLimit||20,10)||20);
      console.log('[KF.lookup] query →', entity, q, url);
      fetch(url, { credentials:'same-origin', headers:{Accept:'application/json'} })
        .then(function(r){ 
          if (!r.ok) {
            console.warn('[KF.lookup] server returned', r.status, r.statusText);
            return [];
          }
          return r.json();
        })
        .then(function(js){
          console.log('[KF.lookup] response', entity, q, js);
          items = Array.isArray(js.items)? js.items : (Array.isArray(js) ? js : []);
          render(items);
          // fallback: if no items and q is numeric try exact-id param
          if ((!items || items.length === 0) && /^\d+$/.test(q)) {
            var url2 = (base || '') + '/api/lookup/' + encodeURIComponent(entity) + '?id=' + encodeURIComponent(q);
            console.log('[KF.lookup] fallback id lookup →', url2);
            return fetch(url2, { credentials:'same-origin', headers:{Accept:'application/json'} })
              .then(r=> r.ok ? r.json() : [])
              .then(js2 => {
                console.log('[KF.lookup] fallback response', js2);
                items = Array.isArray(js2.items) ? js2.items : (Array.isArray(js2)?js2:[]);
                render(items);
              })
              .catch(()=>{});
          }
        })
        .catch(function(err){
          console.warn('[KF.lookup] error', err);
          items=[]; close();
        });
    }

    inp.addEventListener('input', function(e){
      var q = (e.target.value||'').trim();
      if (q.length < (parseInt(inp.dataset.kfMin||1,10) || 1)){ close(); return; }
      if (timer) clearTimeout(timer);
      timer = setTimeout(function(){ if (q===lastQ) return; lastQ=q; doSearch(q); }, parseInt(inp.dataset.kfDebounce||180,10));
    });

    inp.addEventListener('keydown', function(e){
      if (!list.classList.contains('open')) return;
      var rows = Array.prototype.slice.call(list.querySelectorAll('.kf-item')).filter(function(r){ return !r.classList.contains('text-slate-400'); });
      if (e.key === 'ArrowDown'){ e.preventDefault(); highlight(Math.min(idx+1, rows.length-1)); }
      else if (e.key === 'ArrowUp'){ e.preventDefault(); highlight(Math.max(idx-1,0)); }
      else if (e.key === 'Enter'){ e.preventDefault(); if (idx>=0) pick(idx); }
      else if (e.key === 'Escape'){ close(); }
    });

    document.addEventListener('click', function(ev){ if (!wrap.contains(ev.target)) close(); });
  }

  function scan(){ document.querySelectorAll('[data-kf-lookup]').forEach(bindOne); }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan);
  else scan();
  window.KF = window.KF || {}; window.KF.rescan = function(){ scan(); };
})();