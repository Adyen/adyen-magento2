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

namespace Adyen\Payment\Controller\Return;

use Adyen\AdyenException;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Idempotency;
use Adyen\Payment\Helper\Quote;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Service\Validator\DataArrayValidator;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Status\HistoryFactory;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use Magento\Store\Model\StoreManagerInterface;

class Index extends Action
{
    const BRAND_CODE_DOTPAY = 'dotpay';
    const RESULT_CODE_RECEIVED = 'Received';
    const DETAILS_ALLOWED_PARAM_KEYS = [
        'MD',
        'PaReq',
        'PaRes',
        'billingToken',
        'cupsecureplus.smscode',
        'facilitatorAccessToken',
        'oneTimePasscode',
        'orderID',
        'payerID',
        'payload',
        'paymentID',
        'paymentStatus',
        'redirectResult',
        'threeDSResult',
        'threeds2.challengeResult',
        'threeds2.fingerprint'
    ];

    protected OrderFactory $orderFactory;
    protected Config $configHelper;
    protected Order $order;
    protected HistoryFactory $orderHistoryFactory;
    protected Session $session;
    protected AdyenLogger $adyenLogger;
    protected StoreManagerInterface $storeManager;
    private Quote $quoteHelper;
    private Order\Payment $payment;
    private Vault $vaultHelper;
    private OrderResource $orderResourceModel;
    private StateData $stateDataHelper;
    private Data $adyenDataHelper;
    private OrderRepositoryInterface $orderRepository;
    private Idempotency $idempotencyHelper;

    public function __construct(
        Context                  $context,
        OrderFactory             $orderFactory,
        HistoryFactory           $orderHistoryFactory,
        Session                  $session,
        AdyenLogger              $adyenLogger,
        StoreManagerInterface    $storeManager,
        Quote                    $quoteHelper,
        Vault                    $vaultHelper,
        OrderResource            $orderResourceModel,
        StateData                $stateDataHelper,
        Data                     $adyenDataHelper,
        OrderRepositoryInterface $orderRepository,
        Idempotency              $idempotencyHelper,
        Config                   $configHelper
    ) {
        parent::__construct($context);

        $this->adyenDataHelper = $adyenDataHelper;
        $this->orderFactory = $orderFactory;
        $this->orderHistoryFactory = $orderHistoryFactory;
        $this->session = $session;
        $this->adyenLogger = $adyenLogger;
        $this->storeManager = $storeManager;
        $this->quoteHelper = $quoteHelper;
        $this->vaultHelper = $vaultHelper;
        $this->orderResourceModel = $orderResourceModel;
        $this->stateDataHelper = $stateDataHelper;
        $this->orderRepository = $orderRepository;
        $this->configHelper = $configHelper;
        $this->idempotencyHelper = $idempotencyHelper;
    }

    public function execute(): void
    {
        $result = false;

        // Receive all params as this could be a GET or POST request
        $response = $this->getRequest()->getParams();

        if ($response) {
            $result = $this->validateResponse($response);
            $order = $this->order;
            $additionalInformation = $order->getPayment()->getAdditionalInformation();
            $resultCode = isset($response['resultCode']) ? $response['resultCode'] : null;
            $paymentBrandCode = $additionalInformation['brand_code'] ?? null;
            if ($resultCode === 'cancelled' && $paymentBrandCode === 'svs') {
                $this->adyenDataHelper->cancelOrder($order);
            }

            // Adjust the success path, fail path, and restore quote based on if it is a multishipping quote
            if (
                !empty($response['merchantReference']) &&
                $this->quoteHelper->getIsQuoteMultiShippingWithMerchantReference($response['merchantReference'])
            ) {
                $successPath = $failPath = 'multishipping/checkout/success';
                $setQuoteAsActive = true;
            } else {
                $successPath = $this->configHelper->getAdyenAbstractConfigData('custom_success_redirect_path') ??
                    'checkout/onepage/success';
                $failPath = $this->configHelper->getAdyenAbstractConfigData('return_path');
                $setQuoteAsActive = false;
            }
        } else {
            $this->_redirect($this->configHelper->getAdyenAbstractConfigData('return_path'));
        }

        if ($result) {
            $session = $this->session;
            $session->getQuote()->setIsActive($setQuoteAsActive)->save();
            $paymentAction = $this->order->getPayment()->getAdditionalInformation('action');
            $brandCode = $this->order->getPayment()->getAdditionalInformation('brand_code');
            $resultCode = $this->order->getPayment()->getAdditionalInformation('resultCode');


            // Prevent action component to redirect page again after returning to the shop
            if (($brandCode == self::BRAND_CODE_DOTPAY && $resultCode == self::RESULT_CODE_RECEIVED) ||
                (isset($paymentAction) && $paymentAction['type'] === 'redirect')
            ) {
                $this->payment->unsAdditionalInformation('action');
                $this->order->save();
            }

            // Add OrderIncrementId to redirect parameters for headless support.
            $redirectParams = $this->configHelper->getAdyenAbstractConfigData('custom_success_redirect_path')
                ? ['_query' => ['utm_nooverride' => '1', 'order_increment_id' => $this->order->getIncrementId()]]
                : ['_query' => ['utm_nooverride' => '1']];
            $this->_redirect($successPath, $redirectParams);
        } else {
            $this->adyenLogger->addAdyenResult(
                sprintf(
                    'Payment for order %s was unsuccessful, ' .
                    'it will be cancelled when the OFFER_CLOSED notification has been processed.',
                    $this->order->getIncrementId()
                )
            );
            $this->replaceCart($response);
            $this->_redirect($failPath, ['_query' => ['utm_nooverride' => '1']]);
        }
    }

