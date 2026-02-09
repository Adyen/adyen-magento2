<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\Payment\Helper\Order as AdyenOrderHelper;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Method\TxVariantInterpreterFactory;
use Adyen\Payment\Observer\AdyenCcDataAssignObserver;
use Exception;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\Order as OrderModel;

class PaymentResponseHandler
{
    const AUTHORISED = 'Authorised';
    const REFUSED = 'Refused';
    const GIFTCARD_REFUSED = 'GiftcardRefused';
    const REDIRECT_SHOPPER = 'RedirectShopper';
    const IDENTIFY_SHOPPER = 'IdentifyShopper';
    const CHALLENGE_SHOPPER = 'ChallengeShopper';
    const RECEIVED = 'Received';
    const PENDING = 'Pending';
    const PRESENT_TO_SHOPPER = 'PresentToShopper';
    const ERROR = 'Error';
    const CANCELLED = 'Cancelled';
    const ADYEN_TOKENIZATION = 'Adyen Tokenization';
    const VAULT = 'Magento Vault';
    const POS_SUCCESS = 'Success';

    const PAYMENTS_DETAILS_API_COMMENT_ACTION_DESCRIPTION = 'Submit details for Adyen payment';
    const PAYMENTS_DETAILS_API_COMMENT_ENDPOINT = '/payments/details';

    const ACTION_REQUIRED_STATUSES = [
        self::REDIRECT_SHOPPER,
        self::IDENTIFY_SHOPPER,
        self::CHALLENGE_SHOPPER,
        self::PENDING
    ];

    /**
     * @param AdyenLogger $adyenLogger
     * @param Vault $vaultHelper
     * @param Quote $quoteHelper
     * @param AdyenOrderHelper $orderHelper
     * @param OrderRepository $orderRepository
     * @param StateData $stateDataHelper
     * @param PaymentMethods $paymentMethodsHelper
     * @param OrderStatusHistory $orderStatusHistoryHelper
     * @param OrdersApi $ordersApiHelper
     * @param TxVariantInterpreterFactory $txVariantInterpreterFactory
     */
    public function __construct(
        private readonly AdyenLogger $adyenLogger,
        private readonly Vault $vaultHelper,
        private readonly Quote $quoteHelper,
        private readonly AdyenOrderHelper $orderHelper,
        private readonly OrderRepository $orderRepository,
        private readonly StateData $stateDataHelper,
        private readonly PaymentMethods $paymentMethodsHelper,
        private readonly OrderStatusHistory $orderStatusHistoryHelper,
        private readonly OrdersApi $ordersApiHelper,
        private readonly TxVariantInterpreterFactory $txVariantInterpreterFactory
    ) { }

