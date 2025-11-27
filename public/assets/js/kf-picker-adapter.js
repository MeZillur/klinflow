// Adapter: auto-inject search button for inputs with data-kf-lookup and bind to KF.picker
(function(){
  if (!window.KF) window.KF = {};
  function init(){
    if (window.__kf_picker_adapter_inited) return; window.__kf_picker_adapter_inited = true;

    function injectFor(input){
      if (!input || input.dataset.kfPickerInjected === '1') return;
      input.dataset.kfPickerInjected = '1';

      // Wrap input
      const wrapper = document.createElement('div');
      wrapper.style.display = 'flex';
      wrapper.style.gap = '6px';
      wrapper.style.alignItems = 'center';
      input.parentNode.insertBefore(wrapper, input);
      wrapper.appendChild(input);

      // Create small button
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn';
      btn.title = 'Search';
      btn.style.flex = '0 0 auto';
      btn.innerHTML = '🔍';
      wrapper.appendChild(btn);

      btn.addEventListener('click', function(e){
        e.preventDefault();
        const entity = input.dataset.kfLookup || input.getAttribute('data-kf-lookup');
        const endpoint = input.dataset.kfEndpoint || null;
        const targets = {
          id: input.dataset.kfTargetId || '.pid',
          name: input.dataset.kfTargetName || '.' + (Array.from(input.classList).join('.') || 'prod_input'),
          price: input.dataset.kfTargetPrice || '.price',
          code: input.dataset.kfTargetCode || '.code'
        };
        if (window.KF && KF.picker && typeof KF.picker.show === 'function') {
          KF.picker.show({ entity, endpoint, host: wrapper, targets, q: input.value || '' });
        } else {
          console.warn('KF.picker not available');
        }
      });
    }

    // scan existing inputs
    Array.from(document.querySelectorAll('input[data-kf-lookup], [data-kf-lookup]')).forEach(injectFor);

    // observe DOM for dynamic inputs
    const mo = new MutationObserver(function(records){
      records.forEach(r => {
        Array.from(r.addedNodes || []).forEach(n => {
          if (!(n instanceof HTMLElement)) return;
          if (n.matches && n.matches('input[data-kf-lookup], [data-kf-lookup]')) injectFor(n);
          Array.from(n.querySelectorAll && n.querySelectorAll('input[data-kf-lookup], [data-kf-lookup]') || []).forEach(injectFor);
        });
      });
    });
    mo.observe(document.body, { childList: true, subtree: true });

    // allow explicit hookup on rescan
    document.addEventListener('kf:rescan', function(e){
      const root = (e && e.detail && e.detail.root) || document;
      Array.from((root||document).querySelectorAll('input[data-kf-lookup], [data-kf-lookup]')).forEach(injectFor);
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();