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
    }


    public function isActive($storeId = null)
    {
        return true;
    }

    public function isAvailable($quote = null)
    {
        $this->_logger->critical("CC IS AVAILABLE!! IS TRUE");
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
        $this->_logger->critical("Assign data!!:" . print_r($data, true));
        return parent::assignData($data);
////        print_r($data);die();
//        $this->_logger->critical("TEST in validate FUNTION !!:");
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


    protected function _processRequest(\Magento\Framework\Object $payment, $amount, $request) {

        $merchantAccount = $this->getConfigData('merchant_account');
        $recurringType = $this->getConfigData('recurring_type');
        $enableMoto = $this->getConfigData('enable_moto');


        switch ($request) {
            case "authorise":
                $response = $this->_paymentRequest->fullApiRequest($merchantAccount, $payment);
                break;
        }


        $this->_logger->critical("HIERRR result is " . print_r($response,true));

        if (!empty($response)) {
            $this->_logger->critical("NOT EMPTY ");



//            print_r($response);die();
//            $this->_processResponse($payment, $response, $request);
        } else {
            $this->_logger->critical(" EMPTY response");

            throw new \Magento\Framework\Exception\LocalizedException(__('Empty result.'));
        }

    }

    // does not seem to work.
    public function hasVerification() {
        return true;
    }

}