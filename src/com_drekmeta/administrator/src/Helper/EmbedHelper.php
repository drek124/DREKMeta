<?php

/**
 * @package     DREK Meta
 * @author      drek124 <https://github.com/drek124>
 * @copyright   (C) 2026 drek124
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Drek\Component\Drekmeta\Administrator\Helper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Normalises, resolves, validates and encodes Discord Component Embed payloads.
 *
 * This class is shared by the admin (validation on save) and the system plugin (validation at render time).
 * media/com_drekmeta/js/embed-core.js mirrors it 1:1 so the builder's live counters match the plugin output
 * byte for byte. Keep the two in sync: same key order, same rules, same error keys.
 *
 * Errors are arrays: ['path' => string, 'key' => language key, 'args' => list]. The path is
 *  - ''           payload level (bytes, totals, JSON syntax)
 *  - 'root'       the Container itself
 *  - '3'          the 4th child of the Container
 *  - '3.accessory', '3.components.1', '3.items.0'   something inside that child
 *
 * @since  1.0.0
 */
final class EmbedHelper
{
    public const MAX_BYTES             = 3000;
    public const MAX_COMPONENTS        = 40;
    public const MAX_GALLERY_ITEMS     = 10;
    public const MAX_URL_LENGTH        = 2048;
    public const MAX_BUTTON_URL_LENGTH = 512;
    public const MAX_LABEL_LENGTH      = 80;
    public const MAX_DESCRIPTION       = 1024;
    public const MAX_SECTION_TEXTS     = 3;
    public const MAX_ROW_BUTTONS       = 5;

    public const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG;

    public const TYPE_ACTION_ROW    = 1;
    public const TYPE_BUTTON        = 2;
    public const TYPE_SECTION       = 9;
    public const TYPE_TEXT_DISPLAY  = 10;
    public const TYPE_THUMBNAIL     = 11;
    public const TYPE_MEDIA_GALLERY = 12;
    public const TYPE_SEPARATOR     = 14;
    public const TYPE_CONTAINER     = 17;

    /** Keys Discord accepts on a link button. Anything else invalidates the entire payload. */
    public const BUTTON_KEYS = ['id', 'type', 'url', 'style', 'label', 'emoji', 'disabled'];

    public const IMAGE_EXTENSIONS = ['png', 'gif', 'jpg', 'jpeg', 'webp', 'avif'];
    public const VIDEO_EXTENSIONS = ['mp4', 'webm', 'mov'];

    public const PLACEHOLDERS = ['og_title', 'og_description', 'og_image', 'page_url', 'site_name'];

    /**
     * Full pipeline: decode, normalise, absolutise, resolve placeholders, validate, encode, measure.
     *
     * @param   mixed   $raw       JSON string or already decoded array
     * @param   array   $vars      Placeholder values keyed by name (og_title, page_url, ...)
     * @param   string  $root      Site root URL with trailing slash (Uri::root())
     * @param   string  $hostRoot  scheme://host[:port] without trailing slash
     *
     * @return  array{stored: ?array, resolved: ?array, json: ?string, errors: array, stats: array}
     */
    public static function process($raw, array $vars, string $root, string $hostRoot): array
    {
        $result = [
            'stored'   => null,
            'resolved' => null,
            'json'     => null,
            'errors'   => [],
            'stats'    => ['bytes' => 0, 'components' => 0, 'gallery' => 0],
        ];

        $data = self::decode($raw, $result['errors']);

        if ($data === null) {
            return $result;
        }

        $indexMap = [];
        $stored   = self::normalize($data, $result['errors'], $indexMap);

        if ($stored === null) {
            return $result;
        }

        $stored             = self::absolutize($stored, $root, $hostRoot);
        $result['stored']   = $stored;
        $resolved           = self::resolve($stored, $vars);
        $result['resolved'] = $resolved;

        self::validate($resolved, $result['errors'], $indexMap);

        $json                          = self::encode($resolved);
        $result['json']                = $json;
        $result['stats']['bytes']      = \strlen($json);
        $result['stats']['components'] = self::countComponents($resolved['component']);
        $result['stats']['gallery']    = self::countGalleryItems($resolved['component']);

        if ($result['stats']['bytes'] > self::MAX_BYTES) {
            $result['errors'][] = self::error('', 'COM_DREKMETA_EMBED_ERR_TOO_MANY_BYTES', $result['stats']['bytes'], self::MAX_BYTES);
        }

        return $result;
    }

