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

namespace Adyen\Payment\Controller\Process;

use Adyen\Payment\Helper\StateData;
use \Adyen\Payment\Model\Notification;
use Adyen\Service\Validator\DataArrayValidator;
use Magento\Framework\App\Request\Http as Http;
use Magento\Sales\Model\Order;

class Result extends \Magento\Framework\App\Action\Action
{

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

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $_order;

    /**
     * @var \Magento\Sales\Model\Order\Status\HistoryFactory
     */
    protected $_orderHistoryFactory;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_session;

    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    protected $_adyenLogger;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Adyen\Payment\Helper\Quote
     */
    private $quoteHelper;

    private $payment;

    /**
     * @var \Adyen\Payment\Helper\Vault
     */
    private $vaultHelper;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order
     */
    private $orderResourceModel;

    /**
     * @var StateData
     */
    private $stateDataHelper;

    /**
     * Result constructor.
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Sales\Model\Order\Status\HistoryFactory $orderHistoryFactory
     * @param \Magento\Checkout\Model\Session $session
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Adyen\Payment\Helper\Quote $quoteHelper
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Sales\Model\Order\Status\HistoryFactory $orderHistoryFactory,
        \Magento\Checkout\Model\Session $session,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Adyen\Payment\Helper\Quote $quoteHelper,
        \Adyen\Payment\Helper\Vault $vaultHelper,
        \Magento\Sales\Model\ResourceModel\Order $orderResourceModel,
        StateData $stateDataHelper
    ) {
        $this->_adyenHelper = $adyenHelper;
        $this->_orderFactory = $orderFactory;
        $this->_orderHistoryFactory = $orderHistoryFactory;
        $this->_session = $session;
        $this->_adyenLogger = $adyenLogger;
        $this->storeManager = $storeManager;
        $this->quoteHelper = $quoteHelper;
        $this->vaultHelper = $vaultHelper;
        $this->orderResourceModel = $orderResourceModel;
        $this->stateDataHelper = $stateDataHelper;
        parent::__construct($context);
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        // Receive all params as this could be a GET or POST request
        $response = $this->getRequest()->getParams();
        $this->_adyenLogger->addAdyenResult(json_encode($response));

        if ($response) {
            $result = $this->validateResponse($response);

            // Adjust the success path, fail path, and restore quote based on if it is a multishipping quote
            if (
                !empty($response['merchantReference']) &&
                $this->quoteHelper->getIsQuoteMultiShippingWithMerchantReference($response['merchantReference'])
            ) {
                $successPath = $failPath = 'multishipping/checkout/success';
                $setQuoteAsActive = true;
            } else {
                $successPath = 'checkout/onepage/success';
                $failPath = $this->_adyenHelper->getAdyenAbstractConfigData('return_path');
                $setQuoteAsActive = false;
            }
        } else {
            $this->_redirect($this->_adyenHelper->getAdyenAbstractConfigData('return_path'));
        }

        if ($result) {
            $session = $this->_session;
            $session->getQuote()->setIsActive($setQuoteAsActive)->save();
            $this->_redirect($successPath, ['_query' => ['utm_nooverride' => '1']]);
        } else {
            $this->_adyenLogger->addAdyenResult(
                sprintf(
                    'Payment for order %s was unsuccessful, ' .
                    'it will be cancelled when the OFFER_CLOSED notification has been processed.',
                    $this->_order->getIncrementId()
                )
            );
            $this->replaceCart($response);
            $this->_redirect($failPath, ['_query' => ['utm_nooverride' => '1']]);
        }
    }

    /**
     * @param $response
     */
    protected function replaceCart($response)
    {
        if ($this->_adyenHelper->getConfigData(
            "clone_quote",
            "adyen_abstract",
            $this->_order->getStoreId(),
            true
        )) {
            try {
                $newQuote = $this->quoteHelper->cloneQuote($this->_session->getQuote(), $this->_order);
                $this->_session->replaceQuote($newQuote);
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                $this->_session->restoreQuote();
                $this->_adyenLogger->addAdyenResult(
                    'Error when trying to create a new quote, ' .
                    'the previous quote has been restored instead: ' . $e->getMessage()
                );
            }
        } else {
            $this->_session->restoreQuote();
        }

        if (isset($response['authResult']) && $response['authResult'] == \Adyen\Payment\Model\Notification::CANCELLED) {
            $this->messageManager->addError(__('You have cancelled the order. Please try again'));
        } else {
            $this->messageManager->addError(__('Your payment failed, Please try again later'));
        }
    }

