<?php

/**
 * @package     DREK Meta
 * @author      drek124 <https://github.com/drek124>
 * @copyright   (C) 2026 drek124
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Drek\Component\Drekmeta\Administrator\Extension\DrekmetaComponent;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new MVCFactory('\\Drek\\Component\\Drekmeta'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\Drek\\Component\\Drekmeta'));

        $container->set(
            ComponentInterface::class,
            function (Container $container) {
                $component = new DrekmetaComponent($container->get(ComponentDispatcherFactoryInterface::class));
                $component->setMVCFactory($container->get(MVCFactoryInterface::class));

                return $component;
            }
        );
    }
};
