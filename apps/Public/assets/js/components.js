/* KlinFlow universal Alpine components */
(function () {
  window.KF = window.KF || {};

  // Generic typeahead
  // Usage: x-data="ta('/t/{slug}/apps/dms/api/lookup/customers', it => { ... })"
  // - url     : endpoint returning { ok, items: [{id,label?,'name'?...}] }
  // - onPick  : optional callback(it) when an item is selected
  function makeTA(url, onPick) {
    return {
      q: "",
      items: [],
      open: false,
      picked: null,
      busy: false,
      min: 1,
      async search() {
        const q = (this.q || "").trim();
        if (q.length < this.min) {
          this.items = [];
          this.open = false;
          return;
        }
        this.busy = true;
        try {
          const u = new URL(url, location.origin);
          u.searchParams.set("q", q);
          const res = await fetch(u.toString(), {
            headers: { Accept: "application/json" },
            credentials: "same-origin",
          });
          const js = await res.json().catch(() => ({}));
          this.items = Array.isArray(js.items) ? js.items : [];
          this.open = this.items.length > 0;
        } catch (e) {
          this.items = [];
          this.open = false;
        } finally {
          this.busy = false;
        }
      },
      pick(it) {
        this.picked = it;
        this.q = it.label || it.name || "";
        this.open = false;
        try { if (typeof onPick === "function") onPick(it); } catch (e) {}
      },
      blur(e) {
        const t = e?.relatedTarget;
        // keep open if the next focused el is inside the popup
        if (!t || !t.closest) { this.open = false; return; }
        const list = e.currentTarget.parentElement.querySelector(".kf-ta-list");
        if (!list || !list.contains(t)) this.open = false;
      },
    };
  }

  window.KF.ta = makeTA;
  window.ta = (url, onPick) => window.KF.ta(url, onPick);
})();