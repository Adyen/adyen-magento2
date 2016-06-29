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

namespace Adyen\Payment\Model\Method;

/**
 * Adyen CreditCard payment method
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Cc extends \Magento\Payment\Model\Method\Cc
{

    const METHOD_CODE               = 'adyen_cc';

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canAuthorize = true;

    /**
     * @var bool
     */
    protected $_canCapture = true;

    /**
     * @var bool
     */
    protected $_canCapturePartial = true;

    /**
     * @var bool
     */
    protected $_canCaptureOnce = true;

    /**
     * @var bool
     */
    protected $_canRefund = true;

    /**
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;

    /**
     * @var bool
     */
    protected $_isGateway = true;

    /**
     * @var string
     */
    protected $_code = self::METHOD_CODE;

    /**
     * @var string
     */
    protected $_formBlockType = 'Adyen\Payment\Block\Form\Cc';

    /**
     * @var string
     */
    protected $_infoBlockType = 'Adyen\Payment\Block\Info\Cc';

    /**
     * @var \Adyen\Payment\Model\Api\PaymentRequest
     */
    protected $_paymentRequest;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_urlBuilder;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * Request object
     *
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $_request;

    /**
     * Cc constructor.
     *
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Adyen\Payment\Model\Api\PaymentRequest $paymentRequest
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Magento\Framework\Module\ModuleListInterface $moduleList
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
        \Adyen\Payment\Model\Api\PaymentRequest $paymentRequest,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $moduleList,
            $localeDate,
            $resource,
            $resourceCollection,
            $data
        );
        $this->_paymentRequest = $paymentRequest;
        $this->_urlBuilder = $urlBuilder;
        $this->_adyenHelper = $adyenHelper;
        $this->_request = $request;
    }

    /**
     * @var string
     */
    protected $_paymentMethodType = 'api';

    /**
     * @return string
     */
    public function getPaymentMethodType()
    {
        return $this->_paymentMethodType;
    }

    /**
     * Assign data to info model instance
     *
     * @param \Magento\Framework\DataObject|mixed $data
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function assignData(\Magento\Framework\DataObject $data)
    {
        parent::assignData($data);

        if (!$data instanceof \Magento\Framework\DataObject) {
            $data = new \Magento\Framework\DataObject($data);
        }

        $additionalData = $data->getAdditionalData();
        $infoInstance = $this->getInfoInstance();

        if (isset($additionalData['cc_type'])) {
            $infoInstance->setCcType($additionalData['cc_type']);
        }
        if ($this->_adyenHelper->getAdyenCcConfigDataFlag('cse_enabled')) {
            if (isset($additionalData['encrypted_data'])) {
                $infoInstance->setAdditionalInformation('encrypted_data', $additionalData['encrypted_data']);
            } else {
                throw new \Magento\Framework\Exception\LocalizedException(__('Card encryption failed'));
            }
        }
        
        // set number of installements
        if (isset($additionalData['number_of_installments'])) {
            $infoInstance->setAdditionalInformation('number_of_installments', $additionalData['number_of_installments']);
        }

        // save value remember details checkbox
        if (isset($additionalData['store_cc'])) {
            $infoInstance->setAdditionalInformation('store_cc', $additionalData['store_cc']);
        }

        return $this;
    }

    /**
     * Validate payment method information object
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)authorize
     */
    public function validate()
    {
        // validation only possible on front-end for CSE script
        return $this;
    }

    /**
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if (!$this->canAuthorize()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The authorize action is not available.'));
        }

        /*
         * do not send order confirmation mail after order creation wait for
         * Adyen AUTHORIISATION notification
         */
        $order = $payment->getOrder();
        $order->setCanSendNewEmailFlag(false);

        // do not let magento set status to processing
        $payment->setLastTransId($this->getTransactionId())->setIsTransactionPending(true);

        // DO authorisation
        $this->_processRequest($payment, $amount, "authorise");

        return $this;
    }

    /**
     * @param \Magento\Sales\Model\Order\Payment $payment
     * @param $amount
     * @param $request
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _processRequest(\Magento\Sales\Model\Order\Payment $payment, $amount, $request)
    {
        switch ($request) {
            case "authorise":
                try {
                    $response = $this->_paymentRequest->fullApiRequest($payment, $this->_code);
                } catch (\Adyen\AdyenException $e) {
                    $errorMsg = __('Error with payment method please select different payment method.');
                    $this->_logger->critical($e->getMessage());
                    throw new \Magento\Framework\Exception\LocalizedException(__($errorMsg));
                }
                break;
        }

        if (!empty($response)) {
            $this->_processResponse($payment, $response);
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(__('Empty result.'));
        }
    }

    /**
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param $response
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _processResponse(\Magento\Payment\Model\InfoInterface $payment, $response)
    {
        $payment->setAdditionalInformation('3dActive', false);

        switch ($response['resultCode']) {
            case "Authorised":
                $this->_addStatusHistory($payment, $response['resultCode'], $response['pspReference']);
                $payment->setAdditionalInformation('pspReference', $response['pspReference']);
                break;
            case "RedirectShopper":
                // 3d is active so set the param to true checked in Controller/Validate3d
                $payment->setAdditionalInformation('3dActive', true);
                $issuerUrl = $response['issuerUrl'];
                $paReq = $response['paRequest'];
                $md = $response['md'];

                if (!empty($paReq) && !empty($md) && !empty($issuerUrl)) {
                    $payment->setAdditionalInformation('issuerUrl', $response['issuerUrl']);
                    $payment->setAdditionalInformation('paRequest', $response['paRequest']);
                    $payment->setAdditionalInformation('md', $response['md']);
                } else {
                    throw new \Magento\Framework\Exception\LocalizedException(__('3D secure is not valid'));
                }
                break;
            case "Refused":
                // refusalReason
                if ($response['refusalReason']) {

                    $refusalReason = $response['refusalReason'];
                    switch($refusalReason) {
                        case "Transaction Not Permitted":
                            $errorMsg = __('The transaction is not permitted.');
                            break;
                        case "CVC Declined":
                            $errorMsg = __('Declined due to the Card Security Code(CVC) being incorrect. Please check your CVC code!');
                            break;
                        case "Restricted Card":
                            $errorMsg = __('The card is restricted.');
                            break;
                        case "803 PaymentDetail not found":
                            $errorMsg = __('The payment is REFUSED because the saved card is removed. Please try an other payment method.');
                            break;
                        case "Expiry month not set":
                            $errorMsg = __('The expiry month is not set. Please check your expiry month!');
                            break;
                        default:
                            $errorMsg = __('The payment is REFUSED.');
                            break;
                    }
                } else {
                    $errorMsg = __('The payment is REFUSED.');
                }

                if ($errorMsg) {
                    $this->_logger->critical($errorMsg);
                    throw new \Magento\Framework\Exception\LocalizedException(__($errorMsg));
                }
                break;
            default:
                $errorMsg = __('Error with payment method please select different payment method.');
                $this->_logger->critical($errorMsg);
                throw new \Magento\Framework\Exception\LocalizedException(__($errorMsg));
                break;
        }
    }

    /**
     * @param $payment
     * @param $responseCode
     * @param $pspReference
     * @return $this
     */
    protected function _addStatusHistory($payment, $responseCode, $pspReference)
    {

        $type = 'Adyen Result URL response:';
        $comment = __('%1 <br /> authResult: %2 <br /> pspReference: %3 <br /> paymentMethod: %4',
            $type, $responseCode, $pspReference, "");
        $payment->getOrder()->setAdyenResulturlEventCode($responseCode);
        $payment->getOrder()->addStatusHistoryComment($comment);
        return $this;
    }

    /**
     * Called by validate3d controller when cc payment has 3D secure
     *
     * @param $payment
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function authorise3d($payment)
    {
        $response = $this->_paymentRequest->authorise3d($payment);
        $responseCode = $response['resultCode'];
        return $responseCode;
    }

    /**
     * Checkout redirect URL getter for onepage checkout (hardcode)
     *
     * @see \Magento\Checkout\Controller\Onepage::savePaymentAction()
     * @see \Magento\Quote\Model\Quote\Payment::getCheckoutRedirectUrl()
     * @return string
     */
    public function getCheckoutRedirectUrl()
    {
        return $this->_urlBuilder->getUrl('adyen/process/validate3d/', ['_secure' => $this->_getRequest()->isSecure()]);
    }

    /**
     * Capture on Adyen
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        parent::capture($payment, $amount);
        $this->_paymentRequest->capture($payment, $amount);
        return $this;
    }
    
    /**
     * Refund specified amount for payment
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        parent::refund($payment, $amount);

        $order = $payment->getOrder();
        /*
         * if amount is a full refund send a refund/cancelled request so
         * if it is not captured yet it will cancel the order
         */
        $grandTotal = $order->getGrandTotal();

        if ($grandTotal == $amount) {
            $this->_paymentRequest->cancelOrRefund($payment);
        } else {
            $this->_paymentRequest->refund($payment, $amount);
        }

        return $this;
    }

    /**
     * Retrieve request object
     *
     * @return \Magento\Framework\App\RequestInterface
     */
    protected function _getRequest()
    {
        return $this->_request;
    }

}