    /**
     * Encodes exactly the way the plugin prints it. Escapes such as < count toward the byte limit.
     */
    public static function encode(array $payload): string
    {
        return (string) json_encode($payload, self::JSON_FLAGS);
    }

    /**
     * Encodes the stored (unresolved) payload for the database: compact, same flags.
     */
    public static function encodeForStorage(array $payload): string
    {
        return (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return  array|null  Decoded data or null on failure (error added)
     */
    public static function decode($raw, array &$errors): ?array
    {
        if (\is_array($raw)) {
            return $raw;
        }

        $raw = trim((string) $raw);

        if ($raw === '') {
            $errors[] = self::error('', 'COM_DREKMETA_EMBED_ERR_EMPTY');

            return null;
        }

        try {
            $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $errors[] = self::error('', 'COM_DREKMETA_EMBED_ERR_JSON', $e->getMessage());

            return null;
        }

        if (!\is_array($data)) {
            $errors[] = self::error('', 'COM_DREKMETA_EMBED_ERR_NO_ROOT');

            return null;
        }

        return $data;
    }

    /**
     * Strips unknown keys, drops default values and puts keys in canonical order.
     * Structural problems (wrong types, nesting, missing required fields) are reported as errors.
     *
     * @return  array|null  ['component' => [...]] or null when there is no usable root Container
     */
    public static function normalize(array $data, array &$errors, ?array &$indexMap = null): ?array
    {
        if (!isset($data['component']) || !\is_array($data['component'])) {
            $errors[] = self::error('', 'COM_DREKMETA_EMBED_ERR_NO_ROOT');

            return null;
        }

        $c = $data['component'];

        if ((int) ($c['type'] ?? 0) !== self::TYPE_CONTAINER) {
            $errors[] = self::error('root', 'COM_DREKMETA_EMBED_ERR_ROOT_NOT_CONTAINER');

            return null;
        }

        $out = ['type' => self::TYPE_CONTAINER];

        if (\array_key_exists('accent_color', $c) && $c['accent_color'] !== null && $c['accent_color'] !== '') {
            $color = self::parseColor($c['accent_color']);

            if ($color === null) {
                $errors[] = self::error('root', 'COM_DREKMETA_EMBED_ERR_ACCENT_COLOR');
            } else {
                $out['accent_color'] = $color;
            }
        }

        if (!empty($c['spoiler'])) {
            $out['spoiler'] = true;
        }

        $children = self::listOf($c['components'] ?? null);
        $out['components'] = [];

        if (!$children) {
            $errors[] = self::error('root', 'COM_DREKMETA_EMBED_ERR_CONTAINER_EMPTY');
        }

        $indexMap = [];

        foreach ($children as $i => $child) {
            $normalized = self::normalizeChild($child, (string) $i, $errors);

            if ($normalized !== null) {
                $out['components'][] = $normalized;
                $indexMap[]          = $i;
            }
        }

        return ['component' => $out];
    }

    private static function normalizeChild($c, string $path, array &$errors): ?array
    {
        if (!\is_array($c) || !isset($c['type'])) {
            $errors[] = self::error($path, 'COM_DREKMETA_EMBED_ERR_INVALID_COMPONENT');

            return null;
        }

        $type = (int) $c['type'];

        switch ($type) {
            case self::TYPE_TEXT_DISPLAY:
                return self::normalizeText($c, $path, $errors);

            case self::TYPE_SECTION:
                return self::normalizeSection($c, $path, $errors);

            case self::TYPE_MEDIA_GALLERY:
                return self::normalizeGallery($c, $path, $errors);

            case self::TYPE_SEPARATOR:
                return self::normalizeSeparator($c, $path, $errors);

            case self::TYPE_ACTION_ROW:
                return self::normalizeActionRow($c, $path, $errors);

            case self::TYPE_CONTAINER:
                $errors[] = self::error($path, 'COM_DREKMETA_EMBED_ERR_NESTED_CONTAINER');

                return null;

            case self::TYPE_BUTTON:
            case self::TYPE_THUMBNAIL:
                $errors[] = self::error($path, 'COM_DREKMETA_EMBED_ERR_NOT_ALLOWED_HERE', $type);

                return null;

            default:
                $errors[] = self::error($path, 'COM_DREKMETA_EMBED_ERR_UNKNOWN_TYPE', $type);

                return null;
        }
    }

    private static function normalizeText($c, string $path, array &$errors): ?array
    {
        if (!\is_array($c) || (int) ($c['type'] ?? 0) !== self::TYPE_TEXT_DISPLAY) {
            $errors[] = self::error($path, 'COM_DREKMETA_EMBED_ERR_SECTION_CHILD');

            return null;
        }

        $content = self::str($c['content'] ?? '');

        if (trim($content) === '') {
            $errors[] = self::error($path, 'COM_DREKMETA_EMBED_ERR_TEXT_EMPTY');
        }

        return ['type' => self::TYPE_TEXT_DISPLAY, 'content' => $content];
    }

    private static function normalizeSection(array $c, string $path, array &$errors): ?array
    {
        $texts = self::listOf($c['components'] ?? null);
        $out   = ['type' => self::TYPE_SECTION, 'components' => []];

        if (\count($texts) < 1 || \count($texts) > self::MAX_SECTION_TEXTS) {
            $errors[] = self::error($path, 'COM_DREKMETA_EMBED_ERR_SECTION_TEXT_COUNT', self::MAX_SECTION_TEXTS);
        }

        foreach ($texts as $j => $text) {
            $normalized = self::normalizeText($text, $path . '.components.' . $j, $errors);

            if ($normalized !== null) {
                $out['components'][] = $normalized;
            }
        }

        $accessory = $c['accessory'] ?? null;
        $accType   = \is_array($accessory) ? (int) ($accessory['type'] ?? 0) : 0;

        if ($accType === self::TYPE_THUMBNAIL) {
            $out['accessory'] = self::normalizeThumbnail($accessory, $path . '.accessory', $errors);
        } elseif ($accType === self::TYPE_BUTTON) {
            $button = self::normalizeButton($accessory, $path . '.accessory', $errors);

            if ($button !== null) {
                $out['accessory'] = $button;
            }
        } else {
            $errors[] = self::error($path . '.accessory', 'COM_DREKMETA_EMBED_ERR_SECTION_ACCESSORY');
        }

        return $out;
    }

    private static function normalizeThumbnail(array $c, string $path, array &$errors): array
    {
        $out = ['type' => self::TYPE_THUMBNAIL, 'media' => ['url' => self::mediaUrl($c)]];

        if ($out['media']['url'] === '') {
            $errors[] = self::error($path, 'COM_DREKMETA_EMBED_ERR_URL_REQUIRED');
        }

        $description = self::str($c['description'] ?? '');

        if ($description !== '') {
            $out['description'] = $description;
        }

        if (!empty($c['spoiler'])) {
            $out['spoiler'] = true;
        }

        return $out;
    }

    private static function normalizeGallery(array $c, string $path, array &$errors): array
    {
        $items = self::listOf($c['items'] ?? null);
        $out   = ['type' => self::TYPE_MEDIA_GALLERY, 'items' => []];

        if (!$items) {
            $errors[] = self::error($path, 'COM_DREKMETA_EMBED_ERR_GALLERY_EMPTY');
        }

        foreach ($items as $j => $item) {
            $itemPath = $path . '.items.' . $j;

            if (!\is_array($item)) {
                $errors[] = self::error($itemPath, 'COM_DREKMETA_EMBED_ERR_INVALID_COMPONENT');
                continue;
            }

            $normalized = ['media' => ['url' => self::mediaUrl($item)]];

            if ($normalized['media']['url'] === '') {
                $errors[] = self::error($itemPath, 'COM_DREKMETA_EMBED_ERR_URL_REQUIRED');
            }

            $description = self::str($item['description'] ?? '');

            if ($description !== '') {
                $normalized['description'] = $description;
            }

            if (!empty($item['spoiler'])) {
                $normalized['spoiler'] = true;
            }

            $out['items'][] = $normalized;
        }

        return $out;
    }

    private static function normalizeSeparator(array $c, string $path, array &$errors): array
    {
        $out = ['type' => self::TYPE_SEPARATOR];

        if (\array_key_exists('divider', $c) && $c['divider'] === false) {
            $out['divider'] = false;
        }

        if (\array_key_exists('spacing', $c) && $c['spacing'] !== null) {
            $spacing = (int) $c['spacing'];

            if ($spacing !== 1 && $spacing !== 2) {
                $errors[] = self::error($path, 'COM_DREKMETA_EMBED_ERR_SEPARATOR_SPACING');
            } elseif ($spacing === 2) {
                $out['spacing'] = 2;
            }
        }

        return $out;
    }

    private static function normalizeActionRow(array $c, string $path, array &$errors): array
    {
        $buttons = self::listOf($c['components'] ?? null);
        $out     = ['type' => self::TYPE_ACTION_ROW, 'components' => []];

        if (\count($buttons) < 1 || \count($buttons) > self::MAX_ROW_BUTTONS) {
            $errors[] = self::error($path, 'COM_DREKMETA_EMBED_ERR_ROW_BUTTON_COUNT', self::MAX_ROW_BUTTONS);
        }

        foreach ($buttons as $j => $button) {
            $buttonPath = $path . '.components.' . $j;

            if (!\is_array($button) || (int) ($button['type'] ?? 0) !== self::TYPE_BUTTON) {
                $errors[] = self::error($buttonPath, 'COM_DREKMETA_EMBED_ERR_ROW_CHILD');
                continue;
            }

            $normalized = self::normalizeButton($button, $buttonPath, $errors);

            if ($normalized !== null) {
                $out['components'][] = $normalized;
            }
        }

        return $out;
    }

    private static function normalizeButton(array $c, string $path, array &$errors): ?array
    {
        // Unlike other components, extra button keys are a hard error: Discord drops the whole embed for them,
        // so we refuse instead of silently rewriting what the admin wrote.
        foreach (array_keys($c) as $key) {
            if (!\in_array((string) $key, self::BUTTON_KEYS, true)) {
                $errors[] = self::error($path, 'COM_DREKMETA_EMBED_ERR_BUTTON_KEY', (string) $key);
            }
        }

        if (\array_key_exists('style', $c) && (int) $c['style'] !== 5) {
            $errors[] = self::error($path, 'COM_DREKMETA_EMBED_ERR_BUTTON_STYLE');
        }

        $out   = ['type' => self::TYPE_BUTTON, 'style' => 5];
        $label = self::str($c['label'] ?? '');
        $emoji = self::normalizeEmoji($c['emoji'] ?? null);

        if ($label !== '') {
            $out['label'] = $label;
        }

        if ($emoji !== null) {
            $out['emoji'] = $emoji;
        }

        if ($label === '' && $emoji === null) {
            $errors[] = self::error($path, 'COM_DREKMETA_EMBED_ERR_BUTTON_LABEL');
        }

        $out['url'] = self::str($c['url'] ?? '');

        if (trim($out['url']) === '') {
            $errors[] = self::error($path, 'COM_DREKMETA_EMBED_ERR_URL_REQUIRED');
        }

        if (!empty($c['disabled'])) {
            $out['disabled'] = true;
        }

        return $out;
    }

    /**
     * Accepts {name, id, animated}, a unicode emoji string or a custom emoji string like <a:name:123>.
     */
    public static function normalizeEmoji($emoji): ?array
    {
        if (\is_string($emoji)) {
            $emoji = trim($emoji);

            if ($emoji === '') {
                return null;
            }

            if (preg_match('/^<(a?):([A-Za-z0-9_]{2,32}):(\d{5,25})>$/', $emoji, $m)) {
                $emoji = ['name' => $m[2], 'id' => $m[3], 'animated' => $m[1] === 'a'];
            } else {
                $emoji = ['name' => $emoji];
            }
        }

        if (!\is_array($emoji)) {
            return null;
        }

        $name = self::str($emoji['name'] ?? '');
        $id   = isset($emoji['id']) && preg_match('/^\d{1,25}$/', (string) $emoji['id']) ? (string) $emoji['id'] : '';

        if ($name === '' && $id === '') {
            return null;
        }

        $out = [];

        if ($name !== '') {
            $out['name'] = $name;
        }

        if ($id !== '') {
            $out['id'] = $id;

            if (!empty($emoji['animated'])) {
                $out['animated'] = true;
            }
        }

        return $out;
    }

    /**
     * Converts relative and media-field paths to absolute URLs. Placeholders are left untouched.
     */
    public static function absolutize(array $payload, string $root, string $hostRoot): array
    {
        $walk = static function (array &$node) use (&$walk, $root, $hostRoot): void {
            if (isset($node['media']['url'])) {
                $node['media']['url'] = self::absoluteUrl($node['media']['url'], $root, $hostRoot);
            }

            if (($node['type'] ?? null) === self::TYPE_BUTTON && isset($node['url'])) {
                $node['url'] = self::absoluteUrl($node['url'], $root, $hostRoot);
            }

            foreach (['components', 'items'] as $listKey) {
                if (isset($node[$listKey]) && \is_array($node[$listKey])) {
                    foreach ($node[$listKey] as &$child) {
                        $walk($child);
                    }

                    unset($child);
                }
            }

            if (isset($node['accessory']) && \is_array($node['accessory'])) {
                $walk($node['accessory']);
            }
        };

        $walk($payload['component']);

        return $payload;
    }

    public static function absoluteUrl(string $url, string $root, string $hostRoot): string
    {
        $url = trim(self::cleanMediaPath($url));

        if ($url === '' || str_starts_with($url, '{') || preg_match('#^[a-z][a-z0-9+.\-]*:#i', $url)) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        if (str_starts_with($url, '/')) {
            return rtrim($hostRoot, '/') . $url;
        }

        return rtrim($root, '/') . '/' . ltrim($url, './');
    }

    /**
     * Strips the "#joomlaImage://..." suffix the core media field appends.
     */
    public static function cleanMediaPath(string $value): string
    {
        $pos = strpos($value, '#joomlaImage://');

        return $pos === false ? $value : substr($value, 0, $pos);
    }

    /**
     * Replaces {placeholders} in every text and URL field.
     */
    public static function resolve(array $payload, array $vars): array
    {
        $map = [];

        foreach (self::PLACEHOLDERS as $name) {
            $map['{' . $name . '}'] = (string) ($vars[$name] ?? '');
        }

        $walk = static function (array &$node) use (&$walk, $map): void {
            foreach (['content', 'label', 'url', 'description'] as $key) {
                if (isset($node[$key]) && \is_string($node[$key])) {
                    $node[$key] = strtr($node[$key], $map);
                }
            }

            if (isset($node['media']['url'])) {
                $node['media']['url'] = strtr($node['media']['url'], $map);
            }

            foreach (['components', 'items'] as $listKey) {
                if (isset($node[$listKey]) && \is_array($node[$listKey])) {
                    foreach ($node[$listKey] as &$child) {
                        $walk($child);
                    }

                    unset($child);
                }
            }

            if (isset($node['accessory']) && \is_array($node['accessory'])) {
                $walk($node['accessory']);
            }
        };

        $walk($payload['component']);

        return $payload;
    }

    /**
     * Checks a resolved payload against every Discord rule that normalize() doesn't already cover.
     */
    public static function validate(array $payload, array &$errors, array $indexMap = []): void
    {
        $root = $payload['component'];

        foreach ($root['components'] as $i => $child) {
            // Report against the original position, normalize() may have dropped invalid siblings.
            $path = (string) ($indexMap[$i] ?? $i);

            switch ($child['type']) {
                case self::TYPE_TEXT_DISPLAY:
                    self::validateText($child, $path, $errors);
                    break;

                case self::TYPE_SECTION:
                    foreach ($child['components'] as $j => $text) {
                        self::validateText($text, $path . '.components.' . $j, $errors);
                    }

                    if (isset($child['accessory'])) {
                        if ($child['accessory']['type'] === self::TYPE_THUMBNAIL) {
                            self::validateMedia($child['accessory'], $path . '.accessory', false, $errors);
                        } else {
                            self::validateButton($child['accessory'], $path . '.accessory', $errors);
                        }
                    }
                    break;

                case self::TYPE_MEDIA_GALLERY:
                    foreach ($child['items'] as $j => $item) {
                        self::validateMedia($item, $path . '.items.' . $j, true, $errors);
                    }
                    break;

                case self::TYPE_ACTION_ROW:
                    foreach ($child['components'] as $j => $button) {
                        self::validateButton($button, $path . '.components.' . $j, $errors);
                    }
                    break;
            }
        }

        $components = self::countComponents($root);

        if ($components > self::MAX_COMPONENTS) {
            $errors[] = self::error('', 'COM_DREKMETA_EMBED_ERR_TOO_MANY_COMPONENTS', $components, self::MAX_COMPONENTS);
        }

        $gallery = self::countGalleryItems($root);

        if ($gallery > self::MAX_GALLERY_ITEMS) {
            $errors[] = self::error('', 'COM_DREKMETA_EMBED_ERR_TOO_MANY_GALLERY_ITEMS', $gallery, self::MAX_GALLERY_ITEMS);
        }
    }

    private static function validateText(array $text, string $path, array &$errors): void
    {
        // Only re-check after resolution; normalize() already flagged literally empty text.
        if (trim($text['content']) === '' && !self::hasErrorAt($errors, $path)) {
            $errors[] = self::error($path, 'COM_DREKMETA_EMBED_ERR_TEXT_EMPTY_RESOLVED');
        }
    }

    private static function validateButton(array $button, string $path, array &$errors): void
    {
        if (isset($button['label']) && self::length($button['label']) > self::MAX_LABEL_LENGTH) {
            $errors[] = self::error($path, 'COM_DREKMETA_EMBED_ERR_LABEL_LENGTH', self::MAX_LABEL_LENGTH);
        }

        self::validateUrl($button['url'] ?? '', $path, self::MAX_BUTTON_URL_LENGTH, null, $errors);
    }

    private static function validateMedia(array $media, string $path, bool $allowVideo, array &$errors): void
    {
        if (isset($media['description']) && self::length($media['description']) > self::MAX_DESCRIPTION) {
            $errors[] = self::error($path, 'COM_DREKMETA_EMBED_ERR_DESCRIPTION_LENGTH', self::MAX_DESCRIPTION);
        }

        $extensions = $allowVideo ? array_merge(self::IMAGE_EXTENSIONS, self::VIDEO_EXTENSIONS) : self::IMAGE_EXTENSIONS;

        self::validateUrl($media['media']['url'] ?? '', $path, self::MAX_URL_LENGTH, $extensions, $errors);
    }

    private static function validateUrl(string $url, string $path, int $maxLength, ?array $extensions, array &$errors): void
    {
        if (trim($url) === '') {
            if (!self::hasErrorAt($errors, $path)) {
                $errors[] = self::error($path, 'COM_DREKMETA_EMBED_ERR_URL_EMPTY_RESOLVED');
            }

            return;
        }

        if (preg_match('/\{[a-z_]+\}/', $url, $m)) {
            $errors[] = self::error($path, 'COM_DREKMETA_EMBED_ERR_URL_PLACEHOLDER', $m[0]);

            return;
        }

        if (!preg_match('#^https?://[^\s/?\#]+#i', $url)) {
            $errors[] = self::error($path, 'COM_DREKMETA_EMBED_ERR_URL_SCHEME');

            return;
        }

        if (\strlen($url) > $maxLength) {
            $errors[] = self::error($path, 'COM_DREKMETA_EMBED_ERR_URL_LENGTH', $maxLength);
        }

        if ($extensions !== null) {
            $urlPath   = (string) parse_url($url, PHP_URL_PATH);
            $extension = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION));

            if (!\in_array($extension, $extensions, true)) {
                $errors[] = self::error($path, 'COM_DREKMETA_EMBED_ERR_MEDIA_EXTENSION', implode(', ', $extensions));
            }
        }
    }

