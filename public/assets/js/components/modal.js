// Optional modal helper for JS-only contexts.
// For PHP templates, use modules/Shared/Views/components/modal.php
export function kfModal(el){
  return {
    open:false,
    show(){ this.open=true; },
    hide(){ this.open=false; },
  };
}