<?php

/**
 * @package     DREK Meta
 * @author      drek124 <https://github.com/drek124>
 * @copyright   (C) 2026 drek124
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Drek\Component\Drekmeta\Administrator\Model;

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * List of meta records. The global defaults row is always pinned first.
 *
 * @since  1.0.0
 */
class ItemsModel extends ListModel
{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id',
                'title', 'a.title',
                'state', 'a.state',
                'menu_item_id', 'a.menu_item_id',
                'menu_title', 'm.title',
                'menutype', 'm.menutype',
                'component_embed_enabled', 'a.component_embed_enabled',
                'modified', 'a.modified',
            ];
        }

        parent::__construct($config, $factory);
    }

    protected function populateState($ordering = 'a.title', $direction = 'asc')
    {
        parent::populateState($ordering, $direction);
    }

    protected function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.state');
        $id .= ':' . $this->getState('filter.menutype');
        $id .= ':' . $this->getState('filter.embed');

        return parent::getStoreId($id);
    }

    protected function getListQuery()
    {
        $db    = $this->getDatabase();
        $query = method_exists($db, 'createQuery') ? $db->createQuery() : $db->getQuery(true);

        $query->select(
            [
                $db->quoteName('a') . '.*',
                $db->quoteName('m.title', 'menu_title'),
                $db->quoteName('m.link', 'menu_link'),
                $db->quoteName('m.menutype', 'menutype'),
                $db->quoteName('m.published', 'menu_published'),
                $db->quoteName('mt.title', 'menu_type_title'),
                $db->quoteName('uc.name', 'editor'),
            ]
        )
            ->from($db->quoteName('#__drekmeta_items', 'a'))
            ->join('LEFT', $db->quoteName('#__menu', 'm'), $db->quoteName('m.id') . ' = ' . $db->quoteName('a.menu_item_id'))
            ->join('LEFT', $db->quoteName('#__menu_types', 'mt'), $db->quoteName('mt.menutype') . ' = ' . $db->quoteName('m.menutype'))
            ->join('LEFT', $db->quoteName('#__users', 'uc'), $db->quoteName('uc.id') . ' = ' . $db->quoteName('a.checked_out'));

        $this->applyFilters($query);

        // Pin the global row first, then the user's ordering.
        $query->order('CASE WHEN ' . $db->quoteName('a.menu_item_id') . ' = 0 THEN 0 ELSE 1 END ASC');

        $ordering  = $this->state->get('list.ordering', 'a.title');
        $direction = strtoupper($this->state->get('list.direction', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        if (\in_array($ordering, $this->filter_fields, true) && str_contains($ordering, '.')) {
            $query->order($db->escape($ordering) . ' ' . $direction);
        }

        return $query;
    }

    private function applyFilters(QueryInterface $query): void
    {
        $db = $this->getDatabase();

        $state = (string) $this->getState('filter.state');

        if (is_numeric($state)) {
            $state = (int) $state;
            $query->where($db->quoteName('a.state') . ' = :state')
                ->bind(':state', $state, ParameterType::INTEGER);
        } elseif ($state === '') {
            $query->whereIn($db->quoteName('a.state'), [0, 1]);
        }

        $menutype = (string) $this->getState('filter.menutype');

        if ($menutype !== '') {
            $query->where($db->quoteName('m.menutype') . ' = :menutype')
                ->bind(':menutype', $menutype);
        }

        $embed = (string) $this->getState('filter.embed');

        if (is_numeric($embed)) {
            $embed = (int) $embed;
            $query->where($db->quoteName('a.component_embed_enabled') . ' = :embed')
                ->bind(':embed', $embed, ParameterType::INTEGER);
        }

        $search = trim((string) $this->getState('filter.search'));

        if ($search !== '') {
            if (stripos($search, 'id:') === 0) {
                $id = (int) substr($search, 3);
                $query->where($db->quoteName('a.id') . ' = :id')
                    ->bind(':id', $id, ParameterType::INTEGER);
            } else {
                $like = '%' . str_replace(' ', '%', trim($search)) . '%';
                $query->where(
                    '(' . $db->quoteName('a.title') . ' LIKE :s1'
                    . ' OR ' . $db->quoteName('a.og_title') . ' LIKE :s2'
                    . ' OR ' . $db->quoteName('m.title') . ' LIKE :s3)'
                )
                    ->bind(':s1', $like)
                    ->bind(':s2', $like)
                    ->bind(':s3', $like);
            }
        }
    }
}
