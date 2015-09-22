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
//
//    /**
//     * @var string
//     */
//    protected $_infoBlockType = 'Adyen\Payment\Block\Info\Cc';

    protected $_paymentRequest;

    protected $_adyenLogger;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    protected $_urlBuilder;


    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Module\ModuleListInterface $moduleList
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate
     * @param \Magento\Framework\Model\Resource\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Adyen\Payment\Model\Api\PaymentRequest $paymentRequest,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Framework\Model\Resource\AbstractResource $resource = null,
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
    }

    protected $_paymentMethodType = 'api';
    public function getPaymentMethodType() {
        return $this->_paymentMethodType;
    }

    /**
     * Assign data to info model instance
     *
     * @param \Magento\Framework\Object|mixed $data
     * @return $this
     */
    public function assignData($data)
    {
        parent::assignData($data);
        $infoInstance = $this->getInfoInstance();
        $infoInstance->setAdditionalInformation('encrypted_data', $data['encrypted_data']);
        return $this;
    }

    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if (!$this->canAuthorize()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The authorize action is not available.'));
        }

        // do not let magento set status to processing
        $payment->setLastTransId($this->getTransactionId())->setIsTransactionPending(true);

        // DO authorisation
        $this->_processRequest($payment, $amount, "authorise");

        return $this;
    }

    /**
     * Get config payment action url
     * Used to universalize payment actions when processing payment place
     *
     * @return string
     * @api
     */
    public function getConfigPaymentAction()
    {
//        return $this->getConfigData('payment_action');
        return \Magento\Payment\Model\Method\AbstractMethod::ACTION_AUTHORIZE;
    }


    protected function _processRequest(\Magento\Framework\Object $payment, $amount, $request)
    {
        switch ($request) {
            case "authorise":
                $response = $this->_paymentRequest->fullApiRequest($payment);
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

        switch ($response['paymentResult_resultCode']) {
            case "Authorised":
                //$this->_addStatusHistory($payment, $responseCode, $pspReference, $this->_getConfigData('order_status'));
                $this->_addStatusHistory($payment, $response['paymentResult_resultCode'], $response['paymentResult_pspReference']);
                $payment->setAdditionalInformation('pspReference', $response['paymentResult_pspReference']);
                break;
            case "RedirectShopper":

                $payment->setAdditionalInformation('3dActive', true);
                $IssuerUrl = $response['paymentResult_issuerUrl'];
                $PaReq = $response['paymentResult_paRequest'];
                $MD = $response['paymentResult_md'];

                $payment->setAdditionalInformation('issuerUrl', $response['paymentResult_issuerUrl']);
                $payment->setAdditionalInformation('paRequest', $response['paymentResult_paRequest']);
                $payment->setAdditionalInformation('md', $response['paymentResult_md']);

                $result = $this->getResponse();

                break;
            case "Refused":
                // paymentResult_refusalReason
                if($response['paymentResult_refusalReason']) {

                    $refusalReason = $response['paymentResult_refusalReason'];
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
                    $errorMsg = Mage::helper('adyen')->__('The payment is REFUSED by Adyen.');
                }

                if ($errorMsg) {
                    $this->_logger->critical($errorMsg);
                    throw new \Magento\Framework\Exception\LocalizedException($errorMsg);
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
        $responseCode = $response['paymentResult_resultCode'];
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

}