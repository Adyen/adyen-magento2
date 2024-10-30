<?php
namespace Adyen\Payment\Test\Unit\Helper;

use Magento\Framework\Serialize\SerializerInterface;
use PHPUnit\Framework\TestCase;
use Adyen\Payment\Helper\Webhook\OrderClosedWebhookHandler;
use Adyen\Payment\Helper\AdyenOrderPayment;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Order as OrderHelper;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Magento\Sales\Model\Order as MagentoOrder;
use Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory as OrderPaymentCollectionFactory;
use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface as MagentoOrderPaymentInterface;
use Magento\Sales\Model\Order;

class OrderClosedWebhookHandlerTest extends TestCase
{
    private $orderClosedWebhookHandler;
    private $adyenOrderPaymentHelperMock;
    private $orderHelperMock;
    private $configHelperMock;
    private $adyenLoggerMock;
    private $adyenOrderPaymentCollectionFactoryMock;
    private $serializerMock;

    protected function setUp(): void
    {
        $this->adyenOrderPaymentHelperMock = $this->createMock(AdyenOrderPayment::class);
        $this->orderHelperMock = $this->createMock(OrderHelper::class);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->adyenOrderPaymentCollectionFactoryMock = $this->createMock(OrderPaymentCollectionFactory::class);
        $this->serializerMock = $this->createMock(SerializerInterface::class);

        $this->orderClosedWebhookHandler = new OrderClosedWebhookHandler(
            $this->adyenOrderPaymentHelperMock,
            $this->orderHelperMock,
            $this->configHelperMock,
            $this->adyenOrderPaymentCollectionFactoryMock,
            $this->adyenLoggerMock,
            $this->serializerMock
        );
    }

    public function testHandleWebhookSuccessfulNotificationWithMatchingAdyenOrderPayment()
    {
        $orderMock = $this->createMock(MagentoOrder::class);
        $notificationMock = $this->createMock(Notification::class);
        $adyenOrderPaymentMock = $this->createConfiguredMock(Order\Payment::class, [
            'getId' => 123
        ]);
        $adyenOrderPaymentCollectionMock = $this->createMock(\Magento\Framework\Data\Collection\AbstractDb::class);
        $additionalDataString = 'a:1:{s:20:"order-1-pspReference";s:16:"testPspReference";}';
        $additionalData = array (
            'order-1-pspReference' => 'testPspReference',
        );
        $notificationMock->expects($this->once())->method('isSuccessful')->willReturn(true);
        $notificationMock->expects($this->once())->method('getAdditionalData')->willReturn($additionalDataString);
        $this->serializerMock
            ->method('unserialize')
            ->with($additionalDataString)
            ->willReturn($additionalData);

        $adyenOrderPaymentCollectionMock->method('addFieldToFilter')->willReturnSelf();
        $adyenOrderPaymentCollectionMock->method('getFirstItem')->willReturn($adyenOrderPaymentMock);

        $this->adyenOrderPaymentCollectionFactoryMock->method('create')->willReturn($adyenOrderPaymentCollectionMock);

        $this->adyenLoggerMock->method('addAdyenNotification')
            ->with('Updated adyen_order_payment with order status 1 for pspReference testPspReference', [
                'pspReference' => 'testPspReference',
                'status' => 1,
                'merchantReference' => $notificationMock->getMerchantReference()
            ]);

        $orderMock->expects($this->once())->method('addCommentToStatusHistory')
            ->with(__('This order has been successfully completed.'));

        $result = $this->orderClosedWebhookHandler->handleWebhook($orderMock, $notificationMock, 'completed');
        $this->assertSame($orderMock, $result);
    }

    public function testHandleWebhookUnsuccessfulNotificationWithRefund()
    {
        $orderMock = $this->createMock(MagentoOrder::class);
        $notificationMock = $this->createMock(Notification::class);
        $adyenOrderPaymentMock = $this->createMock(OrderPaymentInterface::class);
        $adyenOrderPaymentCollectionMock = $this->createMock(\Magento\Framework\Data\Collection\AbstractDb::class);

        $notificationMock->expects($this->once())->method('isSuccessful')->willReturn(false);

        $orderMock->expects($this->once())->method('getPayment')->willReturn(
            $this->createConfiguredMock(MagentoOrderPaymentInterface::class, ['getEntityId' => 123])
        );

        $adyenOrderPaymentCollectionMock->method('addFieldToFilter')
            ->willReturnSelf();

        $adyenOrderPaymentCollectionMock->expects($this->once())->method('getItems')->willReturn([$adyenOrderPaymentMock]);

        $this->adyenOrderPaymentCollectionFactoryMock->expects($this->once())->method('create')->willReturn($adyenOrderPaymentCollectionMock);

        $this->adyenOrderPaymentHelperMock->expects($this->once())->method('refundFullyAdyenOrderPayment')->with($adyenOrderPaymentMock);

        $orderMock->expects($this->once())->method('addCommentToStatusHistory')
            ->with(__('All the funds captured/settled will be refunded by Adyen.'));

        $this->adyenLoggerMock->expects($this->once())->method('addAdyenNotification')
            ->with('All the funds captured/settled will be refunded by Adyen.', [
                'pspReference' => $notificationMock->getPspreference(),
                'merchantReference' => $notificationMock->getMerchantReference()
            ]);

        $this->orderHelperMock->method('holdCancelOrder')->with($orderMock, true);

        $this->configHelperMock->method('getNotificationsCanCancel')->with($orderMock->getStoreId())->willReturn(true);

        $result = $this->orderClosedWebhookHandler->handleWebhook($orderMock, $notificationMock, 'completed');
        $this->assertSame($orderMock, $result);
    }
}
