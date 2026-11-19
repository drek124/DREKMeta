<?php

/**
 * @package     DREK Meta
 * @author      drek124 <https://github.com/drek124>
 * @copyright   (C) 2026 drek124
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Drek\Plugin\System\Drekmeta\Extension\Drekmeta;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $config = (array) PluginHelper::getPlugin('system', 'drekmeta');

                // Joomla 5+ takes the config only; 4.4 still wants the dispatcher first.
                $plugin = version_compare(JVERSION, '5.0.0', '>=')
                    ? new Drekmeta($config)
                    : new Drekmeta($container->get(DispatcherInterface::class), $config);

                $plugin->setApplication(Factory::getApplication());
                $plugin->setDatabase($container->get(DatabaseInterface::class));

                return $plugin;
            }
        );
    }
};
