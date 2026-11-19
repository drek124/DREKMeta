<?php

/**
 * @package     DREK Meta
 * @author      drek124 <https://github.com/drek124>
 * @copyright   (C) 2026 drek124
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Drek\Component\Drekmeta\Administrator\Model;

use Drek\Component\Drekmeta\Administrator\Helper\EmbedHelper;
use Drek\Component\Drekmeta\Administrator\Helper\MetaHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Single meta record: form handling, custom tags, component embed validation.
 *
 * @since  1.0.0
 */
class ItemModel extends AdminModel
{
    public const ERRORS_STATE_KEY = 'com_drekmeta.edit.item.embed_errors';

    /** Local images above this size get a warning: Discord gives the whole fetch 10 seconds. */
    public const LARGE_IMAGE_BYTES = 1572864;

    /** Cap for the menu item map handed to the builder. */
    private const MENU_ITEM_LIMIT = 2500;

    public $typeAlias = 'com_drekmeta.item';

    protected $text_prefix = 'COM_DREKMETA_ITEM';

    private ?array $menuInfoCache = null;

    public function getTable($name = 'Item', $prefix = 'Administrator', $options = [])
    {
        return parent::getTable($name, $prefix, $options);
    }

    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm('com_drekmeta.item', 'item', ['control' => 'jform', 'load_data' => $loadData]);

        if (!$form) {
            return false;
        }

        $id = (int) ($data['id'] ?? $form->getValue('id'));

        if ($this->isGlobalRecord($id)) {
            $this->lockGlobalForm($form);
        }

