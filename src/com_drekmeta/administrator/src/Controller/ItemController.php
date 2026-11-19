<?php

/**
 * @package     DREK Meta
 * @author      drek124 <https://github.com/drek124>
 * @copyright   (C) 2026 drek124
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Drek\Component\Drekmeta\Administrator\Controller;

use Joomla\CMS\MVC\Controller\FormController;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Single record controller (add, edit, apply, save, save2new, cancel). CSRF checks live in FormController.
 *
 * @since  1.0.0
 */
class ItemController extends FormController
{
    protected $text_prefix = 'COM_DREKMETA_ITEM';

    /**
     * Drops the per-request builder errors once the admin leaves the form.
     */
    public function cancel($key = null)
    {
        $this->app->setUserState('com_drekmeta.edit.item.embed_errors', null);

        return parent::cancel($key);
    }

    protected function postSaveHook($model, $validData = [])
    {
        $this->app->setUserState('com_drekmeta.edit.item.embed_errors', null);
    }
}
