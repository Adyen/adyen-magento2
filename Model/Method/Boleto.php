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
 * Class Sepa
 * @package Adyen\Payment\Model\Method
 */
class Boleto extends \Magento\Payment\Model\Method\AbstractMethod
{

    const METHOD_CODE = 'adyen_boleto';

    /**
     * @var string
     */
    protected $_code = self::METHOD_CODE;

    /**
     * @var string
     */
    protected $_formBlockType = 'Adyen\Payment\Block\Form\Boleto';

    /**
     * @var string
     */
    protected $_infoBlockType = 'Adyen\Payment\Block\Info\Boleto';

    /**
     * @var \Adyen\Payment\Model\Api\PaymentRequest
     */
    protected $_paymentRequest;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_urlBuilder;

    /**
     * Request object
     *
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $_request;

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
     * @var bool
     */
    protected $_canUseInternal = true;

    /**
     * Sepa constructor.
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Adyen\Payment\Model\Api\PaymentRequest $paymentRequest
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
        \Adyen\Payment\Model\Api\PaymentRequest $paymentRequest,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
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
            $resource,
            $resourceCollection,
            $data
        );
        $this->_paymentRequest = $paymentRequest;
        $this->_urlBuilder = $urlBuilder;
        $this->_request = $request;
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
        $infoInstance->setAdditionalInformation('social_security_number', $additionalData['social_security_number']);
        $infoInstance->setAdditionalInformation('boleto_type', $additionalData['boleto_type']);
        $infoInstance->setAdditionalInformation('firstname', $additionalData['firstname']);
        $infoInstance->setAdditionalInformation('lastname', $additionalData['lastname']);
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

        // do not let magento set status to processing
        $payment->setLastTransId($this->getTransactionId())->setIsTransactionPending(true);

        // Do authorisation
        $this->_processRequest($payment, "authorise");

        return $this;
    }

    /**
     * @param \Magento\Sales\Model\Order\Payment $payment
     * @param $amount
     * @param $request
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _processRequest(\Magento\Sales\Model\Order\Payment $payment, $request)
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

    /**
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param $response
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _processResponse(\Magento\Payment\Model\InfoInterface $payment, $response)
    {
        $payment->setAdditionalInformation('3dActive', false);

        switch ($response['resultCode']) {
            case "Received":
                $this->_addStatusHistory($payment, $response['resultCode'], $response['pspReference']);
                $payment->setAdditionalInformation('pspReference', $response['pspReference']);

                if (isset($response['additionalData']) && is_array($response['additionalData'])) {

                    $additionalData = $response['additionalData'];
                    if (isset($additionalData['boletobancario.dueDate'])) {
                        $payment->setAdditionalInformation(
                            'dueDate',
                            $additionalData['boletobancario.dueDate']
                        );
                    }

                    if (isset($additionalData['boletobancario.expirationDate'])) {
                        $payment->setAdditionalInformation(
                            'expirationDate',
                            $additionalData['boletobancario.expirationDate']
                        );
                    }

                    if (isset($additionalData['boletobancario.url'])) {
                        $payment->setAdditionalInformation(
                            'url',
                            $additionalData['boletobancario.url']
                        );
                    }
                }
                break;
            case "Refused":
                $errorMsg = __('The payment is REFUSED.');
                $this->_logger->critical($errorMsg);
                throw new \Magento\Framework\Exception\LocalizedException(__($errorMsg));
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
     * Checkout redirect URL getter for onepage checkout (hardcode)
     *
     * @see \Magento\Checkout\Controller\Onepage::savePaymentAction()
     * @see \Magento\Quote\Model\Quote\Payment::getCheckoutRedirectUrl()
     * @return string
     */
    public function getCheckoutRedirectUrl()
    {
        return $this->_urlBuilder->getUrl('checkout/onepage/success/',
            ['_secure' => $this->_getRequest()->isSecure()]);
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