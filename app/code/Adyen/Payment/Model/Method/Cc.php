<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Adyen\Payment\Model\Method;

/**
 * @method \Magento\Quote\Api\Data\PaymentMethodExtensionInterface getExtensionAttributes()
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
//
//    /**
//     * @var string
//     */
//    protected $_infoBlockType = 'Adyen\Payment\Block\Info\Cc';

    protected $_paymentRequest;

    protected $_adyenLogger;

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
    }

    protected $_paymentMethodType = 'api';
    public function getPaymentMethodType() {
        return $this->_paymentMethodType;
    }


    public function isActive($storeId = null)
    {
        return true;
    }

    public function isAvailable($quote = null)
    {
        $this->_logger->critical("CC IS AVAILABLE!! IS TRUE");
//        $this->_adyenLogger->critical("TESTTT");
        return true;
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


        $this->_logger->critical("Assign data!!:" . print_r($data, true));


//        throw new \Magento\Framework\Exception\LocalizedException(__('The authorize action is not available.' . print_r($data, true)));

        ////        print_r($data);die();
//        $this->_logger->critical("TEST in validate FUNTION !!:");


        $infoInstance->setAdditionalInformation('encrypted_data', $data['encrypted_data']);

        $this->_logger->critical("encrypted dat:" . $data['encrypted_data']);


        return $this;
    }

    public function validate()
    {
        $this->_logger->critical("TEST in validate FUNTION !!:");
        return true;
    }

    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->_logger->critical("TEST in authorize FUNTION !!:");

        if (!$this->canAuthorize()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The authorize action is not available.'));
        }

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
        $this->_logger->critical("TEST getConfigPaymentAction !!:");
        return \Magento\Payment\Model\Method\AbstractMethod::ACTION_AUTHORIZE;
    }


    protected function _processRequest(\Magento\Framework\Object $payment, $amount, $request)
    {


        switch ($request) {
            case "authorise":
                $response = $this->_paymentRequest->fullApiRequest($payment);
                break;
        }


        $this->_logger->critical("HIERRR result is " . print_r($response,true));

        if (!empty($response)) {



            $this->_logger->critical("NOT EMPTY ");


            $this->_processResponse($payment, $response);



//            print_r($response);die();
//            $this->_processResponse($payment, $response, $request);
        } else {
            $this->_logger->critical(" EMPTY response");

            throw new \Magento\Framework\Exception\LocalizedException(__('Empty result.'));
        }

    }

    protected function _processResponse(\Magento\Payment\Model\InfoInterface $payment, $response)
    {


        switch ($response['paymentResult_resultCode']) {
            case "Authorised":
                //$this->_addStatusHistory($payment, $responseCode, $pspReference, $this->_getConfigData('order_status'));
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


        if($response['paymentResult_resultCode'] == 'Refused') {

        }


//        print_R($response);die();

    }

    // does not seem to work.
    public function hasVerification() {
        return true;
    }

}