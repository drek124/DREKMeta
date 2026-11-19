<div align="center">

# DREK Meta

**Control how your Joomla pages unfurl in Discord, X and everywhere else.**

Per-menu-item Open Graph, Twitter Card, `theme-color` and custom meta tags,
plus a visual builder for Discord's new **Component Embeds**.

[![Joomla 5 | 6](https://img.shields.io/badge/Joomla-5.x%20%7C%206.x-1a3867?logo=joomla&logoColor=white)](https://www.joomla.org/)
[![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-777bb4?logo=php&logoColor=white)](https://www.php.net/)
[![License: GPL v2+](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)

[**Download**](../../releases/latest) · [Features](#features) · [Install](#install) · [Component embeds](#discord-component-embeds) · [How it works](#how-it-works) · [Development](#development)

<img src=".github/assets/builder.jpg" alt="The component embed builder: component tree on the left, live Discord preview on the right, byte and component counters on top" width="820">

</div>

---

## Why

Joomla core gives you a meta description and robots per menu item. That's it. No `og:image`, no `theme-color`, and definitely no Discord component embeds, so links to your site unfurl as whatever the template happens to guess. DREK Meta fixes that, per page, from one admin screen.

## Features

- 🏷️ **Meta tags per menu item**: `og:title`, `og:description`, `og:image` (+ alt, auto width/height), `og:type`, `og:site_name`, `twitter:card`, `theme-color`
- 🌍 **Global defaults** that every page inherits field by field, so you only override what's different
- ➕ **Custom tags**: any `name` / `property` / `http-equiv` tag, reorderable, overrides presets with the same key
- 🧱 **Discord Component Embed builder**: containers, sections, text, thumbnails, media galleries, separators and link buttons, with drag-and-drop
- 📏 **Live limits**: bytes, components and gallery items, measured on the *exact* JSON the plugin prints
- 🔍 **Previews** that look like Discord, for both the standard OG card and the component embed, plus a table showing where every tag's value comes from
- 🧩 **Placeholders** like `{og_title}` and `{page_url}`, so one layout works on every page
- 🛡️ **Validated three times** (browser, save, render). A broken embed never reaches the page; the OG tags stay as the fallback.
- 🧹 **Clean uninstall**: removes everything, including the table

<div align="center">
<img src=".github/assets/preview.jpg" alt="Preview tab: a Discord-style standard embed next to a table of the tags the page will get and where each value comes from" width="820">
</div>

## Requirements

| | |
|---|---|
| Joomla | 5.x or 6.x (built to also run on 4.4) |
| PHP | 8.1+ |
| Database | MySQL / MariaDB, PostgreSQL |

## Install

1. Grab `pkg_drekmeta-x.y.z.zip` from the [latest release](../../releases/latest).
2. In Joomla: **System → Install → Extensions → Upload Package File**.
3. Open **Components → DREK Meta**.

The package installs the admin component and the system plugin, switches the plugin on, and creates the **Global defaults** record.

> [!TIP]
> Prefer the CLI? `php cli/joomla.php extension:install --path=/path/to/pkg_drekmeta-x.y.z.zip`

## Usage

### 1. Set the global defaults

The first row in the list, **Global defaults (all pages)**, applies to every page. Give it at least an image and a colour. It can't be deleted or unpublished.

### 2. Override per menu item

Click **New**, pick a menu item and fill in only what should be different. Empty fields inherit from the global defaults; the field placeholders tell you what they'll inherit.

| Tab | What's in it |
|---|---|
| **Target** | Menu item (one record each), admin label that fills itself in |
| **Meta Tags** | og:title, og:description (with char/byte counter), og:image + alt, og:type, og:site_name, twitter:card, theme-color |
| **Custom Tags** | Extra `<meta>` rows: attribute, key, value |
| **Component Embed** | The builder (see below) |
| **Preview** | Discord-style standard / component embed, plus the full tag list with sources (*this item*, *inherited*, *fallback*, *custom*) |

### 3. Check it

View the page source, or ask like Discord does:

```powershell
curl.exe -s -A "Discordbot/2.0" https://your-site.example/some-page | Select-String 'og:|twitter:|theme-color|discord:'
```

Then drop the URL into Discord's [Embed Debugger](https://discord.com/developers/embeds). When pasting into Discord itself, add `?v=2` (bump it each time) to get past Discord's ~30 minute cache.

## Discord Component Embeds

[Component embeds](https://docs.discord.com/developers/link-previews/component-embeds) let a page describe its own Discord preview with real components instead of a plain OG card. DREK Meta builds the payload visually and prints it into `<head>`:

```html
<script id="discord:component-embed" type="application/json">{"component":{"type":17,…}}</script>
```

**The builder**

- A fixed root **Container** (accent colour defaults to your theme-color, optional spoiler)
- **Text Display**, **Section** (1 to 3 texts + a thumbnail *or* link button), **Media Gallery**, **Separator**, **Buttons** (up to 5 link buttons)
- Drag or arrow to reorder, duplicate, delete, collapse
- **Pick** images and videos straight from Joomla's media manager
- **Start from page** gives you a ready-made layout: title + description + thumbnail, and an *Open* button
- **JSON tab**: edit the raw payload, import a `.json`, copy it, or view the final resolved output

**Placeholders** work in any text or URL field and are filled in per page at render time:

| Placeholder | Becomes |
|---|---|
| `{og_title}` | The page's final og:title |
| `{og_description}` | The page's final og:description |
| `{og_image}` | The absolute og:image URL |
| `{page_url}` | The current page URL |
| `{site_name}` | og:site_name, or the site name |

**Limits** (enforced live, on save, and again when the page renders):

| Rule | Limit |
|---|---|
| Payload size, measured *after* encoding (escapes count) | 3,000 bytes |
| Components, Container included | 40 |
| Media gallery items across the whole embed | 10 |
| Buttons | link style (`5`) only; keys limited to `id type url style label emoji disabled` |
| Media URLs | http(s), ≤ 2,048 chars, png / gif / jpg / jpeg / webp / avif (gallery also mp4 / webm / mov) |
| Button labels / alt text | 80 / 1,024 characters |

> [!IMPORTANT]
> Discord drops the **whole** embed if a button has a key it doesn't know. Unknown keys on other components are stripped silently, but extra button keys block saving, so you find out in the admin and not in Discord.

If an embed is invalid or too big at render time (say, a placeholder resolves to something too long on one page), the plugin prints nothing for it, keeps the OG tags, and logs why to `administrator/logs/drekmeta.php`.

## How it works

On every site HTML page, `plg_system_drekmeta` hooks `onBeforeCompileHead` and:

1. Finds the active menu item. With **Exact Menu Item Pages Only** on, a record only applies when the page *is* that menu item. An article opened from a blog gets the global defaults, not the blog's title.
2. Loads the global and the item record in one query (published only, cached per request).
3. Merges them field by field. `og:image:alt` follows whichever record supplied the image.
4. Fills gaps from the document title / meta description / site name (configurable).
5. Strips `og:*` / `twitter:*` tags others added (configurable), then writes its own: `property=` for OG, `name=` for `twitter:*` and `theme-color`, mirroring title, description and image to Twitter tags.
6. Applies custom tags last, so they win.
7. Resolves, re-validates and prints the component embed, for **every** visitor (not only Discordbot) so page caching can't serve the wrong variant.

Nothing here ever throws on the frontend. Errors are caught and logged, and disabling the plugin puts the page back exactly as it was.

## Options

**Components → DREK Meta → Options**

| Option | Default | What it does |
|---|:---:|---|
| Remove Existing OG/Twitter Tags | ✅ | Strip tags the template or other extensions added |
| Output og:url | ✅ | Add the current URL as `og:url` |
| Title Fallback | ✅ | Use the document title when no og:title is set |
| Description Fallback | ✅ | Use the meta description when no og:description is set |
| Exact Menu Item Pages Only | ✅ | See step 1 above |
| Discord Component Embeds | ✅ | Master switch for every component embed |

Plus standard Joomla **Permissions**.

## Uninstall

Uninstall **pkg_drekmeta** under **System → Manage → Extensions**. The component, plugin, media, language files and the `#__drekmeta_items` table are all removed. Only `administrator/logs/drekmeta.php` stays, if anything was ever logged.

## Development

```
src/
├── pkg_drekmeta.xml · script.php · language/      package manifest, installer script
├── com_drekmeta/
│   ├── drekmeta.xml
│   ├── administrator/
│   │   ├── src/Helper/EmbedHelper.php             embed validation, shared with the plugin
│   │   ├── src/Helper/MetaHelper.php              merge rules, image URLs, custom tags
│   │   └── access.xml · config.xml · forms/ · sql/ · tmpl/ · language/ · services/
│   └── media/
│       └── js/embed-core.js                       1:1 JS mirror of EmbedHelper.php
│           js/builder.js · js/item-edit.js · css/drekmeta.css
└── plg_system_drekmeta/                           Drek\Plugin\System\Drekmeta
```

> [!NOTE]
> `embed-core.js` mirrors `EmbedHelper.php` line by line (same key order, rules and error keys). That's why the builder's byte counter matches what the plugin prints exactly. Change one, change the other.

### Releasing

There's no local build step. Publishing a GitHub release builds the package:

1. Create a release with a tag like `v1.2.3`.
2. [`release.yml`](.github/workflows/release.yml) lints the PHP, JS and XML, stamps `1.2.3` into all three manifests, builds `pkg_drekmeta-1.2.3.zip` and attaches it to the release.

The tag is the only place the version lives; the `<version>` in `src/` is just a placeholder. Need a zip without releasing? Run **Actions → Release package → Run workflow** and download it from the run's artifacts.

<details>
<summary><b>Manual test checklist</b></summary>

<br>

1. **Install** the package. *System - DREK Meta* is enabled, and the list shows the global row.
2. **Global defaults**: set an image and colour. Every page's source has `og:title`, `og:type`, `og:site_name`, `og:url`, `og:image` (absolute) + width/height/alt, the `twitter:*` mirrors and `theme-color`.
3. **Per item**: give one menu item a different image. That page uses it (with its real size); others keep the global one.
4. **Custom tag**: add `property` / `og:locale` / `en_US`. It shows up. A custom tag with a preset's key replaces the preset.
5. **Component embed**: *Start from page*, switch it on, save. `curl -A "Discordbot/2.0" <url>` shows the `<script id="discord:component-embed">` tag, and its byte count equals the builder's **Bytes** counter.
6. **Limits**: 41 components, 11 gallery items, > 3,000 bytes, or a button with an extra key turns the counter / component red and blocks saving. Force one into the `component_embed` column instead: the page prints no embed, keeps its OG tags, and `administrator/logs/drekmeta.php` says why.
7. **Discord**: check the public URL in the [Embed Debugger](https://discord.com/developers/embeds), then paste it into Discord with `?v=N`.
8. **Disable the plugin**: every DREK Meta tag disappears and the page source is back to what the template outputs on its own.
9. **Uninstall, then reinstall**: the site keeps working, the table and every file are gone, and reinstalling brings back a fresh global row.

</details>

<details>
<summary><b>Known limits</b></summary>

<br>

- The builder estimates fallback titles and descriptions from the menu item. If a component sets a different title at runtime and you use `{og_title}` without setting og:title, the live byte count can differ a little. Set og:title, or check the live page.
- The global record's embed is validated on save against the home page. On other pages it's re-validated at render time and skipped (and logged) if it doesn't fit there.
- The external `<link rel="discord:component-embed">` variant isn't supported yet.
- Discord's link previews are officially "subject to change". The Embed Debugger is the source of truth.

</details>

## License

GPL-2.0-or-later. Made by [drek124](https://github.com/drek124).
