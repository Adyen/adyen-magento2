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
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canCaptureOnce = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;

    /**
     * @var string
     */
    protected $_code = self::METHOD_CODE;

    /**
     * @var string
     */
    protected $_formBlockType = 'Adyen\Payment\Block\Form\Cc';
    protected $_infoBlockType = 'Adyen\Payment\Block\Info\Cc';

    /**
     * @var \Adyen\Payment\Model\Api\PaymentRequest
     */
    protected $_paymentRequest;

    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    protected $_adyenLogger;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_urlBuilder;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * @param \Adyen\Payment\Model\Api\PaymentRequest $paymentRequest
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     * @param \Magento\Checkout\Model\Session $checkoutSession
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
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Adyen\Payment\Model\Api\PaymentRequest $paymentRequest,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Magento\Checkout\Model\Session $checkoutSession,
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
        $this->_adyenLogger = $adyenLogger;
        $this->_checkoutSession = $checkoutSession;
        $this->_urlBuilder = $urlBuilder;
        $this->_adyenHelper = $adyenHelper;
    }

    protected $_paymentMethodType = 'api';
    public function getPaymentMethodType() {
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
        $infoInstance = $this->getInfoInstance();

        $infoInstance->setCcType($data['cc_type']);

        if($this->_adyenHelper->getAdyenCcConfigDataFlag('cse_enabled')) {
            if(isset($data['encrypted_data'])) {
                $infoInstance->setAdditionalInformation('encrypted_data', $data['encrypted_data']);
            } else {
                throw new \Magento\Framework\Exception\LocalizedException(__('Card encryption failed'));
            }
        }

        // save value remember details checkbox
        $infoInstance->setAdditionalInformation('store_cc', $data['store_cc']);

        return $this;
    }

    /**
     * Validate payment method information object
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function validate()
    {
        // validation only possible on front-end for CSE script
        return $this;
    }

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

    protected function _processRequest(\Magento\Sales\Model\Order\Payment $payment, $amount, $request)
    {
        switch ($request) {
            case "authorise":
                $response = $this->_paymentRequest->fullApiRequest($payment, $this->_code);
                break;
        }

        if (!empty($response)) {
            $this->_processResponse($payment, $response);
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(__('Empty result.'));
        }
    }

    protected function _processResponse(\Magento\Payment\Model\InfoInterface $payment, $response)
    {
        $payment->setAdditionalInformation('3dActive', false);

        switch ($response['resultCode']) {
            case "Authorised":
                //$this->_addStatusHistory($payment, $responseCode, $pspReference, $this->_getConfigData('order_status'));
                $this->_addStatusHistory($payment, $response['resultCode'], $response['pspReference']);
                $payment->setAdditionalInformation('pspReference', $response['pspReference']);
                break;
            case "RedirectShopper":
                // 3d is active so set the param to true checked in Controller/Validate3d
                $payment->setAdditionalInformation('3dActive', true);
                $issuerUrl = $response['issuerUrl'];
                $PaReq = $response['paRequest'];
                $md = $response['md'];

                if(!empty($PaReq) && !empty($md) && !empty($issuerUrl)) {
                    $payment->setAdditionalInformation('issuerUrl', $response['issuerUrl']);
                    $payment->setAdditionalInformation('paRequest', $response['paRequest']);
                    $payment->setAdditionalInformation('md', $response['md']);
                } else {
                    throw new \Magento\Framework\Exception\LocalizedException(__('3D secure is not valid'));
                }
                break;
            case "Refused":
                // refusalReason
                if($response['refusalReason']) {

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
                            $errorMsg = __('The payment is REFUSED by Adyen.');
                            break;
                    }
                } else {
                    $errorMsg = __('The payment is REFUSED by Adyen.');
                }

                if ($errorMsg) {
                    $this->_logger->critical($errorMsg);
                    throw new \Magento\Framework\Exception\LocalizedException(__($errorMsg));
                }
                break;
        }
    }

    protected function _addStatusHistory($payment, $responseCode, $pspReference)
    {

        $type = 'Adyen Result URL response:';
        $comment = __('%1 <br /> authResult: %2 <br /> pspReference: %3 <br /> paymentMethod: %4', $type, $responseCode, $pspReference, "");
        $payment->getOrder()->setAdyenResulturlEventCode($responseCode);
        $payment->getOrder()->addStatusHistoryComment($comment);
        return $this;
    }

    /*
     * Called by validate3d controller when cc payment has 3D secure
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
        return $this->_urlBuilder->getUrl('adyen/process/validate3d/');
    }

    /**
     * Capture on Adyen
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
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
     * @param \Magento\Framework\DataObject|InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        parent::refund($payment, $amount);

        $order = $payment->getOrder();
        // if amount is a full refund send a refund/cancelled request so if it is not captured yet it will cancel the order
        $grandTotal = $order->getGrandTotal();

        if($grandTotal == $amount) {
            $this->_paymentRequest->cancelOrRefund($payment);
        } else {
            $this->_paymentRequest->refund($payment, $amount);
        }

        return $this;
    }

}