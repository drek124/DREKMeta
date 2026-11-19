<?php

/**
 * @package     DREK Meta
 * @author      drek124 <https://github.com/drek124>
 * @copyright   (C) 2026 drek124
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Drek\Component\Drekmeta\Administrator\Table;

use Drek\Component\Drekmeta\Administrator\Helper\MetaHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Table for #__drekmeta_items.
 *
 * @since  1.0.0
 */
class ItemTable extends Table
{
    protected $_supportNullValue = true;

    public function __construct(DatabaseDriver $db, ?DispatcherInterface $dispatcher = null)
    {
        $this->typeAlias = 'com_drekmeta.item';

        parent::__construct('#__drekmeta_items', 'id', $db, $dispatcher);

        $this->setColumnAlias('published', 'state');
    }

    public function check()
    {
        try {
            parent::check();
        } catch (\Exception $e) {
            $this->setError($e->getMessage());

            return false;
        }

        $this->menu_item_id = (int) $this->menu_item_id;
        $this->title        = trim((string) $this->title);

        if ($this->menu_item_id < 0) {
            $this->setError(Text::_('COM_DREKMETA_ERROR_MENU_ITEM_REQUIRED'));

            return false;
        }

        if ($this->title === '') {
            $this->setError(Text::_('COM_DREKMETA_ERROR_TITLE_REQUIRED'));

            return false;
        }

        // Nullable text columns: store NULL instead of '' so "inherit" is unambiguous.
        foreach (MetaHelper::META_FIELDS as $field) {
            $value         = $this->$field === null ? '' : trim((string) $this->$field);
            $this->$field  = $value === '' ? null : $value;
        }

        // The core colour field can submit keywords when cleared.
        if ($this->theme_color !== null && \in_array(strtolower($this->theme_color), ['none', 'transparent'], true)) {
            $this->theme_color = null;
        }

        if ($this->theme_color !== null) {
            if (!preg_match(MetaHelper::THEME_COLOR_PATTERN, $this->theme_color)) {
                $this->setError(Text::sprintf('COM_DREKMETA_ERROR_THEME_COLOR', $this->theme_color));

                return false;
            }

            $this->theme_color = strtolower($this->theme_color);
        }

        if ($this->og_type !== null && !\in_array($this->og_type, MetaHelper::OG_TYPES, true)) {
            $this->og_type = null;
        }

        if ($this->twitter_card !== null && !\in_array($this->twitter_card, MetaHelper::TWITTER_CARDS, true)) {
            $this->twitter_card = null;
        }

        $this->component_embed_enabled = (int) (bool) $this->component_embed_enabled;
        $this->state                   = (int) $this->state;

        // The global defaults row is always published.
        if ($this->menu_item_id === 0) {
            $this->state = 1;
        }

        // One record per menu item (the DB has a UNIQUE key too, this gives a friendly message).
        $db    = method_exists($this, 'getDatabase') ? $this->getDatabase() : $this->_db;
        $query = method_exists($db, 'createQuery') ? $db->createQuery() : $db->getQuery(true);
        $menu  = $this->menu_item_id;
        $id    = (int) $this->id;

        $query->select($db->quoteName('id'))
            ->from($db->quoteName('#__drekmeta_items'))
            ->where($db->quoteName('menu_item_id') . ' = :menu')
            ->where($db->quoteName('id') . ' != :id')
            ->bind(':menu', $menu, ParameterType::INTEGER)
            ->bind(':id', $id, ParameterType::INTEGER);

        $existing = (int) $db->setQuery($query)->loadResult();

        if ($existing) {
            $this->setError(
                $menu === 0
                    ? Text::_('COM_DREKMETA_ERROR_GLOBAL_EXISTS')
                    : Text::sprintf('COM_DREKMETA_ERROR_DUPLICATE_MENU_ITEM', $existing)
            );

            return false;
        }

        return true;
    }

    public function store($updateNulls = true)
    {
        $date = Factory::getDate()->toSql();
        $user = Factory::getApplication()->getIdentity();
        $uid  = $user ? (int) $user->id : 0;

        if (!(int) $this->id) {
            $this->created    = $this->created ?: $date;
            $this->created_by = $this->created_by ?: $uid;
        }

        $this->modified    = $date;
        $this->modified_by = $uid;

        return parent::store($updateNulls);
    }

    /**
     * The global defaults row can never be deleted.
     */
    public function delete($pk = null)
    {
        $pk = $pk ?? $this->id;

        if ($pk !== null && $this->load($pk) && (int) $this->menu_item_id === 0) {
            $this->setError(Text::_('COM_DREKMETA_ERROR_GLOBAL_DELETE'));

            return false;
        }

        return parent::delete($pk);
    }
}
