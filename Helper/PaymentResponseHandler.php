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
 * Copyright (c) 2020 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\PaymentResponseFactory;
use Adyen\Payment\Model\ResourceModel\PaymentResponse\Collection;
use Adyen\Payment\Model\ResourceModel\PaymentResponse;
use Adyen\Payment\Model\ResourceModel\PaymentResponse\CollectionFactory as PaymentResponseCollectionFactory;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Adyen\Payment\Helper\Vault;


class PaymentResponseHandler
{
    const AUTHORISED = 'Authorised';
    const REFUSED = 'Refused';
    const REDIRECT_SHOPPER = 'RedirectShopper';
    const IDENTIFY_SHOPPER = 'IdentifyShopper';
    const CHALLENGE_SHOPPER = 'ChallengeShopper';
    const RECEIVED = 'Received';
    const PENDING = 'Pending';
    const PRESENT_TO_SHOPPER = 'PresentToShopper';
    const ERROR = 'Error';
    const CANCELLED = 'Cancelled';

    /**
     * @var AdyenLogger
     */
    private $adyenLogger;

    /**
     * @var Data
     */
    private $adyenHelper;

    /**
     * @var Vault
     */
    private $vaultHelper;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order
     */
    private $orderResourceModel;

    /**
     * @var PaymentResponseFactory
     */
    private $paymentResponseFactory;

    /**
     * @var \Adyen\Payment\Model\ResourceModel\PaymentResponse
     */
    private $paymentResponseResourceModel;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var Collection
     */
    private $paymentResponseCollection;

    /**
     * @var PaymentResponseCollectionFactory
     */
    private $paymentResponseCollectionFactory;


    /**
     * PaymentResponseHandler constructor.
     *
     * @param AdyenLogger $adyenLogger
     * @param Data $adyenHelper
     * @param \Adyen\Payment\Helper\Vault $vaultHelper
     */
    public function __construct(
        AdyenLogger $adyenLogger,
        Data $adyenHelper,
        Vault $vaultHelper,
        \Magento\Sales\Model\ResourceModel\Order $orderResourceModel,

        PaymentResponseFactory $paymentResponseFactory,
        PaymentResponse $paymentResponseResourceModel,
        Collection $paymentResponseCollection,
        PaymentResponseCollectionFactory $paymentResponseCollectionFactory,
        SerializerInterface $serializer
    ) {
        $this->adyenLogger = $adyenLogger;
        $this->adyenHelper = $adyenHelper;
        $this->vaultHelper = $vaultHelper;
        $this->paymentResponseFactory = $paymentResponseFactory;
        $this->orderResourceModel = $orderResourceModel;
        $this->paymentResponseResourceModel = $paymentResponseResourceModel;
        $this->paymentResponseCollection = $paymentResponseCollection;
        $this->paymentResponseCollectionFactory = $paymentResponseCollectionFactory;
        $this->serializer = $serializer;
    }

    public function formatPaymentResponse($resultCode, $action = null, $additionalData = null)
    {
        switch ($resultCode) {
            case self::AUTHORISED:
            case self::REFUSED:
            case self::ERROR:
                return [
                    "isFinal" => true,
                    "resultCode" => $resultCode,
                ];
            case self::REDIRECT_SHOPPER:
            case self::IDENTIFY_SHOPPER:
            case self::CHALLENGE_SHOPPER:
            case self::PENDING:
                return [
                    "isFinal" => false,
                    "resultCode" => $resultCode,
                    "action" => $action
                ];
            case self::PRESENT_TO_SHOPPER:
                return [
                    "isFinal" => true,
                    "resultCode" => $resultCode,
                    "action" => $action
                ];
            case self::RECEIVED:
                return [
                    "isFinal" => true,
                    "resultCode" => $resultCode,
                    "additionalData" => $additionalData
                ];
            default:
                return [
                    "isFinal" => true,
                    "resultCode" => self::ERROR,
                ];
        }
    }

    public function findOrCreatePaymentResponseEntry($incrementId, $storeId) {
        // Check if paymentResponse already exists, otherwise create one
        $paymentResponseDetails = $this->paymentResponseResourceModel->getPaymentResponseByIncrementAndStoreId($incrementId, $storeId);

        if(is_null($paymentResponseDetails)) {
            $paymentResponse = $this->paymentResponseFactory->create();
            $paymentResponse->setMerchantReference($incrementId);
            $paymentResponse->setStoreId($storeId);
        } else {
            $paymentResponseFactory = $this->paymentResponseFactory->create();
            $paymentResponse = $paymentResponseFactory->load($paymentResponseDetails['entity_id']);
        }

        return $paymentResponse;
    }

    /**
     * Updates the additional information in the adyen_payment_response table. Add if not yet exists, otherwise append
     *
     * @param $paymentResponse
     * @param $paymentResponseData
     * @return mixed
     */
    public function updateAdditionalInformation($paymentResponse, $paymentResponseData) {
        $additionalInfoFields = ['additionalData']; // Add other fields to store in adyen_payment_response additional_information. E.g. pspReference

        foreach($additionalInfoFields as $field) {
            if (!empty($paymentResponseData[$field])) {
                $paymentResponse->setAdditionalInformationByField($field, $paymentResponseData[$field]);
            }
        }

        return $paymentResponse;
    }


