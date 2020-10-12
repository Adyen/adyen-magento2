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

namespace Adyen\Payment\Block\Redirect;

use Adyen\AdyenException;
use Symfony\Component\Config\Definition\Exception\Exception;

class Redirect extends \Magento\Payment\Block\Form
{
    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var  \Magento\Sales\Model\Order
     */
    protected $_order;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * @var ResolverInterface
     */
    protected $_resolver;

    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    protected $_adyenLogger;

    /**
     * @var \Magento\Tax\Model\Config
     */
    protected $_taxConfig;

    /**
     * @var \Magento\Tax\Model\Calculation
     */
    protected $_taxCalculation;

    /**
     * Request object
     */
    protected $_request;

    /**
     * Redirect constructor.
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param array $data
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Framework\Locale\ResolverInterface $resolver
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     * @param \Magento\Tax\Model\Config $taxConfig
     * @param \Magento\Tax\Model\Calculation $taxCalculation
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Framework\Locale\ResolverInterface $resolver,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Magento\Tax\Model\Config $taxConfig,
        \Magento\Tax\Model\Calculation $taxCalculation,
        array $data = []
    ) {
        $this->_orderFactory = $orderFactory;
        $this->_checkoutSession = $checkoutSession;
        parent::__construct($context, $data);

        $this->_adyenHelper = $adyenHelper;
        $this->_resolver = $resolver;
        $this->_adyenLogger = $adyenLogger;

        $this->_getOrder();
        $this->_taxConfig = $taxConfig;
        $this->_taxCalculation = $taxCalculation;
        $this->_request = $context->getRequest();
    }

    /**
     * @return mixed|string[]
     * @throws AdyenException
     */
    public function getRedirectMethod()
    {
        if ($redirectMethod = $this->getPayment()->getAdditionalInformation('redirectMethod')) {
            return $redirectMethod;
        }

        throw new AdyenException("No redirect method is provided.");
    }

    /**
     * @return Redirect
     */
    public function _prepareLayout()
    {
        return parent::_prepareLayout();
    }

    /**
     * Retrieves redirect url for the flow of checkout API
     *
     * @return string[]
     * @throws AdyenException
     */
    public function getRedirectUrl()
    {
        if ($redirectUrl = $this->getPayment()->getAdditionalInformation('redirectUrl')) {
            return $redirectUrl;
        }

        throw new AdyenException("No redirect url is provided.");
    }

    /**
     * @return string
     */
    public function getFormUrl()
    {
        $url = "";
        try {
            if ($this->_order->getPayment()) {
                switch ($this->_adyenHelper->isDemoMode()) {
                    case true:
                        if ($this->_adyenHelper->doesPaymentMethodSkipDetails(
                            $this->_order->getPayment()->getAdditionalInformation('brand_code')
                        )
                        ) {
                            $url = "https://test.adyen.com/hpp/skipDetails.shtml";
                        } else {
                            $url = "https://test.adyen.com/hpp/details.shtml";
                        }
                        break;
                    default:
                        if ($this->_adyenHelper->doesPaymentMethodSkipDetails(
                            $this->_order->getPayment()->getAdditionalInformation('brand_code')
                        )
                        ) {
                            $url = "https://live.adyen.com/hpp/skipDetails.shtml";
                        } else {
                            $url = "https://live.adyen.com/hpp/details.shtml";
                        }
                        break;
                }
            }
        } catch (Exception $e) {
            // do nothing for now
            throw($e);
        }

        return $url;
    }

    /**
     * @return mixed
     */
    private function getBrandCode()
    {
        return $this->getPayment()->getAdditionalInformation('brand_code');
    }

