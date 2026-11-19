<?php

/**
 * @package     DREK Meta
 * @author      drek124 <https://github.com/drek124>
 * @copyright   (C) 2026 drek124
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Drek\Component\Drekmeta\Administrator\View\Item;

use Drek\Component\Drekmeta\Administrator\View\Items\HtmlView as ItemsView;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Edit screen for a meta record.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
    protected $form;

    protected $item;

    protected $state;

    protected $isGlobal = false;

    protected $builderOptions = [];

    public function display($tpl = null): void
    {
        /** @var \Drek\Component\Drekmeta\Administrator\Model\ItemModel $model */
        $model = $this->getModel();

        $this->form  = $model->getForm();
        $this->item  = $model->getItem();
        $this->state = $model->getState();

        if ($errors = $model->getErrors()) {
            throw new \Exception(implode("\n", $errors), 500);
        }

        $this->builderOptions = $model->getBuilderOptions($this->item);
        $this->isGlobal       = $this->builderOptions['isGlobal'];

        $this->addToolbar();

        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        Factory::getApplication()->getInput()->set('hidemainmenu', true);

        $user       = Factory::getApplication()->getIdentity();
        $isNew      = !(int) $this->item->id;
        $checkedOut = !(\is_null($this->item->checked_out) || (int) $this->item->checked_out === (int) $user->id);
        $canDo      = ContentHelper::getActions('com_drekmeta');
        $toolbar    = ItemsView::toolbar();

        ToolbarHelper::title(
            Text::_($isNew ? 'COM_DREKMETA_MANAGER_ITEM_NEW' : 'COM_DREKMETA_MANAGER_ITEM_EDIT'),
            'share-alt'
        );

        $canSave = !$checkedOut && ($isNew ? $canDo->get('core.create') : $canDo->get('core.edit'));

        if ($canSave) {
            $toolbar->apply('item.apply');

            $saveGroup = $toolbar->dropdownButton('save-group');
            $saveGroup->configure(
                function ($childBar) use ($canDo) {
                    $childBar->save('item.save');

                    if ($canDo->get('core.create') && !$this->isGlobal) {
                        $childBar->save2new('item.save2new');
                    }
                }
            );
        }

        $toolbar->cancel('item.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');
    }
}
