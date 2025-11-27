// Tiny KF.modal helper (skeleton)
(function(){
  if (!window.KF) window.KF = {};
  KF.modal = KF.modal || {
    alert(msg){ window.alert(msg); },
    confirm(msg){ return window.confirm(msg); }
    // replace with nicer UI later
  };
})();