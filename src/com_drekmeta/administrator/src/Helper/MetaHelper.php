<?php

/**
 * @package     DREK Meta
 * @author      drek124 <https://github.com/drek124>
 * @copyright   (C) 2026 drek124
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Drek\Component\Drekmeta\Administrator\Helper;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Uri\Uri;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Shared meta helpers: merging item over global values, image URL handling, custom tag normalisation.
 *
 * @since  1.0.0
 */
final class MetaHelper
{
    /** Fields that inherit from the global row when empty on an item. */
    public const META_FIELDS = [
        'og_title',
        'og_description',
        'og_image',
        'og_image_alt',
        'og_type',
        'og_site_name',
        'twitter_card',
        'theme_color',
    ];

    public const OG_TYPES       = ['website', 'article', 'profile', 'book', 'video.movie', 'video.other', 'music.song'];
    public const TWITTER_CARDS  = ['summary', 'summary_large_image'];
    public const TAG_ATTRIBUTES = ['name', 'property', 'http-equiv'];

    /** Meta keys go into the HTML unescaped by Joomla's renderer, so they are locked down hard. */
    public const TAG_KEY_PATTERN = '/^[A-Za-z0-9_:.\-]{1,128}$/';

    public const THEME_COLOR_PATTERN = '/^#[0-9A-Fa-f]{6}$/';

    /** <link rel="">: one or more space-separated link types. */
    public const LINK_REL_PATTERN = '/^[A-Za-z0-9_:.\-]+(?: [A-Za-z0-9_:.\-]+)*$/';

    /** Extra <link> attribute names. Event handlers (on*) and the dedicated rel/href columns are refused separately. */
    public const LINK_ATTR_PATTERN = '/^[A-Za-z][A-Za-z0-9_:.\-]{0,63}$/';

    public const MAX_LINK_ATTRIBUTES = 20;

    public const MAX_HREF_LENGTH = 2048;

    /** Rels that only make sense once per page: an item's value replaces the global one and anything Joomla added. */
    public const SINGLE_LINK_RELS = ['canonical', 'manifest', 'shortlink'];

    /**
     * Item values override global ones. og:image:alt follows whichever row supplied the image.
     *
     * @param   object|null  $global
     * @param   object|null  $item
     *
     * @return  array<string, string>
     */
    public static function merge(?object $global, ?object $item): array
    {
        $merged = [];

        foreach (self::META_FIELDS as $field) {
            $itemValue   = $item ? trim((string) ($item->$field ?? '')) : '';
            $globalValue = $global ? trim((string) ($global->$field ?? '')) : '';

            $merged[$field] = $itemValue !== '' ? $itemValue : $globalValue;
        }

        $itemHasImage = $item && trim((string) ($item->og_image ?? '')) !== '';

        $merged['og_image_alt'] = $itemHasImage
            ? trim((string) ($item->og_image_alt ?? ''))
            : ($global ? trim((string) ($global->og_image_alt ?? '')) : '');

        return $merged;
    }

    /**
     * Resolves a media field value or URL into an absolute URL plus known dimensions.
     *
     * @return  array{url: string, local: ?string, width: int, height: int}
     */
    public static function image(string $value, ?string $root = null): array
    {
        $result = ['url' => '', 'local' => null, 'width' => 0, 'height' => 0];
        $value  = trim($value);

        if ($value === '') {
            return $result;
        }

        $root = $root ?? Uri::root();

        // Core helper reads width/height out of the "#joomlaImage://...?width=&height=" suffix.
        $clean = HTMLHelper::_('cleanImageURL', $value);
        $path  = trim(EmbedHelper::cleanMediaPath((string) $clean->url));

        $result['width']  = (int) ($clean->attributes['width'] ?? 0);
        $result['height'] = (int) ($clean->attributes['height'] ?? 0);

        if (preg_match('#^https?://#i', $path)) {
            $result['url'] = $path;

            // An absolute URL pointing at this site is still a local file we can measure.
            if (stripos($path, $root) === 0) {
                $result['local'] = rawurldecode((string) parse_url(substr($path, \strlen($root)), PHP_URL_PATH));
            }

            return $result;
        }

        if (str_starts_with($path, '//')) {
            $result['url'] = 'https:' . $path;

            return $result;
        }

        $relative        = ltrim($path, '/');
        $result['url']   = rtrim($root, '/') . '/' . $relative;
        $result['local'] = rawurldecode((string) parse_url($relative, PHP_URL_PATH));

        return $result;
    }

    /**
     * Absolute filesystem path for a local relative path, or null if it escapes the site root or doesn't exist.
     */
    public static function localFile(?string $relative): ?string
    {
        if ($relative === null || $relative === '' || str_contains($relative, '..')) {
            return null;
        }

        $file = JPATH_ROOT . '/' . ltrim($relative, '/');

        return is_file($file) ? $file : null;
    }

    /**
     * Normalises custom tags from the subform or JSON into a clean list. Invalid rows are dropped.
     *
     * @param   mixed  $tags  JSON string or array of rows
     *
     * @return  array<int, array{attribute: string, key: string, value: string}>
     */
    public static function customTags($tags): array
    {
        if (\is_string($tags)) {
            $tags = trim($tags) === '' ? [] : json_decode($tags, true);
        }

        if (!\is_array($tags)) {
            return [];
        }

        $out = [];

        foreach ($tags as $row) {
            if (!\is_array($row)) {
                continue;
            }

            $attribute = (string) ($row['attribute'] ?? 'name');
            $key       = trim((string) ($row['key'] ?? ''));
            $value     = trim((string) ($row['value'] ?? ''));

            if ($key === '' || !\in_array($attribute, self::TAG_ATTRIBUTES, true) || !preg_match(self::TAG_KEY_PATTERN, $key)) {
                continue;
            }

            $out[] = ['attribute' => $attribute, 'key' => $key, 'value' => $value];
        }

        return $out;
    }

