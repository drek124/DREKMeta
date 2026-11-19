<?php

/**
 * @package     DREK Meta
 * @author      drek124 <https://github.com/drek124>
 * @copyright   (C) 2026 drek124
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Drek\Component\Drekmeta\Administrator\View\Items;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarFactoryInterface;
use Joomla\CMS\Toolbar\ToolbarHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * List of meta records.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
    public $filterForm;

    public $activeFilters = [];

    protected $items = [];

    protected $pagination;

    protected $state;

    protected $canDo;

    public function display($tpl = null): void
    {
        /** @var \Drek\Component\Drekmeta\Administrator\Model\ItemsModel $model */
        $model = $this->getModel();

        $this->items         = $model->getItems();
        $this->pagination    = $model->getPagination();
        $this->state         = $model->getState();
        $this->filterForm    = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();
        $this->canDo         = ContentHelper::getActions('com_drekmeta');

        if ($errors = $model->getErrors()) {
            throw new \Exception(implode("\n", $errors), 500);
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        $toolbar = self::toolbar();
        $canDo   = $this->canDo;

        ToolbarHelper::title(Text::_('COM_DREKMETA_MANAGER_ITEMS'), 'share-alt');

        if ($canDo->get('core.create')) {
            $toolbar->addNew('item.add');
        }

        if ($canDo->get('core.edit')) {
            $toolbar->edit('item.edit')->listCheck(true);
        }

        if ($canDo->get('core.edit.state') || $canDo->get('core.admin')) {
            $dropdown = $toolbar->dropdownButton('status-group', 'JTOOLBAR_CHANGE_STATUS')
                ->toggleSplit(false)
                ->icon('icon-ellipsis-h')
                ->buttonClass('btn btn-action')
                ->listCheck(true);

            $childBar = $dropdown->getChildToolbar();
            $childBar->publish('items.publish')->listCheck(true);
            $childBar->unpublish('items.unpublish')->listCheck(true);
            $childBar->checkin('items.checkin')->listCheck(true);
        }

        if ($canDo->get('core.delete')) {
            $toolbar->delete('items.delete', 'JTOOLBAR_DELETE')
                ->message('JGLOBAL_CONFIRM_DELETE')
                ->listCheck(true);
        }

        if ($canDo->get('core.admin') || $canDo->get('core.options')) {
            $toolbar->preferences('com_drekmeta');
        }
    }

    /**
     * Toolbar instance that works on Joomla 4.4, 5 and 6.
     */
    public static function toolbar(): Toolbar
    {
        $document = Factory::getApplication()->getDocument();

        if (method_exists($document, 'getToolbar')) {
            return $document->getToolbar('toolbar');
        }

        return Factory::getContainer()->get(ToolbarFactoryInterface::class)->createToolbar('toolbar');
    }
}
