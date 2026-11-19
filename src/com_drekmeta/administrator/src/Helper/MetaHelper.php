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
