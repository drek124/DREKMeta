<?php

/**
 * @package     DREK Meta
 * @author      drek124 <https://github.com/drek124>
 * @copyright   (C) 2026 drek124
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Drek\Component\Drekmeta\Administrator\Controller;

use Joomla\CMS\MVC\Controller\AdminController;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * List controller (publish, unpublish, check-in, delete). CSRF checks live in AdminController.
 *
 * @since  1.0.0
 */
class ItemsController extends AdminController
{
    protected $text_prefix = 'COM_DREKMETA_ITEMS';

    public function getModel($name = 'Item', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }
}
