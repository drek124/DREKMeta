<?php

/**
 * @package     DREK Meta
 * @author      drek124 <https://github.com/drek124>
 * @copyright   (C) 2026 drek124
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Drek\Component\Drekmeta\Administrator\Controller;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Default controller.
 *
 * @since  1.0.0
 */
class DisplayController extends BaseController
{
    protected $default_view = 'items';

    public function display($cachable = false, $urlparams = [])
    {
        $view   = $this->input->get('view', 'items');
        $layout = $this->input->get('layout', 'default');
        $id     = $this->input->getInt('id');

        // Block direct access to the edit form without going through the controller (checkout + ACL).
        if ($view === 'item' && $layout === 'edit' && !$this->checkEditId('com_drekmeta.edit.item', $id)) {
            if (!\count($this->app->getMessageQueue())) {
                $this->setMessage(Text::sprintf('JLIB_APPLICATION_ERROR_UNHELD_ID', $id), 'error');
            }

            $this->setRedirect(Route::_('index.php?option=com_drekmeta&view=items', false));

            return false;
        }

        return parent::display($cachable, $urlparams);
    }
}