    public static function countComponents(array $node): int
    {
        $count = 1;

        foreach ($node['components'] ?? [] as $child) {
            if (\is_array($child)) {
                $count += self::countComponents($child);
            }
        }

        if (isset($node['accessory']) && \is_array($node['accessory'])) {
            $count += self::countComponents($node['accessory']);
        }

        return $count;
    }

    public static function countGalleryItems(array $node): int
    {
        $count = 0;

        if (($node['type'] ?? null) === self::TYPE_MEDIA_GALLERY) {
            $count += \count($node['items'] ?? []);
        }

        foreach ($node['components'] ?? [] as $child) {
            if (\is_array($child)) {
                $count += self::countGalleryItems($child);
            }
        }

        return $count;
    }

    /**
     * Collects every resolved media URL (thumbnails + gallery items), used for size warnings.
     *
     * @return  string[]
     */
    public static function mediaUrls(array $payload): array
    {
        $urls = [];
        $walk = static function (array $node) use (&$walk, &$urls): void {
            if (isset($node['media']['url'])) {
                $urls[] = $node['media']['url'];
            }

            foreach (['components', 'items'] as $listKey) {
                foreach ($node[$listKey] ?? [] as $child) {
                    if (\is_array($child)) {
                        $walk($child);
                    }
                }
            }

            if (isset($node['accessory']) && \is_array($node['accessory'])) {
                $walk($node['accessory']);
            }
        };

        $walk($payload['component']);

        return array_values(array_unique($urls));
    }

    /**
     * Parses an int, "#RRGGBB" or "RRGGBB" into 0..0xFFFFFF.
     */
    public static function parseColor($value): ?int
    {
        if (\is_int($value) || (\is_string($value) && ctype_digit($value))) {
            $int = (int) $value;

            return ($int >= 0 && $int <= 0xFFFFFF) ? $int : null;
        }

        if (\is_string($value) && preg_match('/^#?([0-9A-Fa-f]{6})$/', trim($value), $m)) {
            return hexdec($m[1]);
        }

        return null;
    }

    private static function mediaUrl(array $c): string
    {
        if (isset($c['media']) && \is_array($c['media'])) {
            return self::str($c['media']['url'] ?? '');
        }

        return '';
    }

    private static function listOf($value): array
    {
        return \is_array($value) ? array_values($value) : [];
    }

    private static function str($value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }

    private static function length(string $value): int
    {
        return \function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : \strlen($value);
    }

    private static function hasErrorAt(array $errors, string $path): bool
    {
        foreach ($errors as $error) {
            if ($error['path'] === $path) {
                return true;
            }
        }

        return false;
    }

    private static function error(string $path, string $key, ...$args): array
    {
        return ['path' => $path, 'key' => $key, 'args' => $args];
    }
}
