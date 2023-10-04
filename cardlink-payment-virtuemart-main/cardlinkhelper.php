<?php
/**
 * @package     GR Bank Payment Helper
 * @version     4.2
 * @company   	Cardlink
 * @developer   Cardlink
 * @link        http://www.cardlink.gr
 * @copyright   Copyright (C) 2022 Cardlink All Rights Reserved
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die('Restricted access');
//ini_set('display_errors',1);
//error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
jimport( 'joomla.plugin.plugin');
jimport('joomla.filesystem.folder');
class plgSystemCardlinkhelper extends JPlugin
{
	protected $debug=array();
	protected $isNotify=0;
	protected $replacements=array();
	
	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);
	}
	
	public function onAfterInitialise()
	{
		$app=JFactory::getApplication();
		if($app->isAdmin()) return;
		
		$session = JFactory::getSession();
		$compo=$app->input->get('com','');
		$option=$app->input->get('option','');
		$bank=$app->input->get('b','');
		$pmode=$app->input->getWord('pmode','');	
		$task=$app->input->getWord('task','none');	//deprecated
		$actions=array('ok'=>'pluginresponsereceived','cancel'=>'pluginUserPaymentCancel','notify'=>'pluginNotification','none'=>null);	
		$components=array('vm'=>'com_virtuemart');
		$banksList=array('alpha'=>'cardlink');
		
		if(!empty($pmode)) $task=$pmode;
		
		if((array_key_exists($bank,$banksList) || in_array($bank,$banksList)) && (array_key_exists($compo,$components) || in_array($option,$components)) && $app->input->get('t')=='checkout')
		{
			$html=$session->get('grbank_checkouthtml',null);
			if(!empty($html))
			{
				echo '<!DOCTYPE html>
				<html xmlns="//www.w3.org/1999/xhtml" xml:lang="en-gb" lang="en-gb" dir="ltr">
				<head>
				<meta http-equiv="content-type" content="text/html; charset=utf-8" />
				<title>Redirecting to payment gateway...</title>
				</head>
				   <body>
					 '.$html.'
				   </body>
				</html>';			
				$session->set('grbank_checkouthtml','');
			}
			else
			{
				echo 'There was a problem with your request, please contact with us or go back to your order and try again.';
			}
			$app->close();
		}
		
		if((array_key_exists($bank,$banksList) || in_array($bank,$banksList)) && (array_key_exists($compo,$components) || in_array($option,$components)) && count($_REQUEST)>2)
		{
			$this->isNotify=1;
			if(array_key_exists($bank,$banksList))
			{
				$bank=$banksList[$bank];
			}
					
			if(array_key_exists($compo,$components))
			{
				$option=$components[$compo];
				$this->setVar('option',$option);
			}
			
			if($this->params->get('disablesef',0))
			{
				$params = $app->getParams();
				$params->set('sef', 0);
			}
			
			if($option=='com_virtuemart')
			{
				$view=JFolder::exists(JPATH_SITE.'/components/com_virtuemart/views/pluginresponse')?'pluginresponse':'vmplg';
				$this->setVar('view',$view);
				$this->setVar('task',isset($actions[$task])?$actions[$task]:$task);
				if($task=='notify') $this->setVar('tmpl','component');
				$orderid=$app->input->getCmd('oid','');
				$onumber=$app->input->getCmd('on','');
				if(empty($onumber) && $orderid) $app->input->set('on',$orderid);
			}
			
			$Itemid=(int)$session->get('Itemid',0);
			if($Itemid)
			{
				$this->setVar('Itemid',$Itemid);
			}
			
			$lang=$session->get('lang');
			if(!empty($lang))
			{
				$this->setVar('lang',$lang);
			}
			
			if(method_exists($app,'getRouter') && $this->params->get('router',0))
			{
				$this->debug[]='Router Attached';
				
				$router = $app->getRouter();
				$router->attachBuildRule(array($this, 'preprocessBuildRule'), JRouter::PROCESS_BEFORE);
			}
		}
	}
	
	protected function setVar($var,$value=NULL)
	{
		$app=JFactory::getApplication();
		$this->replacements[$var]=$_REQUEST[$var]=$_GET[$var]=$value;
		$app->input->set($var,$value);
		$this->debug[]='Var set '.$var.'='.$value;
	}
	
	public function onAfterRoute()
	{
		if($this->params->get('debug',0))
		{
			$app=JFactory::getApplication();
			$app->enqueueMessage('Router Vars:'.print_r($app->getRouter()->getVars(),true));
		}
	}
	
	public function onAfterRender()
	{
		$app=JFactory::getApplication();
		if($app->isAdmin() || !$this->isNotify) return;
		
		$session = JFactory::getSession();
		$redirectURL=$session->get('grbank_redirect',null);
		
		if($this->params->get('debug',0))
		{
			$buffer=JResponse::getBody();
			$debug='<p>CARDLINK PLUGIN DEBUG</p>';
			$debug.='<p>Debug:<br/>'.implode("\n<br/>",$this->debug).'</p>';
			$debug.='<p>Router:<br/><pre>'.print_r($app->getRouter()->getVars(),true).'</pre></p>';
			if(!empty($redirectURL))
			{
				$debug.='<p>Redirect URL:'.JRoute::_($redirectURL).'</p>';
			}
			
			$debug.='<p>Option:'.$app->input->get('option').' View:'.$app->input->get('view').' Task:'.$app->input->get('task').' Lang:'.$app->input->get('lang').' Itemid:'.$app->input->get('Itemid').'</p>';
			$debug.='<p>$_GET: <pre>'.print_r($_GET,true).'</pre></p>';
			$debug.='<p>$_POST: <pre>'.print_r($_POST,true).'</pre></p>';
			JResponse::setBody(str_replace('</body>',$debug.'</body>',$buffer));
		}
		
		if(!$this->params->get('debug',0) && !empty($redirectURL))
		{
			$session->set('grbank_redirect',null); 
			$app->redirect(JRoute::_($redirectURL));
			$app->close();
		}
	}
	
	public function preprocessBuildRule(&$router, &$uri)
	{
		//$router->setVars($this->replacements);
		foreach($this->replacements as $k=>$v)
		{
			$uri->setVar($k,$v);
			$router->setVar($k,$v);
		}
		$uri->delVar('b');
		$uri->delVar('virtuemart_manufacturer_id');
		$uri->delVar('virtuemart_category_id');
	}

	public function onAjaxdeleteCardlinkToken()
    {
		$selected_card_value = $_POST['selected_card_value'];

		if( $selected_card_value ){
			$db = JFactory::getDBO();
			$db->setQuery ("DELETE FROM #__virtuemart_payment_plg_cardlink_tokens WHERE token='". $selected_card_value ."'");
			$db->execute();
			//for joomla 2.5 $db->query();
		}
    }

	public function onAjaxcheckOrderStatus() {
		$order_id = $_POST['order_id'];
		$order    = $this->getOrderByID( $order_id )[0];
		if ( ! $order ) { return false; }

		$confirmUrl = JURI::root().'?com=vm&b=cardlink&pmode=ok&pm='.$order->virtuemart_paymentmethod_id.'&on='.$order->order_number;
		$cancelUrl  = JURI::root().'?com=vm&b=cardlink&pmode=cancel&pm='.$order->virtuemart_paymentmethod_id.'&on='.$order->order_number;

		if( $order->order_status == 'P' ){
			$redirected = true;
		}

		$response = [
			'redirect_url' => false,
			'redirected'   => $redirected,
		];

		if ( $response['redirected'] !== '1' ) {
			if( $order->order_status == 'C' ){
				$response['redirect_url'] = $confirmUrl;
			}else{
				$response['redirect_url'] = $cancelUrl;
			}
		}

		return $response;
	}

	public function getOrderByID ($virtuemart_order_id) {
		if(method_exists($this, 'getDatasByOrderId')) return $this->getDatasByOrderId($virtuemart_order_id);
		$db = JFactory::getDBO ();
		$q = 'SELECT * FROM `#__virtuemart_orders` '
			. 'WHERE `order_number` = "' . $virtuemart_order_id. '" ';
		$db->setQuery($q);
		return $db->loadObjectList();
	}
	
}