    public function formatPaymentResponse(
        string $resultCode,
        ?array $action = null
    ): array {
        switch ($resultCode) {
            case self::AUTHORISED:
            case self::REFUSED:
            case self::ERROR:
            case self::POS_SUCCESS:
                return [
                    "isFinal" => true,
                    "resultCode" => $resultCode
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
            case self::RECEIVED:
                return [
                    "isFinal" => true,
                    "resultCode" => $resultCode,
                    "action" => $action
                ];
            default:
                return [
                    "isFinal" => true,
                    "resultCode" => self::ERROR,
                ];
        }
    }

    /**
     * @param array $paymentsDetailsResponse
     * @param OrderInterface $order
     * @return bool
     * @throws AlreadyExistsException
     * @throws InputException
     * @throws NoSuchEntityException|LocalizedException
     */
    public function handlePaymentsDetailsResponse(
        array $paymentsDetailsResponse,
        OrderInterface $order
    ): bool {
        if (empty($paymentsDetailsResponse)) {
            $this->adyenLogger->error("Payment details call failed, paymentsResponse is empty");
            return false;
        }

        if(!$this->isValidMerchantReference($paymentsDetailsResponse, $order)){
            return false;
        }

        $this->adyenLogger->addAdyenResult('Updating the order');
        $payment = $order->getPayment();

        //Check magento Payment Method
        $paymentMethodInstance = $payment->getMethodInstance();
        $isWalletPaymentMethod = $this->paymentMethodsHelper->isWalletPaymentMethod($paymentMethodInstance);
        $isCardPaymentMethod = $payment->getMethod() === PaymentMethods::ADYEN_CC ||
            $payment->getMethod() === PaymentMethods::ADYEN_CC_VAULT;

        $authResult = $paymentsDetailsResponse['authResult'] ?? $paymentsDetailsResponse['resultCode'] ?? null;
        if (is_null($authResult)) {
            // In case the result is unknown we log the request and don't update the history
            $this->adyenLogger->error(
                "Unexpected result query parameter. Response: " . json_encode($paymentsDetailsResponse)
            );

            return false;
        }

        $resultCode = $paymentsDetailsResponse['resultCode'];
        if (!empty($resultCode)) {
            $payment->setAdditionalInformation('resultCode', $resultCode);
        }

        if (!empty($paymentsDetailsResponse['action'])) {
            $payment->setAdditionalInformation('action', $paymentsDetailsResponse['action']);
        }

        if (!empty($paymentsDetailsResponse['additionalData'])) {
            $payment->setAdditionalInformation('additionalData', $paymentsDetailsResponse['additionalData']);
        }

        if (!empty($paymentsDetailsResponse['pspReference'])) {
            $payment->setAdditionalInformation('pspReference', $paymentsDetailsResponse['pspReference']);
        }

        if (!empty($paymentsDetailsResponse['details'])) {
            $payment->setAdditionalInformation('details', $paymentsDetailsResponse['details']);
        }

        if (!empty($paymentsDetailsResponse['donationToken'])) {
            $payment->setAdditionalInformation('donationToken', $paymentsDetailsResponse['donationToken']);
        }

        // `ccType` is set on card or wallet payments only.
        if (!empty($paymentsDetailsResponse['paymentMethod'])) {
            if ($this->paymentMethodsHelper->isWalletPaymentMethod($paymentMethodInstance)) {
                // Extract the scheme card brand from the wallet payment response
                $txVariant = $this->txVariantInterpreterFactory->create([
                    'txVariant' => $paymentsDetailsResponse['paymentMethod']['brand']
                ]);

                $ccType = $txVariant->getCard();
            } elseif (in_array($payment->getMethod(), [PaymentMethods::ADYEN_CC, PaymentMethods::ADYEN_CC_VAULT])) {
                // `brand` always refers to the scheme card brand, use it as is
                $ccType = $paymentsDetailsResponse['paymentMethod']['brand'];
            }

            if (isset($ccType)) {
                $payment->setAdditionalInformation(AdyenCcDataAssignObserver::CC_TYPE, $ccType);
                $payment->setCcType($ccType);
            } else {
                // Cleanup ccType if not set, this might be inherited from the previous payment attempt
                $payment->unsAdditionalInformation(AdyenCcDataAssignObserver::CC_TYPE);
                $payment->setCcType(null);
            }
        }

        // Handle recurring details
        $this->vaultHelper->handlePaymentResponseRecurringDetails($payment, $paymentsDetailsResponse);

        // If the response is valid, update the order status.
        if (!in_array($resultCode, PaymentResponseHandler::ACTION_REQUIRED_STATUSES) && $order->getState() === OrderModel::STATE_PENDING_PAYMENT) {
            /*
             * Change order state from pending_payment to new and expect authorisation webhook
             * if no additional action is required according to /paymentsDetails response.
             * Otherwise keep the order state as pending_payment.
             */
            $order = $this->orderHelper->setStatusOrderCreation($order);
        }

        // Add order status history comment for /payments/details API response
        $comment = $this->orderStatusHistoryHelper->buildApiResponseComment(
            $paymentsDetailsResponse,
            self::PAYMENTS_DETAILS_API_COMMENT_ACTION_DESCRIPTION,
            self::PAYMENTS_DETAILS_API_COMMENT_ENDPOINT
        );
        $order->addCommentToStatusHistory($comment, $order->getStatus());

        // Cleanup state data if exists.
        try {
            $this->stateDataHelper->cleanQuoteStateData($order->getQuoteId(), $authResult);
        } catch (Exception $exception) {
            $this->adyenLogger->error(__('Error cleaning the payment state data: %s', $exception->getMessage()));
        }

        $paymentMethod = $paymentsDetailsResponse['paymentMethod']['brand'] ??
            $paymentsDetailsResponse['paymentMethod']['type'] ??
            '';

        switch ($resultCode) {
            case self::AUTHORISED:
                if (!empty($paymentsDetailsResponse['pspReference'])) {
                    // set pspReference as transactionId
                    $payment->setCcTransId($paymentsDetailsResponse['pspReference']);
                    $payment->setLastTransId($paymentsDetailsResponse['pspReference']);

                    // set transaction
                    $payment->setTransactionId($paymentsDetailsResponse['pspReference']);
                }

                try {
                    $this->quoteHelper->disableQuote($order->getQuoteId());
                } catch (Exception $e) {
                    $this->adyenLogger->error('Failed to disable quote: ' . $e->getMessage(), [
                        'quoteId' => $order->getQuoteId()
                    ]);
                }

                $result = true;
                break;
            case self::PENDING:
                /* Change order state from pending_payment to new and expect authorisation webhook
                 * if no additional action is required according to /paymentDetails response. */
                $order = $this->orderHelper->setStatusOrderCreation($order);
                $this->orderRepository->save($order);

                // do nothing wait for the notification
                if (strpos((string) $paymentMethod, "bankTransfer") !== false) {
                    $pendingComment = "Waiting for the customer to transfer the money.";
                } elseif ($paymentMethod == "sepadirectdebit") {
                    $pendingComment = "This request will be send to the bank at the end of the day.";
                } else {
                    $pendingComment = "The payment result is not confirmed (yet).
                                 <br />Once the payment is authorised, the order status will be updated accordingly.
                                 <br />If the order is stuck on this status, the payment can be seen as unsuccessful.
                                 <br />The order can be automatically cancelled based on the OFFER_CLOSED notification.
                                 Please contact Adyen Support to enable this.";
                }

                $order->addCommentToStatusHistory($pendingComment, $order->getStatus());
                $this->adyenLogger->addAdyenResult('Do nothing wait for the notification');

                $result = true;
                break;
            case self::PRESENT_TO_SHOPPER:
            case self::IDENTIFY_SHOPPER:
            case self::CHALLENGE_SHOPPER:
            case self::REDIRECT_SHOPPER:
                $this->adyenLogger->addAdyenResult("Additional action is required for the payment.");
                $result = true;
                break;
            case self::RECEIVED:
                $result = true;
                if (str_contains((string)$paymentMethod, "alipay_hk")) {
                    $result = false;
                }
                $this->adyenLogger->addAdyenResult('Do nothing wait for the notification');
                break;
            case self::REFUSED:
            case self::CANCELLED:
                // Cancel Checkout API Order in case of partial payments
                $checkoutApiOrder = $payment->getAdditionalInformation(OrdersApi::DATA_KEY_CHECKOUT_API_ORDER);
                if (isset($checkoutApiOrder)) {
                    $this->ordersApiHelper->cancelOrder(
                        $order,
                        $checkoutApiOrder['pspReference'],
                        $checkoutApiOrder['orderData']
                    );
                }

                // Cancel order in case result is refused
                if (null !== $order) {
                    // Check if the current state allows for changing to new for cancellation
                    if ($order->canCancel()) {
                        // Proceed to set cancellation action flag and cancel the order
                        $order->setActionFlag(\Magento\Sales\Model\Order::ACTION_FLAG_CANCEL, true);
                        $this->orderHelper->cancelOrder($order, $resultCode);
                    } else {
                        $this->adyenLogger->addAdyenResult('The order cannot be cancelled');
                    }
                }
                $result = false;
                break;
            default:
                $this->adyenLogger->error(
                    sprintf("Payment details call failed for action, resultCode is %s Raw API responds: %s.
                    Cancel or Hold the order on OFFER_CLOSED notification.",
                        $resultCode,
                        json_encode($paymentsDetailsResponse)
                    ));

                $result = false;
                break;
        }

        // needed because then we need to save $order objects
        $order->setAdyenResulturlEventCode($authResult);
        $this->orderRepository->save($order);

        return $result;
    }

    /**
     * Validate whether the merchant reference is present in the response and belongs to the current order.
     *
     * @param array $paymentsDetailsResponse
     * @param OrderInterface $order
     * @return bool
     */
    private function isValidMerchantReference(array $paymentsDetailsResponse, OrderInterface $order): bool
    {
        $merchantReference = $paymentsDetailsResponse['merchantReference'] ?? null;
        if (!$merchantReference) {
            $this->adyenLogger->error("No merchantReference in the response");
            return false;
        }

        if ($order->getIncrementId() !== $merchantReference) {
            $this->adyenLogger->error("Wrong merchantReference was set in the query or in the session");
            return false;
        }

        return true;
    }
}
