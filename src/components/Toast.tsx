import { createSignal, For } from 'solid-js';

type ToastItem = { id: number; title?: string; body: string; type?: string; ttl?: number };

let idCounter = 1;
const [items, setItems] = createSignal<ToastItem[]>([]);

export const toastController = {
  push(msg: string, opts: any = {}) {
    const id = idCounter++;
    const item: ToastItem = { id, title: opts.title, body: msg, type: opts.type || 'info', ttl: opts.duration || 3500 };
    setItems(prev => [...prev, item]);
    if (item.ttl && item.ttl > 0) {
      setTimeout(()=> {
        setItems(prev => prev.filter(i => i.id !== id));
      }, item.ttl);
    }
  },
  clear() { setItems([]); }
};

export default function ToastApp(){
  return (
    <div id="kf-toast" style={{ position:'fixed', right:'12px', bottom:'12px', display:'flex', 'flex-direction':'column', gap:'8px', 'z-index':'120000' }}>
      <For each={items()}>
        {it => (
          <div class={`kf-toast-item ${it.type || 'info'}`} style={{ display:'flex', gap:'10px', padding:'10px 12px', 'min-width':'220px', 'max-width':'420px', 'align-items':'center' }}>
            <div class={`kf-toast-icon`} style={{ width:'28px', height:'28px', 'border-radius':'6px', display:'inline-flex', 'align-items':'center', 'justify-content':'center', color:'#fff', 'flex':'0 0 28px', 'font-size':'14px' }}>
              {it.type === 'error' ? '✕' : it.type === 'success' ? '✓' : 'i'}
            </div>
            <div style={{ display:'flex', 'flex-direction':'column', gap:'4px' }}>
              {it.title && <div style={{ 'font-weight':600 }}>{it.title}</div>}
              <div style={{ color:'#0b1220' }}>{it.body}</div>
            </div>
          </div>
        )}
      </For>
    </div>
  );
}