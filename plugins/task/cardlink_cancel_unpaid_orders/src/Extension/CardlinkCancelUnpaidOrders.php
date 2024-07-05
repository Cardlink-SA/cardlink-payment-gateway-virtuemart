<?php

/**
 * @package         Joomla.Plugins
 * @subpackage      Task.Resthits
 *
 * @copyright   (C) 2023 JCM
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Task\CardlinkCancelUnpaidOrders\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status as TaskStatus;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use LogicException;

\defined('_JEXEC') or die;

/**
 * Task plugin with routines that offer checks on files.
 * At the moment, offers a single routine to check and resize image files in a directory.
 *
 * @since  4.1.0
 */
final class CardlinkCancelUnpaidOrders extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;
    use DatabaseAwareTrait;

    /**
     * @var string[]
     *
     * @since 4.1.0
     */
    protected const TASKS_MAP = [
        'CardlinkCancelUnpaidOrders' => [
            'langConstPrefix' => 'PLG_TASK_CARDLINK_CANCEL_UNPAID_ORDERS_TASK',
            'form' => 'cardlink_cancel_unpaid_orders',
            'method' => 'cancelUnpaidOrders',
        ],
    ];

    /**
     * @inheritDoc
     *
     * @return string[]
     *
     * @since 4.1.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList' => 'advertiseRoutines',
            'onExecuteTask' => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    /**
     * @var boolean
     * @since 4.1.0
     */
    protected $autoloadLanguage = true;

    /**
     * Constructor.
     *
     * @param   DispatcherInterface  $dispatcher  The dispatcher
     * @param   array                $config      An optional associative array of configuration settings
     *
     * @since   4.2.0
     */
    public function __construct(DispatcherInterface $dispatcher, array $config)
    {
        parent::__construct($dispatcher, $config);
    }

    protected function cancelUnpaidOrders(ExecuteTaskEvent $event): int
    {
        // Boot the DI container.
        $container = \Joomla\CMS\Factory::getContainer();

        // Alias the session service key to the web session service.
        $container->alias(\Joomla\Session\SessionInterface::class, 'session.web.site');

        // Get the application.
        $app = $container->get(\Joomla\CMS\Application\SiteApplication::class);
        \Joomla\CMS\Factory::$application = $app;

        $app = Factory::getApplication();

        if (true) {//$app->isClient('administrator')) {
            // Load the VirtueMart environment manually
            $this->loadVirtueMart();

            $cancelTime = (int) $this->params->get('cancel_time', 30);

            $db = \Joomla\CMS\Factory::getDbo();
            $query = $db->getQuery(true);

            // Get the current time and calculate the cutoff time
            $currentTime = time();
            $cutoffTime = $currentTime - ($cancelTime * 60);

            // Build the query to find unpaid orders older than the configured time and using the specified payment method
            $query->select('o.virtuemart_order_id')
                ->from($db->quoteName('#__virtuemart_orders', 'o'))
                ->join('INNER', $db->quoteName('#__virtuemart_paymentmethods', 'p') . ' ON p.virtuemart_paymentmethod_id = o.virtuemart_paymentmethod_id')
                ->where($db->quoteName('o.order_status') . ' = ' . $db->quote('P'))
                ->where($db->quoteName('o.created_on') . ' < ' . $db->quote(date('Y-m-d H:i:s', $cutoffTime)))
                ->where(
                    '('
                    . $db->quoteName('p.payment_element') . ' = ' . $db->quote('cardlinkcard')
                    . ' OR '
                    . $db->quoteName('p.payment_element') . ' = ' . $db->quote('cardlinkiris')
                    . ')'
                );

            // Execute the query
            $db->setQuery($query);

            $orders = $db->loadObjectList();

            foreach ($orders as $order) {
                $this->cancelOrder($app, $order->virtuemart_order_id);
            }
        }

        $app->close();

        return TaskStatus::OK;
    }

    private function cancelOrder($app, $orderId)
    {
        $orderModel = \VmModel::getModel('orders', [
            'ignore_request' => true
        ]);

        $orderDetails = $orderModel->getOrder($orderId);

        try {
            $lang = $this->setLanguageFromOrder($app, $orderDetails);

            // Set the order status to cancelled
            $orderDetails['order_status'] = 'X'; // 'X' is typically used for cancelled orders
            $orderModel->updateStatusForOneOrder($orderId, $orderDetails, true);

            $comment = "UNPAID ORDER AUTO CANCELED";

            $commentData['virtuemart_order_id'] = $orderId;
            $commentData['order_status_code'] = $orderDetails['order_status'];
            $commentData['customer_notified'] = 0;
            $commentData['comments'] = $comment;
            $commentData['created_on'] = date('Y-m-d H:i:s');

            $orderModel->updateOrderHistory($commentData);
        } catch (\Exception $ex) {
            echo $ex->getMessage();
        }
    }

    // Function to set the application language based on order
    function setLanguageFromOrder($app, $order)
    {
        // Assume language is stored in a custom field or in order details
        // Adjust the field name as necessary
        $languageCode = $order['details']['BT']->order_language;

        if ($languageCode) {
            \Joomla\CMS\Factory::$language = \JLanguage::getInstance($languageCode);

            // Set the application language
            $lang = \Joomla\CMS\Factory::getLanguage();
            $lang->load($languageCode);
            $app->loadLanguage($lang);

            // Optionally, you can reload language strings for the current component/module
            $lang->load('com_virtuemart', JPATH_SITE, $languageCode, true);
            $lang->load('cardlink_cancel_unpaid_orders', JPATH_SITE, $languageCode, true);

            return $lang;
        }

        return null;
    }

    private function loadVirtueMart()
    {
        define('JPATH_COMPONENT', JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_virtuemart');
        //define('JPATH_THEMES', JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'templates');

        if (!class_exists('VmConfig')) {
            require (JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_virtuemart' . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'config.php');
            \VmConfig::loadConfig();
            \VmConfig::loadJLang('com_virtuemart_orders', true);
            \VmConfig::loadJLang('com_virtuemart', true);
        }

        if (!class_exists('VmModel')) {
            require (JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_virtuemart' . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'vmmodel.php');
        }
    }
}