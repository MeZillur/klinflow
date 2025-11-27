// Small KF.form helper (skeleton) — ajax form submit with CSRF + double-submit guard
;(function(){
  if (!window.KF) window.KF = {};
  KF.form = KF.form || {
    submit: async function(formEl, opts = {}){
      if (!formEl) throw new Error('form required');
      if (formEl.dataset.kfSubmitting==='1') return;
      formEl.dataset.kfSubmitting = '1';
      try {
        const action = formEl.action || opts.url;
        const method = (formEl.method || opts.method || 'POST').toUpperCase();
        const fd = new FormData(formEl);
        const headers = { 'X-Requested-With':'XMLHttpRequest' };
        const r = await fetch(action, { method, body: fd, credentials: 'include', headers });
        const js = await (r.ok ? r.json().catch(()=>null) : null);
        return { ok: r.ok, status: r.status, data: js };
      } finally {
        delete formEl.dataset.kfSubmitting;
      }
    }
  };
})();