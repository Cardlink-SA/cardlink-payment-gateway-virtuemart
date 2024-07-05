<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');
if (!defined('DS')) {
	define('DS', DIRECTORY_SEPARATOR);
}

use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\File;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\PluginHelper;

if (!class_exists('plgVmPaymentCardlinkcardInstallerScript')) {
	class plgVmPaymentCardlinkcardInstallerScript
	{
		public $pluginName = "cardlinkcard";
		public function preflight($route, $adapter)
		{
			if (file_exists(JPATH_PLUGINS . '/vmpayment/' . $this->pluginName . '/' . $this->pluginName . '.php')) {
				try {
					PluginHelper::importPlugin('vmpayment', $this->pluginName);
				} catch (Exception $e) {

				}
			}
			return true;
		}

		public function install($adapter)
		{
			return $this->createTokensTable($adapter);
		}

		public function update($adapter)
		{
		}

		public function uninstall($adapter)
		{
		}

		public function postflight($route, $adapter)
		{
			return $this->copyLogo($adapter);
		}

		private function createTokensTable($adapter)
		{
			$db = Factory::getContainer()->get(DatabaseInterface::class);
			$db->setQuery(
				'CREATE TABLE IF NOT EXISTS ' . $db->getPrefix() . 'virtuemart_payment_plg_' . $this->pluginName . '_tokens' .
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

			return true;
		}

		private function copyLogo($adapter)
		{
			$logoImages = ['cardlink.svg', 'alphabank.png', 'eurobank.png'];

			foreach ($logoImages as $logoImg) {
				$filesource = JPATH_SITE . '/plugins/vmpayment/' . $this->pluginName . '/assets/images/' . $logoImg;
				$filedest = JPATH_SITE . '/images/virtuemart/payment/' . $logoImg;
				if (!is_dir(dirname($filedest))) {
					Folder::create(dirname($filedest));
				}
				if (!File::copy($filesource, $filedest)) {
					Log::add(Text::sprintf('JLIB_INSTALLER_ERROR_FAIL_COPY_FILE', $filesource, $filedest), Log::WARNING, 'jerror');
					throw new Exception('JInstaller::install: ' . Text::sprintf('Failed to copy file to', $filesource, $filedest));
				}
			}
			return true;
		}

		private function dropTable($adapter)
		{
			$db = Factory::getContainer()->get(DatabaseInterface::class);
			$db->setQuery('DROP TABLE IF EXISTS `#__virtuemart_payment_plg_' . $this->pluginName . '`;');
			$db->execute();

			$db->setQuery('DROP TABLE IF EXISTS `#__virtuemart_payment_plg_' . $this->pluginName . '_tokens`;');
			$db->execute();

			return true;
		}

	}
}