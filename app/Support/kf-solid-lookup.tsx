/** 
 * Simple Solid Lookup component (TSX)
 * Save under app/Support/kf-solid-lookup.tsx (or move into your Solid src/ folder)
 *
 * Usage (Solid):
 *  import Lookup from './kf-solid-lookup';
 *  <Lookup entity="products" onSelect={(item) => console.log(item)} placeholder="Search products..." />
 *
 * Note: Ideally move this file into your Solid app source (src/) and import in your build.
 */

import { createSignal, createEffect, onCleanup, For, JSX } from 'solid-js';

type LookupItem = {
  id: string | number;
  label: string;
  name?: string;
  code?: string;
  price?: any;
  meta?: Record<string, any>;
};

type Props = {
  entity: string;
  placeholder?: string;
  debounceMs?: number;
  minLength?: number;
  onSelect?: (item: LookupItem) => void;
  class?: string;
};

export default function Lookup(props: Props): JSX.Element {
  const [q, setQ] = createSignal('');
  const [items, setItems] = createSignal<LookupItem[]>([]);
  const [open, setOpen] = createSignal(false);
  const [loading, setLoading] = createSignal(false);
  const [highlight, setHighlight] = createSignal(0);

  const debounceMs = props.debounceMs ?? 200;
  const minLength = props.minLength ?? 1;

  let abortCtrl: AbortController | null = null;
  let timer: number | undefined;

  function moduleBase(): string {
    try {
      if (typeof window !== 'undefined' && window.KF && window.KF.moduleBase) return window.KF.moduleBase;
      const meta = document.querySelector('meta[name="kf-module-base"]') as HTMLMetaElement;
      if (meta) return meta.content || '';
    } catch (e) {}
    return '';
  }

  function doFetch(qstr: string) {
    if (abortCtrl) abortCtrl.abort();
    abortCtrl = new AbortController();
    setLoading(true);
    const base = (moduleBase().replace(/\/+$/,'') || '');
    const url = (base || '') + '/api/lookup/' + encodeURIComponent(props.entity) + '?q=' + encodeURIComponent(qstr) + '&limit=20';
    fetch(url, { credentials: 'same-origin', signal: abortCtrl.signal })
      .then(r => r.ok ? r.json() : Promise.reject(new Error('Lookup failed')))
      .then(js => {
        setItems(Array.isArray(js.items) ? js.items : []);
        setOpen(true);
        setHighlight(0);
      })
      .catch(() => {
        setItems([]);
        setOpen(false);
      })
      .finally(() => setLoading(false));
  }

  function scheduleFetch(val: string) {
    if (timer) clearTimeout(timer);
    if (val.length < minLength) {
      setItems([]);
      setOpen(false);
      return;
    }
    timer = setTimeout(() => doFetch(val), debounceMs);
  }

  createEffect(() => {
    const val = q();
    scheduleFetch(val);
  });

  onCleanup(() => {
    if (abortCtrl) abortCtrl.abort();
    if (timer) clearTimeout(timer);
  });

  function choose(item: LookupItem) {
    setQ(item.label);
    setOpen(false);
    if (props.onSelect) props.onSelect(item);
    // dispatch change on enclosing input if needed (for legacy bindings)
    // (no-op here: parent should handle onSelect)
  }

  function onKey(e: KeyboardEvent) {
    if (!open()) return;
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setHighlight((n) => Math.min(n + 1, items().length - 1));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setHighlight((n) => Math.max(n - 1, 0));
    } else if (e.key === 'Enter') {
      e.preventDefault();
      const it = items()[highlight()];
      if (it) choose(it);
    } else if (e.key === 'Escape') {
      setOpen(false);
    }
  }

  return (
    <div class={props.class || 'kf-lookup-wrapper'} style={{ position: 'relative' }}>
      <input
        type="text"
        value={q()}
        onInput={(e) => { setQ((e.target as HTMLInputElement).value); }}
        onKeyDown={onKey}
        placeholder={props.placeholder || ''}
        class="kf-lookup-input"
        autocomplete="off"
      />
      <div style={{ position: 'absolute', right: '8px', top: '8px' }}>
        {loading() ? <span>...</span> : null}
      </div>

      <div class={`kf-suggest ${open() && items().length ? 'open' : ''}`} style={{ display: open() && items().length ? 'block' : 'none', zIndex: '1000' }}>
        <For each={items()}>
          {(it, i) => (
            <div
              class={`kf-item ${highlight() === i() ? 'highlight' : ''}`}
              onMouseEnter={() => setHighlight(i())}
              onMouseDown={(ev) => { ev.preventDefault(); choose(it); }}
            >
              <div class="kf-title">{it.label}</div>
              <div class="kf-sub">{it.code || it.name || ''}</div>
            </div>
          )}
        </For>
      </div>
    </div>
  );
}