<?php
defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\Task\CardlinkCancelUnpaidOrders\Extension\CardlinkCancelUnpaidOrders;

return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     * @param   Container  $container  The DI container.
     * @return  void
     * @since   4.3.0
     */
    public function register(Container $container)
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $dispatcher = $container->get(DispatcherInterface::class);

                $plugin = new CardlinkCancelUnpaidOrders(
                    $dispatcher,
                    (array) PluginHelper::getPlugin('task', 'cardlink_cancel_unpaid_orders')
                );
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};