    /**
     * Normalises custom <link> rows from the subform or JSON. Invalid rows are dropped.
     *
     * An empty href is kept only for single-use rels (canonical, ...): it means "remove it on this page".
     *
     * @param   mixed  $links  JSON string or array of rows
     *
     * @return  array<int, array{rel: string, href: string, attributes: string}>
     */
    public static function customLinks($links): array
    {
        if (\is_string($links)) {
            $links = trim($links) === '' ? [] : json_decode($links, true);
        }

        if (!\is_array($links)) {
            return [];
        }

        $out = [];

        foreach ($links as $row) {
            if (!\is_array($row)) {
                continue;
            }

            $rel  = strtolower(preg_replace('/\s+/', ' ', trim((string) ($row['rel'] ?? ''))));
            $href = str_replace(' ', '%20', trim((string) ($row['href'] ?? '')));

            if ($rel === '' || \strlen($rel) > 128 || !preg_match(self::LINK_REL_PATTERN, $rel)) {
                continue;
            }

            if ($href === '' ? !\in_array($rel, self::SINGLE_LINK_RELS, true) : !self::isSafeHref($href)) {
                continue;
            }

            $attributes = self::linkAttributes((string) ($row['attributes'] ?? ''));

            $out[] = ['rel' => $rel, 'href' => $href, 'attributes' => self::attributesToString($attributes)];
        }

        return $out;
    }

    /**
     * http(s), protocol-relative or site-relative only; no control characters, no javascript:/data: and friends.
     */
    public static function isSafeHref(string $href): bool
    {
        if ($href === '' || \strlen($href) > self::MAX_HREF_LENGTH || preg_match('/[\x00-\x20\x7F<>"\'`]/', $href)) {
            return false;
        }

        if (preg_match('/^([A-Za-z][A-Za-z0-9+.\-]*):/', $href, $m)) {
            return \in_array(strtolower($m[1]), ['http', 'https'], true);
        }

        return true;
    }

    /**
     * Absolute URL for an href: relative ones are resolved against the site root.
     */
    public static function absoluteHref(string $href, ?string $root = null): string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        if (str_starts_with($href, '//')) {
            return 'https:' . $href;
        }

        $root = $root ?? Uri::root();

        // "/path" is relative to the host, "path" to the Joomla root (which may live in a subfolder).
        return str_starts_with($href, '/')
            ? self::hostRoot($root) . $href
            : rtrim($root, '/') . '/' . $href;
    }

    /**
     * Parses `hreflang="en" type=image/png crossorigin` into [name => value]. Unsafe or unknown syntax is dropped.
     *
     * @return  array<string, string>
     */
    public static function linkAttributes(string $text): array
    {
        $out = [];

        preg_match_all(
            '/([^\s="\'<>\/`]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?/',
            $text,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $m) {
            $name = strtolower($m[1]);

            if (
                !preg_match(self::LINK_ATTR_PATTERN, $name)
                || str_starts_with($name, 'on')
                || \in_array($name, ['rel', 'href'], true)
            ) {
                continue;
            }

            $value = ($m[2] ?? '') . ($m[3] ?? '') . ($m[4] ?? '');

            $out[$name] = preg_replace('/[\x00-\x1F\x7F]/', '', $value);

            if (\count($out) >= self::MAX_LINK_ATTRIBUTES) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param   array<string, string>  $attributes
     */
    public static function attributesToString(array $attributes): string
    {
        $parts = [];

        // Stored as typed (not entity-encoded) so it round-trips through linkAttributes(); escaping happens on output.
        foreach ($attributes as $name => $value) {
            if ($value === '') {
                $parts[] = $name;
            } elseif (!str_contains($value, '"')) {
                $parts[] = $name . '="' . $value . '"';
            } elseif (!str_contains($value, "'")) {
                $parts[] = $name . "='" . $value . "'";
            } else {
                $parts[] = $name . '="' . str_replace('"', '', $value) . '"';
            }
        }

        return implode(' ', $parts);
    }

    /**
     * Ready-to-print <link> element for a normalised row.
     */
    public static function linkHtml(array $link, ?string $root = null): string
    {
        $attributes = ['rel' => $link['rel'], 'href' => self::absoluteHref($link['href'], $root)]
            + self::linkAttributes($link['attributes']);

        $html = '<link';

        foreach ($attributes as $name => $value) {
            $html .= $value === '' && $name !== 'href'
                ? ' ' . $name
                : ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }

        return $html . '>';
    }

    /**
     * Absolute root of the site without path: scheme://host[:port]
     */
    public static function hostRoot(?string $root = null): string
    {
        $uri = new Uri($root ?? Uri::root());

        return $uri->toString(['scheme', 'host', 'port']);
    }

    /**
     * Converts "#RRGGBB" into Discord's integer accent colour.
     */
    public static function colorToInt(string $hex): ?int
    {
        return preg_match(self::THEME_COLOR_PATTERN, $hex) ? hexdec(substr($hex, 1)) : null;
    }
}
