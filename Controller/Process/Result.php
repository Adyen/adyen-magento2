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

use \Adyen\Payment\Model\Notification;

class Result extends \Magento\Framework\App\Action\Action
{
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
     * Result constructor.
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Sales\Model\Order\Status\HistoryFactory $orderHistoryFactory
     * @param \Magento\Checkout\Model\Session $session
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
	 * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Sales\Model\Order\Status\HistoryFactory $orderHistoryFactory,
        \Magento\Checkout\Model\Session $session,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
		\Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->_adyenHelper = $adyenHelper;
        $this->_orderFactory = $orderFactory;
        $this->_orderHistoryFactory = $orderHistoryFactory;
        $this->_session = $session;
        $this->_adyenLogger = $adyenLogger;
        $this->storeManager = $storeManager;
        parent::__construct($context);
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        $response = $this->getRequest()->getParams();
        $this->_adyenLogger->addAdyenResult(print_r($response, true));

        $failReturnPath = $this->_adyenHelper->getAdyenAbstractConfigData('return_path');

        if ($response) {
            $result = $this->validateResponse($response);

            if ($result) {
                $session = $this->_session;
                $session->getQuote()->setIsActive(false)->save();
                $this->_redirect('checkout/onepage/success', ['_query' => ['utm_nooverride' => '1']]);
            } else {
                $this->_cancel($response);
                $this->_redirect($failReturnPath);
            }
        } else {
            // redirect to checkout page
            $this->_redirect($failReturnPath);
        }
    }

    /**
     * @param $response
     */
    protected function _cancel($response)
    {
        $session = $this->_session;

        // restore the quote
        $session->restoreQuote();

        $order = $this->_order;

        $this->_adyenHelper->cancelOrder($order);

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

        if (empty($response)) {
            $this->_adyenLogger->addAdyenResult(
                'Response is empty, please check your webserver that the result url accepts parameters'
            );

            throw new \Magento\Framework\Exception\LocalizedException(
                __('Response is empty, please check your webserver that the result url accepts parameters')
            );
        }

        // If the merchant signature is present, authenticate the result url
        if (!empty($response['merchantSig'])) {
			// authenticate result url
			$authStatus = $this->_authenticate($response);
			if (!$authStatus) {
				throw new \Magento\Framework\Exception\LocalizedException(__('ResultUrl authentification failure'));
			}
		// Otherwise validate the pazload and get back the response that can be used to finish the order
		} else {
			// send the payload verification payment\details request to validate the response
			$response = $this->validatePayloadAndReturnResponse($response);
		}

        $incrementId = null;

        if (!empty($response['merchantReference'])) {
            $incrementId = $response['merchantReference'];
        }

        $order = $this->_getOrder($incrementId);
        if ($order->getId()) {
            $this->_eventManager->dispatch('adyen_payment_process_resulturl_before', [
                'order' => $order,
                'adyen_response' => $response
            ]);
            if (isset($response['handled'])) {
                return $response['handled_response'];
            }

            // update the order
            $result = $this->_validateUpdateOrder($order, $response);

            $this->_eventManager->dispatch('adyen_payment_process_resulturl_after', [
                'order' => $order,
                'adyen_response' => $response
            ]);
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Order does not exists with increment_id: %1', $incrementId)
            );
        }

        return $result;
    }

    /**
     * @param $order
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
                                 <br />The order can be automatically cancelled based on the OFFER_CLOSED notification. Please contact Adyen Support to enable this.";
                }
                $this->_adyenLogger->addAdyenResult('Do nothing wait for the notification');
                break;
            case Notification::CANCELLED:
                $this->_adyenLogger->addAdyenResult('Cancel or Hold the order');
                $result = false;
                break;
            case Notification::REFUSED:
                // if refused there will be a AUTHORIZATION : FALSE notification send only exception is idea
                $this->_adyenLogger->addAdyenResult('Cancel or Hold the order');
                $result = false;
                break;
            case Notification::ERROR:
                //attempt to hold/cancel
                $this->_adyenLogger->addAdyenResult('Cancel or Hold the order');
                $result = false;
                break;
            default:
                $this->_adyenLogger->addAdyenResult('This event is not supported: ' . $authResult);
                $result = false;
                break;
        }

        $history = $this->_orderHistoryFactory->create()
            //->setStatus($status)
            ->setComment($comment)
            ->setEntityName('order')
            ->setOrder($order);

        $history->save();

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
            if (!is_null($incrementId)) {
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
    protected function validatePayloadAndReturnResponse($response)
	{
		$client = $this->_adyenHelper->initializeAdyenClient($this->storeManager->getStore()->getId());
		$service = $this->_adyenHelper->createAdyenCheckoutService($client);

        $request = [];

        if (!empty($this->_session->getLastRealOrder()) &&
            !empty($this->_session->getLastRealOrder()->getPayment()) &&
            !empty($this->_session->getLastRealOrder()->getPayment()->getAdditionalInformation("paymentData"))
        ) {
            $request['paymentData'] = $this->_session->getLastRealOrder()->getPayment()->getAdditionalInformation("paymentData");
        }

		$request["details"] = $response;

		try {
			$response = $service->paymentsDetails($request);
		} catch(\Adyen\AdyenException $e) {
			$response['error'] =  $e->getMessage();
		}

		return $response;
	}
}
