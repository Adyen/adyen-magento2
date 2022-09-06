<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper\Webhook;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Api\PaymentRequest;
use Adyen\Payment\Model\Billing\AgreementFactory;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\ResourceModel\Billing\Agreement;
use Adyen\Payment\Model\ResourceModel\Billing\Agreement\CollectionFactory as AgreementCollectionFactory;
use Adyen\Payment\Model\Ui\AdyenCcConfigProvider;
use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Sales\Model\Order as MagentoOrder;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Model\PaymentTokenManagement;


class RecurringContractWebhookHandler implements WebhookHandlerInterface
{
    /** @var Vault */
    private $vaultHelper;

    /** @var AdyenLogger */
    private $adyenLogger;

    /** @var PaymentRequest */
    private $paymentRequest;

    /** @var AgreementCollectionFactory */
    private $billingAgreementCollectionFactory;

    /** @var AgreementFactory */
    private $billingAgreementFactory;

    /** @var Agreement */
    private $billingAgreementResourceModel;

    /** @var Config */
    private $configHelper;

    /** @var PaymentTokenManagement */
    private $paymentTokenManagement;

    /** @var PaymentTokenFactoryInterface */
    private $paymentTokenFactory;

    /** @var EncryptorInterface */
    private $encryptor;

    /** @var PaymentTokenRepositoryInterface */
    private $paymentTokenRepository;

    public function __construct(
        Vault $vaultHelper,
        AdyenLogger $adyenLogger,
        PaymentRequest $paymentRequest,
        AgreementCollectionFactory $billingAgreementCollectionFactory,
        AgreementFactory $billingAgreementFactory,
        Agreement $billingAgreementResourceModel,
        Config $configHelper,
        PaymentTokenManagement $paymentTokenManagement,
        PaymentTokenFactoryInterface $paymentTokenFactory,
        EncryptorInterface $encryptor,
        PaymentTokenRepositoryInterface $paymentTokenRepository
    ) {
        $this->vaultHelper = $vaultHelper;
        $this->adyenLogger = $adyenLogger;
        $this->paymentRequest = $paymentRequest;
        $this->billingAgreementCollectionFactory = $billingAgreementCollectionFactory;
        $this->billingAgreementFactory = $billingAgreementFactory;
        $this->billingAgreementResourceModel = $billingAgreementResourceModel;
        $this->configHelper = $configHelper;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->paymentTokenFactory = $paymentTokenFactory;
        $this->encryptor = $encryptor;
        $this->paymentTokenRepository = $paymentTokenRepository;
    }

    public function handleWebhook(MagentoOrder $order, Notification $notification, string $transitionState): MagentoOrder
    {
        $paymentMethodCode = $order->getPayment()->getMethod();
        // only store billing agreements if Vault is disabled
        if ($paymentMethodCode === PaymentMethods::ADYEN_CC && !$this->vaultHelper->isCardVaultEnabled()) {
            $order = $this->handleNonVaultCardContract($order, $notification);
        } elseif ($paymentMethodCode === PaymentMethods::ADYEN_HPP && $this->configHelper->isStoreAlternativePaymentMethodEnabled()) {
            $order = $this->handlePaymentMethodContract($order, $notification);
        } else {
            $this->adyenLogger->addAdyenNotification(
                'Ignore recurring_contract notification because Vault feature is enabled'
            );
        }

        return $order;
    }

