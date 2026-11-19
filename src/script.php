<?php

/**
 * @package     DREK Meta
 * @author      drek124 <https://github.com/drek124>
 * @copyright   (C) 2026 drek124
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

return new class () implements InstallerScriptInterface {
    private string $minimumPhp = '8.1.0';

    private string $minimumJoomla = '4.4.0';

    public function install(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function update(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function uninstall(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function preflight(string $type, InstallerAdapter $adapter): bool
    {
        if ($type === 'uninstall') {
            return true;
        }

        $app = Factory::getApplication();

        if (version_compare(PHP_VERSION, $this->minimumPhp, '<')) {
            $app->enqueueMessage(Text::sprintf('PKG_DREKMETA_ERROR_PHP_VERSION', $this->minimumPhp), 'error');

            return false;
        }

        if (version_compare(JVERSION, $this->minimumJoomla, '<')) {
            $app->enqueueMessage(Text::sprintf('PKG_DREKMETA_ERROR_JOOMLA_VERSION', $this->minimumJoomla), 'error');

            return false;
        }

        return true;
    }

    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        // Only switch the plugin on for fresh installs, so an admin who disabled it keeps it disabled on update.
        if ($type !== 'install' && $type !== 'discover_install') {
            return true;
        }

        try {
            $db      = Factory::getContainer()->get(DatabaseInterface::class);
            $query   = method_exists($db, 'createQuery') ? $db->createQuery() : $db->getQuery(true);
            $type    = 'plugin';
            $folder  = 'system';
            $element = 'drekmeta';

            $query->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('type') . ' = :type')
                ->where($db->quoteName('folder') . ' = :folder')
                ->where($db->quoteName('element') . ' = :element')
                ->bind(':type', $type, ParameterType::STRING)
                ->bind(':folder', $folder, ParameterType::STRING)
                ->bind(':element', $element, ParameterType::STRING);

            $db->setQuery($query)->execute();
        } catch (\Throwable $e) {
            Factory::getApplication()->enqueueMessage(Text::_('PKG_DREKMETA_WARNING_PLUGIN_NOT_ENABLED'), 'warning');
        }

        return true;
    }
};
