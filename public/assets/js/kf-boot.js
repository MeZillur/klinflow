// /public/assets/js/kf-boot.js
;(() => {
  "use strict";

  /* ──────────────────────────────────────────────────────────────
   * SEGMENT 1: Namespace (idempotent)
   * ──────────────────────────────────────────────────────────── */
  const KF = (window.KF = window.KF || {});

  /* ──────────────────────────────────────────────────────────────
   * SEGMENT 2: Resolve module base (tenant-safe)
   *  - Prefer <meta name="kf-module-base" content="/t/{slug}/apps/dms">
   *  - Fallback to existing KF.moduleBase (if SSR/inline set)
   *  - Final fallback for dev: "/apps/dms"
   *  - Normalize: remove trailing slashes
   * ──────────────────────────────────────────────────────────── */
  const meta = document.querySelector('meta[name="kf-module-base"]');
  const metaVal = (meta && typeof meta.content === "string") ? meta.content : "";
  const previous = typeof KF.moduleBase === "string" ? KF.moduleBase : "";
  const fallback = "/apps/dms";

  function normBase(s) {
    const v = String(s || "").trim();
    return v ? v.replace(/\/+$/,"") : "";
  }

  // Only set if not already provided or meta provides a value
  const chosen = normBase(metaVal) || normBase(previous) || normBase(fallback);
  if (!previous || normBase(previous) !== chosen) {
    KF.moduleBase = chosen;
  }

  // tiny helpers
  KF.getBase = () => KF.moduleBase || "";
  KF.url = (path) => {
    const base = KF.getBase();
    if (!path) return base;
    const p = String(path);
    return base + (p.startsWith("/") ? p : "/" + p);
  };

  /* ──────────────────────────────────────────────────────────────
   * SEGMENT 3: Assets map (front URLs use /assets/*)
   *  - You can override by defining window.KF.assets BEFORE this file
   *  - We only fill missing keys (won’t clobber your overrides)
   * ──────────────────────────────────────────────────────────── */
  const defaults = {
    // Choices (optional; used when <select data-choices> is present)
    CHOICES_JS:      "/assets/ui/choices/choices.min.js",
    CHOICES_CSS:     "/assets/ui/choices/choices.min.css",
    CHOICES_JS_CDN:  "https://cdn.jsdelivr.net/npm/choices.js@10.2.0/public/assets/scripts/choices.min.js",
    CHOICES_CSS_CDN: "https://cdn.jsdelivr.net/npm/choices.js@10.2.0/public/assets/styles/choices.min.css",

    // (Legacy) Algolia autocomplete.js — only if you explicitly use it later
    AC_JS:           "/assets/ui/autocomplete/autocomplete.min.js",
    AC_CSS:          "/assets/ui/autocomplete/autocomplete.min.css",
    AC_JS_CDN:       "https://cdn.jsdelivr.net/npm/autocomplete.js@0.38.1/dist/autocomplete.min.js",
    AC_CSS_CDN:      "https://cdn.jsdelivr.net/npm/autocomplete.js@0.38.1/dist/autocomplete.min.css",
  };

  const incoming = (window.KF && window.KF.assets) ? window.KF.assets : {};
  const merged = { ...defaults, ...incoming }; // caller overrides win
  KF.assets = merged;

  /* ──────────────────────────────────────────────────────────────
   * SEGMENT 4: Brand token (optional convenience)
   *  - Don’t force styles here; just expose brand hex if needed
   * ──────────────────────────────────────────────────────────── */
  if (!KF.brandColor) {
    KF.brandColor = "#228B22";
  }

  // End boot — keep this file tiny; heavy logic belongs in kf-global.js
})();