    protected function replaceCart(array $response): void
    {
        $this->session->restoreQuote();

        if (isset($response['authResult']) && $response['authResult'] == \Adyen\Payment\Model\Notification::CANCELLED) {
            $this->messageManager->addError(__('You have cancelled the order. Please try again'));
        } else {
            $this->messageManager->addError(__('Your payment failed, Please try again later'));
        }
    }

    protected function validateResponse(array $response): bool
    {
        $this->adyenLogger->addAdyenResult('Processing ResultUrl');

        // send the payload verification payment\details request to validate the response
        $response = $this->validatePayloadAndReturnResponse($response);

        $order = $this->order;

        $this->_eventManager->dispatch(
            'adyen_payment_process_resulturl_before',
            [
                'order' => $order,
                'adyen_response' => $response
            ]
        );

        // Save PSP reference from the response
        if (!empty($response['pspReference'])) {
            $this->payment->setAdditionalInformation('pspReference', $response['pspReference']);
        }

        // Handle recurring details
        $this->vaultHelper->handlePaymentResponseRecurringDetails($this->payment, $response);

        // Save donation token if available in the response
        if (!empty($response['donationToken'])) {
            $this->payment->setAdditionalInformation('donationToken', $response['donationToken']);
        }

        // update the order
        $result = $this->validateUpdateOrder($order, $response);

        $this->_eventManager->dispatch(
            'adyen_payment_process_resulturl_after',
            [
                'order' => $order,
                'adyen_response' => $response
            ]
        );

        return $result;
    }

