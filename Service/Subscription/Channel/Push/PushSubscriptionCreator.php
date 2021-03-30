<?php

namespace MageSuite\BackInStockPwaNotifications\Service\Subscription\Channel\Push;

class PushSubscriptionCreator
{
    const PWA_DEVICE_ID = 'pwa_device_id';

    /**
     * @var \Magento\Customer\Model\SessionFactory
     */
    protected $customerSession;

    /**
     * @var \MageSuite\BackInStock\Model\BackInStockSubscription
     */
    protected $backInStockSubscription;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \MageSuite\BackInStock\Api\BackInStockSubscriptionRepositoryInterface
     */
    protected $backInStockSubscriptionRepository;

    /**
     * @var \MageSuite\PwaNotifications\Helper\Session
     */
    protected $session;

    /**
     * @var \MageSuite\PwaNotifications\Model\Notification\SendByDevice
     */
    protected $sendByDevice;

    /**
     * @var \MageSuite\PwaNotifications\Api\Data\NotificationInterfaceFactory
     */
    protected $notificationFactory;

    /**
     * @var \Magento\Store\Model\App\Emulation
     */
    protected $emulation;

    /**
     * @var \MageSuite\BackInStock\Service\Subscription\ProductResolver
     */
    protected $productResolver;

    /**
     * @var \MageSuite\PwaNotifications\Model\PermissionManagement
     */
    protected $permissionManagement;

    public function __construct(
        \Magento\Customer\Model\SessionFactory $customerSession,
        \MageSuite\BackInStock\Model\BackInStockSubscription $backInStockSubscription,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \MageSuite\BackInStock\Api\BackInStockSubscriptionRepositoryInterface $backInStockSubscriptionRepository,
        \MageSuite\PwaNotifications\Helper\Session $session,
        \MageSuite\PwaNotifications\Model\Notification\SendByDevice $sendByDevice,
        \MageSuite\PwaNotifications\Api\Data\NotificationInterfaceFactory $notificationFactory,
        \Magento\Store\Model\App\Emulation $emulation,
        \MageSuite\BackInStock\Service\Subscription\ProductResolver $productResolver,
        \MageSuite\PwaNotifications\Model\PermissionManagement $permissionManagement
    )
    {
        $this->customerSession = $customerSession;
        $this->backInStockSubscription = $backInStockSubscription;
        $this->storeManager = $storeManager;
        $this->backInStockSubscriptionRepository = $backInStockSubscriptionRepository;
        $this->session = $session;
        $this->sendByDevice = $sendByDevice;
        $this->notificationFactory = $notificationFactory;
        $this->emulation = $emulation;
        $this->productResolver = $productResolver;
        $this->permissionManagement = $permissionManagement;
    }

    public function subscribe($params)
    {
        $storeId = $this->storeManager->getStore()->getId();
        $customerSession = $this->customerSession->create();
        $customerId = $customerSession->getCustomerId() ?? 0;

        $deviceId = $this->session->getDeviceId();

        if(empty($deviceId)) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Device capable of receiving push notifications is not registered.'));
        }

        $this->permissionManagement->add('back_in_stock_notification');

        $product = $this->productResolver->resolve($params);

        if($this->subscriptionExist($product->getId(), $deviceId, $storeId)){
            throw new \Magento\Framework\Exception\AlreadyExistsException(__('You have already subscribed for a back-to-stock notification for this product.'));
        }

        $subscription = $this->backInStockSubscription
            ->setCustomerId($customerId)
            ->setProductId($product->getId())
            ->setParentProductId($product->getParentProductId())
            ->setStoreId($storeId)
            ->setNotificationChannel('push')
            ->setCustomerConfirmed(true)
            ->setPwaDeviceId($deviceId)
        ;

        $this->backInStockSubscriptionRepository->save($subscription);

        $this->emulation->startEnvironmentEmulation($storeId);

        $notification = $this->notificationFactory->create();
        $notification->setTitle(__('Success'));
        $notification->setBody(__('You are successfully subscribed for back in stock notification'));

        $this->emulation->stopEnvironmentEmulation();

        $this->sendByDevice->execute($deviceId, $notification, ['back_in_stock_notification']);
    }

    public function subscriptionExist($productId, $deviceId, $storeId)
    {
        return $this->backInStockSubscriptionRepository->subscriptionExist(
            $productId,
            \MageSuite\BackInStockPwaNotifications\Service\Subscription\Channel\Push\PushSubscriptionCreator::PWA_DEVICE_ID,
            $deviceId,
            $storeId
        );
    }
}
