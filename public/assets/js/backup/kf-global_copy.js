;(() => {
  "use strict";

  /*
   =====================================================================
   KlinFlow Global Core — kf-global.js
   Full IIFE (refined). This build is safe to drop in place of the existing
   kf-global.js used across modules. Changes focused on making lookup
   requests include credentials (cookies) so tenant session is available
   server-side (fixes empty supplier/items responses), and making Enter
   + mouse selection work reliably for KF.lookup/typeahead.
   =====================================================================
  */

  /* ──────────────────────────────────────────────────────────────
   * SEGMENT 0: Namespace + module base + asset config (GLOBAL)
   * ──────────────────────────────────────────────────────────── */

  const KF = (window.KF = window.KF || {});

  const META_BASE =
    document
      .querySelector('meta[name="kf-module-base"]')
      ?.content?.replace(/\/+$/, "") || "";

  KF.moduleBase = META_BASE || KF.moduleBase || "";
  KF.cfg = KF.cfg || {};

  KF.assets = Object.assign(
    {
      CHOICES_JS: "/assets/ui/choices/choices.min.js",
      CHOICES_CSS: "/assets/ui/choices/choices.min.css",
      CHOICES_JS_CDN:
        "https://cdn.jsdelivr.net/npm/choices.js@10.2.0/public/assets/scripts/choices.min.js",
      CHOICES_CSS_CDN:
        "https://cdn.jsdelivr.net/npm/choices.js@10.2.0/public/assets/styles/choices.min.css",
    },
    window.KF?.assets || {}
  );

  KF.version = KF.version || "2025-11-25.kf-global";

  /* ──────────────────────────────────────────────────────────────
   * SEGMENT 1: Small utilities
   * ──────────────────────────────────────────────────────────── */

  const ready = (fn) =>
    document.readyState !== "loading"
      ? fn()
      : document.addEventListener("DOMContentLoaded", fn, { once: true });

  const idle = (fn) =>
    (window.requestIdleCallback || window.setTimeout)(fn, { timeout: 500 });

  const once = (el, id) => {
    const k = `data-kf-once-${id}`;
    if (el.hasAttribute(k)) return false;
    el.setAttribute(k, "1");
    return true;
  };

  function debounce(fn, ms) {
    let t;
    return function (...a) {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(this, a), ms);
    };
  }
  KF.debounce = debounce;

  /* ──────────────────────────────────────────────────────────────
   * SEGMENT 2: Brand style
   * ──────────────────────────────────────────────────────────── */

  (function injectKFStyle() {
    if (document.getElementById("kf-brand-style")) return;

    const css = `
      :root { --brand:#228B22; }
      .kf-suggest {
        position:absolute;
        left:0;
        right:0;
        margin-top:.25rem;
        border:1px solid #c6e6c6;
        border-radius:.5rem;
        background:#fff;
        box-shadow:0 6px 12px rgba(0,0,0,.06);
        max-height:14rem;
        overflow:auto;
        font-family:system-ui,-apple-system,Segoe UI,Inter,sans-serif;
        font-size:.9rem;
        opacity:0;
        transform:translateY(-2px);
        transition:opacity .12s ease,transform .12s ease;
        z-index:100000;
      }
      .kf-suggest.open { opacity:1; transform:translateY(0); }
      .kf-suggest .kf-item { padding:.45rem .75rem; cursor:pointer; border-bottom:1px solid #f0fdf4; }
      .kf-suggest .kf-item:hover, .kf-suggest .kf-item[aria-selected="true"] { background:#e9fbe9; }
      .kf-suggest .kf-title { color:#065f46; font-weight:600; }
      .kf-suggest .kf-sub { color:#475569; font-size:.75rem; }
      @media (prefers-color-scheme: dark){
        .kf-suggest{ background:#0f172a; border-color:#334155; box-shadow:0 8px 18px rgba(0,0,0,.6); }
        .kf-suggest .kf-item{ border-bottom:1px solid #1f2937; }
        .kf-suggest .kf-item:hover, .kf-suggest .kf-item[aria-selected="true"]{ background:#114f22; }
        .kf-suggest .kf-title{ color:#86efac } .kf-suggest .kf-sub{ color:#cbd5e1 }
      }
      /* highest stacking for lookups / choices dropdowns */
      .kf-suggest, .choices__list--dropdown { z-index: 999999 !important; }
    `.trim();

    const el = document.createElement("style");
    el.id = "kf-brand-style";
    el.textContent = css;
    document.head.appendChild(el);
  })();

  /* ──────────────────────────────────────────────────────────────
   * SEGMENT 3: Loader + preconnect
   * ──────────────────────────────────────────────────────────── */

  KF.load = (() => {
    const cache = new Map();

    function preconnect(url) {
      try {
        const u = new URL(url, location.href);
        const href = `${u.protocol}//${u.host}`;
        if (document.querySelector(`link[rel="preconnect"][href="${href}"]`))
          return;
        const l = document.createElement("link");
        l.rel = "preconnect";
        l.href = href;
        l.crossOrigin = "";
        document.head.appendChild(l);
      } catch {}
    }

    function js(src) {
      if (cache.has(src)) return cache.get(src);
      preconnect(src);
      const p = new Promise((res, rej) => {
        const s = document.createElement("script");
        s.src = src;
        s.defer = true;
        s.onload = res;
        s.onerror = rej;
        document.head.appendChild(s);
      });
      cache.set(src, p);
      return p;
    }

    function css(href) {
      if (document.querySelector(`link[href="${href}"]`))
        return Promise.resolve();
      preconnect(href);
      return new Promise((res) => {
        const l = document.createElement("link");
        l.rel = "stylesheet";
        l.href = href;
        l.onload = res;
        document.head.appendChild(l);
      });
    }

    async function module(path) {
      return import(/* @vite-ignore */ path);
    }

    return { js, css, module, preconnect };
  })();

  /* ──────────────────────────────────────────────────────────────
   * SEGMENT 4: API wrapper + LRU cache + keys + toast
   * ──────────────────────────────────────────────────────────── */

  KF.api = async function (url, opts = {}) {
    const o = {
      method: "GET",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      credentials: "include",
      ...opts,
    };

    if (o.body && !(o.body instanceof FormData)) {
      o.headers["Content-Type"] = "application/json";
      o.body = JSON.stringify(o.body);
    }

    const token =
      document.querySelector('meta[name="csrf-token"]')?.content || null;
    if (token) o.headers["X-CSRF-Token"] = token;

    const r = await fetch(url, o);
    const ct = r.headers.get("content-type") || "";
    const data = ct.includes("json")
      ? await r.json().catch(() => null)
      : await r.text();

    if (!r.ok) throw Object.assign(new Error("HTTP " + r.status), { data });

    return data;
  };

  KF.LRU = function (max = 100) {
    const m = new Map();
    return {
      get(k) {
        if (!m.has(k)) return;
        const v = m.get(k);
        m.delete(k);
        m.set(k, v);
        return v;
      },
      set(k, v) {
        if (m.has(k)) m.delete(k);
        m.set(k, v);
        if (m.size > max) m.delete(m.keys().next().value);
      },
      has: (k) => m.has(k),
      clear: () => m.clear(),
      size: () => m.size,
    };
  };

  KF.keys = (() => {
    const handlers = new Set();
    function on(fn) {
      handlers.add(fn);
      return () => handlers.delete(fn);
    }
    addEventListener(
      "keydown",
      (e) => {
        const t = (e.target?.tagName || "").toLowerCase();
        if (["input", "textarea", "select"].includes(t) || e.isComposing)
          return;
        for (const h of handlers) {
          if (h(e) === false) break;
        }
      },
      { capture: true }
    );
    return { on };
  })();

  KF.toast = function (msg) {
    let box = document.getElementById("kf-toast");
    if (!box) {
      box = document.createElement("div");
      box.id = "kf-toast";
      box.style.position = "fixed";
      box.style.right = "1rem";
      box.style.bottom = "1rem";
      box.style.zIndex = "999999";
      document.body.appendChild(box);
    }
    const t = document.createElement("div");
    t.className =
      "mb-2 rounded-xl border bg-white shadow-lg px-3 py-2 text-sm";
    t.textContent = msg;
    box.appendChild(t);
    setTimeout(() => {
      t.style.opacity = "0";
      t.style.transition = "opacity .3s";
    }, 2500);
    setTimeout(() => {
      if (t.parentNode === box) box.removeChild(t);
    }, 3000);
  };

  /* ──────────────────────────────────────────────────────────────
   * SEGMENT 5: Choices.js bootstrap (select[data-choices])
   * - Note: when doing AJAX fetches we include credentials: 'include'
   * ──────────────────────────────────────────────────────────── */

  KF.choices = (() => {
    const registry = new WeakMap();
    const dnow = Date.now;

    const debounceLocal = (fn, ms) => {
      let t;
      return (...a) => {
        clearTimeout(t);
        t = setTimeout(() => fn(...a), ms);
      };
    };

    const toBool = (v, d) => {
      if (v == null) return d;
      const s = String(v).toLowerCase().trim();
      if (["1", "true", "yes", "on"].includes(s)) return true;
      if (["0", "false", "no", "off"].includes(s)) return false;
      return d;
    };

    function parseOptions(el) {
      return {
        searchEnabled: toBool(el.dataset.choicesSearch, true),
        removeItemButton: toBool(el.dataset.choicesRemoveitembutton, false),
        allowHTML: toBool(el.dataset.choicesAllowhtml, false),
        shouldSort: toBool(
          el.dataset.choicesShouldsort ?? el.dataset.choicesSort,
          true
        ),
        placeholder: true,
        placeholderValue: el.dataset.choicesPlaceholder ?? "",
        noResultsText: el.dataset.choicesNoresults ?? "No results found",
        noChoicesText:
          el.dataset.choicesNoselections ?? "No choices to choose from",
        itemSelectText: "",
        position: "auto",
      };
    }

    function writeVal(selector, v) {
      if (!selector) return;
      const el =
        selector.startsWith("#") || selector.startsWith(".")
          ? document.querySelector(selector)
          : null;
      if (!el) return;
      el.value = String(v ?? "");
      el.dispatchEvent(new Event("change", { bubbles: true }));
    }

    function bindAjax(el, inst) {
      const urlBase = el.dataset.choicesAjax;
      const valueKey = el.dataset.valueKey || "id";
      const labelKey = el.dataset.labelKey || "label";

      const tgtId = el.dataset.targetId || null;
      const tgtName = el.dataset.targetName || null;
      const tgtCode = el.dataset.targetCode || null;
      const tgtPrice = el.dataset.targetPrice || null;

      const cache = new Map();
      let lastQ = "";
      let lastFetchAt = 0;

      const rebuild = (rows) => {
        el._kfRows = rows;
        const toChoices = rows.map((r) => ({
          value: String(r[valueKey]),
          label: String(r[labelKey] ?? r.name ?? r.code ?? r.id),
          customProperties: r,
        }));
        inst.clearChoices();
        if (toChoices.length) inst.setChoices(toChoices, "value", "label", true);
        else inst.setChoices([], "value", "label", true);
      };

      const run = debounceLocal(async () => {
        const input = el.parentElement?.querySelector(".choices__input");
        const q = (input?.value || "").trim();

        if (cache.has(q)) {
          rebuild(cache.get(q));
          return;
        }

        const sep = urlBase.includes("?") ? "&" : "?";
        const url = q ? urlBase + sep + "q=" + encodeURIComponent(q) : urlBase;
        lastQ = q;
        lastFetchAt = dnow();

        try {
          const res = await fetch(url, {
            headers: { Accept: "application/json" },
            credentials: "include",
          });
          const js = await res.json().catch(() => ({}));
          const rows = Array.isArray(js)
            ? js
            : Array.isArray(js.items)
            ? js.items
            : [];
          cache.set(q, rows);
          rebuild(rows);
        } catch {
          /* silent */
        }
      }, 220);

      el.addEventListener("change", () => {
        const v = el.value;
        const row = (el._kfRows || []).find(
          (r) => String(r[valueKey]) === String(v)
        );
        if (!row) return;
        const label = row[labelKey] ?? row.name ?? row.code ?? "";
        writeVal(tgtId, row[valueKey]);
        writeVal(tgtName, label);
        writeVal(tgtCode, row.code ?? "");
        if (tgtPrice && row.unit_price != null)
          writeVal(tgtPrice, row.unit_price);
      });

      el.addEventListener("showDropdown", run, { once: true });
      const input = el.parentElement?.querySelector(".choices__input");
      input && input.addEventListener("input", run);

      if (el.value) {
        (async () => {
          try {
            await run();
            el.dispatchEvent(new Event("change"));
          } catch {}
        })();
      }
    }

    function initOne(el, cfg) {
      if (!el || registry.has(el)) return registry.get(el);
      if (!window.Choices) return null;

      const inst = new window.Choices(el, cfg || parseOptions(el));
      registry.set(el, inst);
      el.dataset.kfChoices = "1";

      if (el.dataset.choicesAjax) bindAjax(el, inst);

      const wrap = el.closest(".choices");
      if (wrap) {
        wrap.style.position = "relative";
        wrap.style.zIndex = 40;
      }

      return inst;
    }

    function destroyOne(el) {
      const inst = registry.get(el);
      if (inst && inst.destroy) {
        try {
          inst.destroy();
        } catch {}
      }
      registry.delete(el);
      delete el.dataset.kfChoices;
    }

    function scan(root) {
      if (!window.Choices) return;
      (root || document)
        .querySelectorAll("select[data-choices]:not([data-kf-choices])")
        .forEach((n) => initOne(n));
    }

    const io =
      "IntersectionObserver" in window
        ? new IntersectionObserver((entries) => {
            if (!entries.some((e) => e.isIntersecting)) return;
            io.disconnect();
            Promise.all([
              KF.load.css(KF.assets.CHOICES_CSS).catch(() =>
                KF.load.css(KF.assets.CHOICES_CSS_CDN)
              ),
              KF.load.js(KF.assets.CHOICES_JS).catch(() =>
                KF.load.js(KF.assets.CHOICES_JS_CDN)
              ),
            ])
              .then(() => scan(document))
              .catch(() => {});
          })
        : null;

    ready(() => {
      const candidates = document.querySelectorAll("select[data-choices]");
      if (!candidates.length) return;

      if (window.Choices) {
        idle(() => scan(document));
        return;
      }

      if (io) {
        candidates.forEach((el, i) => {
          if (i === 0 && once(el, "choices-io")) io.observe(el);
        });
      } else {
        Promise.all([
          KF.load.css(KF.assets.CHOICES_CSS).catch(() =>
            KF.load.css(KF.assets.CHOICES_CSS_CDN)
          ),
          KF.load.js(KF.assets.CHOICES_JS).catch(() =>
            KF.load.js(KF.assets.CHOICES_JS_CDN)
          ),
        ]).then(() => scan(document));
      }
    });

    document.addEventListener("kf:choices:scan", (e) =>
      scan(e.detail?.root || document)
    );
    document.addEventListener("kf:choices:destroy", (e) => {
      const el = e.detail?.el;
      if (el) destroyOne(el);
    });

    return {
      init: scan,
      scan,
      get: (el) => registry.get(el) || null,
      destroy: destroyOne,
      refresh(el) {
        if (!el) return;
        const v = el.value;
        destroyOne(el);
        const inst = initOne(el);
        if (inst && v) inst.setChoiceByValue(v);
        return inst;
      },
    };
  })();

  /* ──────────────────────────────────────────────────────────────
   * SEGMENT 6: Alpine helpers (typeahead, pills) + notifications
   * - include credentials in remote fetches for typeahead too
   * ──────────────────────────────────────────────────────────── */

  function kfTypeaheadFactory() {
    return {
      q: "",
      open: false,
      items: [],
      idx: -1,
      src: "remote",
      remoteUrl: "",
      max: 30,
      min: 1,
      limit: null,
      pool: [],
      ctrl: null,
      labelKey: "",
      valueKey: "id",
      isLoading: false,
      cache: KF.LRU ? new KF.LRU(200) : new Map(),

      async init() {
        const el = this.$el;
        this.src = el.dataset.src || "remote";
        this.max = +el.dataset.max || 30;
        this.min = +el.dataset.min || 1;
        this.limit = +(el.dataset.limit || this.max);
        this.remoteUrl = el.dataset.remoteUrl || el.dataset.endpoint || "";
        this.labelKey = (el.dataset.label || "").trim();
        this.valueKey = (el.dataset.value || "id").trim();

        const W = window.APP || {};
        if (this.src !== "remote" && this.src) {
          this.pool = Array.isArray(W[this.src]) ? W[this.src] : [];
        }

        document.addEventListener("click", (e) => {
          if (!el.contains(e.target)) {
            this.open = false;
            this.$el.setAttribute("aria-expanded", "false");
          }
        });

        el.setAttribute("role", "combobox");
        el.setAttribute("aria-expanded", "false");
        el.setAttribute("aria-haspopup", "listbox");

        const input = el.querySelector('input,[contenteditable="true"]');
        if (input) {
          input.setAttribute("autocomplete", "off");
          input.setAttribute("aria-autocomplete", "list");
          input.addEventListener("keydown", this.onKeyDown.bind(this));
        }
      },

      labelOf(it) {
        if (!it) return "";
        if (this.labelKey && it[this.labelKey] != null)
          return String(it[this.labelKey]);
        const c = [
          "name",
          "label",
          "title",
          "account_name",
          "bank_name",
          "code",
          "sku",
          "email",
          "phone",
        ];
        for (const k of c) if (it[k]) return String(it[k]);
        return String(it.code ?? it.id ?? "");
      },

      onInput: KF.debounce(async function () {
        const t = (this.q || "").trim();
        if (t.length < this.min) {
          this.items = [];
          this.open = false;
          this.idx = -1;
          this.$el.setAttribute("aria-expanded", "false");
          return;
        }

        if (this.src !== "remote" || !this.remoteUrl) {
          const s = t.toLowerCase();
          const list = (this.pool || []).filter((x) =>
            JSON.stringify(x).toLowerCase().includes(s)
          );
          this.items = list.slice(0, this.max);
          this.idx = this.items.length ? 0 : -1;
          this.open = !!this.items.length;
          this.$el.setAttribute("aria-expanded", String(this.open));
          return;
        }

        const key = `${t}::${this.limit}`;
        if (this.cache.get && this.cache.get(key)) {
          this.items = this.cache.get(key);
          this.idx = this.items.length ? 0 : -1;
          this.open = !!this.items.length;
          this.$el.setAttribute("aria-expanded", String(this.open));
          return;
        }

        try {
          this.ctrl && this.ctrl.abort();
        } catch {}
        this.ctrl = new AbortController();

        let url = this.remoteUrl;
        url += (url.includes("?") ? "&" : "?") + "q=" + encodeURIComponent(t);
        if (this.limit) url += "&limit=" + encodeURIComponent(this.limit);

        this.isLoading = true;
        try {
          const res = await fetch(url, {
            headers: { Accept: "application/json" },
            signal: this.ctrl.signal,
            credentials: "include",
          });
          if (!res.ok) throw new Error("HTTP " + res.status);
          const data = await res.json();
          const arr = Array.isArray(data)
            ? data
            : Array.isArray(data.items)
            ? data.items
            : [];
          this.items = arr.slice(0, this.max);
          this.cache.set
            ? this.cache.set(key, this.items)
            : this.cache.set(key, this.items);
        } catch (e) {
          if (e?.name !== "AbortError")
            console.warn("[kfTypeahead] fetch failed:", e);
          this.items = [];
        } finally {
          this.isLoading = false;
          this.idx = this.items.length ? 0 : -1;
          this.open = !!this.items.length;
          this.$el.setAttribute("aria-expanded", String(this.open));
        }
      }, 150),

      onKeyDown(e) {
        if (!this.open && (e.key === "ArrowDown" || e.key === "ArrowUp")) {
          this.open = !!this.items.length;
          this.$el.setAttribute("aria-expanded", "true");
        }

        switch (e.key) {
          case "ArrowDown":
            e.preventDefault();
            if (!this.items.length) return;
            this.idx = (this.idx + 1) % this.items.length;
            this.scrollActiveIntoView();
            break;
          case "ArrowUp":
            e.preventDefault();
            if (!this.items.length) return;
            this.idx = (this.idx - 1 + this.items.length) % this.items.length;
            this.scrollActiveIntoView();
            break;
          case "Enter":
            if (this.open && this.idx >= 0) {
              e.preventDefault();
              this.choose(this.idx);
            }
            break;
          case "Escape":
            this.open = false;
            this.$el.setAttribute("aria-expanded", "false");
            break;
        }
      },

      move(d) {
        if (!this.items.length) return;
        this.idx = (this.idx + d + this.items.length) % this.items.length;
        this.scrollActiveIntoView();
      },

      choose(i = null) {
        const it = this.items[i ?? this.idx];
        if (!it) return;
        const input = this.$el.querySelector('input,[contenteditable="true"]');
        if (input && !input.hasAttribute("data-no-fill")) {
          const label = this.labelOf(it);
          if (input.hasAttribute("contenteditable")) input.textContent = label;
          else input.value = label;
        }
        this.$dispatch("select", { item: it });
        this.open = false;
        this.$el.setAttribute("aria-expanded", "false");
      },

      scrollActiveIntoView() {
        const list = this.$el.querySelector("[data-ta-list]");
        if (!list) return;
        const act = list.querySelector("[data-ta-item].is-active");
        if (!act) return;
        const lb = list.getBoundingClientRect();
        const ab = act.getBoundingClientRect();
        if (ab.top < lb.top) list.scrollTop -= lb.top - ab.top;
        else if (ab.bottom > lb.bottom) list.scrollTop += ab.bottom - lb.bottom;
      },
    };
  }

  function kfPillsFactory() {
    return {
      targetSel: "#hidden",
      value: "",
      onCls: "",
      offCls: "",
      init() {
        const el = this.$el;
        this.targetSel = el.dataset.target || this.targetSel;
        this.value = el.dataset.active || "";
        this.onCls =
          el.dataset.on || "bg-green-600 text-white border-green-600";
        this.offCls =
          el.dataset.off || "bg-white text-slate-700 border-slate-300";
        const hid = document.querySelector(this.targetSel);
        if (hid && hid.value) this.value = hid.value;
        this.syncUI();
        el.addEventListener("click", (e) => {
          const b = e.target.closest("[data-val]");
          if (!b) return;
          e.preventDefault();
          this.value = String(b.dataset.val || "");
          const h = document.querySelector(this.targetSel);
          if (h) h.value = this.value;
          this.syncUI();
          this.$dispatch("pill-change", { value: this.value });
        });
        el.addEventListener("keydown", (e) => {
          if (!["ArrowLeft", "ArrowRight"].includes(e.key)) return;
          const btns = Array.from(el.querySelectorAll("[data-val]"));
          if (!btns.length) return;
          const idx = Math.max(
            0,
            btns.findIndex((b) => String(b.dataset.val || "") === this.value)
          );
          const next = e.key === "ArrowRight" ? idx + 1 : idx - 1;
          this.value = String(
            btns[(next + btns.length) % btns.length].dataset.val || ""
          );
          const h = document.querySelector(this.targetSel);
          if (h) h.value = this.value;
          this.syncUI();
          this.$dispatch("pill-change", { value: this.value });
        });
        el.setAttribute("role", "tablist");
        el.querySelectorAll("[data-val]").forEach((b) => {
          b.setAttribute("role", "tab");
          b.setAttribute("tabindex", "0");
        });
      },
      syncUI() {
        this.$el.querySelectorAll("[data-val]").forEach((btn) => {
          const on = String(btn.dataset.val || "") === this.value;
          this.offCls
            .split(/\s+/)
            .filter(Boolean)
            .forEach((c) => btn.classList.remove(c));
          this.onCls
            .split(/\s+/)
            .filter(Boolean)
            .forEach((c) => btn.classList.remove(c));
          (on ? this.onCls : this.offCls)
            .split(/\s+/)
            .filter(Boolean)
            .forEach((c) => btn.classList.add(c));
          btn.setAttribute("aria-selected", on ? "true" : "false");
        });
      },
    };
  }

  function registerNotificationStore() {
    document.addEventListener("alpine:init", () => {
      Alpine.store("notify", {
        base: "",
        open: false,
        unread: 0,
        items: [],
        _timer: null,
        init(moduleBase) {
          if (moduleBase) this.base = moduleBase;
          this.refresh(true);
          this._timer = setInterval(() => this.refresh(false), 30000);
        },
        async refresh(firstLoad) {
          try {
            const [count, list] = await Promise.all([
              KF.api(`${this.base}/api/notifications/count`),
              KF.api(`${this.base}/api/notifications?limit=12`),
            ]);
            const prev = this.unread;
            this.unread = Number(count.unread || 0);
            this.items = Array.isArray(list.items) ? list.items : [];
            if (!firstLoad && this.unread > prev) {
              KF.toast(
                `You have ${this.unread} new notification${
                  this.unread > 1 ? "s" : ""
                }`
              );
            }
          } catch {}
        },
        async read(n) {
          if (!n || !n.id) return;
          try {
            await KF.api(`${this.base}/api/notifications/${n.id}/read`, {
              method: "POST",
            });
            n.is_read = 1;
            if (this.unread > 0) this.unread--;
          } catch {}
        },
        async readAll() {
          try {
            await KF.api(`${this.base}/api/notifications/read-all`, {
              method: "POST",
            });
            this.items = this.items.map((x) => ({ ...x, is_read: 1 }));
            this.unread = 0;
          } catch {}
        },
      });
    });
  }

  function registerAlpineComponents() {
    document.addEventListener("alpine:init", () => {
      window.Alpine.data("kfTypeahead", kfTypeaheadFactory);
      window.Alpine.data("kfPills", kfPillsFactory);
    });
  }

  (function bootAlpine() {
    if (window.Alpine) {
      registerAlpineComponents();
      registerNotificationStore();
    } else {
      document.addEventListener("alpine:init", () => {
        registerAlpineComponents();
        registerNotificationStore();
      });
    }
  })();

  /* ──────────────────────────────────────────────────────────────
   * SEGMENT 7: Example global shortcut (Logout on "L")
   * ──────────────────────────────────────────────────────────── */

  KF.keys.on((e) => {
    if ((e.key || "").toLowerCase() === "l") {
      if (e.altKey || e.ctrlKey || e.metaKey) return;
      e.preventDefault();
      const logoutUrl = "/tenant/logout";
      fetch(logoutUrl, {
        method: "POST",
        credentials: "include",
      })
        .then(() => location.replace("/tenant/login"))
        .catch(() => {
          location.href = logoutUrl;
        });
      return false;
    }
  });

  /* ──────────────────────────────────────────────────────────────
   * SEGMENT 8: Unified Lookup (KF.lookup) — REFINED
   * - Ensures fetch uses credentials: "include"
   * - Fixes mouse selection + Enter selection
   * ──────────────────────────────────────────────────────────── */

  (function initUnifiedLookup() {
    const base = (KF.moduleBase || "").replace(/\/+$/, "");
    const DEBUG = false;

    const ENTITY_ALIASES = {
      item: "items",
      items: "items",
      product: "items",
      products: "items",
      supplier: "suppliers",
      suppliers: "suppliers",
      customer: "customers",
      customers: "customers",
    };

    function normaliseEntity(raw) {
      const key = String(raw || "").toLowerCase().trim();
      return ENTITY_ALIASES[key] || key;
    }

    const escapeHtml = (s) =>
      String(s ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");

    const clamp = (n, min, max) => Math.max(min, Math.min(max, n));

    function urlFor(entity, q, limit, endpointOverride) {
      if (endpointOverride) {
        const u = new URL(endpointOverride, location.origin);
        if (q) u.searchParams.set("q", q);
        if (limit) u.searchParams.set("limit", String(limit));
        return u.toString();
      }
      const canon = normaliseEntity(entity);
      const path = `${base}/api/lookup/${encodeURIComponent(canon)}`;
      const u = new URL(path, location.origin);
      if (q) u.searchParams.set("q", q);
      if (limit) u.searchParams.set("limit", String(limit));
      return u.toString();
    }

    const cache = KF.LRU
      ? new KF.LRU(1500)
      : (function () {
          const m = new Map();
          return {
            get(k) {
              return m.get(k);
            },
            set(k, v) {
              m.set(k, v);
              if (m.size > 1500) m.delete(m.keys().next().value);
            },
            has(k) {
              return m.has(k);
            },
            delete(k) {
              m.delete(k);
            },
            clear() {
              m.clear();
            },
            size() {
              return m.size;
            },
          };
        })();

    function cacheKey(entity, q, limit, endpointOverride) {
      return `${entity}|${endpointOverride || ""}|${q || ""}|${limit || ""}`;
    }

    async function fetchRows(entity, q, limit, callerAborter, endpointOverride) {
      const key = cacheKey(entity, q, limit, endpointOverride);

      const cached = cache.get(key);
      if (cached && Array.isArray(cached)) {
        if (DEBUG) console.debug("[KF.lookup] cache hit array", key);
        return cached;
      }

      if (cached && typeof cached.then === "function") {
        if (DEBUG) console.debug("[KF.lookup] awaiting in-flight promise", key);
        try {
          return await cached;
        } catch {
          return [];
        }
      }

      const url = urlFor(entity, q, limit, endpointOverride);
      const controller = new AbortController();
      const signal = controller.signal;

      if (callerAborter && callerAborter.signal) {
        if (callerAborter.signal.aborted) controller.abort();
        else
          callerAborter.signal.addEventListener(
            "abort",
            () => controller.abort(),
            { once: true }
          );
      }

      const TIMEOUT_MS = 6000;
      const timeoutId = setTimeout(() => controller.abort(), TIMEOUT_MS);

      const p = (async () => {
        try {
          if (DEBUG) console.debug("[KF.lookup] fetching", url);
          const r = await fetch(url, {
            headers: {
              Accept: "application/json",
              "X-Requested-With": "fetch",
            },
            credentials: "include",
            signal,
          });
          if (!r || !r.ok) {
            if (DEBUG)
              console.debug("[KF.lookup] non-ok response", r && r.status);
            return [];
          }
          const js = await r.json().catch(() => null);
          const arr = Array.isArray(js)
            ? js
            : Array.isArray(js.items)
            ? js.items
            : [];
          cache.set(key, arr);
          if (DEBUG)
            console.debug("[KF.lookup] fetched rows", {
              key,
              count: arr.length,
            });
          return arr;
        } catch (err) {
          cache.delete(key);
          if (err?.name !== "AbortError") {
            console.warn("[KF.lookup] fetch error", err);
          } else if (DEBUG) {
            console.debug("[KF.lookup] fetch aborted/timeout", key);
          }
          return [];
        } finally {
          clearTimeout(timeoutId);
        }
      })();

      cache.set(key, p);
      return p;
    }

    const _lookupRegistry = new WeakMap();
    if (!window.__kf_lookup_global) {
      window.__kf_lookup_global = {
        openSet: new Set(),
        installed: true,
        handler_mousedown: null,
      };
      window.__kf_lookup_global.handler_mousedown = function (e) {
        const openSet = window.__kf_lookup_global.openSet;
        if (!openSet.size) return;
        let node = e.target;
        while (node) {
          if (openSet.has(node) || (node._kfPanel && openSet.has(node._kfPanel)))
            return;
          node = node.parentElement;
        }
        openSet.forEach((el) => {
          const st = _lookupRegistry.get(el);
          if (st && st.close) st.close();
        });
        openSet.clear();
      };
      document.addEventListener(
        "mousedown",
        window.__kf_lookup_global.handler_mousedown,
        { capture: true }
      );
    }

    function _markOpen(el, isOpen) {
      const s = window.__kf_lookup_global.openSet;
      if (isOpen) s.add(el);
      else s.delete(el);
    }

    function ensurePanel(anchor) {
      if (anchor._kfPanel && document.body.contains(anchor._kfPanel))
        return anchor._kfPanel;
      const parent =
        anchor.closest(".relative") || anchor.parentElement || anchor;
      if (
        parent &&
        getComputedStyle(parent).position === "static"
      )
        parent.style.position = "relative";
      const p = document.createElement("div");
      p.className = "kf-suggest";
      p.setAttribute("role", "listbox");
      const pid = `kf-suggest-${Math.random().toString(36).slice(2, 9)}`;
      p.id = pid;
      anchor.setAttribute("aria-controls", pid);
      parent.appendChild(p);
      anchor._kfPanel = p;
      const mo = new MutationObserver(() => {
        if (!document.body.contains(anchor)) {
          try {
            if (p.parentNode) p.parentNode.removeChild(p);
          } catch {}
          try {
            mo.disconnect();
          } catch {}
        }
      });
      mo.observe(document.body, { childList: true, subtree: true });
      return p;
    }

    function renderItems(panel, rows, formatter) {
      panel.innerHTML = "";
      if (!rows.length) {
        const empty = document.createElement("div");
        empty.className = "kf-item";
        empty.setAttribute("aria-disabled", "true");
        const sub = document.createElement("div");
        sub.className = "kf-sub";
        sub.textContent = "No matches";
        empty.appendChild(sub);
        panel.appendChild(empty);
      } else {
        rows.forEach((row, i) => {
          const div = document.createElement("div");
          div.className = "kf-item";
          div.setAttribute("role", "option");
          const optId = `kf-opt-${Math.random().toString(36).slice(2, 9)}`;
          div.id = optId;
          div.dataset.index = String(i);
          const formatted = formatter(row);
          if (formatted && formatted.nodeType) {
            div.appendChild(formatted);
          } else if (formatted && typeof formatted.html === "string") {
            div.innerHTML = formatted.html;
          } else {
            const title = document.createElement("div");
            title.className = "kf-title";
            title.textContent =
              (formatted && formatted.text)
                ? String(formatted.text)
                : String(
                    row.name || row.label || row.code || row.id || ""
                  );
            div.appendChild(title);
            const subVal =
              row.sublabel || row.sku || row.barcode || row.email || "";
            if (subVal) {
              const sub = document.createElement("div");
              sub.className = "kf-sub";
              sub.textContent = String(subVal);
              div.appendChild(sub);
            }
          }
          panel.appendChild(div);
        });
      }
      panel.classList.add("open");
    }

    function highlight(panel, idx, input) {
      const children = Array.from(panel.children);
      children.forEach((n, i) => {
        if (n.getAttribute("aria-disabled") === "true") return;
        n.setAttribute("aria-selected", i === idx ? "true" : "false");
      });
      const el = children[idx];
      if (el) {
        const pb = panel.getBoundingClientRect();
        const eb = el.getBoundingClientRect();
        if (eb.top < pb.top) panel.scrollTop -= pb.top - eb.top + 8;
        else if (eb.bottom > pb.bottom)
          panel.scrollTop += eb.bottom - pb.bottom + 8;
        if (input && el.id) input.setAttribute("aria-activedescendant", el.id);
      } else if (input) {
        input.removeAttribute("aria-activedescendant");
      }
    }

    function writeTarget(selector, value) {
      if (!selector) return;
      const node = document.querySelector(selector);
      if (!node) return;
      node.value = value == null ? "" : String(value);
      node.dispatchEvent(new Event("change", { bubbles: true }));
    }

    KF.lookup = KF.lookup || {};
    KF.lookup.bind = function bind(opts) {
      const el =
        typeof opts.el === "string"
          ? document.querySelector(opts.el)
          : opts.el;
      if (!el) return;
      if (el.dataset.kfBound === "1") return;
      el.dataset.kfBound = "1";

      const rawEntity = String(opts.entity || el.dataset.kfLookup || "").trim();
      if (!rawEntity) {
        console.warn("[KF.lookup] Missing data-kf-lookup on", el);
        return;
      }
      const entity = normaliseEntity(rawEntity);

      const min = Number(
        opts.min != null ? opts.min : el.dataset.kfMin || 1
      );
      const limit = Number(
        opts.limit != null ? opts.limit : el.dataset.kfLimit || 50
      );
      const local = Array.isArray(opts.local) ? opts.local : null;

      const endpointOverride =
        opts.endpoint || el.dataset.kfEndpoint || el.dataset.kfUrl || null;

      const tgtId = opts.targetId || el.dataset.kfTargetId || null;
      const tgtName = opts.targetName || el.dataset.kfTargetName || null;
      const tgtCode = opts.targetCode || el.dataset.kfTargetCode || null;
      const tgtUnit = opts.targetUnit || el.dataset.kfTargetUnit || null;
      const tgtPrice = opts.targetPrice || el.dataset.kfTargetPrice || null;
      const tgtDesc =
        opts.targetDescription || el.dataset.kfTargetDescription || null;

      const panel = ensurePanel(el);
      let rows = [];
      let active = -1;
      let aborter = null;

      const formatter =
        opts.formatter ||
        ((x) => {
          const title = escapeHtml(x.label ?? x.name ?? x.code ?? x.id ?? "");
          const sub = escapeHtml(
            x.sublabel ?? x.sku ?? x.barcode ?? x.email ?? ""
          );
          return {
            html: `<div class="kf-title">${title}</div>${
              sub ? `<div class="kf-sub">${sub}</div>` : ""
            }`,
          };
        });

      const defaultOnPick = function (r) {
        const label = r.label || r.name || r.code || "";
        el.value = label;
        el.dispatchEvent(new Event("change", { bubbles: true }));

        if (tgtId && r.id != null) writeTarget(tgtId, r.id);
        if (tgtName) writeTarget(tgtName, label);
        if (tgtCode && r.code != null) writeTarget(tgtCode, r.code);
        if (tgtUnit && r.unit != null) writeTarget(tgtUnit, r.unit);
        if (tgtPrice && r.unit_price != null)
          writeTarget(tgtPrice, r.unit_price);
        if (tgtDesc && r.description) writeTarget(tgtDesc, r.description);
      };

      const onPick =
        typeof opts.onPick === "function" ? opts.onPick : defaultOnPick;

      function close() {
        panel.classList.remove("open");
        panel.innerHTML = "";
        active = -1;
        el.removeAttribute("aria-activedescendant");
        el.setAttribute("aria-expanded", "false");
        _markOpen(el, false);
        if (DEBUG) console.debug("[KF.lookup] closed", el);
      }

      function pick(i) {
        const r = rows[i];
        if (!r) return;
        close();
        try {
          onPick(r);
        } catch (e) {
          console.warn("[KF.lookup] onPick error", e);
        }
      }

      async function performLookup(q) {
        const t = String(q || "").trim();
        if (t.length < min) {
          close();
          rows = [];
          active = -1;
          return;
        }

        try {
          aborter && aborter.abort();
        } catch {}
        aborter = new AbortController();

        if (local && local.length) {
          const s = t.toLowerCase();
          rows = local
            .filter((x) => JSON.stringify(x).toLowerCase().includes(s))
            .slice(0, limit);
        } else {
          rows = await fetchRows(entity, t, limit, aborter, endpointOverride);
        }

        renderItems(panel, rows, formatter);
        active = rows.length ? 0 : -1;
        if (rows.length) {
          highlight(panel, active, el);
          el.setAttribute("aria-expanded", "true");
          _markOpen(el, true);
        } else {
          el.setAttribute("aria-expanded", "false");
          _markOpen(el, false);
        }

        if (DEBUG)
          console.debug("[KF.lookup] performLookup result", {
            q: t,
            count: rows.length,
          });
      }

      const run = KF.debounce(
        () => performLookup(el.value),
        Number(el.dataset.kfDebounce || 160)
      );

      const state = {
        close,
        pick,
        onInputHandler: null,
        onFocusHandler: null,
        onKeydownHandler: null,
        onPanelMouseDown: null,
      };

      state.onKeydownHandler = function (e) {
        const isOpen = panel.classList.contains("open");

        if (!isOpen && (e.key === "ArrowDown" || e.key === "ArrowUp")) {
          if (!rows.length) return;
          e.preventDefault();
          panel.classList.add("open");
          active = clamp(active, 0, rows.length - 1);
          highlight(panel, active, el);
          el.setAttribute("aria-expanded", "true");
          _markOpen(el, true);
          return;
        }

        const max = Math.max(0, rows.length - 1);
        if (e.key === "ArrowDown") {
          if (!rows.length) return;
          if (!isOpen) return;
          e.preventDefault();
          active = clamp(active + 1, 0, max);
          highlight(panel, active, el);
        } else if (e.key === "ArrowUp") {
          if (!rows.length) return;
          if (!isOpen) return;
          e.preventDefault();
          active = clamp(active - 1, 0, max);
          highlight(panel, active, el);
        } else if (e.key === "Enter") {
          if (isOpen && active >= 0) {
            e.preventDefault();
            pick(active);
          } else {
            const q = String(el.value || "").trim();
            if (q.length >= min) {
              e.preventDefault();
              performLookup(q).then(() => {
                if (rows.length) pick(0);
              });
            }
          }
        } else if (e.key === "Escape") {
          if (!isOpen) return;
          e.preventDefault();
          close();
        }
      };

      state.onPanelMouseDown = function (e) {
        // IMPORTANT FIX: use :not([aria-disabled="true"]) so normal items match
        const it = e.target.closest(
          '.kf-item:not([aria-disabled="true"])'
        );
        if (!it) return;
        const idx = Number(it.dataset.index || -1);
        if (idx >= 0) {
          e.preventDefault();
          pick(idx);
        }
      };
      panel.addEventListener("mousedown", state.onPanelMouseDown);

      state.onInputHandler = run;
      state.onFocusHandler = run;

      el.addEventListener("input", state.onInputHandler);
      el.addEventListener("focus", state.onFocusHandler);
      el.addEventListener("keydown", state.onKeydownHandler);

      _lookupRegistry.set(el, state);

      if (el.value && el.value.trim()) {
        run();
      }

      if (DEBUG) console.debug("[KF.lookup] bound", el);
    };

    KF.lookup.fetch = (entity, q, limit, endpoint) =>
      fetchRows(entity, q, limit, null, endpoint);

    KF.lookup.unbind = (elOrSelector) => {
      const el =
        typeof elOrSelector === "string"
          ? document.querySelector(elOrSelector)
          : elOrSelector;
      if (!el) return;
      const st = _lookupRegistry.get(el);
      if (st) {
        try {
          el.removeEventListener("input", st.onInputHandler);
        } catch {}
        try {
          el.removeEventListener("focus", st.onFocusHandler);
        } catch {}
        try {
          el.removeEventListener("keydown", st.onKeydownHandler);
        } catch {}
        try {
          if (el._kfPanel) {
            el._kfPanel.removeEventListener(
              "mousedown",
              st.onPanelMouseDown
            );
            if (el._kfPanel.parentNode)
              el._kfPanel.parentNode.removeChild(el._kfPanel);
            delete el._kfPanel;
          }
        } catch {}
        _lookupRegistry.delete(el);
      }
      delete el.dataset.kfBound;
      _markOpen(el, false);
      if (DEBUG) console.debug("[KF.lookup] unbound", el);
    };

    ready(() => {
      document
        .querySelectorAll("[data-kf-lookup]:not([data-kf-bound='1'])")
        .forEach((el) => {
          try {
            KF.lookup.bind({ el });
          } catch (e) {
            console.warn("[KF.lookup] bind error", e);
          }
        });
    });
  })();

  /* ──────────────────────────────────────────────────────────────
   * SEGMENT 9: Rescan helper
   * ──────────────────────────────────────────────────────────── */

  KF.rescan = function (root) {
    root = root || document;
    KF.choices.scan(root);
    if (KF.lookup && typeof KF.lookup.bind === "function") {
      root
        .querySelectorAll("[data-kf-lookup]:not([data-kf-bound='1'])")
        .forEach((el) => {
          KF.lookup.bind({ el });
        });
    }
  };

  ready(() => {
    KF.rescan(document);
  });
})();