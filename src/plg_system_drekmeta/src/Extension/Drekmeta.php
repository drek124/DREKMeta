<?php

/**
 * @package     DREK Meta
 * @author      drek124 <https://github.com/drek124>
 * @copyright   (C) 2026 drek124
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Drek\Plugin\System\Drekmeta\Extension;

use Drek\Component\Drekmeta\Administrator\Helper\EmbedHelper;
use Drek\Component\Drekmeta\Administrator\Helper\MetaHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Writes DREK Meta's tags and the Discord component embed into the <head> of site HTML pages.
 *
 * Never throws on the frontend: everything is wrapped, failures are logged to the "drekmeta" category
 * (administrator/logs/drekmeta.php) and the page renders as if the plugin weren't there.
 *
 * @since  1.0.0
 */
final class Drekmeta extends CMSPlugin implements SubscriberInterface, DatabaseAwareInterface
{
    use DatabaseAwareTrait;

    public const LOG_CATEGORY = 'drekmeta';

    /** Rows per request: [menu_item_id => row|null]. */
    private static array $rowCache = [];

    private static bool $loggerAdded = false;

    public static function getSubscribedEvents(): array
    {
        return ['onBeforeCompileHead' => 'onBeforeCompileHead'];
    }

    public function onBeforeCompileHead($event = null): void
    {
        try {
            $this->apply();
        } catch (\Throwable $e) {
            $this->log('Unexpected error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), Log::ERROR);
        }
    }

    private function apply(): void
    {
        $app = $this->getApplication();

        if (!$app || !$app->isClient('site')) {
            return;
        }

        $doc = $app->getDocument();

        if (!$doc instanceof HtmlDocument || $doc->getType() !== 'html') {
            return;
        }

        // The package installs both; if the component is gone or disabled, do nothing.
        if (!class_exists(MetaHelper::class) || !ComponentHelper::isEnabled('com_drekmeta')) {
            return;
        }

        $params = ComponentHelper::getParams('com_drekmeta');
        $menuId = $this->activeMenuItemId($params);
        $rows   = $this->loadRows($menuId);
        $global = $rows[0] ?? null;
        $item   = $menuId ? ($rows[$menuId] ?? null) : null;

        if (!$global && !$item) {
            return;
        }

        $meta = MetaHelper::merge($global, $item);

        if ($meta['og_title'] === '' && $params->get('auto_title_fallback', 1)) {
            $meta['og_title'] = trim((string) $doc->getTitle());
        }

        if ($meta['og_description'] === '' && $params->get('auto_description_fallback', 1)) {
            $meta['og_description'] = trim((string) $doc->getDescription());
        }

        if ($meta['og_site_name'] === '') {
            $meta['og_site_name'] = (string) $app->get('sitename');
        }

        $pageUrl = Uri::getInstance()->toString(['scheme', 'user', 'pass', 'host', 'port', 'path', 'query']);
        $image   = MetaHelper::image($meta['og_image']);

        if ($params->get('remove_existing_og', 1)) {
            $this->removeExisting($doc);
        }

        $this->writePresets($doc, $meta, $image, $pageUrl, (bool) $params->get('output_og_url', 1));
        $this->writeCustomTags($doc, $global, $item);

        if ($params->get('component_embeds_enabled', 1)) {
            $this->writeComponentEmbed($doc, $global, $item, [
                'og_title'       => $meta['og_title'],
                'og_description' => $meta['og_description'],
                'og_image'       => $image['url'],
                'page_url'       => $pageUrl,
                'site_name'      => $meta['og_site_name'],
            ]);
        }
    }

    /**
     * Active menu item ID, or 0 when there is none (or, in strict mode, when the page isn't that menu item).
     */
    private function activeMenuItemId(Registry $params): int
    {
        $app    = $this->getApplication();
        $active = $app->getMenu()->getActive();

        if (!$active) {
            return 0;
        }

        if ($params->get('strict_menu_match', 1)) {
            $input = $app->getInput();

            foreach (['option', 'view', 'layout', 'id'] as $key) {
                if (!isset($active->query[$key]) || $active->query[$key] === '') {
                    continue;
                }

                $expected = (string) $active->query[$key];
                $actual   = (string) $input->get($key, '', 'string');

                if ($key === 'id') {
                    $expected = (string) (int) $expected;
                    $actual   = (string) (int) $actual;
                }

                if ($expected !== $actual) {
                    return 0;
                }
            }
        }

        return (int) $active->id;
    }

    /**
     * One query for the global row and this menu item's row (published only). Cached per request.
     *
     * @return  array<int, object>  Keyed by menu_item_id
     */
    private function loadRows(int $menuId): array
    {
        if (\array_key_exists($menuId, self::$rowCache)) {
            return self::$rowCache[$menuId];
        }

        $db    = $this->getDatabase();
        $query = method_exists($db, 'createQuery') ? $db->createQuery() : $db->getQuery(true);
        $ids   = array_values(array_unique([0, $menuId]));

        $query->select('*')
            ->from($db->quoteName('#__drekmeta_items'))
            ->whereIn($db->quoteName('menu_item_id'), $ids, ParameterType::INTEGER)
            ->where($db->quoteName('state') . ' = 1');

        $rows = [];

        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $rows[(int) $row->menu_item_id] = $row;
        }

        return self::$rowCache[$menuId] = $rows;
    }

