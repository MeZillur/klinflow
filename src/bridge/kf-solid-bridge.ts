// Provides an imperative bridge so window.KF.picker.show(...) and KF.toast(...) work.
// The bridge does not overwrite your existing KF.* if already present; it only attaches
// missing methods and delegates to Solid via DOM events.

export interface PickerOpts {
  entity?: string;
  endpoint?: string | null;
  host?: HTMLElement | null;
  targets?: { id?: string; name?: string; price?: string; code?: string };
  q?: string;
  limit?: number;
  title?: string;
  onPick?: (item: any, host?: HTMLElement | null) => void;
}

export interface BridgeAPI {
  showPicker: (opts: PickerOpts) => Promise<void>;
  toast: (message: string, opts?: any) => void;
}

declare global {
  interface Window { KF: any; }
}

export function initKFBridge(api: BridgeAPI) {
  window.KF = window.KF || {};
  window.KF.picker = window.KF.picker || {};
  window.KF.picker.show = window.KF.picker.show || function(opts: PickerOpts){ return api.showPicker(opts); };
  window.KF.toast = window.KF.toast || function(msg: string, opts?: any){ return api.toast(msg, opts); };

  // Add a writeToHost helper if not present (keeps compatibility)
  window.KF.writeToHost = window.KF.writeToHost || function(host: HTMLElement | null, selector: string, value: any){
    if (!selector) return;
    try {
      let node = null;
      if (host && typeof (host as HTMLElement).querySelector === 'function') {
        try { node = host.querySelector(selector); } catch(_) { node = null; }
      }
      if (!node) node = document.querySelector(selector);
      if (!node) return;
      if ('value' in node) (node as HTMLInputElement).value = (value == null ? '' : String(value));
      else if ((node as HTMLElement).isContentEditable) node.textContent = (value == null ? '' : String(value));
      else node.textContent = (value == null ? '' : String(value));
      node.dispatchEvent(new Event('change', { bubbles: true }));
    } catch (e) {
      console.warn('KF.writeToHost failed', e);
    }
  };

  // allow other code to wait for bridge
  document.dispatchEvent(new CustomEvent('kf:bridge:ready', { detail: { KF: window.KF } }));
}