    /**
     * @param $response
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function validateResponse($response)
    {
        $result = true;

        $this->_adyenLogger->addAdyenResult('Processing ResultUrl');

        // send the payload verification payment\details request to validate the response
        $response = $this->validatePayloadAndReturnResponse($response);

        $order = $this->_order;

        $this->_eventManager->dispatch(
            'adyen_payment_process_resulturl_before',
            [
                'order' => $order,
                'adyen_response' => $response
            ]
        );

        // Save payment token if available in the response
        if (!empty($response['additionalData']['recurring.recurringDetailReference']) &&
            $this->payment->getMethodInstance()->getCode() !== \Adyen\Payment\Model\Ui\AdyenOneclickConfigProvider::CODE) {
            if ($this->_adyenHelper->isCreditCardVaultEnabled()) {
                $this->vaultHelper->saveRecurringDetails($this->payment, $response['additionalData']);
            } else {
                $order = $this->payment->getOrder();
                $this->_adyenHelper->createAdyenBillingAgreement($order, $response['additionalData']);
            }
            $this->orderResourceModel->save($order);
        }

        // update the order
        $result = $this->_validateUpdateOrder($order, $response);

        $this->_eventManager->dispatch(
            'adyen_payment_process_resulturl_after',
            [
                'order' => $order,
                'adyen_response' => $response
            ]
        );

        return $result;
    }

    /**
     * @param Order $order
     * @param $response
     * @return bool
     */
    protected function _validateUpdateOrder($order, $response)
    {
        $result = false;

        if (!empty($response['authResult'])) {
            $authResult = $response['authResult'];
        } elseif (!empty($response['resultCode'])) {
            $authResult = $response['resultCode'];
        } else {
            // In case the result is unknown we log the request and don't update the history
            $this->_adyenLogger->addError("Unexpected result query parameter. Response: " . json_encode($response));

            return $result;
        }

        $this->_adyenLogger->addAdyenResult('Updating the order');

        $paymentMethod = isset($response['paymentMethod']) ? trim($response['paymentMethod']) : '';
        $pspReference = isset($response['pspReference']) ? trim($response['pspReference']) : '';

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

        switch (strtoupper($authResult)) {
            case Notification::AUTHORISED:
                $result = true;
                $this->_adyenLogger->addAdyenResult('Do nothing wait for the notification');
                break;
            case Notification::RECEIVED:
                $result = true;
                if (strpos($paymentMethod, "alipay_hk") !== false) {
                    $result = false;
                }
                $this->_adyenLogger->addAdyenResult('Do nothing wait for the notification');
                break;
            case Notification::PENDING:
                // do nothing wait for the notification
                $result = true;
                if (strpos($paymentMethod, "bankTransfer") !== false) {
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
                $this->_adyenLogger->addAdyenResult('Do nothing wait for the notification');
                break;
            case Notification::CANCELLED:
            case Notification::ERROR:
                $this->_adyenLogger->addAdyenResult('Cancel or Hold the order on OFFER_CLOSED notification');
                $result = false;
                break;
            case Notification::REFUSED:
                // if refused there will be a AUTHORIZATION : FALSE notification send only exception is idea
                $this->_adyenLogger->addAdyenResult(
                    'Cancel or Hold the order on AUTHORISATION
                success = false notification'
                );
                $result = false;
                break;
            default:
                $this->_adyenLogger->addAdyenResult('This event is not supported: ' . $authResult);
                $result = false;
                break;
        }

        $history = $this->_orderHistoryFactory->create()
            ->setStatus($order->getStatus())
            ->setComment($comment)
            ->setEntityName('order')
            ->setOrder($order);

        $history->save();

        // Cleanup state data
        try {
            $this->stateDataHelper->cleanQuoteStateData($order->getQuoteId(), $authResult);
        } catch (\Exception $exception) {
            $this->_adyenLogger->addError(__('Error cleaning the payment state data: %s', $exception->getMessage()));
        }


        return $result;
    }

    /**
     * Authenticate using sha256 Merchant signature
     *
     * @param $response
     * @return bool
     */
    protected function _authenticate($response)
    {
        $merchantSigNotification = $response['merchantSig'];

        // do it like this because $_GET is converting dot to underscore
        $queryString = $_SERVER['QUERY_STRING'];
        $result = [];
        $pairs = explode("&", $queryString);

        foreach ($pairs as $pair) {
            $nv = explode("=", $pair);
            $name = urldecode($nv[0]);
            $value = urldecode($nv[1]);
            $result[$name] = $value;
        }

        // do not include the merchantSig in the merchantSig calculation
        unset($result['merchantSig']);

        // Sign request using secret key
        $hmacKey = $this->_adyenHelper->getHmac();
        $merchantSig = \Adyen\Util\Util::calculateSha256Signature($hmacKey, $result);

        if (strcmp($merchantSig, $merchantSigNotification) === 0) {
            return true;
        }

        return false;
    }

    /**
     * The character escape function is called from the array_map function in _signRequestParams
     *
     * @param $val
     * @return mixed
     */
    protected function escapeString($val)
    {
        return str_replace(':', '\\:', str_replace('\\', '\\\\', $val));
    }

    /**
     * Get order based on increment_id
     *
     * @param $incrementId
     * @return \Magento\Sales\Model\Order
     */
    protected function _getOrder($incrementId = null)
    {
        if (!$this->_order) {
            if ($incrementId !== null) {
                //TODO Replace with order repository search for best practice
                $this->_order = $this->_orderFactory->create()->loadByIncrementId($incrementId);
            } else {
                $this->_order = $this->_session->getLastRealOrder();
            }
        }

        return $this->_order;
    }

    /**
     * Validates the payload from checkout /payments hpp and returns the api response
     *
     * @param $response
     * @return mixed
     * @throws \Adyen\AdyenException
     */
    protected function validatePayloadAndReturnResponse($result)
    {
        $client = $this->_adyenHelper->initializeAdyenClient($this->storeManager->getStore()->getId());
        $service = $this->_adyenHelper->createAdyenCheckoutService($client);

        $order = $this->_getOrder(
            !empty($result['merchantReference']) ? $result['merchantReference'] : null
        );

        if (!$order->getId()) {
            throw new \Magento\Framework\Exception\LocalizedException(
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

        if (!empty($this->payment)) {
            // for pending payment that redirect we store this under adyenPaymentData
            // TODO: refactor the code in the plugin that all paymentData is stored in paymentData and not in adyenPaymentData
            if (!empty($this->payment->getAdditionalInformation('adyenPaymentData'))) {
                $request['paymentData'] = $this->payment->getAdditionalInformation("adyenPaymentData");

                // remove paymentData from db
                $this->payment->unsAdditionalInformation('adyenPaymentData');
                $this->payment->save();
            }
        } else {
            $this->_adyenLogger->addError("Payment object cannot be loaded from order");
        }

        try {
            $response = $service->paymentsDetails($request);
            $responseMerchantReference = !empty($response['merchantReference']) ? $response['merchantReference'] : null;
            $resultMerchantReference = !empty($result['merchantReference']) ? $result['merchantReference'] : null;
            $merchantReference = $responseMerchantReference ?: $resultMerchantReference;
            if ($merchantReference) {
                if ($order->getIncrementId() === $merchantReference) {
                    $this->_order = $order;
                } else {
                    $this->_adyenLogger->addError("Wrong merchantReference was set in the query or in the session");
                    $response['error'] = 'merchantReference mismatch';
                }
            } else {
                $this->_adyenLogger->addError("No merchantReference in the response");
                $response['error'] = 'merchantReference is missing from the response';
            }
        } catch (\Adyen\AdyenException $e) {
            $response['error'] = $e->getMessage();
        }

        return $response;
    }
}