        return $form;
    }

    protected function loadFormData()
    {
        $data = Factory::getApplication()->getUserState('com_drekmeta.edit.item.data', []);

        if (empty($data)) {
            $data = $this->getItem();
        }

        $this->preprocessData('com_drekmeta.item', $data);

        return $data;
    }

    public function getItem($pk = null)
    {
        $item = parent::getItem($pk);

        if (!$item) {
            return $item;
        }

        if (!(int) $item->id) {
            $item->state = 1;

            if ($menuId = (int) $this->getState('item.menu_item_id')) {
                $item->menu_item_id = $menuId;
            }
        }

        // Subform expects rows keyed custom_tags0, custom_tags1, ...
        $rows = [];

        foreach (MetaHelper::customTags($item->custom_tags ?? '') as $i => $row) {
            $rows['custom_tags' . $i] = $row;
        }

        $item->custom_tags = $rows;

        return $item;
    }

    public function save($data)
    {
        $app      = Factory::getApplication();
        $pk       = (int) ($data['id'] ?? 0);
        $isGlobal = $this->isGlobalRecord($pk);

        $app->setUserState(self::ERRORS_STATE_KEY, null);

        if ($isGlobal) {
            $data['menu_item_id'] = 0;
            $data['state']        = 1;
            $data['title']        = trim((string) ($data['title'] ?? '')) ?: 'Global defaults (all pages)';
        } else {
            $menuId = (int) ($data['menu_item_id'] ?? 0);

            if ($menuId <= 0) {
                $this->setError(Text::_('COM_DREKMETA_ERROR_MENU_ITEM_REQUIRED'));

                return false;
            }

            $menu = $this->getMenuInfo()[$menuId] ?? null;

            if ($menu === null) {
                $this->setError(Text::_('COM_DREKMETA_ERROR_MENU_ITEM_NOT_FOUND'));

                return false;
            }

            $data['menu_item_id'] = $menuId;

            if (trim((string) ($data['title'] ?? '')) === '') {
                $data['title'] = $menu['title'];
            }
        }

        // Custom tags: subform rows -> clean JSON list.
        $tags                = MetaHelper::customTags(array_values((array) ($data['custom_tags'] ?? [])));
        $data['custom_tags'] = $tags ? json_encode($tags, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

        if (!$this->prepareEmbed($data, $isGlobal)) {
            return false;
        }

        $this->warnLargeImage((string) ($data['og_image'] ?? ''));

        return parent::save($data);
    }

    /**
     * Validates and cleans the component embed. Returns false (with errors stored for the builder) to block saving.
     */
    private function prepareEmbed(array &$data, bool $isGlobal): bool
    {
        $app     = Factory::getApplication();
        $raw     = trim((string) ($data['component_embed'] ?? ''));
        $enabled = !empty($data['component_embed_enabled']);

        $data['component_embed_enabled'] = (int) $enabled;

        if ($raw === '') {
            $data['component_embed'] = null;

            if ($enabled) {
                $this->setError(Text::_('COM_DREKMETA_EMBED_ERR_ENABLED_EMPTY'));

                return false;
            }

            return true;
        }

        $root    = Uri::root();
        $context = $this->getRenderContext($data, $isGlobal);
        $result  = EmbedHelper::process($raw, $context, $root, MetaHelper::hostRoot($root));
        $errors  = array_map([$this, 'describeError'], $result['errors']);

        // Unparseable JSON or no root Container: never store it, enabled or not.
        if ($result['stored'] === null) {
            $app->setUserState(self::ERRORS_STATE_KEY, $errors);
            $this->setError(Text::_('COM_DREKMETA_EMBED_ERR_SAVE_BLOCKED') . ' ' . ($errors[0]['message'] ?? ''));

            return false;
        }

        $data['component_embed'] = EmbedHelper::encodeForStorage($result['stored']);

        if ($errors) {
            if ($enabled) {
                $app->setUserState(self::ERRORS_STATE_KEY, $errors);

                $list = array_map(static fn ($e) => $e['location'] . $e['message'], \array_slice($errors, 0, 5));
                $this->setError(
                    Text::plural('COM_DREKMETA_EMBED_N_ERRORS', \count($errors))
                    . '<ul><li>' . implode('</li><li>', array_map('htmlspecialchars', $list)) . '</li></ul>'
                );

                return false;
            }

            // Disabled embeds may be saved as drafts, but say so.
            $app->enqueueMessage(Text::plural('COM_DREKMETA_EMBED_N_ERRORS_DRAFT', \count($errors)), 'warning');
        }

        if ($result['resolved'] !== null) {
            foreach (EmbedHelper::mediaUrls($result['resolved']) as $url) {
                $this->warnLargeImage($url);
            }
        }

        return true;
    }

    /**
     * Placeholder values as the plugin would see them on this menu item's page.
     * Fallback title/description are approximations of what Joomla puts in the document.
     *
     * @return  array<string, string>
     */
    public function getRenderContext(array $data, bool $isGlobal): array
    {
        $params  = ComponentHelper::getParams('com_drekmeta');
        $current = (object) $data;
        $global  = $isGlobal ? $current : $this->getGlobalRow();
        $merged  = MetaHelper::merge($global, $isGlobal ? null : $current);
        $menu    = $isGlobal ? $this->getHomeInfo() : ($this->getMenuInfo()[(int) ($data['menu_item_id'] ?? 0)] ?? null);
        $site    = $this->getSiteInfo();

        $title = $merged['og_title'];

        if ($title === '' && $params->get('auto_title_fallback', 1)) {
            $title = $menu['docTitle'] ?? $site['name'];
        }

        $description = $merged['og_description'];

        if ($description === '' && $params->get('auto_description_fallback', 1)) {
            $description = $menu['description'] ?? $site['description'];
        }

        return [
            'og_title'       => $title,
            'og_description' => $description,
            'og_image'       => MetaHelper::image($merged['og_image'])['url'],
            'page_url'       => $menu['url'] ?? Uri::root(),
            'site_name'      => $merged['og_site_name'] !== '' ? $merged['og_site_name'] : $site['name'],
        ];
    }

    /**
     * Everything the edit screen's JavaScript needs: global values, site info, menu items, limits and save errors.
     */
    public function getBuilderOptions(object $item): array
    {
        $global   = $this->getGlobalRow();
        $isGlobal = $this->isGlobalRecord((int) ($item->id ?? 0));
        $params   = ComponentHelper::getParams('com_drekmeta');
        $root     = Uri::root();

        $globalValues = [];

        foreach (MetaHelper::META_FIELDS as $field) {
            $globalValues[$field] = $global ? (string) ($global->$field ?? '') : '';
        }

        $globalValues['og_image_url'] = MetaHelper::image($globalValues['og_image'])['url'];
        $globalValues['customTags']   = $global ? MetaHelper::customTags($global->custom_tags ?? '') : [];

        return [
            'isGlobal'  => $isGlobal,
            'global'    => $globalValues,
            'site'      => $this->getSiteInfo() + ['root' => $root, 'hostRoot' => MetaHelper::hostRoot($root)],
            'home'      => $this->getHomeInfo(),
            'menuItems' => $this->getMenuInfo(),
            'fallbacks' => [
                'title'       => (bool) $params->get('auto_title_fallback', 1),
                'description' => (bool) $params->get('auto_description_fallback', 1),
                'masterSwitch' => (bool) $params->get('component_embeds_enabled', 1),
            ],
            'errors'    => Factory::getApplication()->getUserState(self::ERRORS_STATE_KEY) ?: [],
            'limits'    => [
                'bytes'          => EmbedHelper::MAX_BYTES,
                'components'     => EmbedHelper::MAX_COMPONENTS,
                'gallery'        => EmbedHelper::MAX_GALLERY_ITEMS,
                'url'            => EmbedHelper::MAX_URL_LENGTH,
                'buttonUrl'      => EmbedHelper::MAX_BUTTON_URL_LENGTH,
                'label'          => EmbedHelper::MAX_LABEL_LENGTH,
                'description'    => EmbedHelper::MAX_DESCRIPTION,
                'largeImage'     => self::LARGE_IMAGE_BYTES,
            ],
            'debugger'  => 'https://discord.com/developers/embeds',
        ];
    }

    public function publish(&$pks, $value = 1)
    {
        if ((int) $value !== 1) {
            $pks = $this->withoutGlobal((array) $pks, 'COM_DREKMETA_ERROR_GLOBAL_UNPUBLISH');
        }

        return $pks ? parent::publish($pks, $value) : true;
    }

    public function delete(&$pks)
    {
        $pks = $this->withoutGlobal((array) $pks, 'COM_DREKMETA_ERROR_GLOBAL_DELETE');

        return $pks ? parent::delete($pks) : true;
    }

    /**
     * Adds a human-readable message and location to a raw validator error.
     */
    public function describeError(array $error): array
    {
        $error['message']  = Text::sprintf($error['key'], ...$error['args']);
        $error['location'] = '';

        if ($error['path'] === 'root') {
            $error['location'] = Text::_('COM_DREKMETA_BUILDER_CONTAINER') . ': ';
        } elseif ($error['path'] !== '') {
            $index             = (int) explode('.', $error['path'])[0];
            $error['location'] = Text::sprintf('COM_DREKMETA_BUILDER_COMPONENT_N', $index + 1) . ': ';
        }

        return $error;
    }

    private function warnLargeImage(string $value): void
    {
        if ($value === '' || str_contains($value, '{')) {
            return;
        }

        $image = MetaHelper::image($value);
        $file  = MetaHelper::localFile($image['local']);

        if ($file !== null && filesize($file) > self::LARGE_IMAGE_BYTES) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_DREKMETA_WARN_LARGE_IMAGE', $image['url'], number_format(filesize($file) / 1048576, 1)),
                'warning'
            );
        }
    }

    private function withoutGlobal(array $pks, string $messageKey): array
    {
        $pks = array_map('intval', $pks);

        if (!$pks) {
            return [];
        }

        $global = $this->getGlobalRow();

        if ($global && \in_array((int) $global->id, $pks, true)) {
            Factory::getApplication()->enqueueMessage(Text::_($messageKey), 'warning');
            $pks = array_values(array_diff($pks, [(int) $global->id]));
        }

        return $pks;
    }

    private function isGlobalRecord(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $global = $this->getGlobalRow();

        return $global && (int) $global->id === $id;
    }

    private function lockGlobalForm(Form $form): void
    {
        $form->setFieldAttribute('menu_item_id', 'required', 'false');
        $form->setFieldAttribute('menu_item_id', 'disabled', 'true');
        $form->setFieldAttribute('menu_item_id', 'filter', 'unset');
        $form->setFieldAttribute('state', 'disabled', 'true');
        $form->setFieldAttribute('state', 'filter', 'unset');
    }

    private ?object $globalRow = null;

    private bool $globalLoaded = false;

    public function getGlobalRow(): ?object
    {
        if ($this->globalLoaded) {
            return $this->globalRow;
        }

        $db    = $this->getDatabase();
        $query = method_exists($db, 'createQuery') ? $db->createQuery() : $db->getQuery(true);

        $query->select('*')
            ->from($db->quoteName('#__drekmeta_items'))
            ->where($db->quoteName('menu_item_id') . ' = 0');

        $this->globalRow    = $db->setQuery($query)->loadObject() ?: null;
        $this->globalLoaded = true;

        return $this->globalRow;
    }

    /**
     * @return  array{name: string, description: string}
     */
    private function getSiteInfo(): array
    {
        $app = Factory::getApplication();

        return [
            'name'        => (string) $app->get('sitename'),
            'description' => (string) $app->get('MetaDesc'),
        ];
    }

    private function getHomeInfo(): array
    {
        foreach ($this->getMenuInfo() as $info) {
            if ($info['home']) {
                return $info;
            }
        }

        $site = $this->getSiteInfo();

        return [
            'id'          => 0,
            'title'       => $site['name'],
            'docTitle'    => $site['name'],
            'description' => $site['description'],
            'url'         => Uri::root(),
            'menu'        => '',
            'home'        => true,
        ];
    }

    /**
     * Site menu items with an approximation of the page title/description Joomla will produce,
     * plus the SEF URL. Keyed by menu item ID.
     *
     * @return  array<int, array>
     */
    public function getMenuInfo(): array
    {
        if ($this->menuInfoCache !== null) {
            return $this->menuInfoCache;
        }

        $db    = $this->getDatabase();
        $query = method_exists($db, 'createQuery') ? $db->createQuery() : $db->getQuery(true);

        $query->select(
            [
                $db->quoteName('m.id'),
                $db->quoteName('m.title'),
                $db->quoteName('m.type'),
                $db->quoteName('m.home'),
                $db->quoteName('m.language'),
                $db->quoteName('m.params'),
                $db->quoteName('m.link'),
                $db->quoteName('mt.title', 'menu_title'),
            ]
        )
            ->from($db->quoteName('#__menu', 'm'))
            ->join('LEFT', $db->quoteName('#__menu_types', 'mt'), $db->quoteName('mt.menutype') . ' = ' . $db->quoteName('m.menutype'))
            ->where($db->quoteName('m.client_id') . ' = 0')
            ->where($db->quoteName('m.id') . ' > 1')
            ->where($db->quoteName('m.published') . ' IN (0, 1)')
            ->order($db->quoteName('mt.title') . ', ' . $db->quoteName('m.lft'));

        $query->setLimit(self::MENU_ITEM_LIMIT);

        $rows = $db->setQuery($query)->loadObjectList();
        $app  = Factory::getApplication();
        $site = $this->getSiteInfo();
        $mode = (int) $app->get('sitename_pagetitles', 0);
        $out  = [];

        foreach ($rows as $row) {
            $params    = new Registry($row->params);
            $pageTitle = trim((string) $params->get('page_title', '')) ?: $row->title;

            if ($mode === 1) {
                $pageTitle = Text::sprintf('JPAGETITLE', $site['name'], $pageTitle);
            } elseif ($mode === 2) {
                $pageTitle = Text::sprintf('JPAGETITLE', $pageTitle, $site['name']);
            }

            $out[(int) $row->id] = [
                'id'          => (int) $row->id,
                'title'       => (string) $row->title,
                'docTitle'    => $pageTitle,
                'description' => trim((string) $params->get('menu-meta_description', '')) ?: $site['description'],
                'url'         => $this->siteUrl((int) $row->id, (int) $row->home === 1, (string) $row->type),
                'menu'        => (string) $row->menu_title,
                'link'        => (string) $row->link,
                'home'        => (int) $row->home === 1 && \in_array($row->language, ['*', ''], true),
            ];
        }

        return $this->menuInfoCache = $out;
    }

    private function siteUrl(int $id, bool $home, string $type): string
    {
        if ($home) {
            return Uri::root();
        }

        try {
            return Route::link('site', 'index.php?Itemid=' . $id, false, Route::TLS_IGNORE, true);
        } catch (\Throwable $e) {
            return Uri::root() . 'index.php?Itemid=' . $id;
        }
    }

    /**
     * Prefill the menu item when "New" is used with &menu_item_id=.
     */
    protected function populateState()
    {
        parent::populateState();

        $menuId = Factory::getApplication()->getInput()->getInt('menu_item_id');

        if ($menuId) {
            $this->setState('item.menu_item_id', $menuId);
        }
    }
}
