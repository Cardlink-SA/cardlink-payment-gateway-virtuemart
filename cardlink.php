<?php
/**
 * @package     Cardlink Payment Helper
 * @version     4.2
 * @company   	Cardlink
 * @developer   Cardlink
 * @link        http://www.cardlink.gr
 * @copyright   Copyright (C) 2022 Cardlink All Rights Reserved
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die('Restricted access');

// if (!class_exists('vmDefines')) {
//     require_once (JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_virtuemart' . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'vmdefines.php');
//     vmDefines::defines('site');
// }
// if (!class_exists('vmPlugin')) {
//     require_once (JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_virtuemart' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'vmplugin.php');
// }
// if (!class_exists('vmPSPlugin')) {
//     require_once (JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_virtuemart' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'vmpsplugin.php');
// }

use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\Folder;
use Joomla\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Router\ApiRouter;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Router\Router;

class plgSystemCardlink extends JPlugin
{
    protected $debug = array();
    protected $isNotify = 0;
    protected $replacements = array();

    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
    }

    public function onAfterInitialise()
    {
        $app = Factory::getApplication();

        if ($app->isClient('admin')) {
            return;
        }

        $session = $app->getSession();
        $component = $app->input->get->get('com', '');
        $option = $app->input->get->get('option', '');
        $paymentMethod = $app->input->get->get('type', '');
        $paymentStatus = $app->input->get->get('status', '');
        $layout = $app->input->get->get('layout', '');
        $actions = array('ok' => 'pluginresponsereceived', 'cancel' => 'pluginUserPaymentCancel', 'notify' => 'pluginNotification', 'none' => null);
        $components = array('cardlink' => 'com_virtuemart');
        $paymentMethodsList = array('card' => 'cardlinkcard', 'iris' => 'cardlinkiris');

        $task = $paymentStatus;

        if ($layout == 'orderdone') {
            $app->allowCache(false);
            $app->set('caching', 0);
        }

        if ($option == 'com_ajax') {
            require_once (JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_virtuemart' . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'vmdefines.php');
            vmDefines::defines('site');

            require_once (JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_virtuemart' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'vmplugin.php');
            require_once (JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_virtuemart' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'vmpsplugin.php');
        }

        if (
            (array_key_exists($paymentMethod, $paymentMethodsList) || in_array($paymentMethod, $paymentMethodsList))
            && (array_key_exists($component, $components) || in_array($option, $components))
            && $app->input->get('t') == 'checkout'
        ) {
            $html = $session->get('cardlink_checkouthtml', null);
            if (!empty($html)) {
                echo '<!DOCTYPE html>
        		<html xmlns="//www.w3.org/1999/xhtml" xml:lang="en-gb" lang="en-gb" dir="ltr">
        		<head>
        		<meta http-equiv="content-type" content="text/html; charset=utf-8" />
        		<title>Redirecting to payment gateway...</title>
        		</head>
        		   <body>
        			 ' . $html . '
        		   </body>
        		</html>';
                $session->set('cardlink_checkouthtml', '');
            } else {
                echo 'There was a problem with your request, please contact with us or go back to your order and try again.';
            }
            $app->close();
        }

        if (
            (array_key_exists($paymentMethod, $paymentMethodsList) || in_array($paymentMethod, $paymentMethodsList))
            && (array_key_exists($component, $components) || in_array($option, $components))
            && count($_REQUEST) > 2
        ) {
            $this->isNotify = 1;
            if (array_key_exists($paymentMethod, $paymentMethodsList)) {
                $paymentMethod = $paymentMethodsList[$paymentMethod];
            }

            if (array_key_exists($component, $components)) {
                $option = $components[$component];
                $this->setVar('option', $option);
            }

            if ($this->params->get('disablesef', 0)) {
                $params = $app->getParams();
                $params->set('sef', 0);
            }

            if ($option == 'com_virtuemart') {
                $view = is_dir(JPATH_SITE . '/components/com_virtuemart/views/pluginresponse') ? 'pluginresponse' : 'vmplg';
                $this->setVar('view', $view);
                $this->setVar('task', isset($actions[$task]) ? $actions[$task] : $task);
                if ($task == 'notify') {
                    $this->setVar('tmpl', 'component');
                }
                $orderid = $app->input->getCmd('oid', '');
                $onumber = $app->input->getCmd('on', '');
                if (empty($onumber) && $orderid) {
                    $app->input->set('on', $orderid);
                }
            }

            $Itemid = (int) $this->params->get('Itemid', 0);
            if ($Itemid) {
                $this->setVar('Itemid', $Itemid);
            }

            $lang = $this->params->get('lang');
            if (!empty($lang)) {
                $this->setVar('lang', $lang);
            }

            if (method_exists($app, 'getRouter') && $this->params->get('router', 0)) {
                $this->debug[] = 'Router Attached';

                $router = Factory::getContainer()->get(ApiRouter::class);
                $router->attachBuildRule(array($this, 'preprocessBuildRule'), Router::PROCESS_BEFORE);
            }
        }
    }

    protected function setVar($var, $value = NULL)
    {
        $app = Factory::getApplication();
        $this->replacements[$var] = $_REQUEST[$var] = $_GET[$var] = $value;
        $app->input->set($var, $value);
        $this->debug[] = 'Var set ' . $var . '=' . $value;
    }

    public function onAfterRoute()
    {
        $app = Factory::getApplication();

        if ($this->params->get('debug', 0)) {
            $app->enqueueMessage('Router Vars:' . print_r(Factory::getContainer()->get(ApiRouter::class)->getVars(), true));
        }
    }

    public function onAfterRender()
    {
        $app = Factory::getApplication();
        if ($app->isClient('admin') || !$this->isNotify) {
            return;
        }

        $session = $app->getSession();
        $router = Factory::getContainer()->get(ApiRouter::class);
        $redirectURL = $session->get('cardlink_redirect', null);

        if ($this->params->get('debug', 0)) {
            $buffer = Factory::getApplication()->getBody();
            $debug = '<p>CARDLINK PLUGIN DEBUG</p>';
            $debug .= '<p>Debug:<br/>' . implode("\n<br/>", $this->debug) . '</p>';
            $debug .= '<p>Router:<br/><pre>' . print_r($router->getVars(), true) . '</pre></p>';
            if (!empty($redirectURL)) {
                $debug .= '<p>Redirect URL:' . Route::_($redirectURL) . '</p>';
            }

            $debug .= '<p>Option:' . $app->input->get('option') . ' View:' . $app->input->get('view') . ' Task:' . $app->input->get('task') . ' Lang:' . $app->input->get('lang') . ' Itemid:' . $app->input->get('Itemid') . '</p>';
            $debug .= '<p>$_GET: <pre>' . print_r($_GET, true) . '</pre></p>';
            $debug .= '<p>$_POST: <pre>' . print_r($_POST, true) . '</pre></p>';
            $app->setBody(str_replace('</body>', $debug . '</body>', $buffer));
        }

        if (!$this->params->get('debug', 0) && !empty($redirectURL)) {
            $session->set('cardlink_redirect', null);
            $app->redirect(Route::_($redirectURL));
            $app->close();
        }
    }

    public function preprocessBuildRule(&$router, &$uri)
    {
        //$router->setVars($this->replacements);
        foreach ($this->replacements as $k => $v) {
            $uri->setVar($k, $v);
            $router->setVar($k, $v);
        }
        $uri->delVar('b');
        $uri->delVar('virtuemart_manufacturer_id');
        $uri->delVar('virtuemart_category_id');
    }

    public function getOrderByID($virtuemart_order_id)
    {
        if (method_exists($this, 'getDatasByOrderId')) {
            return $this->getDatasByOrderId($virtuemart_order_id);
        }
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $q = 'SELECT * FROM `#__virtuemart_orders` '
            . 'WHERE `order_number` = "' . $virtuemart_order_id . '" ';
        $db->setQuery($q);
        return $db->loadObjectList();
    }

}