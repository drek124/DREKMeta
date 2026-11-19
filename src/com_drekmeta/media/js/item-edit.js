/**
 * DREK Meta: edit screen glue. Wires the form fields to the builder and the preview tab, shows inherited values,
 * counters and auto-fills the title, and blocks saving while the component embed is invalid.
 *
 * @author   drek124 <https://github.com/drek124>
 * @license  GNU General Public License version 2 or later
 */
((window, document) => {
  'use strict';

  const DM = window.DrekMeta = window.DrekMeta || {};

  const init = () => {
    const C = DM.core;
    const { t } = C;
    const opts = window.Joomla.getOptions('com_drekmeta.item');
    const form = document.getElementById('item-form');

    if (!opts || !form) return;

    const field = (name) => form.querySelector(`[name="jform[${name}]"]`);
    const value = (name) => {
      const el = field(name);
      return el ? String(el.value || '') : '';
    };

    const readItem = () => {
      const o = {};
      C.META_FIELDS.forEach((f) => { o[f] = value(f); });
      if (/^(none|transparent)$/i.test(o.theme_color)) o.theme_color = '';
      return o;
    };

    const menuId = () => parseInt(value('menu_item_id') || '0', 10) || 0;
    const getMenu = () => (opts.isGlobal ? opts.home : (opts.menuItems[menuId()] || null));
    const getMerged = () => {
      const item = readItem();
      return opts.isGlobal ? C.merge(item, null) : C.merge(opts.global, item);
    };

    const getContext = () => {
      const merged = getMerged();
      const menu = getMenu();
      const { site } = opts;

      let title = merged.og_title;
      if (title === '' && opts.fallbacks.title) title = menu ? menu.docTitle : site.name;

      let description = merged.og_description;
      if (description === '' && opts.fallbacks.description) description = menu ? menu.description : site.description;

      return {
        vars: {
          og_title: title,
          og_description: description,
          og_image: C.imageUrl(merged.og_image, site.root),
          page_url: menu ? menu.url : site.root,
          site_name: merged.og_site_name !== '' ? merged.og_site_name : site.name,
        },
        root: site.root,
        hostRoot: site.hostRoot,
        merged,
        menu,
      };
    };

    const themeColor = () => {
      const c = getMerged().theme_color;
      return /^#[0-9a-f]{6}$/i.test(c) ? c.toLowerCase() : '';
    };

    const isEnabled = () => {
      const r = form.querySelector('input[name="jform[component_embed_enabled]"]:checked');
      return r ? r.value === '1' : false;
    };

    const enable = () => {
      const r = form.querySelector('input[name="jform[component_embed_enabled]"][value="1"]');
      if (r && !r.checked) {
        r.checked = true;
        r.dispatchEvent(new Event('change', { bubbles: true }));
      }
    };

    const controlsOf = (name) => {
      const el = field(name);
      if (!el) return null;
      const group = el.closest('.control-group');
      return group ? group.querySelector('.controls') : el.parentElement;
    };

    const note = (name, cls) => {
      const controls = controlsOf(name);
      if (!controls) return null;
      let el = controls.querySelector(`:scope > .${cls}`);
      if (!el) {
        el = document.createElement('div');
        el.className = `form-text ${cls}`;
        controls.append(el);
      }
      return el;
    };

    // -- builder ------------------------------------------------------------------------------------

    let builder = null;
    const builderRoot = document.getElementById('drekmeta-builder');

    // -- counters -------------------------------------------------------------------------------------

    const counters = () => {
      const title = value('og_title') || (opts.isGlobal ? '' : opts.global.og_title || '');
      const titleNote = note('og_title', 'drekmeta-counter');
      if (titleNote) {
        const bytes = C.byteLength(value('og_title'));
        titleNote.textContent = t('COM_DREKMETA_COUNTER_TITLE', C.charLength(value('og_title')), bytes);
        titleNote.classList.toggle('is-warn', bytes > 70);
      }

      const descNote = note('og_description', 'drekmeta-counter');
      if (descNote) {
        const chars = C.charLength(value('og_description'));
        const bytes = C.byteLength(value('og_description'));
        descNote.textContent = t('COM_DREKMETA_COUNTER_DESCRIPTION', chars, bytes);
        descNote.classList.toggle('is-warn', chars > 200 && bytes <= 350);
        descNote.classList.toggle('is-error', bytes > 350);
      }

      return title;
    };

    // -- inherited hints ------------------------------------------------------------------------------

    const setupInherited = () => {
      if (opts.isGlobal) return;
      const g = opts.global;

      const placeholder = (name, fallback) => {
        const el = field(name);
        if (!el) return;
        if (g[name]) el.placeholder = t('COM_DREKMETA_INHERITED_PLACEHOLDER', g[name]);
        else if (fallback) el.placeholder = fallback;
      };

      placeholder('og_title', opts.fallbacks.title ? t('COM_DREKMETA_FALLBACK_TITLE') : '');
      placeholder('og_description', opts.fallbacks.description ? t('COM_DREKMETA_FALLBACK_DESCRIPTION') : '');

      placeholder('og_site_name', t('COM_DREKMETA_FALLBACK_SITE_NAME', opts.site.name));

      ['og_type', 'twitter_card'].forEach((name) => {
        const el = field(name);
        const first = el && el.querySelector('option[value=""]');
        if (first && g[name]) {
          const label = name === 'twitter_card'
            ? (el.querySelector(`option[value="${CSS.escape(g[name])}"]`)?.textContent || g[name])
            : g[name];
          first.textContent = t('COM_DREKMETA_INHERIT_WITH_VALUE', label);
        }
      });
    };

    const updateInherited = () => {
      if (opts.isGlobal) return;
      const g = opts.global;

      // og:image:alt follows the image: only inherited when the image is inherited too.
      const altEl = field('og_image_alt');
      if (altEl) {
        altEl.placeholder = !value('og_image') && g.og_image_alt ? t('COM_DREKMETA_INHERITED_PLACEHOLDER', g.og_image_alt) : '';
      }

      const imgNote = note('og_image', 'drekmeta-inherit');
      if (imgNote) {
        imgNote.innerHTML = '';
        if (!value('og_image') && g.og_image_url) {
          const img = document.createElement('img');
          img.src = g.og_image_url;
          img.alt = '';
          img.className = 'drekmeta-inherit-thumb';
          imgNote.append(img, document.createTextNode(` ${t('COM_DREKMETA_INHERITED')}: ${C.cleanMediaPath(g.og_image)}`));
        }
      }

      const colorNote = note('theme_color', 'drekmeta-inherit');
      if (colorNote) {
        colorNote.innerHTML = '';
        const own = readItem().theme_color;
        if (!own && g.theme_color) {
          const sw = document.createElement('span');
          sw.className = 'drekmeta-swatch drekmeta-swatch-sm';
          sw.style.backgroundColor = g.theme_color;
          colorNote.append(sw, document.createTextNode(` ${t('COM_DREKMETA_INHERITED')}: ${g.theme_color}`));
        }
      }
    };

    // -- target tab: auto title + page URL --------------------------------------------------------------

    let lastAutoTitle = null;
    const onMenuChange = () => {
      if (opts.isGlobal) return;
      const menu = opts.menuItems[menuId()];
      const titleEl = field('title');
      if (menu && titleEl && (titleEl.value.trim() === '' || titleEl.value === lastAutoTitle)) {
        titleEl.value = menu.title;
        lastAutoTitle = menu.title;
      }
      const urlNote = note('menu_item_id', 'drekmeta-page-url');
      if (urlNote) {
        urlNote.innerHTML = '';
        if (menu) {
          const a = document.createElement('a');
          a.href = menu.url;
          a.target = '_blank';
          a.rel = 'noopener noreferrer';
          a.textContent = menu.url;
          urlNote.append(document.createTextNode(`${t('COM_DREKMETA_PAGE_URL')}: `), a);
        }
      }
    };

    // -- preview tab ------------------------------------------------------------------------------------

    const previewRoot = document.getElementById('drekmeta-preview');
    let previewMode = 'standard';

    const readCustomTags = () => {
      const rows = {};
      form.querySelectorAll('input[name^="jform[custom_tags]"], select[name^="jform[custom_tags]"]').forEach((el) => {
        const m = String(el.getAttribute('name') || '').match(/^jform\[custom_tags\]\[([^\]]+)\]\[(attribute|key|value)\]$/);
        if (!m) return;
        rows[m[1]] = rows[m[1]] || {};
        rows[m[1]][m[2]] = el.value;
      });
      return Object.values(rows).filter((r) => r.key && /^[A-Za-z0-9_:.-]{1,128}$/.test(r.key.trim()));
    };

    const source = (name, item, ctxValue) => {
      if (item[name]) return 'item';
      if (!opts.isGlobal && opts.global[name]) return 'inherited';
      return ctxValue ? 'fallback' : '';
    };

    const renderPreview = () => {
      if (!previewRoot) return;
      const ctx = getContext();
      const item = readItem();
      const m = ctx.merged;

      const meta = {
        og_title: ctx.vars.og_title,
        og_description: ctx.vars.og_description,
        og_site_name: ctx.vars.site_name,
        theme_color: m.theme_color,
        twitter_card: m.twitter_card,
        image_url: ctx.vars.og_image,
        og_image_alt: m.og_image_alt,
      };

      const canvas = previewRoot.querySelector('.drekmeta-preview-canvas');
      const noteEl = previewRoot.querySelector('.drekmeta-preview-note');
      noteEl.textContent = '';
      noteEl.className = 'drekmeta-preview-note';

      if (previewMode === 'standard') {
        canvas.innerHTML = C.renderStandardEmbed(meta, ctx.vars.page_url);
      } else {
        const result = builder ? builder.result : null;
        canvas.innerHTML = C.renderComponentEmbed(result ? result.resolved : null, ctx.vars.page_url);
        if (!isEnabled()) {
          noteEl.textContent = t('COM_DREKMETA_PREVIEW_COMPONENT_DISABLED');
          noteEl.classList.add('is-warn');
        } else if (!opts.fallbacks.masterSwitch) {
          noteEl.textContent = t('COM_DREKMETA_PREVIEW_MASTER_OFF');
          noteEl.classList.add('is-warn');
        } else if (result && result.errors.length) {
          noteEl.textContent = t('COM_DREKMETA_PREVIEW_COMPONENT_INVALID', result.errors.length);
          noteEl.classList.add('is-error');
        }
      }

      // Tag list: what the plugin will output, and where each value comes from.
      const rows = [];
      const add = (tag, val, src) => { if (val) rows.push([tag, val, src]); };
      add('og:title', ctx.vars.og_title, source('og_title', item, ctx.vars.og_title));
      add('og:description', ctx.vars.og_description, source('og_description', item, ctx.vars.og_description));
      add('og:type', m.og_type, source('og_type', item));
      add('og:site_name', ctx.vars.site_name, source('og_site_name', item, ctx.vars.site_name));
      add('og:image', ctx.vars.og_image, source('og_image', item));
      add('og:image:alt', m.og_image_alt, item.og_image ? 'item' : source('og_image_alt', item));
      add('twitter:card', m.twitter_card, source('twitter_card', item));
      add('theme-color', m.theme_color, source('theme_color', item));

      const custom = new Map();
      if (!opts.isGlobal) (opts.global.customTags || []).forEach((r) => custom.set(r.key.toLowerCase(), [r.key, r.value, 'inherited']));
      readCustomTags().forEach((r) => custom.set(r.key.trim().toLowerCase(), [r.key.trim(), r.value, 'item']));
      custom.forEach(([k, v, s]) => {
        const i = rows.findIndex((r) => r[0].toLowerCase() === k.toLowerCase());
        if (i !== -1) rows.splice(i, 1);
        rows.push([k, v, `${s}-custom`]);
      });

      const tbody = previewRoot.querySelector('.drekmeta-tags tbody');
      tbody.innerHTML = '';
      rows.forEach(([tag, val, src]) => {
        const tr = document.createElement('tr');
        const td1 = document.createElement('td');
        td1.innerHTML = `<code>${C.escapeHtml(tag)}</code>`;
        const td2 = document.createElement('td');
        td2.className = 'text-break';
        td2.textContent = val;
        const td3 = document.createElement('td');
        if (src) {
          const badge = document.createElement('span');
          const base = src.replace('-custom', '');
          badge.className = `badge drekmeta-src drekmeta-src-${base}`;
          badge.textContent = t(`COM_DREKMETA_SOURCE_${base.toUpperCase()}`) + (src.endsWith('-custom') ? ` · ${t('COM_DREKMETA_SOURCE_CUSTOM')}` : '');
          td3.append(badge);
        }
        tr.append(td1, td2, td3);
        tbody.append(tr);
      });
    };

    if (previewRoot) {
      previewRoot.querySelectorAll('[data-preview-mode]').forEach((btn) => {
        btn.addEventListener('click', () => {
          previewMode = btn.dataset.previewMode;
          previewRoot.querySelectorAll('[data-preview-mode]').forEach((b) => {
            b.classList.toggle('is-active', b === btn);
            b.setAttribute('aria-pressed', b === btn ? 'true' : 'false');
          });
          renderPreview();
        });
      });
    }

    // -- wire up ----------------------------------------------------------------------------------------

    if (builderRoot) {
      builder = new DM.Builder(builderRoot, {
        field: field('component_embed'),
        mediaField: document.querySelector('#drekmeta-media-picker joomla-field-media'),
        getContext,
        isEnabled,
        enable,
        getThemeColor: themeColor,
        serverErrors: opts.errors || [],
        debugger: opts.debugger,
        limits: opts.limits,
        onChange: () => renderPreview(),
      });
      DM.builder = builder;
    }

    let queued = false;
    const refreshAll = () => {
      if (queued) return;
      queued = true;
      // A timer, not requestAnimationFrame: rAF pauses in background tabs/panes and would leave the preview stale.
      window.setTimeout(() => {
        queued = false;
        counters();
        updateInherited();
        if (builder) builder.refresh(); else renderPreview();
      }, 0);
    };

    const watched = /^jform\[(og_|twitter_card|theme_color|menu_item_id|component_embed_enabled|custom_tags)/;
    // Capture phase on purpose: the core media field fires a non-bubbling "change" on its input when an image is
    // picked or cleared, which a normal (bubbling) form listener never hears.
    ['input', 'change'].forEach((type) => {
      form.addEventListener(type, (e) => {
        const name = e.target && e.target.name;
        if (!name || !watched.test(name)) return;
        if (name === 'jform[menu_item_id]') onMenuChange();
        refreshAll();
      }, true);
    });

    // The core colour picker (minicolors) fires jQuery events native listeners can't hear, so poll it.
    const colorEl = field('theme_color');
    if (colorEl) {
      let last = colorEl.value;
      window.setInterval(() => {
        if (colorEl.value !== last) {
          last = colorEl.value;
          refreshAll();
        }
      }, 400);
    }

    // Subform rows added/removed/moved don't always fire input events.
    const subform = form.querySelector('joomla-field-subform');
    if (subform) {
      new MutationObserver(() => refreshAll()).observe(subform, { childList: true, subtree: true });
    }

    setupInherited();
    if (!opts.isGlobal) {
      lastAutoTitle = (opts.menuItems[menuId()] || {}).title || null;
      onMenuChange();
    }
    counters();
    updateInherited();
    renderPreview();

    // Save gate: never let an invalid embed (or unapplied/invalid JSON) through.
    const original = window.Joomla.submitbutton;
    window.Joomla.submitbutton = (task, formSelector, validate) => {
      if (task !== 'item.cancel' && builder) {
        const reason = builder.blockingReason();
        if (reason) {
          window.Joomla.renderMessages({ error: [`${t('COM_DREKMETA_BUILDER_SAVE_BLOCKED_CLIENT')} ${reason}`] });
          const tabButton = document.querySelector('[aria-controls="embed"]');
          if (tabButton) tabButton.click();
          builder.focusFirstError();
          return false;
        }
      }
      return original(task, formSelector, validate);
    };
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})(window, document);
