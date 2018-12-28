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
        array $data = [],
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Framework\Locale\ResolverInterface $resolver,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Magento\Tax\Model\Config $taxConfig,
        \Magento\Tax\Model\Calculation $taxCalculation
    )
    {
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
	 * Retrieves redirect url for the flow of checkout API
	 *
	 * @return string[]
	 * @throws AdyenException
	 */
	public function getRedirectUrl()
	{
		try {
			if ($paymentObject = $this->_order->getPayment()) {
				if ($redirectUrl = $paymentObject->getAdditionalInformation('redirectUrl')) {
					return $redirectUrl;
				} else {
					return $this->getIssuerUrl();
				}
			}
		} catch (Exception $e) {
			// do nothing for now
			throw($e);
		}

		throw new AdyenException("No redirect url is provided.");
	}

    /**
     * @return $this
     */
    public function _prepareLayout()
    {
        return parent::_prepareLayout();
    }

    /**
     * @return string
     */
    public function getFormUrl()
    {
        $url = "";
        try {
            if ($this->_order->getPayment()) {
                $paymentRoutine = $this->_adyenHelper->getAdyenHppConfigData('payment_routine');

                switch ($this->_adyenHelper->isDemoMode()) {
                    case true:
                        if ($paymentRoutine == 'single' && $this->getPaymentMethodSelectionOnAdyen()) {
                            $url = 'https://test.adyen.com/hpp/pay.shtml';
                        } else {

                            if ($this->getPaymentMethodSelectionOnAdyen()) {
                                $url = 'https://test.adyen.com/hpp/select.shtml';
                            } else {
                                if ($this->_adyenHelper->isPaymentMethodOpenInvoiceMethod(
									$this->getBrandCode()
                                )
                                ) {
                                    $url = "https://test.adyen.com/hpp/skipDetails.shtml";
                                } else {
                                    $url = "https://test.adyen.com/hpp/details.shtml";
                                }
                            }
                        }
                        break;
                    default:
                        if ($paymentRoutine == 'single' && $this->getPaymentMethodSelectionOnAdyen()) {
                            $url = 'https://live.adyen.com/hpp/pay.shtml';
                        } else {
                            if ($this->getPaymentMethodSelectionOnAdyen()) {
                                $url = 'https://live.adyen.com/hpp/select.shtml';
                            } else {
                                if ($this->_adyenHelper->isPaymentMethodOpenInvoiceMethod(
                                    $this->getBrandCode()
                                )
                                ) {
                                    $url = "https://live.adyen.com/hpp/skipDetails.shtml";
                                } else {
                                    $url = "https://live.adyen.com/hpp/details.shtml";
                                }
                            }
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
    public function getPaymentMethodSelectionOnAdyen()
    {
        return $this->_adyenHelper->getAdyenHppConfigDataFlag('payment_selection_on_adyen');
    }

	/**
	 * @return mixed
	 */
	private function getBrandCode()
	{
		return $this->_order->getPayment()->getAdditionalInformation('brand_code');
	}

    /**
     * @return array
     */
    public function getFormFields()
    {
        $formFields = [];
        try {
            if ($this->_order->getPayment()) {

                $realOrderId = $this->_order->getRealOrderId();
                $orderCurrencyCode = $this->_order->getOrderCurrencyCode();
                $skinCode = trim($this->_adyenHelper->getAdyenHppConfigData('skin_code'));
                $amount = $this->_adyenHelper->formatAmount(
                    $this->_order->getGrandTotal(), $orderCurrencyCode
                );
                $merchantAccount = trim($this->_adyenHelper->getAdyenAbstractConfigData('merchant_account'));
                $shopperEmail = $this->_order->getCustomerEmail();
                $customerId = $this->_order->getCustomerId();
                $shopperIP = $this->_order->getRemoteIp();
                $browserInfo = $_SERVER['HTTP_USER_AGENT'];
                $deliveryDays = $this->_adyenHelper->getAdyenHppConfigData('delivery_days');
                $shopperLocale = trim($this->_adyenHelper->getAdyenHppConfigData('shopper_locale'));
                $shopperLocale = (!empty($shopperLocale)) ? $shopperLocale : $this->_resolver->getLocale();
                $countryCode = trim($this->_adyenHelper->getAdyenHppConfigData('country_code'));
                $countryCode = (!empty($countryCode)) ? $countryCode : false;

                // if directory lookup is enabled use the billingaddress as countrycode
                if ($countryCode == false) {
                    if ($this->_order->getBillingAddress() &&
                        $this->_order->getBillingAddress()->getCountryId() != ""
                    ) {
                        $countryCode = $this->_order->getBillingAddress()->getCountryId();
                    }
                }

                $formFields = [];

                $formFields['merchantAccount'] = $merchantAccount;
                $formFields['merchantReference'] = $realOrderId;
                $formFields['paymentAmount'] = (int)$amount;
                $formFields['currencyCode'] = $orderCurrencyCode;
                $formFields['shipBeforeDate'] = date(
                    "Y-m-d",
                    mktime(date("H"), date("i"), date("s"), date("m"), date("j") + $deliveryDays, date("Y"))
                );
                $formFields['skinCode'] = $skinCode;
                $formFields['shopperLocale'] = $shopperLocale;
                $formFields['countryCode'] = $countryCode;
                $formFields['shopperIP'] = $shopperIP;
                $formFields['browserInfo'] = $browserInfo;
                $formFields['sessionValidity'] = date(
                    DATE_ATOM,
                    mktime(date("H") + 1, date("i"), date("s"), date("m"), date("j"), date("Y"))
                );
                $formFields['shopperEmail'] = $shopperEmail;
                // recurring
                $recurringType = trim($this->_adyenHelper->getAdyenAbstractConfigData(
                    'recurring_type')
                );
                $brandCode = $this->_order->getPayment()->getAdditionalInformation(
                    \Adyen\Payment\Observer\AdyenHppDataAssignObserver::BRAND_CODE
                );

                // Paypal does not allow ONECLICK,RECURRING only RECURRING
                if ($brandCode == "paypal" && $recurringType == 'ONECLICK,RECURRING') {
                    $recurringType = "RECURRING";
                }

                if ($customerId > 0) {
                    $formFields['recurringContract'] = $recurringType;
                    $formFields['shopperReference'] = $customerId;
                } else {
                    // required for openinvoice payment methods use unique id
                    $uniqueReference = "guest_" . $realOrderId . "_" . $this->_order->getStoreId();
                    $formFields['shopperReference'] = $uniqueReference;
                }

                //blocked methods
                $formFields['blockedMethods'] = "";

                $baseUrl = $this->_storeManager->getStore($this->getStore())
                    ->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK);

                $formFields['resURL'] = $baseUrl . 'adyen/process/result';

                if ($brandCode) {
                    $formFields['brandCode'] = $brandCode;
                }

                $issuerId = $this->_order->getPayment()->getAdditionalInformation("issuer_id");
                if ($issuerId) {
                    $formFields['issuerId'] = $issuerId;
                }

                $formFields = $this->setBillingAddressData($formFields);
                $formFields = $this->setShippingAddressData($formFields);
                $formFields = $this->setOpenInvoiceData($formFields);

                $formFields['shopper.gender'] = $this->getGenderText($this->_order->getCustomerGender());
                $dob = $this->_order->getCustomerDob();
                if ($dob) {
                    $formFields['shopper.dateOfBirthDayOfMonth'] = trim($this->_adyenHelper->formatDate($dob, 'd'));
                    $formFields['shopper.dateOfBirthMonth'] = trim($this->_adyenHelper->formatDate($dob, 'm'));
                    $formFields['shopper.dateOfBirthYear'] = trim($this->_adyenHelper->formatDate($dob, 'Y'));
                }

                // For klarna acceptPrivacyPolicy to skip HPP page
                if ($brandCode == "klarna") {
                    $ssn = $this->_order->getPayment()->getAdditionalInformation('ssn');
                    if (!empty($ssn)) {
                        $formFields['shopper.socialSecurityNumber'] = $ssn;
                    }
                    //  // needed for DE and AT
                    $formFields['klarna.acceptPrivacyPolicy'] = 'true';
                }

                // OpenInvoice don't allow to edit billing and delivery items

                if ($this->_adyenHelper->isPaymentMethodOpenInvoiceMethod($brandCode)) {
                    // don't allow editable shipping/delivery address
                    $formFields['billingAddressType'] = "1";
                    $formFields['deliveryAddressType'] = "1";
                }

                if ($this->_order->getPayment()->getAdditionalInformation("df_value") != "") {
                    $formFields['dfValue'] = $this->_order->getPayment()->getAdditionalInformation("df_value");
                }

                // Sign request using secret key
                $hmacKey = $this->_adyenHelper->getHmac();
                $merchantSig = \Adyen\Util\Util::calculateSha256Signature($hmacKey, $formFields);
                $formFields['merchantSig'] = $merchantSig;

                $this->_adyenLogger->addAdyenDebug(print_r($formFields, true));
            }

        } catch (Exception $e) {
            // do nothing for now
        }
        return $formFields;
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
            $street = $this->_adyenHelper->getStreet($billingAddress);

            if (isset($street['name']) && $street['name'] != "") {
                $formFields['billingAddress.street'] = $street['name'];
            }

            if (isset($street['house_number']) && $street['house_number'] != "") {
                $formFields['billingAddress.houseNumberOrName'] = $street['house_number'];
            } else {
                $formFields['billingAddress.houseNumberOrName'] = "";
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

            $street = $this->_adyenHelper->getStreet($shippingAddress);

            if (isset($street['name']) && $street['name'] != "") {
                $formFields['deliveryAddress.street'] = $street['name'];
            }

            if (isset($street['house_number']) && $street['house_number'] != "") {
                $formFields['deliveryAddress.houseNumberOrName'] = $street['house_number'];
            } else {
                $formFields['deliveryAddress.houseNumberOrName'] = "";
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
                $this->_order->getPayment()
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

            $formFields = $this->_adyenHelper->getOpenInvoiceLineData($formFields, $count, $currency, $description, $itemAmount,
                $itemVatAmount, $itemVatPercentage, $numberOfItems, $this->_order->getPayment());
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

        $formFields['openinvoicedata.refundDescription'] = "Refund / Correction for " . $formFields['merchantReference'];
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
	public function getIssuerUrl()
	{
		return $this->_order->getPayment()->getAdditionalInformation('issuerUrl');
	}

	/**
	 * @return mixed
	 */
	public function getPaReq()
	{
		return $this->_order->getPayment()->getAdditionalInformation('paRequest');
	}

	/**
	 * @return mixed
	 */
	public function getMd()
	{
		return $this->_order->getPayment()->getAdditionalInformation('md');
	}

	/**
	 * @return string
	 */
	public function getTermUrl()
	{
		return $this->getUrl('adyen/process/redirect',
			['_secure' => $this->_getRequest()->isSecure()]);
	}
}