    private function removeExisting(HtmlDocument $doc): void
    {
        foreach (['name', 'property'] as $attribute) {
            foreach (array_keys($doc->_metaTags[$attribute] ?? []) as $key) {
                if (preg_match('/^(og|twitter):/i', (string) $key)) {
                    unset($doc->_metaTags[$attribute][$key]);
                }
            }
        }

        // Tags other extensions injected as raw custom HTML.
        $doc->_custom = array_values(array_filter(
            $doc->_custom,
            static fn ($html) => !preg_match('/<meta\s[^>]*(?:property|name)\s*=\s*["\'](?:og|twitter):/i', (string) $html)
        ));
    }

    private function writePresets(HtmlDocument $doc, array $meta, array $image, string $pageUrl, bool $ogUrl): void
    {
        $og = static function (string $key, string $value) use ($doc): void {
            if ($value !== '') {
                $doc->setMetaData($key, $value, 'property');
            }
        };

        $og('og:title', $meta['og_title']);
        $og('og:description', $meta['og_description']);
        $og('og:type', $meta['og_type'] !== '' ? $meta['og_type'] : 'website');
        $og('og:site_name', $meta['og_site_name']);

        if ($ogUrl) {
            $og('og:url', $pageUrl);
        }

        if ($image['url'] !== '') {
            $og('og:image', $image['url']);

            [$width, $height] = $this->imageSize($image);

            if ($width && $height) {
                $og('og:image:width', (string) $width);
                $og('og:image:height', (string) $height);
            }

            $og('og:image:alt', $meta['og_image_alt']);
        }

        // twitter:* and theme-color use name="".
        $name = static function (string $key, string $value) use ($doc): void {
            if ($value !== '' && empty($doc->_metaTags['name'][$key])) {
                $doc->setMetaData($key, $value, 'name');
            }
        };

        $name('twitter:card', $meta['twitter_card'] !== '' ? $meta['twitter_card'] : ($image['url'] !== '' ? 'summary_large_image' : 'summary'));
        $name('twitter:title', $meta['og_title']);
        $name('twitter:description', $meta['og_description']);

        if ($image['url'] !== '') {
            $name('twitter:image', $image['url']);
            $name('twitter:image:alt', $meta['og_image_alt']);
        }

        if ($meta['theme_color'] !== '' && preg_match(MetaHelper::THEME_COLOR_PATTERN, $meta['theme_color'])) {
            $doc->setMetaData('theme-color', $meta['theme_color'], 'name');
        }
    }

    /**
     * @return  array{0: int, 1: int}
     */
    private function imageSize(array $image): array
    {
        if ($image['width'] && $image['height']) {
            return [$image['width'], $image['height']];
        }

        $file = MetaHelper::localFile($image['local']);

        if ($file === null) {
            return [0, 0];
        }

        try {
            $size = @getimagesize($file);

            return $size ? [(int) $size[0], (int) $size[1]] : [0, 0];
        } catch (\Throwable $e) {
            $this->log('Could not read image size of ' . $image['local'] . ': ' . $e->getMessage(), Log::WARNING);

            return [0, 0];
        }
    }