    /**
     * Persists the payment response in the sales_order_payment table
     *
     * @param $paymentResponseData
     * @param $payment
     * @return mixed
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function saveAdyenResponseData($paymentResponseData, $payment) {
        // Unique identifier for each payment
        $incrementId = $payment->getOrder()->getIncrementId();
        $storeId = $payment->getOrder()->getStoreId();

        $adyenPaymentResponse = $this->findOrCreatePaymentResponseEntry($incrementId, $storeId);
        $adyenPaymentResponse->setResponse(json_encode($paymentResponseData)); // TODO: What to do with this? These two are overwritten on paymentDetails call
        $adyenPaymentResponse->setResultCode($paymentResponseData['resultCode']);
        $adyenPaymentResponse = $this->updateAdditionalInformation($adyenPaymentResponse, $paymentResponseData);

        // Set payment id if available
        if ($payment->getEntityId() !== null) {
            $adyenPaymentResponse->setPaymentId($payment->getEntityId());
        }

        $this->paymentResponseResourceModel->save($adyenPaymentResponse);

        return $adyenPaymentResponse;
    }

    /**
     * @param $paymentResponseData
     * @param OrderPaymentInterface $payment
     * @param OrderInterface|null $order
     * @return bool
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function handlePaymentResponse($paymentResponseData, $payment, $order = null)
    {
        // TODO: Make sure all payment response calls use this handler!
        if (empty($paymentResponseData)) {
            $this->adyenLogger->error("Payment details call failed, paymentsResponse is empty");
            return false;
        }

        $this->saveAdyenResponseData($paymentResponseData, $payment);

        if (!empty($paymentResponseData['resultCode'])) {
            $payment->setAdditionalInformation('resultCode', $paymentResponseData['resultCode']);
        }

        if (!empty($paymentResponseData['action'])) {
            $payment->setAdditionalInformation('action', $paymentResponseData['action']);
        }

        if (!empty($paymentResponseData['additionalData'])) {
            $payment->setAdditionalInformation('additionalData', $paymentResponseData['additionalData']);
        }

        if (!empty($paymentResponseData['pspReference'])) {
            $payment->setAdditionalInformation('pspReference', $paymentResponseData['pspReference']);
        }

        if (!empty($paymentResponseData['paymentData'])) {
            $payment->setAdditionalInformation('adyenPaymentData', $paymentResponseData['paymentData']);
        }

        if (!empty($paymentResponseData['details'])) {
            $payment->setAdditionalInformation('details', $paymentResponseData['details']);
        }

        // TODO: Should this flow be entered on the first request? (Or is it already triggered somewhere else? -> Maybe only trigger if payment id is available)
        switch ($paymentResponseData['resultCode']) {
            case self::PRESENT_TO_SHOPPER:
            case self::PENDING:
            case self::RECEIVED:
            case self::IDENTIFY_SHOPPER:
            case self::CHALLENGE_SHOPPER:
                break;
            //We don't need to handle these resultCodes
            case self::REDIRECT_SHOPPER:
                $this->adyenLogger->addAdyenResult("Customer was redirected.");
                if ($order) {
                    $order->addStatusHistoryComment(
                        __(
                            'Customer was redirected to an external payment page. (In case of card payments the shopper is redirected to the bank for 3D-secure validation.) Once the shopper is authenticated,
                        the order status will be updated accordingly.
                        <br />Make sure that your notifications are being processed!
                        <br />If the order is stuck on this status, the shopper abandoned the session.
                        The payment can be seen as unsuccessful.
                        <br />The order can be automatically cancelled based on the OFFER_CLOSED notification.
                        Please contact Adyen Support to enable this.'
                        ),
                        $order->getStatus()
                    )->save();
                }
                break;
            case self::AUTHORISED:
                if (!empty($paymentResponseData['pspReference'])) {
                    // set pspReference as transactionId
                    $payment->setCcTransId($paymentResponseData['pspReference']);
                    $payment->setLastTransId($paymentResponseData['pspReference']);

                    // set transaction
                    $payment->setTransactionId($paymentResponseData['pspReference']);
                }

                if (!empty($paymentResponseData['additionalData']['recurring.recurringDetailReference']) &&
                    $payment->getMethodInstance()->getCode() !== \Adyen\Payment\Model\Ui\AdyenOneclickConfigProvider::CODE) {
                    if ($this->adyenHelper->isCreditCardVaultEnabled()) {
                        $this->vaultHelper->saveRecurringDetails($payment, $paymentResponseData['additionalData']);
                    } else {
                        $order = $payment->getOrder();
                        $this->adyenHelper->createAdyenBillingAgreement($order, $paymentResponseData['additionalData']);
                    }
                }

                if (!empty($paymentResponseData['donationToken'])) {
                    $payment->setAdditionalInformation('donationToken', $paymentResponseData['donationToken']);
                }

                $this->orderResourceModel->save($order);
                break;
            case self::REFUSED:
                // Cancel order in case result is refused
                if (null !== $order) {
                    // Set order to new so it can be cancelled
                    $order->setState(\Magento\Sales\Model\Order::STATE_NEW);
                    $order->save();

                    $order->setActionFlag(\Magento\Sales\Model\Order::ACTION_FLAG_CANCEL, true);

                    if ($order->canCancel()) {
                        $order->cancel();
                        $order->save();
                    } else {
                        $this->adyenLogger->addAdyenDebug('Order can not be canceled');
                    }
                }

                return false;
            case self::ERROR:
            default:
                $this->adyenLogger->error(
                    sprintf("Payment details call failed for action, resultCode is %s Raw API responds: %s",
                        $paymentResponseData['resultCode'],
                            json_encode($paymentResponseData)
                    ));

                return false;
        }
        return true;
    }
}
