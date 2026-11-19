/**
 * DREK Meta: Discord Component Embed builder.
 *
 * Vanilla JS, no dependencies besides embed-core.js. Keeps the canonical payload as its state, writes the
 * normalised (unresolved) JSON into the hidden jform[component_embed] textarea, and shows live counters computed on
 * the resolved, final JSON exactly as the system plugin will print it.
 *
 * @author   drek124 <https://github.com/drek124>
 * @license  GNU General Public License version 2 or later
 */
((window, document) => {
  'use strict';

  const DM = window.DrekMeta = window.DrekMeta || {};
  const C = DM.core;
  const T = C.TYPES;
  const { t } = C;

  // ---------------------------------------------------------------------------------------------
  // Icons (inline SVG, 20x20 viewBox, stroke-based)
  // ---------------------------------------------------------------------------------------------

  const svg = (d, extra = '') => `<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" ${extra}>${d}</svg>`;

  const ICONS = {
    container: svg('<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M7 3v18"/>'),
    text: svg('<path d="M4 7V5h16v2"/><path d="M12 5v14"/><path d="M9 19h6"/>'),
    section: svg('<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 9h6M7 13h6"/><rect x="15" y="9" width="3" height="4" rx="0.5"/>'),
    gallery: svg('<rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/>'),
    separator: svg('<path d="M3 12h18"/><path d="M8 6h8M8 18h8" opacity=".45"/>'),
    row: svg('<rect x="2" y="8" width="9" height="8" rx="2"/><rect x="13" y="8" width="9" height="8" rx="2"/>'),
    button: svg('<rect x="3" y="7" width="18" height="10" rx="3"/><path d="M8 12h8"/>'),
    thumb: svg('<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/>'),
    up: svg('<path d="m18 15-6-6-6 6"/>'),
    down: svg('<path d="m6 9 6 6 6-6"/>'),
    copy: svg('<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/>'),
    trash: svg('<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/>'),
    grip: svg('<circle cx="9" cy="6" r="1"/><circle cx="15" cy="6" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="9" cy="18" r="1"/><circle cx="15" cy="18" r="1"/>', 'class="dm-grip-svg"'),
    chevron: svg('<path d="m6 9 6 6 6-6"/>'),
    plus: svg('<path d="M12 5v14M5 12h14"/>'),
    wand: svg('<path d="m15 4-1 2-2 1 2 1 1 2 1-2 2-1-2-1z"/><path d="M3 21 14 10"/><path d="m19 13-.5 1-1 .5 1 .5.5 1 .5-1 1-.5-1-.5z"/>'),
    external: svg('<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>'),
    image: svg('<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/>'),
    warn: svg('<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>'),
    close: svg('<path d="M18 6 6 18M6 6l12 12"/>'),
    upload: svg('<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/>'),
    check: svg('<path d="M20 6 9 17l-5-5"/>'),
  };

  const TYPE_INFO = {
    [T.TEXT]: { key: 'COM_DREKMETA_TYPE_TEXT', icon: 'text' },
    [T.SECTION]: { key: 'COM_DREKMETA_TYPE_SECTION', icon: 'section' },
    [T.GALLERY]: { key: 'COM_DREKMETA_TYPE_GALLERY', icon: 'gallery' },
    [T.SEPARATOR]: { key: 'COM_DREKMETA_TYPE_SEPARATOR', icon: 'separator' },
    [T.ROW]: { key: 'COM_DREKMETA_TYPE_ROW', icon: 'row' },
  };

  const BLANK = {
    text: () => ({ type: T.TEXT, content: '' }),
    section: () => ({ type: T.SECTION, components: [{ type: T.TEXT, content: '' }], accessory: { type: T.THUMB, media: { url: '' } } }),
    gallery: () => ({ type: T.GALLERY, items: [{ media: { url: '' } }] }),
    separator: () => ({ type: T.SEPARATOR }),
    row: () => ({ type: T.ROW, components: [{ type: T.BUTTON, style: 5, label: '', url: '' }] }),
  };

  /** Structural errors that make imported JSON unusable in the tree: refuse to apply instead of silently fixing. */
  const FATAL_IMPORT = [
    'COM_DREKMETA_EMBED_ERR_NO_ROOT',
    'COM_DREKMETA_EMBED_ERR_JSON',
    'COM_DREKMETA_EMBED_ERR_ROOT_NOT_CONTAINER',
    'COM_DREKMETA_EMBED_ERR_NESTED_CONTAINER',
    'COM_DREKMETA_EMBED_ERR_NOT_ALLOWED_HERE',
    'COM_DREKMETA_EMBED_ERR_UNKNOWN_TYPE',
    'COM_DREKMETA_EMBED_ERR_INVALID_COMPONENT',
    'COM_DREKMETA_EMBED_ERR_BUTTON_KEY',
    'COM_DREKMETA_EMBED_ERR_BUTTON_STYLE',
    'COM_DREKMETA_EMBED_ERR_ROW_CHILD',
    'COM_DREKMETA_EMBED_ERR_SECTION_CHILD',
    'COM_DREKMETA_EMBED_ERR_ACCENT_COLOR',
    'COM_DREKMETA_EMBED_ERR_SEPARATOR_SPACING',
  ];

  // ---------------------------------------------------------------------------------------------
  // DOM helper
  // ---------------------------------------------------------------------------------------------

  const h = (tag, props, ...kids) => {
    const el = document.createElement(tag);
    Object.entries(props || {}).forEach(([k, v]) => {
      if (v === undefined || v === null || v === false) return;
      if (k === 'class') el.className = v;
      else if (k === 'text') el.textContent = v;
      else if (k === 'html') el.innerHTML = v; // Only ever used with our own icons / escaped renderer output.
      else if (k === 'value') el.value = v;
      else if (k === 'checked') el.checked = !!v;
      else if (k === 'dataset') Object.assign(el.dataset, v);
      else if (k.startsWith('on') && typeof v === 'function') el.addEventListener(k.slice(2).toLowerCase(), v);
      else if (v === true) el.setAttribute(k, '');
      else el.setAttribute(k, String(v));
    });
    kids.flat(Infinity).forEach((kid) => {
      if (kid === null || kid === undefined || kid === false) return;
      el.append(kid instanceof Node ? kid : document.createTextNode(String(kid)));
    });
    return el;
  };

  const iconBtn = (icon, label, onClick, extra = {}) => h('button', {
    type: 'button', class: `dm-icon-btn ${extra.class || ''}`.trim(), title: label, 'aria-label': label, html: ICONS[icon], onClick, disabled: extra.disabled,
  });

  let uid = 0;
  const nextId = (p) => `dm-${p}-${++uid}`;

  const emojiToText = (e) => {
    if (!e) return '';
    if (e.id) return `<${e.animated ? 'a' : ''}:${e.name || '_'}:${e.id}>`;
    return e.name || '';
  };

  const move = (list, from, to) => {
    if (to < 0 || to >= list.length || from === to) return;
    const [x] = list.splice(from, 1);
    list.splice(to, 0, x);
  };

  /** Children of the root in a raw (not yet normalised) payload, for labelling errors that point into it. */
  const rawChildren = (data) => {
    const list = data && data.component && data.component.components;
    if (Array.isArray(list)) return list;
    return list && typeof list === "object" ? Object.values(list) : [];
  };

  const formatBytes = (n) => (n >= 1048576 ? `${(n / 1048576).toFixed(1)} MB` : `${Math.round(n / 1024)} KB`);

  // ---------------------------------------------------------------------------------------------
  // Builder
  // ---------------------------------------------------------------------------------------------

  class Builder {
    /**
     * @param {HTMLElement} root
     * @param {object} opts {field, mediaField, getContext, isEnabled, getThemeColor, serverErrors, debugger, limits, onChange}
     */
    constructor(root, opts) {
      this.root = root;
      this.opts = opts;
      this.field = opts.field;
      this.collapsed = new WeakSet();
      this.lastFocus = null;
      this.jsonDirty = false;
      this.jsonInvalid = null;
      this.result = null;
      this.sizeCache = new Map();
      this.pickSetter = null;
      this.cards = [];
      this.pending = false;
      this.loadNotices = [];
      this.previewMode = 'preview';
      this.jsonMode = 'edit';

      this.state = this.load(this.field.value);
      this.build();
      this.renderTree();
      this.update();
      this.bindMediaPicker();
    }

    // -- state ------------------------------------------------------------------------------------

    emptyState() {
      return { component: { type: T.CONTAINER, components: [] } };
    }

    load(raw) {
      if (C.phpTrim(raw || '') === '') return this.emptyState();

      const errors = [];
      const data = C.decode(raw, errors);
      const norm = data ? C.normalize(data, errors) : null;

      if (!norm) {
        // Keep the broken JSON visible in the JSON tab so nothing is lost; saving stays blocked until fixed.
        this.jsonDirty = true;
        this.pendingJson = raw;
        this.jsonInvalid = errors.length ? C.errorMessage(errors[0]) : null;
        this.loadNotices = errors;
        this.loadChildren = rawChildren(data);
        return this.emptyState();
      }

      this.loadNotices = errors.filter((e) => FATAL_IMPORT.includes(e.key));
      this.loadChildren = rawChildren(data);
      return norm;
    }

    get container() {
      return this.state.component;
    }

    get children() {
      return this.container.components;
    }

    storedJson() {
      if (!this.children.length) return '';
      const norm = C.normalize(this.state, []);
      return norm ? C.encodeForStorage(norm) : '';
    }

    prettyStored() {
      const norm = C.normalize(this.state, []) || this.state;
      return JSON.stringify(norm, null, 2);
    }

    changed(structural = false) {
      if (structural) this.renderTree();
      if (this.pending) return;
      this.pending = true;
      window.requestAnimationFrame(() => {
        this.pending = false;
        this.update();
      });
    }

    // -- layout -----------------------------------------------------------------------------------

    build() {
      this.root.classList.add('dm-builder');
      this.root.innerHTML = '';

      // Header: counters + actions
      this.counterEls = {
        bytes: this.counter('bytes', t('COM_DREKMETA_BUILDER_COUNTER_BYTES')),
        components: this.counter('components', t('COM_DREKMETA_BUILDER_COUNTER_COMPONENTS')),
        gallery: this.counter('gallery', t('COM_DREKMETA_BUILDER_COUNTER_GALLERY')),
      };

      this.statusEl = h('span', { class: 'dm-status' });

      const header = h('div', { class: 'dm-header' },
        h('div', { class: 'dm-header-title' },
          h('div', { class: 'dm-h' }, t('COM_DREKMETA_BUILDER_TITLE'), this.statusEl),
          h('div', { class: 'dm-sub' }, t('COM_DREKMETA_BUILDER_SUBTITLE'))),
        h('div', { class: 'dm-counters' }, Object.values(this.counterEls).map((c) => c.el)),
        h('div', { class: 'dm-header-actions' },
          h('button', {
            type: 'button', class: 'btn btn-primary btn-sm dm-btn-start', html: `${ICONS.wand}<span>${C.escapeHtml(t('COM_DREKMETA_BUILDER_START_FROM_PAGE'))}</span>`, onClick: () => this.startFromPage(),
          }),
          h('a', {
            class: 'btn btn-outline-secondary btn-sm', href: this.opts.debugger, target: '_blank', rel: 'noopener noreferrer', html: `<span>${C.escapeHtml(t('COM_DREKMETA_BUILDER_DEBUGGER'))}</span>${ICONS.external}`,
          })));

      this.bannerEl = h('div', { class: 'dm-banners' });

      this.placeholderBar = h('div', { class: 'dm-placeholders' },
        h('span', { class: 'dm-placeholders-label' }, t('COM_DREKMETA_BUILDER_PLACEHOLDERS')),
        C.PLACEHOLDERS.map((p) => h('button', {
          type: 'button', class: 'dm-chip', title: t('COM_DREKMETA_BUILDER_PLACEHOLDER_INSERT'), onMousedown: (e) => e.preventDefault(), onClick: () => this.insertPlaceholder(`{${p}}`),
        }, `{${p}}`)),
        h('span', { class: 'dm-placeholders-hint' }, t('COM_DREKMETA_BUILDER_DEBUGGER_NOTE')));

      this.treeEl = h('div', { class: 'dm-tree' });

      // Right panel: preview / JSON
      this.previewEl = h('div', { class: 'dm-preview-canvas' });
      this.jsonEl = this.buildJsonPanel();
      this.tabPreview = h('button', { type: 'button', class: 'dm-tab is-active', onClick: () => this.setPanel('preview') }, t('COM_DREKMETA_BUILDER_TAB_PREVIEW'));
      this.tabJson = h('button', { type: 'button', class: 'dm-tab', onClick: () => this.setPanel('json') }, t('COM_DREKMETA_BUILDER_TAB_JSON'));
      this.noticesEl = h('div', { class: 'dm-notices' });

      const panel = h('div', { class: 'dm-panel' },
        h('div', { class: 'dm-tabs', role: 'tablist' }, this.tabPreview, this.tabJson),
        h('div', { class: 'dm-panel-body' }, this.previewEl, this.jsonEl),
        this.noticesEl);

      this.root.append(
        header,
        this.bannerEl,
        this.placeholderBar,
        h('div', { class: 'dm-grid' },
          h('div', { class: 'dm-col-tree' }, this.treeEl),
          h('div', { class: 'dm-col-panel' }, panel)),
      );

      this.root.addEventListener('focusin', (e) => {
        if (e.target.matches('input[type=text], input[type=url], textarea') && e.target.closest('.dm-tree')) this.lastFocus = e.target;
      });

      this.renderBanners();
      this.setPanel(this.jsonDirty ? 'json' : 'preview');
    }

    counter(key, label) {
      const value = h('span', { class: 'dm-counter-value' }, '0');
      const bar = h('span', { class: 'dm-counter-bar' });
      const el = h('div', { class: 'dm-counter', dataset: { counter: key } },
        h('div', { class: 'dm-counter-top' }, h('span', { class: 'dm-counter-label' }, label), value),
        h('div', { class: 'dm-counter-track' }, bar));
      return { el, value, bar };
    }

    setPanel(mode) {
      this.previewMode = mode;
      this.tabPreview.classList.toggle('is-active', mode === 'preview');
      this.tabJson.classList.toggle('is-active', mode === 'json');
      this.previewEl.hidden = mode !== 'preview';
      this.jsonEl.hidden = mode !== 'json';
      if (mode === 'json') this.refreshJson(true);
    }

    buildJsonPanel() {
      this.jsonModeEdit = h('button', { type: 'button', class: 'dm-seg is-active', onClick: () => this.setJsonMode('edit') }, t('COM_DREKMETA_BUILDER_JSON_EDITABLE'));
      this.jsonModeOut = h('button', { type: 'button', class: 'dm-seg', onClick: () => this.setJsonMode('output') }, t('COM_DREKMETA_BUILDER_JSON_OUTPUT'));

      this.jsonText = h('textarea', {
        class: 'dm-json-text', spellcheck: 'false', autocomplete: 'off', 'aria-label': t('COM_DREKMETA_BUILDER_JSON_EDITABLE'), onInput: () => this.onJsonInput(),
      });
      this.jsonOut = h('pre', { class: 'dm-json-out', tabindex: '0' });
      this.jsonStatus = h('div', { class: 'dm-json-status' });

      this.fileInput = h('input', {
        type: 'file', accept: '.json,application/json', class: 'visually-hidden', tabindex: '-1', onChange: (e) => this.importFile(e),
      });

      this.jsonEditActions = h('div', { class: 'dm-json-actions' },
        h('button', { type: 'button', class: 'btn btn-success btn-sm', onClick: () => this.applyJson() }, t('COM_DREKMETA_BUILDER_JSON_APPLY')),
        h('button', { type: 'button', class: 'btn btn-outline-secondary btn-sm', onClick: () => this.discardJson() }, t('COM_DREKMETA_BUILDER_JSON_DISCARD')),
        h('button', { type: 'button', class: 'btn btn-outline-secondary btn-sm', onClick: () => this.formatJson() }, t('COM_DREKMETA_BUILDER_JSON_FORMAT')),
        h('button', { type: 'button', class: 'btn btn-outline-secondary btn-sm', html: `${ICONS.upload}<span>${C.escapeHtml(t('COM_DREKMETA_BUILDER_JSON_IMPORT'))}</span>`, onClick: () => this.fileInput.click() }),
        h('button', { type: 'button', class: 'btn btn-outline-secondary btn-sm', html: `${ICONS.copy}<span>${C.escapeHtml(t('COM_DREKMETA_BUILDER_JSON_COPY'))}</span>`, onClick: (e) => this.copy(this.jsonText.value, e.currentTarget) }),
        this.fileInput);

      this.jsonOutActions = h('div', { class: 'dm-json-actions' },
        h('button', { type: 'button', class: 'btn btn-outline-secondary btn-sm', html: `${ICONS.copy}<span>${C.escapeHtml(t('COM_DREKMETA_BUILDER_JSON_COPY_OUTPUT'))}</span>`, onClick: (e) => this.copy(this.result?.json || '', e.currentTarget) }),
        h('span', { class: 'dm-json-note' }, t('COM_DREKMETA_BUILDER_JSON_OUTPUT_NOTE')));

      return h('div', { class: 'dm-json', hidden: true },
        h('div', { class: 'dm-segs' }, this.jsonModeEdit, this.jsonModeOut),
        this.jsonText, this.jsonOut, this.jsonStatus, this.jsonEditActions, this.jsonOutActions);
    }

    setJsonMode(mode) {
      this.jsonMode = mode;
      this.jsonModeEdit.classList.toggle('is-active', mode === 'edit');
      this.jsonModeOut.classList.toggle('is-active', mode === 'output');
      this.jsonText.hidden = mode !== 'edit';
      this.jsonEditActions.hidden = mode !== 'edit';
      this.jsonOut.hidden = mode !== 'output';
      this.jsonOutActions.hidden = mode !== 'output';
      this.refreshJson(true);
    }

    refreshJson(force = false) {
      if (this.jsonMode === 'output') {
        this.jsonOut.textContent = this.result?.json || '';
      }
      if (!this.jsonDirty && (force || this.previewMode === 'json')) {
        this.jsonText.value = this.children.length ? this.prettyStored() : '';
      } else if (this.jsonDirty && this.pendingJson !== undefined) {
        this.jsonText.value = this.pendingJson;
        this.pendingJson = undefined;
      }
      this.renderJsonStatus();
    }

    renderJsonStatus() {
      this.jsonStatus.className = 'dm-json-status';
      this.jsonStatus.innerHTML = '';
      if (this.jsonMode === 'output') {
        this.jsonStatus.textContent = t('COM_DREKMETA_BUILDER_JSON_OUTPUT_BYTES', this.result?.stats.bytes || 0, C.LIMITS.bytes);
        return;
      }
      if (!this.jsonDirty) {
        this.jsonStatus.textContent = t('COM_DREKMETA_BUILDER_JSON_IN_SYNC');
        return;
      }
      if (this.jsonInvalid) {
        this.jsonStatus.classList.add('is-error');
        this.jsonStatus.append(h('strong', {}, t('COM_DREKMETA_BUILDER_JSON_INVALID')), ' ', this.jsonInvalid);
        return;
      }
      this.jsonStatus.classList.add('is-warn');
      this.jsonStatus.textContent = t('COM_DREKMETA_BUILDER_JSON_UNAPPLIED');
    }

    onJsonInput() {
      this.jsonDirty = true;
      const errors = [];
      C.decode(this.jsonText.value, errors);
      this.jsonInvalid = errors.length && C.phpTrim(this.jsonText.value) !== '' ? C.errorMessage(errors[0]) : null;
      this.renderJsonStatus();
      this.updateStatus();
    }

    applyJson() {
      const raw = this.jsonText.value;

      if (C.phpTrim(raw) === '') {
        this.state = this.emptyState();
        this.jsonDirty = false;
        this.jsonInvalid = null;
        this.changed(true);
        this.refreshJson(true);
        return;
      }

      const errors = [];
      const data = C.decode(raw, errors);
      const norm = data ? C.normalize(data, errors) : null;
      const fatal = errors.filter((e) => FATAL_IMPORT.includes(e.key));

      if (!norm || fatal.length) {
        this.jsonInvalid = (fatal.length ? fatal : errors).slice(0, 4).map((e) => this.describe(e, rawChildren(data))).join(' · ');
        this.renderJsonStatus();
        this.updateStatus();
        return;
      }

      this.state = norm;
      this.jsonDirty = false;
      this.jsonInvalid = null;
      this.loadNotices = [];
      this.renderBanners();
      this.changed(true);
      this.refreshJson(true);
      this.flash(this.jsonStatus, t('COM_DREKMETA_BUILDER_JSON_APPLIED'));
    }

    discardJson() {
      this.jsonDirty = false;
      this.jsonInvalid = null;
      this.refreshJson(true);
      this.updateStatus();
    }

    formatJson() {
      try {
        this.jsonText.value = JSON.stringify(JSON.parse(this.jsonText.value), null, 2);
      } catch (e) {
        // Leave it, the status line already explains.
      }
    }

    importFile(e) {
      const file = e.target.files && e.target.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = () => {
        this.jsonText.value = String(reader.result || '');
        this.setJsonMode('edit');
        this.onJsonInput();
        this.applyJson();
      };
      reader.readAsText(file);
      e.target.value = '';
    }

    copy(text, btn) {
      const done = () => this.flash(btn, t('COM_DREKMETA_BUILDER_COPIED'));
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(done, () => this.copyFallback(text, done));
      } else {
        this.copyFallback(text, done);
      }
    }

    copyFallback(text, done) {
      const ta = h('textarea', { class: 'visually-hidden' });
      ta.value = text;
      document.body.append(ta);
      ta.select();
      try { document.execCommand('copy'); done(); } catch (e) { /* ignore */ }
      ta.remove();
    }

    flash(el, text) {
      if (!el) return;
      el.dataset.flash = text;
      el.classList.add('dm-flash');
      window.setTimeout(() => el.classList.remove('dm-flash'), 1400);
    }

    renderBanners() {
      this.bannerEl.innerHTML = '';
      const server = this.opts.serverErrors || [];

      if (server.length) {
        const banner = h('div', { class: 'dm-banner is-error', role: 'alert' },
          h('span', { class: 'dm-banner-icon', html: ICONS.warn }),
          h('div', { class: 'dm-banner-body' },
            h('strong', {}, t('COM_DREKMETA_BUILDER_SAVE_BLOCKED')),
            h('ul', {}, server.slice(0, 8).map((e) => h('li', {}, (e.location || '') + (e.message || ''))))),
          iconBtn('close', t('JCLOSE'), () => { this.opts.serverErrors = []; this.renderBanners(); }));
        this.bannerEl.append(banner);
      }

      if (this.loadNotices.length) {
        const banner = h('div', { class: 'dm-banner is-warn', role: 'status' },
          h('span', { class: 'dm-banner-icon', html: ICONS.warn }),
          h('div', { class: 'dm-banner-body' },
            h('strong', {}, t('COM_DREKMETA_BUILDER_LOAD_CLEANED')),
            h('ul', {}, this.loadNotices.slice(0, 8).map((e) => h('li', {}, this.describe(e, this.loadChildren))))),
          iconBtn('close', t('JCLOSE'), () => { this.loadNotices = []; this.renderBanners(); }));
        this.bannerEl.append(banner);
      }
    }

    // -- tree -------------------------------------------------------------------------------------

    renderTree() {
      this.treeEl.innerHTML = '';
      this.cards = [];

      this.treeEl.append(this.renderContainerCard());

      this.listEl = h('div', { class: 'dm-list' });
      this.bindDnd(this.listEl);

      if (!this.children.length) {
        this.listEl.append(h('div', { class: 'dm-empty-tree' },
          h('div', { class: 'dm-empty-title' }, t('COM_DREKMETA_BUILDER_EMPTY_TITLE')),
          h('div', { class: 'dm-empty-text' }, t('COM_DREKMETA_BUILDER_EMPTY_TEXT')),
          h('button', {
            type: 'button', class: 'btn btn-primary btn-sm', html: `${ICONS.wand}<span>${C.escapeHtml(t('COM_DREKMETA_BUILDER_START_FROM_PAGE'))}</span>`, onClick: () => this.startFromPage(),
          })));
      }

      this.children.forEach((node, index) => this.listEl.append(this.renderCard(node, index)));

      const adds = [
        ['text', 'COM_DREKMETA_TYPE_TEXT'],
        ['section', 'COM_DREKMETA_TYPE_SECTION'],
        ['gallery', 'COM_DREKMETA_TYPE_GALLERY'],
        ['separator', 'COM_DREKMETA_TYPE_SEPARATOR'],
        ['row', 'COM_DREKMETA_TYPE_ROW'],
      ];

      const addBar = h('div', { class: 'dm-add' },
        h('span', { class: 'dm-add-label', html: `${ICONS.plus}<span>${C.escapeHtml(t('COM_DREKMETA_BUILDER_ADD'))}</span>` }),
        adds.map(([kind, key]) => h('button', {
          type: 'button', class: 'dm-add-btn', html: `${ICONS[kind === 'row' ? 'row' : kind]}<span>${C.escapeHtml(t(key))}</span>`, onClick: () => this.add(kind),
        })));

      this.treeEl.append(this.listEl, addBar);
    }

    renderContainerCard() {
      const c = this.container;
      const hasAccent = Number.isInteger(c.accent_color);
      const colorInput = h('input', {
        type: 'color', class: 'dm-color', value: hasAccent ? C.toHex(c.accent_color) : (this.opts.getThemeColor() || '#5865f2'), disabled: !hasAccent, 'aria-label': t('COM_DREKMETA_BUILDER_ACCENT'),
        onInput: (e) => { c.accent_color = parseInt(e.target.value.substring(1), 16); this.changed(); },
      });

      const accentToggle = this.toggle(t('COM_DREKMETA_BUILDER_ACCENT_ON'), hasAccent, (on) => {
        if (on) {
          c.accent_color = parseInt(colorInput.value.substring(1), 16);
        } else {
          delete c.accent_color;
        }
        colorInput.disabled = !on;
        this.changed();
      });

      const syncBtn = h('button', {
        type: 'button', class: 'btn btn-link btn-sm p-0', onClick: () => {
          const theme = this.opts.getThemeColor();
          if (!theme) {
            this.flash(syncBtn, t('COM_DREKMETA_BUILDER_NO_THEME_COLOR'));
            return;
          }
          c.accent_color = parseInt(theme.substring(1), 16);
          this.changed(true);
        },
      }, t('COM_DREKMETA_BUILDER_ACCENT_SYNC'));

      const card = h('div', { class: 'dm-card dm-card-root', dataset: { path: 'root' } },
        h('div', { class: 'dm-card-head' },
          h('span', { class: 'dm-card-icon', html: ICONS.container }),
          h('div', { class: 'dm-card-titles' },
            h('span', { class: 'dm-card-type' }, t('COM_DREKMETA_BUILDER_CONTAINER')),
            h('span', { class: 'dm-card-summary' }, t('COM_DREKMETA_BUILDER_CONTAINER_HINT'))),
          h('span', { class: 'dm-badge-fixed' }, t('COM_DREKMETA_BUILDER_ROOT'))),
        h('div', { class: 'dm-card-body dm-inline-fields' },
          h('div', { class: 'dm-field dm-field-accent' },
            h('label', { class: 'dm-label' }, t('COM_DREKMETA_BUILDER_ACCENT')),
            h('div', { class: 'dm-accent-row' }, accentToggle, colorInput, syncBtn)),
          h('div', { class: 'dm-field' },
            h('label', { class: 'dm-label' }, t('COM_DREKMETA_BUILDER_SPOILER')),
            this.toggle(t('COM_DREKMETA_BUILDER_SPOILER_CONTAINER'), !!c.spoiler, (on) => { if (on) c.spoiler = true; else delete c.spoiler; this.changed(); }))),
        h('div', { class: 'dm-card-errors' }));

      this.cards.push({ el: card, node: c, path: 'root' });
      return card;
    }

    renderCard(node, index) {
      const info = TYPE_INFO[node.type] || { key: 'COM_DREKMETA_TYPE_UNKNOWN', icon: 'container' };
      const collapsed = this.collapsed.has(node);
      const summary = h('span', { class: 'dm-card-summary' }, this.summary(node));
      const last = this.children.length - 1;

      const handle = h('span', {
        class: 'dm-grip', title: t('COM_DREKMETA_BUILDER_DRAG'), html: ICONS.grip, 'aria-hidden': 'true',
      });

      const toggleBtn = iconBtn('chevron', t(collapsed ? 'COM_DREKMETA_BUILDER_EXPAND' : 'COM_DREKMETA_BUILDER_COLLAPSE'), () => {
        if (this.collapsed.has(node)) this.collapsed.delete(node); else this.collapsed.add(node);
        this.renderTree();
        this.update();
      }, { class: `dm-collapse${collapsed ? ' is-collapsed' : ''}` });

      const head = h('div', { class: 'dm-card-head' },
        handle,
        h('span', { class: 'dm-card-icon', html: ICONS[info.icon] }),
        h('div', { class: 'dm-card-titles' },
          h('span', { class: 'dm-card-type' }, h('span', { class: 'dm-card-num' }, `#${index + 1}`), t(info.key)),
          summary),
        h('span', { class: 'dm-card-errcount' }),
        h('div', { class: 'dm-card-actions' },
          iconBtn('up', t('COM_DREKMETA_BUILDER_MOVE_UP'), () => { move(this.children, index, index - 1); this.changed(true); }, { disabled: index === 0 }),
          iconBtn('down', t('COM_DREKMETA_BUILDER_MOVE_DOWN'), () => { move(this.children, index, index + 1); this.changed(true); }, { disabled: index === last }),
          iconBtn('copy', t('COM_DREKMETA_BUILDER_DUPLICATE'), () => { this.children.splice(index + 1, 0, C.clone(node)); this.changed(true); }),
          iconBtn('trash', t('COM_DREKMETA_BUILDER_DELETE'), () => { this.children.splice(index, 1); this.changed(true); }, { class: 'is-danger' }),
          toggleBtn));

      const body = h('div', { class: 'dm-card-body' });
      if (!collapsed) this.renderBody(node, String(index), body);

      const card = h('div', {
        class: `dm-card${collapsed ? ' is-collapsed' : ''}`, dataset: { index: String(index), path: String(index) },
      }, head, collapsed ? null : body, h('div', { class: 'dm-card-errors' }));

      handle.addEventListener('mousedown', () => { card.draggable = true; });
      handle.addEventListener('touchstart', () => { card.draggable = true; }, { passive: true });
      card.addEventListener('dragend', () => { card.draggable = false; });
      card.addEventListener('mouseup', () => { card.draggable = false; });

      this.cards.push({ el: card, node, path: String(index), summary });
      return card;
    }

    summary(node) {
      const clip = (s) => {
        const one = String(s || '').replace(/\s+/g, ' ').trim();
        return one.length > 70 ? `${one.substring(0, 70)}…` : one;
      };
      switch (node.type) {
        case T.TEXT:
          return clip(node.content) || t('COM_DREKMETA_BUILDER_EMPTY_TEXT_SUMMARY');
        case T.SECTION: {
          const acc = node.accessory?.type === T.BUTTON ? t('COM_DREKMETA_TYPE_BUTTON') : t('COM_DREKMETA_TYPE_THUMBNAIL');
          return `${clip(node.components?.[0]?.content) || t('COM_DREKMETA_BUILDER_EMPTY_TEXT_SUMMARY')} · ${acc}`;
        }
        case T.GALLERY:
          return t('COM_DREKMETA_BUILDER_N_ITEMS', (node.items || []).length);
        case T.SEPARATOR:
          return `${node.divider === false ? t('COM_DREKMETA_BUILDER_NO_DIVIDER') : t('COM_DREKMETA_BUILDER_DIVIDER')} · ${node.spacing === 2 ? t('COM_DREKMETA_BUILDER_SPACING_LARGE') : t('COM_DREKMETA_BUILDER_SPACING_SMALL')}`;
        case T.ROW:
          return (node.components || []).map((b) => b.label || emojiToText(b.emoji) || '?').join(' · ') || t('COM_DREKMETA_BUILDER_N_BUTTONS', 0);
        default:
          return '';
      }
    }

    renderBody(node, path, body) {
      switch (node.type) {
        case T.TEXT:
          body.append(this.textArea(t('COM_DREKMETA_BUILDER_MARKDOWN'), node.content, (v) => { node.content = v; }, { rows: 4, path }));
          break;

        case T.SECTION:
          this.renderSection(node, path, body);
          break;

        case T.GALLERY:
          this.renderGallery(node, path, body);
          break;

        case T.SEPARATOR:
          body.classList.add('dm-inline-fields');
          body.append(
            h('div', { class: 'dm-field' },
              h('label', { class: 'dm-label' }, t('COM_DREKMETA_BUILDER_DIVIDER')),
              this.toggle(t('COM_DREKMETA_BUILDER_DIVIDER_SHOW'), node.divider !== false, (on) => { if (on) delete node.divider; else node.divider = false; this.changed(); })),
            h('div', { class: 'dm-field' },
              h('label', { class: 'dm-label' }, t('COM_DREKMETA_BUILDER_SPACING')),
              this.segmented([[1, t('COM_DREKMETA_BUILDER_SPACING_SMALL')], [2, t('COM_DREKMETA_BUILDER_SPACING_LARGE')]], node.spacing === 2 ? 2 : 1, (v) => { if (v === 2) node.spacing = 2; else delete node.spacing; this.changed(); })));
          break;

        case T.ROW:
          this.renderRow(node, path, body);
          break;

        default:
      }
    }

    renderSection(node, path, body) {
      node.components = node.components || [];
      const texts = h('div', { class: 'dm-sublist' });

      node.components.forEach((text, j) => {
        const subPath = `${path}.components.${j}`;
        texts.append(h('div', { class: 'dm-subitem', dataset: { path: subPath } },
          h('div', { class: 'dm-subitem-head' },
            h('span', { class: 'dm-subitem-title', html: `${ICONS.text}<span>${C.escapeHtml(t('COM_DREKMETA_BUILDER_TEXT_N', j + 1))}</span>` }),
            this.subActions(node.components, j, 1)),
          this.textArea(null, text.content, (v) => { text.content = v; }, { rows: 3 })));
      });

      const addText = h('button', {
        type: 'button', class: 'dm-sub-add', disabled: node.components.length >= C.LIMITS.sectionTexts, html: `${ICONS.plus}<span>${C.escapeHtml(t('COM_DREKMETA_BUILDER_ADD_TEXT'))}</span>`,
        onClick: () => { node.components.push(BLANK.text()); this.changed(true); },
      });

      const acc = node.accessory || null;
      const accType = acc ? acc.type : 0;
      const accBox = h('div', { class: 'dm-accessory', dataset: { path: `${path}.accessory` } });

      const seg = this.segmented([[T.THUMB, t('COM_DREKMETA_TYPE_THUMBNAIL')], [T.BUTTON, t('COM_DREKMETA_TYPE_BUTTON')]], accType, (v) => {
        if (v === T.THUMB) node.accessory = { type: T.THUMB, media: { url: '' } };
        else node.accessory = { type: T.BUTTON, style: 5, label: '', url: '' };
        this.changed(true);
      });

      accBox.append(h('div', { class: 'dm-accessory-head' }, h('span', { class: 'dm-label' }, t('COM_DREKMETA_BUILDER_ACCESSORY')), seg));

      if (accType === T.THUMB) accBox.append(this.mediaFields(acc, { video: false }));
      if (accType === T.BUTTON) accBox.append(this.buttonFields(acc));

      body.append(
        h('div', { class: 'dm-label' }, t('COM_DREKMETA_BUILDER_SECTION_TEXTS', C.LIMITS.sectionTexts)),
        texts, addText, accBox,
      );
    }

    renderGallery(node, path, body) {
      node.items = node.items || [];
      const list = h('div', { class: 'dm-sublist' });

      node.items.forEach((item, j) => {
        list.append(h('div', { class: 'dm-subitem', dataset: { path: `${path}.items.${j}` } },
          h('div', { class: 'dm-subitem-head' },
            h('span', { class: 'dm-subitem-title', html: `${ICONS.image}<span>${C.escapeHtml(t('COM_DREKMETA_BUILDER_ITEM_N', j + 1))}</span>` }),
            this.subActions(node.items, j, 0)),
          this.mediaFields(item, { video: true })));
      });

      const total = C.countGalleryItems(this.container);

      body.append(list, h('button', {
        type: 'button', class: 'dm-sub-add', disabled: node.items.length >= C.LIMITS.gallery, html: `${ICONS.plus}<span>${C.escapeHtml(t('COM_DREKMETA_BUILDER_ADD_ITEM'))}</span>`,
        title: total >= C.LIMITS.gallery ? t('COM_DREKMETA_BUILDER_GALLERY_FULL') : '',
        onClick: () => { node.items.push({ media: { url: '' } }); this.changed(true); },
      }));
    }

    renderRow(node, path, body) {
      node.components = node.components || [];
      const list = h('div', { class: 'dm-sublist' });

      node.components.forEach((button, j) => {
        list.append(h('div', { class: 'dm-subitem', dataset: { path: `${path}.components.${j}` } },
          h('div', { class: 'dm-subitem-head' },
            h('span', { class: 'dm-subitem-title', html: `${ICONS.button}<span>${C.escapeHtml(t('COM_DREKMETA_BUILDER_BUTTON_N', j + 1))}</span>` }),
            this.subActions(node.components, j, 1)),
          this.buttonFields(button)));
      });

      body.append(list, h('button', {
        type: 'button', class: 'dm-sub-add', disabled: node.components.length >= C.LIMITS.rowButtons, html: `${ICONS.plus}<span>${C.escapeHtml(t('COM_DREKMETA_BUILDER_ADD_BUTTON'))}</span>`,
        onClick: () => { node.components.push({ type: T.BUTTON, style: 5, label: '', url: '' }); this.changed(true); },
      }));
    }

    subActions(list, j, min) {
      return h('div', { class: 'dm-card-actions' },
        iconBtn('up', t('COM_DREKMETA_BUILDER_MOVE_UP'), () => { move(list, j, j - 1); this.changed(true); }, { disabled: j === 0 }),
        iconBtn('down', t('COM_DREKMETA_BUILDER_MOVE_DOWN'), () => { move(list, j, j + 1); this.changed(true); }, { disabled: j === list.length - 1 }),
        iconBtn('copy', t('COM_DREKMETA_BUILDER_DUPLICATE'), () => { list.splice(j + 1, 0, C.clone(list[j])); this.changed(true); }),
        iconBtn('trash', t('COM_DREKMETA_BUILDER_DELETE'), () => { list.splice(j, 1); this.changed(true); }, { class: 'is-danger', disabled: list.length <= min }));
    }

    mediaFields(node, { video }) {
      node.media = node.media || { url: '' };
      const urlField = this.input(t(video ? 'COM_DREKMETA_BUILDER_MEDIA_URL' : 'COM_DREKMETA_BUILDER_IMAGE_URL'), node.media.url, (v) => { node.media.url = v; }, {
        type: 'url', placeholder: 'https://… or {og_image}', pick: true, help: t(video ? 'COM_DREKMETA_BUILDER_MEDIA_HELP_VIDEO' : 'COM_DREKMETA_BUILDER_MEDIA_HELP_IMAGE'),
      });
      const desc = this.input(t('COM_DREKMETA_BUILDER_ALT'), node.description || '', (v) => { if (v === '') delete node.description; else node.description = v; }, { maxlength: C.LIMITS.description });
      const spoiler = this.toggle(t('COM_DREKMETA_BUILDER_SPOILER'), !!node.spoiler, (on) => { if (on) node.spoiler = true; else delete node.spoiler; this.changed(); });

      return h('div', { class: 'dm-fields' }, urlField, h('div', { class: 'dm-fields-2' }, desc, h('div', { class: 'dm-field dm-field-toggle' }, spoiler)));
    }

    buttonFields(node) {
      const label = this.input(t('COM_DREKMETA_BUILDER_BUTTON_LABEL'), node.label || '', (v) => { node.label = v; }, { maxlength: C.LIMITS.label, placeholder: t('COM_DREKMETA_BUILDER_STARTER_BUTTON') });
      const emoji = this.input(t('COM_DREKMETA_BUILDER_BUTTON_EMOJI'), emojiToText(node.emoji), (v) => {
        const e = C.normalizeEmoji(v);
        if (e) node.emoji = e; else delete node.emoji;
      }, { placeholder: '🔗 or <:name:id>', help: t('COM_DREKMETA_BUILDER_BUTTON_EMOJI_HELP') });
      const url = this.input(t('COM_DREKMETA_BUILDER_BUTTON_URL'), node.url || '', (v) => { node.url = v; }, { type: 'url', placeholder: 'https://… or {page_url}' });
      const disabled = this.toggle(t('COM_DREKMETA_BUILDER_BUTTON_DISABLED'), !!node.disabled, (on) => { if (on) node.disabled = true; else delete node.disabled; this.changed(); });

      return h('div', { class: 'dm-fields' }, h('div', { class: 'dm-fields-2' }, label, emoji), url, h('div', { class: 'dm-field dm-field-toggle' }, disabled));
    }

    // -- form controls ------------------------------------------------------------------------------

    input(label, value, set, opts = {}) {
      const id = nextId('in');
      const input = h('input', {
        id, type: 'text', inputmode: opts.type === 'url' ? 'url' : null, class: 'form-control form-control-sm', value: value || '', placeholder: opts.placeholder, maxlength: opts.maxlength, spellcheck: opts.type === 'url' ? 'false' : null,
        onInput: (e) => { set(e.target.value); this.changed(); },
      });

      let control = input;
      if (opts.pick) {
        control = h('div', { class: 'input-group input-group-sm' }, input,
          h('button', {
            type: 'button', class: 'btn btn-outline-secondary', html: `${ICONS.image}<span>${C.escapeHtml(t('COM_DREKMETA_BUILDER_PICK'))}</span>`,
            onClick: () => this.pickMedia((url) => { input.value = url; set(url); this.changed(); }),
          }));
      }

      return h('div', { class: 'dm-field' },
        label ? h('label', { class: 'dm-label', for: id }, label) : null,
        control,
        opts.help ? h('div', { class: 'dm-help' }, opts.help) : null);
    }

    textArea(label, value, set, opts = {}) {
      const id = nextId('ta');
      const count = h('span', { class: 'dm-count' });
      const ta = h('textarea', {
        id, class: 'form-control form-control-sm dm-md', rows: opts.rows || 3, spellcheck: 'true',
        placeholder: t('COM_DREKMETA_BUILDER_MARKDOWN_HINT'),
        onInput: (e) => { set(e.target.value); count.textContent = C.charLength(e.target.value); this.changed(); },
      });
      ta.value = value || '';
      count.textContent = C.charLength(ta.value);

      return h('div', { class: 'dm-field' },
        label ? h('div', { class: 'dm-label-row' }, h('label', { class: 'dm-label', for: id }, label), count) : null,
        ta);
    }

    toggle(label, checked, onChange) {
      const id = nextId('tg');
      return h('div', { class: 'form-check form-switch dm-switch' },
        h('input', {
          class: 'form-check-input', type: 'checkbox', role: 'switch', id, checked, onChange: (e) => onChange(e.target.checked),
        }),
        h('label', { class: 'form-check-label', for: id }, label));
    }

    segmented(options, value, onChange) {
      const wrap = h('div', { class: 'dm-segs dm-segs-sm', role: 'group' });
      options.forEach(([v, label]) => {
        wrap.append(h('button', {
          type: 'button', class: `dm-seg${v === value ? ' is-active' : ''}`, 'aria-pressed': v === value ? 'true' : 'false',
          onClick: () => onChange(v),
        }, label));
      });
      return wrap;
    }

    // -- actions ------------------------------------------------------------------------------------

    add(kind) {
      const node = BLANK[kind]();
      this.children.push(node);
      this.changed(true);
      window.requestAnimationFrame(() => {
        const card = this.listEl.querySelector(`.dm-card[data-index="${this.children.length - 1}"]`);
        if (card) {
          card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
          const first = card.querySelector('textarea, input[type=text]');
          if (first) first.focus({ preventScroll: true });
        }
      });
    }

    startFromPage() {
      if (this.children.length && !window.confirm(t('COM_DREKMETA_BUILDER_START_CONFIRM'))) return;

      const ctx = this.opts.getContext();
      const theme = this.opts.getThemeColor();
      const container = { type: T.CONTAINER };

      if (theme) container.accent_color = parseInt(theme.substring(1), 16);

      const heading = { type: T.TEXT, content: '# {og_title}\n{og_description}' };
      const first = ctx.vars.og_image
        ? { type: T.SECTION, components: [heading], accessory: { type: T.THUMB, media: { url: '{og_image}' } } }
        : heading;

      container.components = [
        first,
        { type: T.ROW, components: [{ type: T.BUTTON, style: 5, label: t('COM_DREKMETA_BUILDER_STARTER_BUTTON'), url: '{page_url}' }] },
      ];

      this.state = { component: container };
      this.jsonDirty = false;
      this.jsonInvalid = null;
      this.changed(true);
      this.refreshJson(true);

      if (!ctx.vars.og_image) this.flash(this.root.querySelector('.dm-btn-start'), t('COM_DREKMETA_BUILDER_START_NO_IMAGE'));

      if (!this.opts.isEnabled() && this.opts.enable) this.opts.enable();
    }

    insertPlaceholder(text) {
      const el = this.lastFocus;
      if (!el || !document.body.contains(el)) {
        this.copy(text, this.placeholderBar);
        return;
      }
      const start = el.selectionStart ?? el.value.length;
      const end = el.selectionEnd ?? el.value.length;
      el.value = el.value.slice(0, start) + text + el.value.slice(end);
      el.focus();
      el.setSelectionRange(start + text.length, start + text.length);
      el.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // -- media picker (reuses the core media field so we get Joomla's media manager for free) -------

    bindMediaPicker() {
      const field = this.opts.mediaField;
      if (!field) return;
      const input = field.querySelector('input[name$="[embed_media_picker]"]') || field.querySelector('input');
      if (!input) return;

      input.addEventListener('change', () => {
        const value = input.value;
        if (!value || !this.pickSetter) return;
        const ctx = this.opts.getContext();
        const url = C.imageUrl(value, ctx.root);
        const setter = this.pickSetter;
        this.pickSetter = null;
        setter(url);
        window.setTimeout(() => {
          if (typeof field.setValue === 'function') field.setValue(''); else input.value = '';
        }, 0);
      });
    }

    pickMedia(setter) {
      const field = this.opts.mediaField;
      const button = field && field.querySelector('.button-select');
      if (!button) {
        window.alert(t('COM_DREKMETA_BUILDER_PICK_UNAVAILABLE'));
        return;
      }
      this.pickSetter = setter;
      button.click();
    }

    // -- drag & drop ----------------------------------------------------------------------------------

    bindDnd(list) {
      let from = null;
      let indicator = null;

      const clear = () => {
        list.querySelectorAll('.dm-drop-before, .dm-drop-after').forEach((el) => el.classList.remove('dm-drop-before', 'dm-drop-after'));
        indicator = null;
      };

      list.addEventListener('dragstart', (e) => {
        const card = e.target.closest('.dm-card[data-index]');
        if (!card || !card.draggable) return;
        from = parseInt(card.dataset.index, 10);
        card.classList.add('is-dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', String(from));
      });

      list.addEventListener('dragover', (e) => {
        if (from === null) return;
        e.preventDefault();
        const card = e.target.closest('.dm-card[data-index]');
        if (!card) return;
        const rect = card.getBoundingClientRect();
        const after = e.clientY > rect.top + rect.height / 2;
        clear();
        card.classList.add(after ? 'dm-drop-after' : 'dm-drop-before');
        indicator = { index: parseInt(card.dataset.index, 10), after };
      });

      list.addEventListener('drop', (e) => {
        if (from === null) return;
        e.preventDefault();
        if (indicator) {
          let to = indicator.index + (indicator.after ? 1 : 0);
          if (to > from) to -= 1;
          move(this.children, from, to);
        }
        from = null;
        clear();
        this.changed(true);
      });

      list.addEventListener('dragend', () => {
        list.querySelectorAll('.is-dragging').forEach((el) => el.classList.remove('is-dragging'));
        from = null;
        clear();
      });
    }

    // -- update cycle -------------------------------------------------------------------------------

    update() {
      const ctx = this.opts.getContext();

      if (this.children.length) {
        this.result = C.process(C.clone(this.state), ctx.vars, ctx.root, ctx.hostRoot);
      } else {
        this.result = {
          stored: null, resolved: null, json: '', errors: [{ path: 'root', key: 'COM_DREKMETA_EMBED_ERR_CONTAINER_EMPTY', args: [] }], stats: { bytes: 0, components: 0, gallery: 0 },
        };
      }

      this.field.value = this.storedJson();

      this.updateCounters();
      this.updateErrors();
      this.updateSummaries();
      this.updatePreview(ctx);
      this.refreshJson();
      this.updateStatus();
      this.checkImageSizes(ctx);

      if (this.opts.onChange) this.opts.onChange(this.result);
    }

    updateCounters() {
      const s = this.result.stats;
      const set = (key, value, limit) => {
        const c = this.counterEls[key];
        c.value.textContent = `${value.toLocaleString()} / ${limit.toLocaleString()}`;
        const pct = Math.min(100, (value / limit) * 100);
        c.bar.style.width = `${pct}%`;
        c.el.classList.toggle('is-over', value > limit);
        c.el.classList.toggle('is-warn', value <= limit && pct >= 85);
      };
      set('bytes', s.bytes, C.LIMITS.bytes);
      set('components', s.components, C.LIMITS.components);
      set('gallery', s.gallery, C.LIMITS.gallery);
    }

    describe(e, children = this.children) {
      const parts = e.path.split('.');
      let where = '';
      if (e.path === 'root') {
        where = t('COM_DREKMETA_BUILDER_CONTAINER');
      } else if (e.path !== '') {
        where = t('COM_DREKMETA_BUILDER_COMPONENT_N', parseInt(parts[0], 10) + 1);
        const sub = this.subLabel(parts, children);
        if (sub) where += ` › ${sub}`;
      }
      return (where ? `${where}: ` : '') + C.errorMessage(e);
    }

    subLabel(parts, children = this.children) {
      if (parts.length < 2) return '';
      const node = (children || [])[parseInt(parts[0], 10)];
      if (parts[1] === 'accessory') return t('COM_DREKMETA_BUILDER_ACCESSORY');
      const n = parseInt(parts[2], 10) + 1;
      if (parts[1] === 'items') return t('COM_DREKMETA_BUILDER_ITEM_N', n);
      if (parts[1] === 'components') return node && Number(node.type) === T.ROW ? t('COM_DREKMETA_BUILDER_BUTTON_N', n) : t('COM_DREKMETA_BUILDER_TEXT_N', n);
      return '';
    }

    updateErrors() {
      const errors = this.result.errors;
      this.root.querySelectorAll('.is-invalid-block').forEach((el) => el.classList.remove('is-invalid-block'));

      this.cards.forEach(({ el, path }) => {
        const mine = errors.filter((e) => (path === 'root' ? e.path === 'root' : e.path === path || e.path.startsWith(`${path}.`)));
        const box = el.querySelector(':scope > .dm-card-errors');
        box.innerHTML = '';
        el.classList.toggle('has-errors', mine.length > 0);
        const badge = el.querySelector(':scope > .dm-card-head > .dm-card-errcount');
        if (badge) badge.textContent = mine.length ? String(mine.length) : '';

        mine.forEach((e) => {
          const parts = e.path.split('.');
          const sub = path === 'root' ? '' : this.subLabel(parts);
          box.append(h('div', { class: 'dm-error' }, h('span', { html: ICONS.warn }), h('span', {}, (sub ? `${sub}: ` : '') + C.errorMessage(e))));
          const target = el.querySelector(`[data-path="${CSS.escape(e.path)}"]`);
          if (target && target !== el) target.classList.add('is-invalid-block');
        });
      });
    }

    updateSummaries() {
      this.cards.forEach(({ node, summary }) => {
        if (summary) summary.textContent = this.summary(node);
      });
    }

    updatePreview(ctx) {
      const enabled = this.opts.isEnabled();
      const html = C.renderComponentEmbed(this.result.resolved, ctx.vars.page_url);
      const key = `${enabled ? 1 : 0}${html}`;
      if (key === this.lastPreview) return;
      this.lastPreview = key;
      this.previewEl.innerHTML = html;
      if (!enabled) {
        this.previewEl.prepend(h('div', { class: 'dm-preview-note' }, t('COM_DREKMETA_BUILDER_PREVIEW_DISABLED')));
      }
      this.previewEl.querySelectorAll('.dm-spoiler, .dm-md-spoiler').forEach((el) => {
        el.addEventListener('click', () => el.classList.add('is-revealed'));
      });
    }

    updateStatus() {
      const enabled = this.opts.isEnabled();
      const blocking = this.blockingReason();
      this.statusEl.className = 'dm-status';
      if (!enabled) {
        this.statusEl.classList.add('is-off');
        this.statusEl.textContent = t('COM_DREKMETA_BUILDER_STATUS_OFF');
      } else if (blocking) {
        this.statusEl.classList.add('is-error');
        this.statusEl.textContent = t('COM_DREKMETA_BUILDER_STATUS_ERRORS');
      } else {
        this.statusEl.classList.add('is-ok');
        this.statusEl.textContent = t('COM_DREKMETA_BUILDER_STATUS_OK');
      }
    }

    /** Warns about large same-origin images (Discord must fetch everything within 10 seconds). */
    checkImageSizes(ctx) {
      const urls = this.result.resolved ? C.mediaUrls(this.result.resolved) : [];
      const local = urls.filter((u) => u.startsWith(ctx.root));
      const limit = this.opts.limits.largeImage;

      local.forEach((url) => {
        if (this.sizeCache.has(url)) return;
        this.sizeCache.set(url, null);
        fetch(url, { method: 'HEAD', credentials: 'same-origin' })
          .then((r) => {
            const len = parseInt(r.headers.get('content-length') || '0', 10);
            this.sizeCache.set(url, r.ok ? len : -1);
            this.renderNotices(ctx);
          })
          .catch(() => this.sizeCache.set(url, -1));
      });

      this.renderNotices(ctx, local, limit);
    }

    renderNotices(ctx, local, limit) {
      const urls = local || (this.result.resolved ? C.mediaUrls(this.result.resolved).filter((u) => u.startsWith(ctx.root)) : []);
      const max = limit || this.opts.limits.largeImage;
      this.noticesEl.innerHTML = '';
      const globalErrors = this.result.errors.filter((e) => e.path === '');

      globalErrors.forEach((e) => {
        this.noticesEl.append(h('div', { class: 'dm-notice is-error' }, h('span', { html: ICONS.warn }), h('span', {}, C.errorMessage(e))));
      });

      urls.forEach((url) => {
        const size = this.sizeCache.get(url);
        if (size && size > max) {
          this.noticesEl.append(h('div', { class: 'dm-notice is-warn' }, h('span', { html: ICONS.warn }), h('span', {}, t('COM_DREKMETA_BUILDER_LARGE_IMAGE', url.replace(ctx.root, ''), formatBytes(size)))));
        }
      });
    }

    // -- save gate ------------------------------------------------------------------------------------

    blockingReason() {
      if (this.jsonDirty) {
        return this.jsonInvalid
          ? `${t('COM_DREKMETA_BUILDER_JSON_INVALID')} ${this.jsonInvalid}`
          : t('COM_DREKMETA_BUILDER_JSON_UNAPPLIED');
      }
      if (!this.opts.isEnabled()) return null;
      if (!this.children.length) return t('COM_DREKMETA_EMBED_ERR_ENABLED_EMPTY');
      if (this.result && this.result.errors.length) {
        return `${t('COM_DREKMETA_BUILDER_SAVE_ERRORS', this.result.errors.length)} ${this.result.errors.slice(0, 3).map((e) => this.describe(e)).join(' · ')}`;
      }
      return null;
    }

    focusFirstError() {
      if (this.jsonDirty) {
        this.setPanel('json');
        this.setJsonMode('edit');
        this.jsonText.focus();
        return;
      }
      const el = this.root.querySelector('.dm-card.has-errors');
      if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    refresh() {
      this.update();
    }
  }

  DM.Builder = Builder;
})(window, document);
