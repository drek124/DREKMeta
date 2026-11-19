/**
 * DREK Meta: component embed core.
 *
 * 1:1 mirror of administrator/components/com_drekmeta/src/Helper/EmbedHelper.php so the builder's counters match
 * the plugin's output byte for byte. Same key order, same rules, same error keys. Change both or neither.
 *
 * Also contains the Discord-ish preview renderers (component embed + standard OG embed) and a tiny markdown renderer.
 *
 * @author   drek124 <https://github.com/drek124>
 * @license  GNU General Public License version 2 or later
 */
((window) => {
  'use strict';

  const DM = window.DrekMeta = window.DrekMeta || {};

  const LIMITS = {
    bytes: 3000,
    components: 40,
    gallery: 10,
    url: 2048,
    buttonUrl: 512,
    label: 80,
    description: 1024,
    sectionTexts: 3,
    rowButtons: 5,
  };

  const T = {
    ROW: 1, BUTTON: 2, SECTION: 9, TEXT: 10, THUMB: 11, GALLERY: 12, SEPARATOR: 14, CONTAINER: 17,
  };

  const BUTTON_KEYS = ['id', 'type', 'url', 'style', 'label', 'emoji', 'disabled'];
  const IMAGE_EXT = ['png', 'gif', 'jpg', 'jpeg', 'webp', 'avif'];
  const VIDEO_EXT = ['mp4', 'webm', 'mov'];
  const PLACEHOLDERS = ['og_title', 'og_description', 'og_image', 'page_url', 'site_name'];

  // ---------------------------------------------------------------------------------------------
  // PHP-compatible primitives
  // ---------------------------------------------------------------------------------------------

  const PHP_TRIM_RE = /^[ \n\r\t\v\0]+|[ \n\r\t\v\0]+$/g;
  const phpTrim = (s) => String(s).replace(PHP_TRIM_RE, '');

  const isObj = (v) => v !== null && typeof v === 'object';

  /** PHP is_array() on json_decode(..., true) output: arrays and objects both count. */
  const isArr = (v) => isObj(v);

  /** PHP array_values() for a decoded JSON value, [] for scalars. */
  const listOf = (v) => {
    if (Array.isArray(v)) return v;
    if (isObj(v)) return Object.values(v);
    return [];
  };

  /** PHP (int) cast for JSON-decoded values. */
  const phpInt = (v) => {
    if (typeof v === 'boolean') return v ? 1 : 0;
    if (typeof v === 'number') return Number.isFinite(v) ? Math.trunc(v) : 0;
    if (typeof v === 'string') {
      const m = v.trim().match(/^[+-]?\d+/);
      return m ? parseInt(m[0], 10) : 0;
    }
    if (Array.isArray(v)) return v.length ? 1 : 0;
    if (isObj(v)) return Object.keys(v).length ? 1 : 0;
    return 0;
  };

  /** PHP empty(). */
  const phpEmpty = (v) => {
    if (v === undefined || v === null || v === false || v === 0 || v === '' || v === '0') return true;
    if (Array.isArray(v)) return v.length === 0;
    if (isObj(v)) return Object.keys(v).length === 0;
    return false;
  };

  /** EmbedHelper::str(): scalars to string, everything else ''. */
  const str = (v) => {
    if (typeof v === 'string') return v;
    if (typeof v === 'number') return String(v);
    if (typeof v === 'boolean') return v ? '1' : '';
    return '';
  };

  const has = (o, k) => isObj(o) && Object.prototype.hasOwnProperty.call(o, k);

  const encoder = new TextEncoder();
  const byteLength = (s) => encoder.encode(s).length;
  const charLength = (s) => [...s].length;

  const err = (path, key, ...args) => ({ path, key, args });

  // U+2028 / U+2029, built from code points so no raw line separators end up in this file.
  const LS = new RegExp(String.fromCharCode(0x2028), 'g');
  const PS = new RegExp(String.fromCharCode(0x2029), 'g');

  // ---------------------------------------------------------------------------------------------
  // Text helpers
  // ---------------------------------------------------------------------------------------------

  const sprintf = (format, args) => {
    let i = 0;
    return String(format).replace(/%(?:(\d+)\$)?([sd%])/g, (m, pos, type) => {
      if (type === '%') return '%';
      const value = pos ? args[parseInt(pos, 10) - 1] : args[i++];
      return type === 'd' ? String(phpInt(value)) : String(value ?? '');
    });
  };

  const t = (key, ...args) => {
    const s = window.Joomla && window.Joomla.Text ? window.Joomla.Text._(key, key) : key;
    return args.length ? sprintf(s, args) : s;
  };

  const escapeHtml = (s) => String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

  // ---------------------------------------------------------------------------------------------
  // Normalisation (mirror of EmbedHelper::normalize and friends)
  // ---------------------------------------------------------------------------------------------

  const parseColor = (value) => {
    if (typeof value === 'number' && Number.isInteger(value)) {
      return value >= 0 && value <= 0xFFFFFF ? value : null;
    }
    if (typeof value === 'string' && /^\d+$/.test(value)) {
      const n = parseInt(value, 10);
      return n >= 0 && n <= 0xFFFFFF ? n : null;
    }
    if (typeof value === 'string') {
      const m = phpTrim(value).match(/^#?([0-9A-Fa-f]{6})$/);
      if (m) return parseInt(m[1], 16);
    }
    return null;
  };

  const mediaUrl = (c) => (isArr(c.media) ? str(c.media.url ?? '') : '');

  const normalizeEmoji = (emoji) => {
    if (typeof emoji === 'string') {
      emoji = phpTrim(emoji);
      if (emoji === '') return null;
      const m = emoji.match(/^<(a?):([A-Za-z0-9_]{2,32}):(\d{5,25})>$/);
      emoji = m ? { name: m[2], id: m[3], animated: m[1] === 'a' } : { name: emoji };
    }
    if (!isArr(emoji)) return null;

    const name = str(emoji.name ?? '');
    const id = has(emoji, 'id') && emoji.id !== null && /^\d{1,25}$/.test(str(emoji.id)) ? str(emoji.id) : '';

    if (name === '' && id === '') return null;

    const out = {};
    if (name !== '') out.name = name;
    if (id !== '') {
      out.id = id;
      if (!phpEmpty(emoji.animated)) out.animated = true;
    }
    return out;
  };

  const normalizeText = (c, path, errors) => {
    if (!isArr(c) || phpInt(c.type ?? 0) !== T.TEXT) {
      errors.push(err(path, 'COM_DREKMETA_EMBED_ERR_SECTION_CHILD'));
      return null;
    }
    const content = str(c.content ?? '');
    if (phpTrim(content) === '') errors.push(err(path, 'COM_DREKMETA_EMBED_ERR_TEXT_EMPTY'));
    return { type: T.TEXT, content };
  };

  const normalizeThumbnail = (c, path, errors) => {
    const out = { type: T.THUMB, media: { url: mediaUrl(c) } };
    if (out.media.url === '') errors.push(err(path, 'COM_DREKMETA_EMBED_ERR_URL_REQUIRED'));
    const description = str(c.description ?? '');
    if (description !== '') out.description = description;
    if (!phpEmpty(c.spoiler)) out.spoiler = true;
    return out;
  };

  const normalizeButton = (c, path, errors) => {
    Object.keys(c).forEach((key) => {
      if (!BUTTON_KEYS.includes(key)) errors.push(err(path, 'COM_DREKMETA_EMBED_ERR_BUTTON_KEY', key));
    });

    if (has(c, 'style') && phpInt(c.style) !== 5) errors.push(err(path, 'COM_DREKMETA_EMBED_ERR_BUTTON_STYLE'));

    const out = { type: T.BUTTON, style: 5 };
    const label = str(c.label ?? '');
    const emoji = normalizeEmoji(c.emoji ?? null);

    if (label !== '') out.label = label;
    if (emoji !== null) out.emoji = emoji;
    if (label === '' && emoji === null) errors.push(err(path, 'COM_DREKMETA_EMBED_ERR_BUTTON_LABEL'));

    out.url = str(c.url ?? '');
    if (phpTrim(out.url) === '') errors.push(err(path, 'COM_DREKMETA_EMBED_ERR_URL_REQUIRED'));
    if (!phpEmpty(c.disabled)) out.disabled = true;

    return out;
  };

  const normalizeSection = (c, path, errors) => {
    const texts = listOf(c.components ?? null);
    const out = { type: T.SECTION, components: [] };

    if (texts.length < 1 || texts.length > LIMITS.sectionTexts) {
      errors.push(err(path, 'COM_DREKMETA_EMBED_ERR_SECTION_TEXT_COUNT', LIMITS.sectionTexts));
    }

    texts.forEach((text, j) => {
      const n = normalizeText(text, `${path}.components.${j}`, errors);
      if (n !== null) out.components.push(n);
    });

    const accessory = c.accessory ?? null;
    const accType = isArr(accessory) ? phpInt(accessory.type ?? 0) : 0;

    if (accType === T.THUMB) {
      out.accessory = normalizeThumbnail(accessory, `${path}.accessory`, errors);
    } else if (accType === T.BUTTON) {
      const b = normalizeButton(accessory, `${path}.accessory`, errors);
      if (b !== null) out.accessory = b;
    } else {
      errors.push(err(`${path}.accessory`, 'COM_DREKMETA_EMBED_ERR_SECTION_ACCESSORY'));
    }

    return out;
  };

  const normalizeGallery = (c, path, errors) => {
    const items = listOf(c.items ?? null);
    const out = { type: T.GALLERY, items: [] };

    if (!items.length) errors.push(err(path, 'COM_DREKMETA_EMBED_ERR_GALLERY_EMPTY'));

    items.forEach((item, j) => {
      const itemPath = `${path}.items.${j}`;
      if (!isArr(item)) {
        errors.push(err(itemPath, 'COM_DREKMETA_EMBED_ERR_INVALID_COMPONENT'));
        return;
      }
      const n = { media: { url: mediaUrl(item) } };
      if (n.media.url === '') errors.push(err(itemPath, 'COM_DREKMETA_EMBED_ERR_URL_REQUIRED'));
      const description = str(item.description ?? '');
      if (description !== '') n.description = description;
      if (!phpEmpty(item.spoiler)) n.spoiler = true;
      out.items.push(n);
    });

    return out;
  };

  const normalizeSeparator = (c, path, errors) => {
    const out = { type: T.SEPARATOR };
    if (has(c, 'divider') && c.divider === false) out.divider = false;
    if (has(c, 'spacing') && c.spacing !== null) {
      const spacing = phpInt(c.spacing);
      if (spacing !== 1 && spacing !== 2) errors.push(err(path, 'COM_DREKMETA_EMBED_ERR_SEPARATOR_SPACING'));
      else if (spacing === 2) out.spacing = 2;
    }
    return out;
  };

  const normalizeActionRow = (c, path, errors) => {
    const buttons = listOf(c.components ?? null);
    const out = { type: T.ROW, components: [] };

    if (buttons.length < 1 || buttons.length > LIMITS.rowButtons) {
      errors.push(err(path, 'COM_DREKMETA_EMBED_ERR_ROW_BUTTON_COUNT', LIMITS.rowButtons));
    }

    buttons.forEach((button, j) => {
      const bPath = `${path}.components.${j}`;
      if (!isArr(button) || phpInt(button.type ?? 0) !== T.BUTTON) {
        errors.push(err(bPath, 'COM_DREKMETA_EMBED_ERR_ROW_CHILD'));
        return;
      }
      const n = normalizeButton(button, bPath, errors);
      if (n !== null) out.components.push(n);
    });

    return out;
  };

  const normalizeChild = (c, path, errors) => {
    if (!isArr(c) || !has(c, 'type') || c.type === null) {
      errors.push(err(path, 'COM_DREKMETA_EMBED_ERR_INVALID_COMPONENT'));
      return null;
    }
    const type = phpInt(c.type);
    switch (type) {
      case T.TEXT: return normalizeText(c, path, errors);
      case T.SECTION: return normalizeSection(c, path, errors);
      case T.GALLERY: return normalizeGallery(c, path, errors);
      case T.SEPARATOR: return normalizeSeparator(c, path, errors);
      case T.ROW: return normalizeActionRow(c, path, errors);
      case T.CONTAINER:
        errors.push(err(path, 'COM_DREKMETA_EMBED_ERR_NESTED_CONTAINER'));
        return null;
      case T.BUTTON:
      case T.THUMB:
        errors.push(err(path, 'COM_DREKMETA_EMBED_ERR_NOT_ALLOWED_HERE', type));
        return null;
      default:
        errors.push(err(path, 'COM_DREKMETA_EMBED_ERR_UNKNOWN_TYPE', type));
        return null;
    }
  };

  const normalize = (data, errors, indexMap = []) => {
    if (!isArr(data) || !isArr(data.component)) {
      errors.push(err('', 'COM_DREKMETA_EMBED_ERR_NO_ROOT'));
      return null;
    }
    const c = data.component;

    if (phpInt(c.type ?? 0) !== T.CONTAINER) {
      errors.push(err('root', 'COM_DREKMETA_EMBED_ERR_ROOT_NOT_CONTAINER'));
      return null;
    }

    const out = { type: T.CONTAINER };

    if (has(c, 'accent_color') && c.accent_color !== null && c.accent_color !== '') {
      const color = parseColor(c.accent_color);
      if (color === null) errors.push(err('root', 'COM_DREKMETA_EMBED_ERR_ACCENT_COLOR'));
      else out.accent_color = color;
    }

    if (!phpEmpty(c.spoiler)) out.spoiler = true;

    const children = listOf(c.components ?? null);
    out.components = [];

    if (!children.length) errors.push(err('root', 'COM_DREKMETA_EMBED_ERR_CONTAINER_EMPTY'));

    children.forEach((child, i) => {
      const n = normalizeChild(child, String(i), errors);
      if (n !== null) {
        out.components.push(n);
        indexMap.push(i);
      }
    });

    return { component: out };
  };

  // ---------------------------------------------------------------------------------------------
  // URLs, placeholders, validation
  // ---------------------------------------------------------------------------------------------

  const cleanMediaPath = (value) => {
    const pos = value.indexOf('#joomlaImage://');
    return pos === -1 ? value : value.substring(0, pos);
  };

  const absoluteUrl = (url, root, hostRoot) => {
    url = phpTrim(cleanMediaPath(url));
    if (url === '' || url.startsWith('{') || /^[a-z][a-z0-9+.-]*:/i.test(url)) return url;
    if (url.startsWith('//')) return `https:${url}`;
    if (url.startsWith('/')) return hostRoot.replace(/\/+$/, '') + url;
    return `${root.replace(/\/+$/, '')}/${url.replace(/^[./]+/, '')}`;
  };

  const walk = (node, fn) => {
    fn(node);
    ['components', 'items'].forEach((k) => {
      if (Array.isArray(node[k])) node[k].forEach((child) => walk(child, fn));
    });
    if (isObj(node.accessory)) walk(node.accessory, fn);
  };

  const clone = (v) => JSON.parse(JSON.stringify(v));

  const absolutize = (payload, root, hostRoot) => {
    const out = clone(payload);
    walk(out.component, (node) => {
      if (isObj(node.media) && typeof node.media.url === 'string') {
        node.media.url = absoluteUrl(node.media.url, root, hostRoot);
      }
      if (node.type === T.BUTTON && typeof node.url === 'string') {
        node.url = absoluteUrl(node.url, root, hostRoot);
      }
    });
    return out;
  };

  /** PHP strtr() with an array: longest match first, single pass. */
  const strtr = (s, map) => {
    const keys = Object.keys(map).filter((k) => k !== '').sort((a, b) => b.length - a.length);
    if (!keys.length) return s;
    const re = new RegExp(keys.map((k) => k.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|'), 'g');
    return s.replace(re, (m) => map[m]);
  };

  const resolve = (payload, vars) => {
    const map = {};
    PLACEHOLDERS.forEach((name) => { map[`{${name}}`] = String(vars[name] ?? ''); });
    const out = clone(payload);
    walk(out.component, (node) => {
      ['content', 'label', 'url', 'description'].forEach((k) => {
        if (typeof node[k] === 'string') node[k] = strtr(node[k], map);
      });
      if (isObj(node.media) && typeof node.media.url === 'string') node.media.url = strtr(node.media.url, map);
    });
    return out;
  };

  const hasErrorAt = (errors, path) => errors.some((e) => e.path === path);

  const urlPath = (url) => url.replace(/^https?:\/\/[^/?#]*/i, '').split(/[?#]/)[0];

  const extensionOf = (url) => {
    const base = urlPath(url).split('/').pop() || '';
    const dot = base.lastIndexOf('.');
    return dot === -1 ? '' : base.substring(dot + 1).toLowerCase();
  };

  const validateUrl = (url, path, maxLength, extensions, errors) => {
    if (phpTrim(url) === '') {
      if (!hasErrorAt(errors, path)) errors.push(err(path, 'COM_DREKMETA_EMBED_ERR_URL_EMPTY_RESOLVED'));
      return;
    }
    const ph = url.match(/\{[a-z_]+\}/);
    if (ph) {
      errors.push(err(path, 'COM_DREKMETA_EMBED_ERR_URL_PLACEHOLDER', ph[0]));
      return;
    }
    if (!/^https?:\/\/[^ \t\n\r\f\v/?#]+/i.test(url)) {
      errors.push(err(path, 'COM_DREKMETA_EMBED_ERR_URL_SCHEME'));
      return;
    }
    if (byteLength(url) > maxLength) errors.push(err(path, 'COM_DREKMETA_EMBED_ERR_URL_LENGTH', maxLength));
    if (extensions !== null && !extensions.includes(extensionOf(url))) {
      errors.push(err(path, 'COM_DREKMETA_EMBED_ERR_MEDIA_EXTENSION', extensions.join(', ')));
    }
  };

  const validateText = (text, path, errors) => {
    if (phpTrim(text.content) === '' && !hasErrorAt(errors, path)) {
      errors.push(err(path, 'COM_DREKMETA_EMBED_ERR_TEXT_EMPTY_RESOLVED'));
    }
  };

  const validateButton = (button, path, errors) => {
    if (typeof button.label === 'string' && charLength(button.label) > LIMITS.label) {
      errors.push(err(path, 'COM_DREKMETA_EMBED_ERR_LABEL_LENGTH', LIMITS.label));
    }
    validateUrl(button.url ?? '', path, LIMITS.buttonUrl, null, errors);
  };

  const validateMedia = (media, path, allowVideo, errors) => {
    if (typeof media.description === 'string' && charLength(media.description) > LIMITS.description) {
      errors.push(err(path, 'COM_DREKMETA_EMBED_ERR_DESCRIPTION_LENGTH', LIMITS.description));
    }
    const ext = allowVideo ? IMAGE_EXT.concat(VIDEO_EXT) : IMAGE_EXT;
    validateUrl(media.media?.url ?? '', path, LIMITS.url, ext, errors);
  };

  const countComponents = (node) => {
    let n = 1;
    (node.components || []).forEach((c) => { if (isObj(c)) n += countComponents(c); });
    if (isObj(node.accessory)) n += countComponents(node.accessory);
    return n;
  };

  const countGalleryItems = (node) => {
    let n = node.type === T.GALLERY ? (node.items || []).length : 0;
    (node.components || []).forEach((c) => { if (isObj(c)) n += countGalleryItems(c); });
    return n;
  };

  const validate = (payload, errors, indexMap = []) => {
    const root = payload.component;
    root.components.forEach((child, i) => {
      // Report against the original position, normalize() may have dropped invalid siblings.
      const path = String(indexMap[i] ?? i);
      switch (child.type) {
        case T.TEXT:
          validateText(child, path, errors);
          break;
        case T.SECTION:
          child.components.forEach((text, j) => validateText(text, `${path}.components.${j}`, errors));
          if (child.accessory) {
            if (child.accessory.type === T.THUMB) validateMedia(child.accessory, `${path}.accessory`, false, errors);
            else validateButton(child.accessory, `${path}.accessory`, errors);
          }
          break;
        case T.GALLERY:
          child.items.forEach((item, j) => validateMedia(item, `${path}.items.${j}`, true, errors));
          break;
        case T.ROW:
          child.components.forEach((b, j) => validateButton(b, `${path}.components.${j}`, errors));
          break;
        default:
      }
    });

    const components = countComponents(root);
    if (components > LIMITS.components) {
      errors.push(err('', 'COM_DREKMETA_EMBED_ERR_TOO_MANY_COMPONENTS', components, LIMITS.components));
    }
    const gallery = countGalleryItems(root);
    if (gallery > LIMITS.gallery) {
      errors.push(err('', 'COM_DREKMETA_EMBED_ERR_TOO_MANY_GALLERY_ITEMS', gallery, LIMITS.gallery));
    }
  };

  /**
   * json_encode($x, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG), byte-identical.
   * PHP still escapes U+2028/U+2029 without JSON_UNESCAPED_LINE_TERMINATORS.
   */
  const encode = (payload) => JSON.stringify(payload)
    .replace(/</g, '\\u003C')
    .replace(/>/g, '\\u003E')
    .replace(LS, '\\u2028')
    .replace(PS, '\\u2029');

  /** PHP json_encode(JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) for storage. */
  const encodeForStorage = (payload) => JSON.stringify(payload)
    .replace(LS, '\\u2028')
    .replace(PS, '\\u2029');

  const decode = (raw, errors) => {
    if (isObj(raw)) return raw;
    raw = phpTrim(raw ?? '');
    if (raw === '') {
      errors.push(err('', 'COM_DREKMETA_EMBED_ERR_EMPTY'));
      return null;
    }
    let data;
    try {
      data = JSON.parse(raw);
    } catch (e) {
      errors.push(err('', 'COM_DREKMETA_EMBED_ERR_JSON', e.message));
      return null;
    }
    if (!isArr(data)) {
      errors.push(err('', 'COM_DREKMETA_EMBED_ERR_NO_ROOT'));
      return null;
    }
    return data;
  };

  const process = (raw, vars, root, hostRoot) => {
    const result = {
      stored: null, resolved: null, json: null, errors: [], stats: { bytes: 0, components: 0, gallery: 0 },
    };
    const data = decode(raw, result.errors);
    if (data === null) return result;

    const indexMap = [];
    let stored = normalize(data, result.errors, indexMap);
    if (stored === null) return result;

    stored = absolutize(stored, root, hostRoot);
    result.stored = stored;
    const resolved = resolve(stored, vars);
    result.resolved = resolved;

    validate(resolved, result.errors, indexMap);

    const json = encode(resolved);
    result.json = json;
    result.stats.bytes = byteLength(json);
    result.stats.components = countComponents(resolved.component);
    result.stats.gallery = countGalleryItems(resolved.component);

    if (result.stats.bytes > LIMITS.bytes) {
      result.errors.push(err('', 'COM_DREKMETA_EMBED_ERR_TOO_MANY_BYTES', result.stats.bytes, LIMITS.bytes));
    }

    return result;
  };

  const mediaUrls = (payload) => {
    const urls = [];
    walk(payload.component, (node) => {
      if (isObj(node.media) && typeof node.media.url === 'string') urls.push(node.media.url);
    });
    return [...new Set(urls)];
  };

  const errorMessage = (e) => t(e.key, ...(e.args || []));

  // ---------------------------------------------------------------------------------------------
  // Meta helpers (mirror of MetaHelper::merge / image)
  // ---------------------------------------------------------------------------------------------

  const META_FIELDS = ['og_title', 'og_description', 'og_image', 'og_image_alt', 'og_type', 'og_site_name', 'twitter_card', 'theme_color'];

  const merge = (global, item) => {
    const merged = {};
    META_FIELDS.forEach((f) => {
      const iv = item ? phpTrim(str(item[f] ?? '')) : '';
      const gv = global ? phpTrim(str(global[f] ?? '')) : '';
      merged[f] = iv !== '' ? iv : gv;
    });
    const itemHasImage = item && phpTrim(str(item.og_image ?? '')) !== '';
    merged.og_image_alt = itemHasImage
      ? phpTrim(str(item.og_image_alt ?? ''))
      : (global ? phpTrim(str(global.og_image_alt ?? '')) : '');
    return merged;
  };

  const imageUrl = (value, root) => {
    value = phpTrim(value || '');
    if (value === '') return '';
    let path = cleanMediaPath(value);
    // Old media format kept size in the query string.
    path = path.replace(/([?&])joomla_image_(width|height)=\d*&?/g, '$1').replace(/[?&]$/, '');
    path = phpTrim(path);
    if (/^https?:\/\//i.test(path)) return path;
    if (path.startsWith('//')) return `https:${path}`;
    return `${root.replace(/\/+$/, '')}/${path.replace(/^\/+/, '')}`;
  };

  // ---------------------------------------------------------------------------------------------
  // Markdown (Discord subset) for previews. Input is escaped first, so output is safe.
  // ---------------------------------------------------------------------------------------------

  const safeHref = (url) => (/^https?:\/\//i.test(url) ? url : null);

  const inline = (text) => {
    const stash = [];
    const keep = (html) => {
      stash.push(html);
      return `\u0000${stash.length - 1}\u0000`;
    };

    let s = escapeHtml(text);

    s = s.replace(/`([^`\n]+)`/g, (m, code) => keep(`<code class="dm-md-code">${code}</code>`));
    s = s.replace(/\[([^\]\n]+)\]\((https?:\/\/[^)\s]+)\)/g, (m, label, url) => {
      const href = safeHref(url.replace(/&amp;/g, '&'));
      return href ? keep(`<a class="dm-md-link" href="${escapeHtml(href)}" target="_blank" rel="noopener noreferrer">${label}</a>`) : m;
    });
    s = s.replace(/(^|[\s(])(https?:\/\/[^\s<]+[^\s<.,;:!?)\]])/g, (m, pre, url) => {
      const href = safeHref(url.replace(/&amp;/g, '&'));
      return href ? pre + keep(`<a class="dm-md-link" href="${escapeHtml(href)}" target="_blank" rel="noopener noreferrer">${url}</a>`) : m;
    });
    s = s.replace(/\|\|([^|]+?)\|\|/g, '<span class="dm-md-spoiler" tabindex="0">$1</span>');
    s = s.replace(/\*\*\*([^*]+?)\*\*\*/g, '<strong><em>$1</em></strong>');
    s = s.replace(/\*\*([^*]+?)\*\*/g, '<strong>$1</strong>');
    s = s.replace(/__([^_]+?)__/g, '<u>$1</u>');
    s = s.replace(/(^|[^*])\*([^*\n]+?)\*/g, '$1<em>$2</em>');
    s = s.replace(/(^|[^\w_])_([^_\n]+?)_(?!\w)/g, '$1<em>$2</em>');
    s = s.replace(/~~([^~]+?)~~/g, '<s>$1</s>');

    // eslint-disable-next-line no-control-regex
    return s.replace(/\u0000(\d+)\u0000/g, (m, i) => stash[parseInt(i, 10)]);
  };

  const markdown = (text) => {
    const lines = String(text).replace(/\r\n?/g, '\n').split('\n');
    const out = [];
    let list = null;
    let inCode = false;
    let code = [];

    const closeList = () => {
      if (list) {
        out.push(`</${list}>`);
        list = null;
      }
    };

    lines.forEach((line) => {
      if (/^```/.test(line)) {
        if (inCode) {
          out.push(`<pre class="dm-md-pre"><code>${escapeHtml(code.join('\n'))}</code></pre>`);
          code = [];
          inCode = false;
        } else {
          closeList();
          inCode = true;
        }
        return;
      }
      if (inCode) {
        code.push(line);
        return;
      }

      let m;
      if ((m = line.match(/^(#{1,3}) (.+)$/))) {
        closeList();
        out.push(`<div class="dm-md-h${m[1].length}">${inline(m[2])}</div>`);
      } else if ((m = line.match(/^-# (.+)$/))) {
        closeList();
        out.push(`<div class="dm-md-sub">${inline(m[1])}</div>`);
      } else if ((m = line.match(/^>>?>? ?(.*)$/))) {
        closeList();
        out.push(`<div class="dm-md-quote">${inline(m[1]) || '&nbsp;'}</div>`);
      } else if ((m = line.match(/^\s*[-*] (.+)$/))) {
        if (list !== 'ul') {
          closeList();
          out.push('<ul class="dm-md-ul">');
          list = 'ul';
        }
        out.push(`<li>${inline(m[1])}</li>`);
      } else if ((m = line.match(/^\s*\d+\. (.+)$/))) {
        if (list !== 'ol') {
          closeList();
          out.push('<ol class="dm-md-ol">');
          list = 'ol';
        }
        out.push(`<li>${inline(m[1])}</li>`);
      } else {
        closeList();
        out.push(line.trim() === '' ? '<div class="dm-md-blank"></div>' : `<div class="dm-md-p">${inline(line)}</div>`);
      }
    });

    if (inCode) out.push(`<pre class="dm-md-pre"><code>${escapeHtml(code.join('\n'))}</code></pre>`);
    closeList();

    return out.join('');
  };

  // ---------------------------------------------------------------------------------------------
  // Preview renderers
  // ---------------------------------------------------------------------------------------------

  const isVideo = (url) => VIDEO_EXT.includes(extensionOf(url || ''));

  const media = (url, alt, cls) => {
    if (!url || !/^https?:\/\//i.test(url)) {
      return `<div class="${cls} dm-media-missing"><span>${escapeHtml(t('COM_DREKMETA_PREVIEW_NO_MEDIA'))}</span></div>`;
    }
    if (isVideo(url)) {
      return `<div class="${cls} dm-media-video"><video src="${escapeHtml(url)}" preload="metadata" muted></video><span class="dm-play" aria-hidden="true">&#9654;</span></div>`;
    }
    return `<img class="${cls}" src="${escapeHtml(url)}" alt="${escapeHtml(alt || '')}" loading="lazy">`;
  };

  const spoilerWrap = (html, spoiler) => (spoiler
    ? `<div class="dm-spoiler" tabindex="0" title="${escapeHtml(t('COM_DREKMETA_PREVIEW_SPOILER_REVEAL'))}">${html}<span class="dm-spoiler-pill">${escapeHtml(t('COM_DREKMETA_PREVIEW_SPOILER'))}</span></div>`
    : html);

  const linkIcon = '<svg class="dm-btn-icon" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M15 2a1 1 0 0 0 0 2h3.59l-8.3 8.3a1 1 0 1 0 1.42 1.4L20 5.42V9a1 1 0 1 0 2 0V3a1 1 0 0 0-1-1h-6Z"/><path fill="currentColor" d="M5 5a3 3 0 0 0-3 3v11a3 3 0 0 0 3 3h11a3 3 0 0 0 3-3v-6a1 1 0 1 0-2 0v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1h6a1 1 0 1 0 0-2H5Z"/></svg>';

  const renderButton = (b) => {
    let emoji = '';
    if (b.emoji) {
      emoji = b.emoji.id
        ? `<img class="dm-btn-emoji" src="https://cdn.discordapp.com/emojis/${escapeHtml(b.emoji.id)}.${b.emoji.animated ? 'gif' : 'png'}?size=44" alt="${escapeHtml(b.emoji.name || '')}">`
        : `<span class="dm-btn-emoji">${escapeHtml(b.emoji.name || '')}</span>`;
    }
    return `<span class="dm-btn${b.disabled ? ' is-disabled' : ''}" title="${escapeHtml(b.url || '')}">${emoji}${b.label ? `<span>${escapeHtml(b.label)}</span>` : ''}${linkIcon}</span>`;
  };

  const renderGallery = (items) => {
    const n = items.length;
    const cls = n >= 5 ? 'dm-gallery-many' : `dm-gallery-${n}`;
    return `<div class="dm-gallery ${cls}">${items.map((it) => `<div class="dm-gallery-item">${spoilerWrap(media(it.media?.url, it.description, 'dm-gallery-media'), it.spoiler)}</div>`).join('')}</div>`;
  };

  const renderChild = (c) => {
    switch (c.type) {
      case T.TEXT:
        return `<div class="dm-text">${markdown(c.content)}</div>`;
      case T.SECTION: {
        const texts = c.components.map((tx) => `<div class="dm-text">${markdown(tx.content)}</div>`).join('');
        let acc = '';
        if (c.accessory && c.accessory.type === T.THUMB) {
          acc = `<div class="dm-section-thumb">${spoilerWrap(media(c.accessory.media?.url, c.accessory.description, 'dm-thumb'), c.accessory.spoiler)}</div>`;
        } else if (c.accessory && c.accessory.type === T.BUTTON) {
          acc = `<div class="dm-section-btn">${renderButton(c.accessory)}</div>`;
        }
        return `<div class="dm-section"><div class="dm-section-texts">${texts}</div>${acc}</div>`;
      }
      case T.GALLERY:
        return renderGallery(c.items);
      case T.SEPARATOR:
        return `<div class="dm-sep dm-sep-${c.spacing === 2 ? 'lg' : 'sm'}">${c.divider === false ? '' : '<hr>'}</div>`;
      case T.ROW:
        return `<div class="dm-row">${c.components.map(renderButton).join('')}</div>`;
      default:
        return '';
    }
  };

  const toHex = (int) => `#${int.toString(16).padStart(6, '0')}`;

  const messageShell = (inner, pageUrl) => `
    <div class="dm-discord">
      <div class="dm-message">
        <div class="dm-avatar" aria-hidden="true"></div>
        <div class="dm-message-body">
          <div class="dm-message-head"><span class="dm-username">${escapeHtml(t('COM_DREKMETA_PREVIEW_USERNAME'))}</span><span class="dm-timestamp">${escapeHtml(t('COM_DREKMETA_PREVIEW_TIMESTAMP'))}</span></div>
          <div class="dm-message-link">${escapeHtml(pageUrl || '')}</div>
          ${inner}
        </div>
      </div>
    </div>`;

  /** Discord-ish render of a resolved component embed payload. */
  const renderComponentEmbed = (payload, pageUrl) => {
    if (!payload || !payload.component) {
      return messageShell(`<div class="dm-empty">${escapeHtml(t('COM_DREKMETA_PREVIEW_EMPTY_EMBED'))}</div>`, pageUrl);
    }
    const c = payload.component;
    const accent = Number.isInteger(c.accent_color) ? `border-left-color:${toHex(c.accent_color)}` : '';
    const body = c.components.map(renderChild).join('');
    const box = `<div class="dm-container${accent ? ' has-accent' : ''}" style="${accent}">${body}</div>`;
    return messageShell(spoilerWrap(box, c.spoiler), pageUrl);
  };

  /** Discord-ish render of a standard OG link preview. */
  const renderStandardEmbed = (meta, pageUrl) => {
    const color = /^#[0-9a-f]{6}$/i.test(meta.theme_color || '') ? meta.theme_color : '#1e1f22';
    const large = (meta.twitter_card || 'summary_large_image') === 'summary_large_image';
    const img = meta.image_url && /^https?:\/\//i.test(meta.image_url)
      ? `<img class="${large ? 'dm-og-image' : 'dm-og-thumb'}" src="${escapeHtml(meta.image_url)}" alt="${escapeHtml(meta.og_image_alt || '')}" loading="lazy">`
      : '';
    const inner = `
      <div class="dm-og${large ? ' is-large' : ' is-summary'}" style="border-left-color:${color}">
        <div class="dm-og-main">
          ${meta.og_site_name ? `<div class="dm-og-site">${escapeHtml(meta.og_site_name)}</div>` : ''}
          ${meta.og_title ? `<div class="dm-og-title">${escapeHtml(meta.og_title)}</div>` : ''}
          ${meta.og_description ? `<div class="dm-og-desc">${escapeHtml(meta.og_description)}</div>` : ''}
          ${large ? img : ''}
        </div>
        ${large ? '' : img}
      </div>`;
    return messageShell(inner, pageUrl);
  };

  DM.core = {
    LIMITS,
    TYPES: T,
    BUTTON_KEYS,
    IMAGE_EXT,
    VIDEO_EXT,
    PLACEHOLDERS,
    META_FIELDS,
    t,
    sprintf,
    escapeHtml,
    phpTrim,
    byteLength,
    charLength,
    parseColor,
    toHex,
    normalize,
    normalizeEmoji,
    absolutize,
    absoluteUrl,
    cleanMediaPath,
    resolve,
    validate,
    encode,
    encodeForStorage,
    decode,
    process,
    countComponents,
    countGalleryItems,
    mediaUrls,
    errorMessage,
    merge,
    imageUrl,
    markdown,
    renderComponentEmbed,
    renderStandardEmbed,
    clone,
  };
})(window);
