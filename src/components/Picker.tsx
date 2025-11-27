import { createSignal, createEffect, onCleanup } from 'solid-js';

type Item = any;

export default function Picker() {
  const [visible, setVisible] = createSignal(false);
  const [query, setQuery] = createSignal('');
  const [items, setItems] = createSignal<Item[]>([]);
  let optsRef: any = null;

  async function doSearch(q?: string) {
    try {
      const o = optsRef || {};
      const qv = (q ?? query()) || '';
      // Call KF.lookup.fetch (bridge provides it in global KF)
      const arr = (window.KF && window.KF.lookup && typeof window.KF.lookup.fetch === 'function')
        ? await window.KF.lookup.fetch(o.entity, qv, o.limit || 30, o.endpoint)
        : [];
      setItems(Array.isArray(arr) ? arr : []);
    } catch (e) {
      console.warn('Picker search failed', e);
      setItems([]);
    }
  }

  function open(o: any) {
    optsRef = o || {};
    setQuery(o.q || '');
    setVisible(true);
    // initial search
    setTimeout(()=> doSearch(o.q || ''), 0);
  }

  function close() {
    setVisible(false);
    optsRef = null;
    setItems([]);
  }

  // Listen to show events from bridge
  function onShow(e: any) {
    open(e.detail || {});
  }

  // pick an item and write to host then call onPick
  function pick(i: number) {
    const it = items()[i];
    if (!it) return;
    const host = (optsRef && optsRef.host) || document;
    const tg = optsRef?.targets || {};
    try {
      if (tg.id) window.KF.writeToHost(host, tg.id, it.id ?? '');
      if (tg.name) window.KF.writeToHost(host, tg.name, it.label || it.name || '');
      if (tg.price) window.KF.writeToHost(host, tg.price, it.unit_price ?? it.price ?? '');
      if (tg.code) window.KF.writeToHost(host, tg.code, it.code ?? '');
    } catch(e){ console.warn('picker pick write failed', e); }
    try { if (typeof optsRef.onPick === 'function') optsRef.onPick(it, host); } catch(e){ console.warn(e); }
    close();
  }

  // subscribe to global event
  const handler = (e: any) => onShow(e);
  document.addEventListener('kf:picker:show', handler as EventListener);

  onCleanup(()=> document.removeEventListener('kf:picker:show', handler as EventListener));

  // keyboard navigation and focus handling is left intentionally minimal
  return (
    <>
      {visible() && (
        <div class="kf-picker-backdrop" style={{ position: 'fixed', inset: '0', display: 'flex', 'align-items': 'center', 'justify-content': 'center', 'z-index': '120000' }}>
          <div class="kf-picker-modal" style={{ width: 'min(980px,96vw)', 'max-height': '86vh', background: '#fff', padding: '12px', borderRadius: '8px', overflow: 'auto' }}>
            <div style={{ display: 'flex', 'justify-content': 'space-between', 'align-items': 'center', 'margin-bottom': '8px' }}>
              <strong>{optsRef?.title || ('Search ' + (optsRef?.entity || ''))}</strong>
              <button onClick={close}>✕</button>
            </div>
            <div style={{ marginBottom: '8px', display: 'flex', gap: '8px' }}>
              <input value={query()} onInput={(e: any)=> setQuery(e.target.value)} placeholder="Search…" style={{ flex:1, padding:'8px', border:'1px solid #ddd', borderRadius:'6px' }} />
              <button onClick={()=>doSearch(query())}>Search</button>
            </div>
            <div style={{ 'max-height': '50vh', overflow: 'auto' }}>
              {items().length === 0 ? <div style={{ padding:'12px' }}>No results</div> : items().map((it, i) =>
                <div class="kf-picker-row" tabindex={0} style={{ padding:'8px', borderBottom: '1px solid #f3f3f3', cursor: 'pointer' }} onClick={()=>pick(i)}>
                  <div style={{ fontWeight:600 }}>{it.label || it.name || it.code || ('ID ' + (it.id ?? ''))}</div>
                  <div style={{ fontSize:'12px', color:'#475569' }}>{it.sublabel || it.sku || ''}</div>
                </div>
              )}
            </div>
          </div>
        </div>
      )}
    </>
  );
}