    /**
     * Set Billing Address data
     *
     * @param $formFields
     */
    protected function setBillingAddressData($formFields)
    {
        $billingAddress = $this->_order->getBillingAddress();

        if ($billingAddress) {
            $formFields['shopper.firstName'] = trim($billingAddress->getFirstname());
            $middleName = trim($billingAddress->getMiddlename());
            if ($middleName != "") {
                $formFields['shopper.infix'] = trim($middleName);
            }

            $formFields['shopper.lastName'] = trim($billingAddress->getLastname());
            $formFields['shopper.telephoneNumber'] = trim($billingAddress->getTelephone());

            if ($this->_adyenHelper->isSeparateHouseNumberRequired($billingAddress->getCountryId())) {
                $street = $this->_adyenHelper->getStreet($billingAddress);

                if (!empty($street['name'])) {
                    $formFields['billingAddress.street'] = $street['name'];
                }

                if (!empty($street['house_number'])) {
                    $formFields['billingAddress.houseNumberOrName'] = $street['house_number'];
                } else {
                    $formFields['billingAddress.houseNumberOrName'] = "NA";
                }
            } else {
                $formFields['billingAddress.street'] = implode(" ", $billingAddress->getStreet());
            }

            if (trim($billingAddress->getCity()) == "") {
                $formFields['billingAddress.city'] = "NA";
            } else {
                $formFields['billingAddress.city'] = trim($billingAddress->getCity());
            }

            if (trim($billingAddress->getPostcode()) == "") {
                $formFields['billingAddress.postalCode'] = "";
            } else {
                $formFields['billingAddress.postalCode'] = trim($billingAddress->getPostcode());
            }

            if (trim($billingAddress->getRegionCode()) == "") {
                $formFields['billingAddress.stateOrProvince'] = "";
            } else {
                $formFields['billingAddress.stateOrProvince'] = trim($billingAddress->getRegionCode());
            }

            if (trim($billingAddress->getCountryId()) == "") {
                $formFields['billingAddress.country'] = "ZZ";
            } else {
                $formFields['billingAddress.country'] = trim($billingAddress->getCountryId());
            }
        }
        return $formFields;
    }

    /**
     * Set Shipping Address data
     *
     * @param $formFields
     */
    protected function setShippingAddressData($formFields)
    {
        $shippingAddress = $this->_order->getShippingAddress();

        if ($shippingAddress) {
            if ($this->_adyenHelper->isSeparateHouseNumberRequired($shippingAddress->getCountryId())) {
                $street = $this->_adyenHelper->getStreet($shippingAddress);

                if (isset($street['name']) && $street['name'] != "") {
                    $formFields['deliveryAddress.street'] = $street['name'];
                }

                if (isset($street['house_number']) && $street['house_number'] != "") {
                    $formFields['deliveryAddress.houseNumberOrName'] = $street['house_number'];
                } else {
                    $formFields['deliveryAddress.houseNumberOrName'] = "NA";
                }
            } else {
                $formFields['deliveryAddress.street'] = implode(" ", $shippingAddress->getStreet());
            }

            if (trim($shippingAddress->getCity()) == "") {
                $formFields['deliveryAddress.city'] = "NA";
            } else {
                $formFields['deliveryAddress.city'] = trim($shippingAddress->getCity());
            }

            if (trim($shippingAddress->getPostcode()) == "") {
                $formFields['deliveryAddress.postalCode'] = "";
            } else {
                $formFields['deliveryAddress.postalCode'] = trim($shippingAddress->getPostcode());
            }

            if (trim($shippingAddress->getRegionCode()) == "") {
                $formFields['deliveryAddress.stateOrProvince'] = "";
            } else {
                $formFields['deliveryAddress.stateOrProvince'] = trim($shippingAddress->getRegionCode());
            }

            if (trim($shippingAddress->getCountryId()) == "") {
                $formFields['deliveryAddress.country'] = "ZZ";
            } else {
                $formFields['deliveryAddress.country'] = trim($shippingAddress->getCountryId());
            }
        }

        return $formFields;
    }

