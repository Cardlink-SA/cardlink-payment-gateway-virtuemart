<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');
if(!defined('DS')) define('DS',DIRECTORY_SEPARATOR);

if(!class_exists('plgVmPaymentCardlinkInstallerScript'))
{
	class plgVmPaymentCardlinkInstallerScript
	{
		public $mainifest="cardlink";
		public function preflight($route, $adapter)
		{
			if(JFile::exists(JPATH_PLUGINS.'/vmpayment/'.$this->mainifest.'/'.$this->mainifest.'.php'))
			{
				try
				{
					$dispatcher = JEventDispatcher::getInstance();
					JPluginHelper::importPlugin('vmpayment',$this->mainifest);
				}
				catch(Exception $e)
				{
					
				}
			}
			return true;
		}
		
		public function install($adapter) {
			return $this->createTokensTable($adapter);
		}

		public function update($adapter)
		{
			return $this->updateTable($adapter);
		}
		
		public function uninstall($adapter) {
			 return $this->dropTable($adapter);
		}
		
		public function postflight($route, $adapter) 
		{
			return $this->copyLogo($adapter);
		}



		private function createTokensTable($adapter){
			$db = JFactory::getDBO();
			$db->setQuery (
				'CREATE TABLE IF NOT EXISTS ' . $db->getPrefix().'virtuemart_payment_plg_'.$this->mainifest.'_tokens' . 
				'   (token_id int(11) unsigned NOT NULL AUTO_INCREMENT,
					token varchar(30) not null,
					user_id int(11) unsigned NOT NULL,
					type varchar(200) not null,
					last4 varchar(100) not null, 
					expiry_year varchar(100) not null, 
					expiry_month varchar(100) not null, 
					card_type varchar(100) not null, 
					PRIMARY KEY (token_id))'
			);
			$db->execute();
			//for joomla 2.5 $db->query();

			return true;
		}
		
		private function copyLogo($adapter)
		{
			jimport('joomla.filesystem.file');
			jimport('joomla.filesystem.folder');
			$filesource = JPATH_SITE.'/plugins/vmpayment/'.$this->mainifest.'/'.$this->mainifest.'/images/cardlink.png';
			$filedest = JPATH_SITE."/images/stories/virtuemart/payment/cardlink.png";
			if(!JFolder::exists(dirname($filedest))) JFolder::create(dirname($filedest));
			if(!JFile::copy($filesource, $filedest))
			{
				JLog::add(JText::sprintf('JLIB_INSTALLER_ERROR_FAIL_COPY_FILE', $filesource, $filedest), JLog::WARNING, 'jerror');
				throw new Exception('JInstaller::install: '.JText::sprintf('Failed to copy file to', $filesource, $filedest));
				return false;
			}
			return true;
		}
		
		private function dropTable($adapter)
		{
			$db = JFactory::getDBO();
			$db->setQuery('DROP TABLE IF EXISTS `#__virtuemart_payment_plg_cardlink`;');
			$db->execute();
			//for joomla 2.5 $db->query();

			$db->setQuery('DROP TABLE IF EXISTS `#__virtuemart_payment_plg_cardlink_tokens`;');
			$db->execute();
			//for joomla 2.5 $db->query();

			return true;
		}
		
		private function updateTable($adapter)
		{
			$table='#__virtuemart_payment_plg_'.$this->mainifest;
			$db = JFactory::getDBO();
			$fields = $db->getTableColumns($table);
			$query=array();
			
			if(!array_key_exists('installments',$fields))
			{
				$query[]='ALTER TABLE '.$db->quoteName($table).' ADD '.$db->quoteName('installments').' INT(3) UNSIGNED NOT NULL;';
			}
			
			foreach($query as $idx=>$q)
			{
				$db->setQuery($q);
				$result=version_compare(JVERSION,'3.0','ge')?$db->execute():$db->query();
				$updated=$db->getAffectedRows();
				$class=$result && $updated?'success':'error';
				JFactory::getApplication()->enqueueMessage($q,$class);
			}
			return true;
		}
	}
}