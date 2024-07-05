<?php
/**
 * @package     Cardlink Payment Gateway dor VirtueMart
 * @version     1.0
 * @author      Cardlink <cardlink.gr>
 * @link        http://www.cardlink.gr
 * @copyright   Copyright (C) 2022 Cardlink All Rights Reserved
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Factory;

class plgSystemCardlinkInstallerScript
{
    public function preflight($route, $adapter)
    {

    }

    public function postflight($type, $parent)
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $status = new stdClass;
        $status->plugins = array();
        $src = $parent->getParent()->getPath('source');
        $manifest = $parent->getParent()->manifest;
        $plugins = $manifest->xpath('plugins/plugin');

        if (!defined('DS')) {
            define('DS', DIRECTORY_SEPARATOR);
        }

        foreach ($plugins as $plugin) {
            $name = (string) $plugin->attributes()->plugin;
            $group = (string) $plugin->attributes()->group;

            if ($group == 'vmpayment' && file_exists(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart' . DS . 'helpers' . DS . 'config.php')) {
                if (!class_exists('VmConfig')) {
                    require (JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart' . DS . 'helpers' . DS . 'config.php');
                }

                $config = VmConfig::loadConfig();
                if (!defined('VM_VERSION') && class_exists('vmVersion')) {
                    define('VM_VERSION', version_compare(vmVersion::$RELEASE, '3.0.0', 'ge') ? 3 : 2);
                }
            }

            $path = $src . '/plugins/' . $group;
            if (is_dir($src . '/plugins/' . $group . '/' . $name)) {
                $path = $src . '/plugins/' . $group . '/' . $name;
            }

            if (!file_exists($path . '/' . $name . '.xml')) {
                continue;
            }

            $installer = new Joomla\CMS\Installer\Installer;
            $result = $installer->install($path);
            if ($result && $group != 'finder') {
                //OK
            }

            $query = "UPDATE #__extensions SET `enabled`=1 WHERE `type`='plugin' AND `element`=" . $db->quote($name) . " AND `folder`=" . $db->quote($group);
            $db->setQuery($query);
            $db->execute();

            $query = "SELECT `name` FROM #__extensions WHERE `type`='plugin' AND element = " . $db->Quote($name) . " AND folder = " . $db->Quote($group);
            $db->setQuery($query);
            $extensions = $db->loadColumn();
            if (count($extensions)) {
                foreach ($extensions as $extension_name) {
                    $status->plugins[] = array('name' => $extension_name, 'group' => $group, 'result' => $result);
                }
            }
        }

        $pGroup = (string) $manifest->attributes()->group[0];
        $pName = $manifest->xpath('name');
        $query = "UPDATE #__extensions SET `enabled`=1 WHERE `type`='plugin' AND `name`=" . $db->quote((string) $pName[0]) . " AND `folder`=" . $db->quote($pGroup);
        $db->setQuery($query);
        $db->execute();
        $status->plugins[] = array('name' => (string) $pName[0], 'group' => $pGroup, 'result' => true);
        $this->installationResults($status);
    }

    public function uninstall($parent)
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $status = new stdClass;
        $status->modules = array();
        $status->plugins = array();
        $manifest = $parent->getParent()->manifest;
        $plugins = $manifest->xpath('plugins/plugin');
        foreach ($plugins as $plugin) {
            $element = (string) $plugin->attributes()->plugin;
            $group = (string) $plugin->attributes()->group;
            $query = "SELECT * FROM #__extensions WHERE `type`='plugin' AND element = " . $db->Quote($element) . " AND folder = " . $db->Quote($group);
            $db->setQuery($query);
            $extensions = $db->loadResult();
            if ($extensions) {
                foreach ($extensions as $extension) {
                    $installer = new Joomla\CMS\Installer\Installer;
                    $result = $installer->uninstall('plugin', $extension->extension_id);
                    $result = false; //remove manually
                }
                $status->plugins[] = array('name' => $extension->name, 'group' => $group, 'result' => $result);
            }
        }
        $pGroup = (string) $manifest->attributes()->group[0];
        $pName = $manifest->xpath('name');
        $status->plugins[] = array('name' => (string) $pName[0], 'group' => $pGroup, 'result' => true);
        $this->uninstallationResults($status);
    }

    public function update($type)
    {

    }
    private function installationResults($status)
    {
        $rows = 0;
        ?>
        <h2>Installation Status</h2>
        <table class="adminlist table table-striped">
            <thead>
                <tr>
                    <th class="title" colspan="2">Extension</th>
                    <th width="30%">Status</th>
                </tr>
            </thead>
            <tfoot>
                <tr>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
            <tbody>
                <?php if (count($status->plugins)): ?>
                    <tr>
                        <th>Plugin</th>
                        <th>Group</th>
                        <th></th>
                    </tr>
                    <?php foreach ($status->plugins as $plugin): ?>
                        <tr class="row<?php echo ($rows++ % 2); ?>">
                            <td class="key">
                                <?php echo ucfirst($plugin['name']); ?>
                            </td>
                            <td class="key">
                                <?php echo ucfirst($plugin['group']); ?>
                            </td>
                            <td><strong>
                                    <?php echo ($plugin['result']) ? 'Installed' : '<span style="color:red;">Not Installed</span>'; ?>
                                </strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }
    private function uninstallationResults($status)
    {
        $rows = 0;
        ?>
        <h2>Removal Status</h2>
        <table class="adminlist table table-striped">
            <thead>
                <tr>
                    <th class="title" colspan="2">Extension</th>
                    <th width="30%">Status</th>
                </tr>
            </thead>
            <tfoot>
                <tr>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
            <tbody>
                <?php if (count($status->plugins)): ?>
                    <tr>
                        <th>Plugin</th>
                        <th>Group</th>
                        <th></th>
                    </tr>
                    <?php foreach ($status->plugins as $plugin): ?>
                        <tr class="row<?php echo ($rows++ % 2); ?>">
                            <td class="key">
                                <?php echo ucfirst($plugin['name']); ?>
                            </td>
                            <td class="key">
                                <?php echo ucfirst($plugin['group']); ?>
                            </td>
                            <td><strong>
                                    <?php echo ($plugin['result']) ? 'Removed' : 'Not removed'; ?>
                                </strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }
}