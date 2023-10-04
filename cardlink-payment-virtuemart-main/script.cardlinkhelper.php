<?php
/**
 * @package     Cardlink Payment Gateway
 * @version     1.0
 * @author      Cardlink <cardlink.gr>
 * @link        http://www.cardlink.gr
 * @copyright   Copyright (C) 2022 Cardlink All Rights Reserved
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die ;
jimport('joomla.filesystem.file');
jimport('joomla.filesystem.folder');

class plgSystemCardlinkhelperInstallerScript
{
    public function preflight($route, $adapter)
	{
		
	}
	
	public function postflight($type, $parent)
    {	
		$db = JFactory::getDBO();
        $status = new stdClass;
        $status->plugins = array();
        $src = $parent->getParent()->getPath('source');
        $manifest = $parent->getParent()->manifest;
        $plugins = $manifest->xpath('plugins/plugin');
		
		if(!defined('DS')) define('DS', DIRECTORY_SEPARATOR);

        foreach ($plugins as $plugin)
        {
            $name = (string)$plugin->attributes()->plugin;
            $group = (string)$plugin->attributes()->group;
			
			if($group=='vmpayment' && JFile::exists(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart' . DS . 'helpers' . DS . 'config.php'))
			{
				if(!class_exists('VmConfig'))
				{
					require(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart' . DS . 'helpers' . DS . 'config.php');
				}
				
				$config = VmConfig::loadConfig();
				if(!defined('VM_VERSION') && class_exists('vmVersion'))
				{
					define('VM_VERSION',version_compare(vmVersion::$RELEASE,'3.0.0','ge')?3:2);
				}
			}
						
            $path = $src.'/plugins/'.$group;
            if (JFolder::exists($src.'/plugins/'.$group.'/'.$name))
            {
                $path = $src.'/plugins/'.$group.'/'.$name;
			}
			
			if(JFile::exists($path.'/'.$name.'.j3.xml'))
			{
				if(defined('VM_VERSION') && VM_VERSION > 2){
					JFile::delete($path.'/'.$name.'.xml');
					rename($path.'/'.$name.'.j3.xml',$path.'/'.$name.'.xml');
				}else{
					JFile::delete($path.'/'.$name.'.j3.xml');				
				}
			}
			if(!JFile::exists($path.'/'.$name.'.xml')) continue;
			
            $installer = new JInstaller;
            $result = $installer->install($path);
            if ($result && $group != 'finder')
            {
				//OK
            }
			
            $query = "UPDATE #__extensions SET `enabled`=1 WHERE `type`='plugin' AND `element`=".$db->quote($name)." AND `folder`=".$db->quote($group);
            $db->setQuery($query);
            $db->exexute();

			$status->plugins[] = array('name' => $name, 'group' => $group, 'result' => $result);
        }
		$pGroup=(string)$manifest->attributes()->group[0];
		$pName=$manifest->xpath('name');
		$query = "UPDATE #__extensions SET `enabled`=1 WHERE `type`='plugin' AND `element`=".$db->quote((string)$pName[0])." AND `folder`=".$db->quote($pGroup);
		$db->setQuery($query);
        $db->exexute();
		$status->plugins[] = array('name' =>(string)$pName[0], 'group' =>$pGroup, 'result' => true);
        $this->installationResults($status);
    }
	
    public function uninstall($parent)
    {
        $db = JFactory::getDBO();
        $status = new stdClass;
        $status->modules = array();
        $status->plugins = array();
        $manifest = $parent->getParent()->manifest;
        $plugins = $manifest->xpath('plugins/plugin');
       foreach ($plugins as $plugin)
        {
            $name = (string)$plugin->attributes()->plugin;
            $group = (string)$plugin->attributes()->group;
            $query = "SELECT `extension_id` FROM #__extensions WHERE `type`='plugin' AND element = ".$db->Quote($name)." AND folder = ".$db->Quote($group);
            $db->setQuery($query);
            $extensions = $db->loadColumn();
            if (count($extensions))
            {
                foreach ($extensions as $id)
                {
                    $installer = new JInstaller;
                   // $result = $installer->uninstall('plugin', $id);
				   $result =false; //remove manually
                }
                $status->plugins[] = array('name' => $name, 'group' => $group, 'result' => $result);
            }
        }
		$pGroup=(string)$manifest->attributes()->group[0];
		$pName=$manifest->xpath('name');
		$status->plugins[] = array('name' =>(string)$pName[0], 'group' =>$pGroup, 'result' =>true); 
        $this->uninstallationResults($status);
    }

    public function update($type){
		
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
                <tr class="row<?php echo($rows++ % 2); ?>">
                    <td class="key"><?php echo ucfirst($plugin['name']); ?></td>
                    <td class="key"><?php echo ucfirst($plugin['group']); ?></td>
                    <td><strong><?php echo ($plugin['result'])?'Installed':'<span style="color:red;">Not Installed</span>'; ?></strong></td>
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
                <tr class="row<?php echo($rows++ % 2); ?>">
                    <td class="key"><?php echo ucfirst($plugin['name']); ?></td>
                    <td class="key"><?php echo ucfirst($plugin['group']); ?></td>
                    <td><strong><?php echo ($plugin['result'])?'Removed':'Not removed';?></strong></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    <?php
    }
}