    /**
     * @param $formFields
     * @return mixed
     */
    protected function setOpenInvoiceData($formFields)
    {
        $count = 0;
        $currency = $this->_order->getOrderCurrencyCode();

        foreach ($this->_order->getAllVisibleItems() as $item) {
            /** @var $item \Magento\Sales\Model\Order\Item */
            ++$count;
            $numberOfItems = (int)$item->getQtyOrdered();

            $formFields = $this->_adyenHelper->createOpenInvoiceLineItem(
                $formFields,
                $count,
                $item->getName(),
                $item->getPrice(),
                $currency,
                $item->getTaxAmount(),
                $item->getPriceInclTax(),
                $item->getTaxPercent(),
                $numberOfItems,
                $this->_order->getPayment(),
                $item->getId()
            );
        }

        // Discount cost
        if ($this->_order->getDiscountAmount() > 0 || $this->_order->getDiscountAmount() < 0) {
            ++$count;

            $description = __('Total Discount');
            $itemAmount = $this->_adyenHelper->formatAmount($this->_order->getDiscountAmount(), $currency);
            $itemVatAmount = "0";
            $itemVatPercentage = "0";
            $numberOfItems = 1;

            $formFields = $this->_adyenHelper->getOpenInvoiceLineData(
                $formFields,
                $count,
                $currency,
                $description,
                $itemAmount,
                $itemVatAmount,
                $itemVatPercentage,
                $numberOfItems,
                $this->_order->getPayment(),
                "totalDiscount"
            );
        }

        // Shipping cost
        if ($this->_order->getShippingAmount() > 0 || $this->_order->getShippingTaxAmount() > 0) {
            ++$count;
            $formFields = $this->_adyenHelper->createOpenInvoiceLineShipping(
                $formFields,
                $count,
                $this->_order,
                $this->_order->getShippingAmount(),
                $this->_order->getShippingTaxAmount(),
                $currency,
                $this->_order->getPayment()
            );
        }

        $formFields['openinvoicedata.refundDescription'] = "Refund / Correction for " .
            $formFields['merchantReference'];
        $formFields['openinvoicedata.numberOfLines'] = $count;

        return $formFields;
    }

    /**
     * @param $genderId
     * @return string
     */
    protected function getGenderText($genderId)
    {
        $result = "";
        if ($genderId == '1') {
            $result = 'MALE';
        } elseif ($genderId == '2') {
            $result = 'FEMALE';
        }
        return $result;
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
     * Get frontend checkout session object
     *
     * @return \Magento\Checkout\Model\Session
     */
    protected function _getCheckout()
    {
        return $this->_checkoutSession;
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

    /**
     * Get order object
     *
     * @return \Magento\Sales\Model\Order
     */
    protected function _getOrder()
    {
        if (!$this->_order) {
            $incrementId = $this->_getCheckout()->getLastRealOrderId();
            $this->_order = $this->_orderFactory->create()->loadByIncrementId($incrementId);
        }
        return $this->_order;
    }

    /**
     * @return mixed
     */
    public function getPaReq()
    {
        if ($paReq = $this->getPayment()->getAdditionalInformation('paRequest')) {
            return $paReq;
        }

        throw new AdyenException("No paRequest is provided.");
    }

    /**
     * @return string[]
     * @throws AdyenException
     */
    public function getMd()
    {
        if ($md = $this->getPayment()->getAdditionalInformation('md')) {
            return $md;
        }

        throw new AdyenException("No MD is provided.");
    }

    /**
     * @return mixed
     */
    public function getTermUrl()
    {
        return $this->getUrl('adyen/process/redirect', ['_secure' => $this->_getRequest()->isSecure()]);
    }

    /**
     * Retrieve payment object if available
     *
     * @return \Magento\Framework\DataObject|\Magento\Sales\Api\Data\OrderPaymentInterface|mixed|null
     * @throws AdyenException
     */
    private function getPayment()
    {
        try {
            $paymentObject = $this->_order->getPayment();
            if (!empty($paymentObject)) {
                return $paymentObject;
            }
        } catch (Exception $e) {
            // do nothing for now
            throw($e);
        }

        throw new AdyenException("No payment object is found.");
    }
}
