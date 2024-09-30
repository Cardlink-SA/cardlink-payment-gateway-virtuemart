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

if (!class_exists('plgVmPaymentCardlinkirisInstallerScript')) {
	class plgVmPaymentCardlinkirisInstallerScript
	{
		public $pluginName = "cardlinkiris";
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

		private function copyLogo($adapter)
		{
			$logoImages = ['cardlink.svg', 'iris.png'];

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

	}
}