    protected function validateUpdateOrder(Order $order, array $response): bool
    {
        $result = false;

        if (!empty($response['authResult'])) {
            $authResult = $response['authResult'];
        } elseif (!empty($response['resultCode'])) {
            $authResult = $response['resultCode'];
        } else {
            // In case the result is unknown we log the request and don't update the history
            $this->adyenLogger->error("Unexpected result query parameter. Response: " . json_encode($response));

            return $result;
        }

        $this->adyenLogger->addAdyenResult('Updating the order');

        if (isset($response['paymentMethod']['brand'])) {
            $paymentMethod = $response['paymentMethod']['brand'];
        }
        elseif (isset($response['paymentMethod']['type'])) {
            $paymentMethod = $response['paymentMethod']['type'];
        }
        else {
            $paymentMethod = '';
        }

        $pspReference = isset($response['pspReference']) ? trim((string) $response['pspReference']) : '';

        $type = 'Adyen Result URL response:';
        $comment = __(
            '%1 <br /> authResult: %2 <br /> pspReference: %3 <br /> paymentMethod: %4',
            $type,
            $authResult,
            $pspReference,
            $paymentMethod
        );

        // needed because then we need to save $order objects
        $order->setAdyenResulturlEventCode($authResult);

        // Update the payment additional information with the new result code
        $orderPayment = $order->getPayment();
        $orderPayment->setAdditionalInformation('resultCode', $authResult);
        $this->orderResourceModel->save($order);

        switch (strtoupper((string) $authResult)) {
            case Notification::AUTHORISED:
                $result = true;
                $this->adyenLogger->addAdyenResult('Do nothing wait for the notification');
                break;
            case Notification::RECEIVED:
                $result = true;
                if (strpos((string) $paymentMethod, "alipay_hk") !== false) {
                    $result = false;
                }
                $this->adyenLogger->addAdyenResult('Do nothing wait for the notification');
                break;
            case Notification::PENDING:
                // do nothing wait for the notification
                $result = true;
                if (strpos((string) $paymentMethod, "bankTransfer") !== false) {
                    $comment .= "<br /><br />Waiting for the customer to transfer the money.";
                } elseif ($paymentMethod == "sepadirectdebit") {
                    $comment .= "<br /><br />This request will be send to the bank at the end of the day.";
                } else {
                    $comment .= "<br /><br />The payment result is not confirmed (yet).
                                 <br />Once the payment is authorised, the order status will be updated accordingly.
                                 <br />If the order is stuck on this status, the payment can be seen as unsuccessful.
                                 <br />The order can be automatically cancelled based on the OFFER_CLOSED notification.
                                 Please contact Adyen Support to enable this.";
                }
                $this->adyenLogger->addAdyenResult('Do nothing wait for the notification');
                break;
            case Notification::CANCELLED:
            case Notification::ERROR:
                $this->adyenLogger->addAdyenResult('Cancel or Hold the order on OFFER_CLOSED notification');
                $result = false;
                break;
            case Notification::REFUSED:
                // if refused there will be a AUTHORIZATION : FALSE notification send only exception is idea
                $this->adyenLogger->addAdyenResult(
                    'Cancel or Hold the order on AUTHORISATION
                success = false notification'
                );
                $result = false;

                if (!$order->canCancel()) {
                    $order->setState(Order::STATE_NEW);
                    $this->orderRepository->save($order);
                }
                $this->adyenDataHelper->cancelOrder($order);

                break;
            default:
                $this->adyenLogger->addAdyenResult('This event is not supported: ' . $authResult);
                $result = false;
                break;
        }

        $history = $this->orderHistoryFactory->create()
            ->setStatus($order->getStatus())
            ->setComment($comment)
            ->setEntityName('order')
            ->setOrder($order);

        $history->save();

        // Cleanup state data
        try {
            $this->stateDataHelper->cleanQuoteStateData($order->getQuoteId(), $authResult);
        } catch (\Exception $exception) {
            $this->adyenLogger->error(__('Error cleaning the payment state data: %s', $exception->getMessage()));
        }


        return $result;
    }

    protected function getOrder(string $incrementId = null): Order
    {
        if (!isset($this->order)) {
            if ($incrementId !== null) {
                //TODO Replace with order repository search for best practice
                $this->order = $this->orderFactory->create()->loadByIncrementId($incrementId);
            } else {
                $this->order = $this->session->getLastRealOrder();
            }
        }

        return $this->order;
    }

    protected function validatePayloadAndReturnResponse(array $result): array
    {
        $client = $this->adyenDataHelper->initializeAdyenClient($this->storeManager->getStore()->getId());
        $service = $this->adyenDataHelper->createAdyenCheckoutService($client);

        $order = $this->getOrder(
            !empty($result['merchantReference']) ? $result['merchantReference'] : null
        );

        if (!$order->getId()) {
            throw new LocalizedException(
                __('Order cannot be loaded')
            );
        }

        $this->payment = $order->getPayment();

        $request = [];

        // filter details to match the keys
        $details = $result;
        // TODO build a validator class which also validates the type of the param
        $details = DataArrayValidator::getArrayOnlyWithApprovedKeys($details, self::DETAILS_ALLOWED_PARAM_KEYS);

        // Remove helper params in case left in the request
        $helperParams = ['isAjax', 'merchantReference'];
        foreach ($helperParams as $helperParam) {
            if (array_key_exists($helperParam, $details)) {
                unset($details[$helperParam]);
            }
        }

        $request["details"] = $details;
        $requestOptions['idempotencyKey'] = $this->idempotencyHelper->generateIdempotencyKey($request);
        $requestOptions['headers'] = $this->adyenDataHelper->buildRequestHeaders();

        try {
            $response = $service->paymentsDetails($request, $requestOptions);
            $responseMerchantReference = !empty($response['merchantReference']) ? $response['merchantReference'] : null;
            $resultMerchantReference = !empty($result['merchantReference']) ? $result['merchantReference'] : null;
            $merchantReference = $responseMerchantReference ?: $resultMerchantReference;
            if ($merchantReference) {
                if ($order->getIncrementId() === $merchantReference) {
                    $this->order = $order;
                } else {
                    $this->adyenLogger->error("Wrong merchantReference was set in the query or in the session");
                    $response['error'] = 'merchantReference mismatch';
                }
            } else {
                $this->adyenLogger->error("No merchantReference in the response");
                $response['error'] = 'merchantReference is missing from the response';
            }
        } catch (AdyenException $e) {
            $response['error'] = $e->getMessage();
        }

        return $response;
    }
}
