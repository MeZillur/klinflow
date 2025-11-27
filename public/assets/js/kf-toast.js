// KF.toast (skeleton)
(function(){
  if (!window.KF) window.KF = {};
  KF.toast = KF.toast || function(msg, opts){
    opts = opts || {};
    let box = document.getElementById('kf-toast');
    if (!box){ box = document.createElement('div'); box.id='kf-toast'; box.style.position='fixed'; box.style.right='12px'; box.style.bottom='12px'; box.style.zIndex='100100'; document.body.appendChild(box); }
    const el = document.createElement('div'); el.className='kf-toast-item'; el.textContent = msg; el.style.background = '#fff'; el.style.padding='8px 12px'; el.style.marginTop='8px'; el.style.border = '1px solid #ddd';
    box.appendChild(el);
    setTimeout(()=>{ try{ el.style.opacity='0'; setTimeout(()=>el.remove(), 300); }catch{} }, opts.duration||3000);
  };
})();