    /**
     * Handle RECURRING_CONTRACT webhooks that are related to card payments but NOT using vault
     *
     * @param MagentoOrder $order
     * @param Notification $notification
     * @return MagentoOrder
     */
    private function handleNonVaultCardContract(MagentoOrder $order, Notification $notification): MagentoOrder
    {
        // storedReferenceCode
        $recurringDetailReference = $notification->getPspreference();

        $storeId = $order->getStoreId();
        $customerReference = $order->getCustomerId();
        $this->adyenLogger->addAdyenNotification(
            __(
                'CustomerReference is: %1 and storeId is %2 and RecurringDetailsReference is %3',
                $customerReference,
                $storeId,
                $recurringDetailReference
            )
        );
        try {
            $listRecurringContracts = $this->paymentRequest->getRecurringContractsForShopper(
                $customerReference,
                $storeId
            );
            $contractDetail = null;
            // get current Contract details and get list of all current ones
            $recurringReferencesList = [];

            if (!$listRecurringContracts) {
                throw new Exception("Empty list recurring contracts");
            }
            // Find the reference on the list
            foreach ($listRecurringContracts as $rc) {
                $recurringReferencesList[] = $rc['recurringDetailReference'];
                if (isset($rc['recurringDetailReference']) &&
                    $rc['recurringDetailReference'] == $recurringDetailReference
                ) {
                    $contractDetail = $rc;
                }
            }

            if ($contractDetail == null) {
                $this->adyenLogger->addAdyenNotification(json_encode($listRecurringContracts));
                $message = __(
                    'Failed to create billing agreement for this order ' .
                    '(listRecurringCall did not contain contract)'
                );
                throw new Exception($message);
            }

            $billingAgreements = $this->billingAgreementCollectionFactory->create();
            $billingAgreements->addFieldToFilter('customer_id', $customerReference);

            // Get collection and update existing agreements

            foreach ($billingAgreements as $updateBillingAgreement) {
                if (!in_array($updateBillingAgreement->getReferenceId(), $recurringReferencesList)) {
                    $updateBillingAgreement->setStatus(
                        \Adyen\Payment\Model\Billing\Agreement::STATUS_CANCELED
                    );
                } else {
                    $updateBillingAgreement->setStatus(
                        \Adyen\Payment\Model\Billing\Agreement::STATUS_ACTIVE
                    );
                }
                $updateBillingAgreement->save();
            }

            // Get or create billing agreement
            $billingAgreement = $this->billingAgreementFactory->create();
            $billingAgreement->load($recurringDetailReference, 'reference_id');
            // check if BA exists
            if (!($billingAgreement && $billingAgreement->getAgreementId() > 0
                && $billingAgreement->isValid())) {
                // create new
                $this->adyenLogger->addAdyenNotification("Creating new Billing Agreement");
                $order->getPayment()->setBillingAgreementData(
                    [
                        'billing_agreement_id' => $recurringDetailReference,
                        'method_code' => $order->getPayment()->getMethodCode(),
                    ]
                );

                $billingAgreement = $this->billingAgreementFactory->create();
                $billingAgreement->setStoreId($order->getStoreId());
                $billingAgreement->importOrderPaymentWithRecurringDetailReference($order->getPayment(), $recurringDetailReference);
                $message = __('Created billing agreement #%1.', $recurringDetailReference);
            } else {
                $this->adyenLogger->addAdyenNotification(
                    "Using existing Billing Agreement"
                );
                $billingAgreement->setIsObjectChanged(true);
                $message = __('Updated billing agreement #%1.', $recurringDetailReference);
            }

            // Populate billing agreement data
            $billingAgreement->parseRecurringContractData($contractDetail);
            if ($billingAgreement->isValid()) {
                if (!$this->billingAgreementResourceModel->getOrderRelation(
                    $billingAgreement->getAgreementId(),
                    $order->getId()
                )) {
                    // save into sales_billing_agreement_order
                    $billingAgreement->addOrderRelation($order);

                    // add to order to save agreement
                    $order->addRelatedObject($billingAgreement);
                }
            } else {
                $message = __('Failed to create billing agreement for this order.');
                throw new Exception($message);
            }
        } catch (Exception $exception) {
            $message = $exception->getMessage();
        }

        $this->adyenLogger->addAdyenNotification($message);
        $comment = $order->addStatusHistoryComment($message, $order->getStatus());
        $order->addRelatedObject($comment);

        return $order;
    }

    /**
     * Handle RECURRING_CONTRACT webhooks that are related to payment methods (non-card)
     *
     * @param MagentoOrder $order
     * @param Notification $notification
     * @return MagentoOrder
     */
    private function handlePaymentMethodContract(MagentoOrder $order, Notification $notification): MagentoOrder
    {
        try {
            //get the payment
            $payment = $order->getPayment();
            $customerId = $order->getCustomerId();

            $this->adyenLogger->addAdyenNotification(
                '$paymentMethodCode ' . $notification->getPaymentMethod()
            );
            if (!empty($notification->getPspreference())) {
                // Check if $paymentTokenAlternativePaymentMethod exists already
                $paymentTokenAlternativePaymentMethod = $this->paymentTokenManagement->getByGatewayToken(
                    $notification->getPspreference(),
                    $payment->getMethodInstance()->getCode(),
                    $payment->getOrder()->getCustomerId()
                );


                // In case the payment token for this payment method does not exist, create it based on the additionalData
                if ($paymentTokenAlternativePaymentMethod === null) {
                    $this->adyenLogger->addAdyenNotification('Creating new gateway token');
                    $paymentTokenAlternativePaymentMethod = $this->paymentTokenFactory->create(
                        PaymentTokenFactoryInterface::TOKEN_TYPE_ACCOUNT
                    );

                    $details = [
                        'type' => $notification->getPaymentMethod(),
                        'maskedCC' => $payment->getAdditionalInformation()['ibanNumber'],
                        'expirationDate' => 'N/A'
                    ];

                    $paymentTokenAlternativePaymentMethod->setCustomerId($customerId)
                        ->setGatewayToken($notification->getPspreference())
                        ->setPaymentMethodCode(AdyenCcConfigProvider::CODE)
                        ->setPublicHash($this->encryptor->getHash($customerId . $notification->getPspreference()))
                        ->setTokenDetails(json_encode($details));
                } else {
                    $this->adyenLogger->addAdyenNotification('Gateway token already exists');
                }

                //SEPA tokens don't expire. The expiration date is set 10 years from now
                $expDate = new DateTime('now', new DateTimeZone('UTC'));
                $expDate->add(new DateInterval('P10Y'));
                $paymentTokenAlternativePaymentMethod->setExpiresAt($expDate->format('Y-m-d H:i:s'));

                $this->paymentTokenRepository->save($paymentTokenAlternativePaymentMethod);
                $this->adyenLogger->addAdyenNotification('New gateway token saved');
            }
        } catch (Exception $exception) {
            $message = $exception->getMessage();
            $this->adyenLogger->addAdyenNotification(
                "An error occurred while saving the payment method " . $message
            );
        }

        return $order;
    }
}
