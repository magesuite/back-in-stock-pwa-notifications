<?php

namespace MageSuite\BackInStockPwaNotifications\Service\Notification\Sender\Channel;

class PushNotificationSender
{
    /**
     * @var \MageSuite\PwaNotifications\Model\Notification\SendByDevice
     */
    protected $sendByDevice;

    /**
     * @var \MageSuite\PwaNotifications\Api\Data\NotificationInterfaceFactory
     */
    protected $notificationFactory;

    /**
     * @var \MageSuite\BackInStock\Api\NotificationProductDataResolverInterface
     */
    protected $notificationProductDataResolver;

    /**
     * @var \Magento\Store\Model\App\Emulation
     */
    protected $emulation;

    public function __construct(
        \MageSuite\PwaNotifications\Model\Notification\SendByDevice $sendByDevice,
        \MageSuite\PwaNotifications\Api\Data\NotificationInterfaceFactory $notificationFactory,
        \MageSuite\BackInStock\Api\NotificationProductDataResolverInterface $notificationProductDataResolver,
        \Magento\Store\Model\App\Emulation $emulation
    ) {
        $this->sendByDevice = $sendByDevice;
        $this->notificationFactory = $notificationFactory;
        $this->notificationProductDataResolver = $notificationProductDataResolver;
        $this->emulation = $emulation;
    }

    public function send($notification, $subscription)
    {
        $deviceId = $subscription->getPwaDeviceId();
        $storeId = $subscription->getStoreId();

        $this->emulation->startEnvironmentEmulation($storeId);

        $productData = $this->notificationProductDataResolver->getProductData($subscription);

        $notification = $this->notificationFactory->create();
        $notification->setTitle(__('Product is back in stock'));
        $notification->setBody(__('%1 is now back in stock', $productData->getName()));
        $notification->setUrl($productData->getProductUrl());
        $notification->setImage($productData->getProductImageUrl());
        $notification->setIcon($productData->getProductImageUrl());

        $this->emulation->stopEnvironmentEmulation();

        $this->sendByDevice->execute($deviceId, $notification, ['back_in_stock_notification']);
    }
}