    /**
     * Global custom tags first, then the item's; a later tag with the same key wins, also over presets.
     */
    private function writeCustomTags(HtmlDocument $doc, ?object $global, ?object $item): void
    {
        $tags = [];

        foreach ([$global, $item] as $row) {
            if (!$row) {
                continue;
            }

            foreach (MetaHelper::customTags($row->custom_tags ?? '') as $tag) {
                $tags[strtolower($tag['key'])] = $tag;
            }
        }

        foreach ($tags as $tag) {
            // A custom tag replaces a preset with the same key, whatever attribute the preset used.
            foreach (MetaHelper::TAG_ATTRIBUTES as $attribute) {
                foreach (array_keys($doc->_metaTags[$attribute] ?? []) as $existing) {
                    if (strcasecmp((string) $existing, $tag['key']) === 0) {
                        unset($doc->_metaTags[$attribute][$existing]);
                    }
                }
            }

            if ($tag['value'] === '') {
                continue;
            }

            $doc->setMetaData($tag['key'], $tag['value'], $tag['attribute']);
        }
    }

    private function writeComponentEmbed(HtmlDocument $doc, ?object $global, ?object $item, array $vars): void
    {
        $source = null;

        if ($item && (int) $item->component_embed_enabled === 1) {
            $source = $item;
        } elseif ($global && (int) $global->component_embed_enabled === 1) {
            $source = $global;
        }

        if (!$source) {
            return;
        }

        $root   = Uri::root();
        $result = EmbedHelper::process((string) $source->component_embed, $vars, $root, MetaHelper::hostRoot($root));

        if ($result['errors'] || $result['json'] === null) {
            $this->logEmbedErrors($source, $result['errors'], $result['stats']['bytes'], $vars['page_url']);

            return;
        }

        // Measured after encoding: JSON_HEX_TAG's < escapes count toward the limit.
        if (\strlen($result['json']) > EmbedHelper::MAX_BYTES) {
            $this->log(\sprintf('Component embed of record %d skipped on %s: %d bytes (max %d).', $source->id, $vars['page_url'], \strlen($result['json']), EmbedHelper::MAX_BYTES), Log::WARNING);

            return;
        }

        // Another extension's embed would make Discord's choice ambiguous.
        $doc->_custom = array_values(array_filter(
            $doc->_custom,
            static fn ($html) => stripos((string) $html, 'discord:component-embed') === false
        ));

        // Printed for every visitor (not only Discordbot) so the page cache can never serve the wrong variant.
        $doc->addCustomTag('<script id="discord:component-embed" type="application/json">' . $result['json'] . '</script>');
    }

    private function logEmbedErrors(object $source, array $errors, int $bytes, string $pageUrl): void
    {
        // Make the messages readable in the log.
        try {
            Factory::getLanguage()->load('com_drekmeta', JPATH_ADMINISTRATOR)
                || Factory::getLanguage()->load('com_drekmeta', JPATH_ADMINISTRATOR . '/components/com_drekmeta');
        } catch (\Throwable $e) {
            // Keys are fine too.
        }

        $messages = array_map(
            static fn ($e) => ($e['path'] !== '' ? '[' . $e['path'] . '] ' : '') . strip_tags(Text::sprintf($e['key'], ...$e['args'])),
            \array_slice($errors, 0, 10)
        );

        $this->log(
            \sprintf(
                'Component embed of record %d (menu item %d) not output on %s (%d bytes): %s',
                $source->id,
                $source->menu_item_id,
                $pageUrl,
                $bytes,
                implode(' | ', $messages)
            ),
            Log::WARNING
        );
    }

    private function log(string $message, int $priority = Log::WARNING): void
    {
        try {
            if (!self::$loggerAdded) {
                Log::addLogger(['text_file' => 'drekmeta.php'], Log::ALL, [self::LOG_CATEGORY]);
                self::$loggerAdded = true;
            }

            Log::add($message, $priority, self::LOG_CATEGORY);
        } catch (\Throwable $e) {
            // Logging must never break the page.
        }
    }
}
