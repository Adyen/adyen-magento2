<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Cron;

use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Helper\AdyenOrderPayment;
use Adyen\Payment\Helper\CaseManagement;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config as ConfigHelper;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Invoice as InvoiceHelper;
use Adyen\Payment\Helper\PaymentMethods as PaymentMethodsHelper;
use Adyen\Payment\Helper\Webhook;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Api\PaymentRequest;
use Adyen\Payment\Model\Billing\AgreementFactory;
use Adyen\Payment\Model\InvoiceFactory;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\Order\PaymentFactory;
use Adyen\Payment\Model\ResourceModel\Billing\Agreement;
use Adyen\Payment\Model\ResourceModel\Billing\Agreement\CollectionFactory as AgreementCollectionFactory;
use Adyen\Payment\Model\ResourceModel\Notification\CollectionFactory as NotificationCollectionFactory;
use Adyen\Payment\Model\ResourceModel\Order\Payment as OrderPaymentResourceModel;
use Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory as OrderPaymentCollectionFactory;
use Adyen\Payment\Model\Ui\AdyenCcConfigProvider;
use Adyen\Webhook\PaymentStates;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Notification\NotifierInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\Webapi\Exception;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Framework\App\Area;
use Magento\Framework\App\AreaList;
use Magento\Framework\Phrase\Renderer\Placeholder;
use Magento\Framework\Phrase;
use Magento\Sales\Model\Order\Payment\Transaction\Builder;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\ResourceModel\Order\Invoice as InvoiceResourceModel;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory as OrderStatusCollectionFactory;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Model\PaymentTokenManagement;
use DateInterval;
use DateTime;
use DateTimeZone;

class WebhookProcessor
{
    /**
     * Logging instance
     *
     * @var AdyenLogger
     */
    private $adyenLogger;

    /**
     * @var NotificationCollectionFactory
     */
    private $notificationFactory;

    /**
     * @var Webhook
     */
    private $webhookHelper;


    /**
     * Cron constructor.
     *
     * @param AdyenLogger $adyenLogger
     * @param NotificationCollectionFactory $notificationFactory
     * @param Webhook $webhookHelper
     */
    public function __construct(
        AdyenLogger $adyenLogger,
        NotificationCollectionFactory $notificationFactory,
        Webhook $webhookHelper
    ) {
        $this->adyenLogger = $adyenLogger;
        $this->notificationFactory = $notificationFactory;
        $this->webhookHelper = $webhookHelper;
    }

    /**
     * Run the webhook processor
     *
     * @return void
     * @throws \Exception
     */
    public function execute()
    {
        try {
            $this->doProcessWebhook();
        } catch (\Exception $e) {
            $this->adyenLogger->addAdyenNotificationCronjob($e->getMessage() . "\n" . $e->getTraceAsString());
            throw $e;
        }
    }

    public function doProcessWebhook()
    {
        // Fetch notifications collection
        $notifications = $this->notificationFactory->create();
        $notifications->notificationsToProcessFilter();

        // Loop through and process notifications.
        $count = 0;
        /** @var Notification[] $notifications */
        foreach ($notifications as $notification) {
            // ignore duplicate notification
            if ($notification->isDuplicate(
                    $notification->getPspreference(),
                    $notification->getEventCode(),
                    $notification->getSuccess(),
                    $notification->getOriginalReference(),
                    true
                )
            ) {
                $this->adyenLogger
                    ->addAdyenNotificationCronjob("This is a duplicate notification and will be ignored");
                continue;
            }

            // Skip notifications that should be delayed
            if ($this->webhookHelper->shouldSkipProcessingNotification($notification)) {
                continue;
            }

            if($this->webhookHelper->processNotification($notification)) {
                $count++;
            }
        }

        if ($count > 0) {
            $this->adyenLogger->addAdyenNotificationCronjob(sprintf("Cronjob updated %s notification(s)", $count));
        }
    }

}
