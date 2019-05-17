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
namespace Adyen\Payment\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Adyen\Payment\Observer\AdyenHppDataAssignObserver;

/**
 * Class CustomerDataBuilder
 */
class CustomerDataBuilder implements BuilderInterface
{
	/**
	 * @var \Adyen\Payment\Helper\Data
	 */
	private $adyenHelper;

	/**
	 * CustomerDataBuilder constructor.
	 *
	 * @param \Adyen\Payment\Helper\Data $adyenHelper
	 */
	public function __construct(
		\Adyen\Payment\Helper\Data $adyenHelper
	)
	{
		$this->adyenHelper = $adyenHelper;
	}

	/**
     * Add shopper data into request
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        $result = [];

        /** @var \Magento\Payment\Gateway\Data\PaymentDataObject $paymentDataObject */
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);
        $order = $paymentDataObject->getOrder();
		$payment = $paymentDataObject->getPayment();
        $customerId = $order->getCustomerId();

        if ($customerId > 0) {
            $result['shopperReference'] = $customerId;
        }

		$billingAddress = $order->getBillingAddress();

        if (!empty($billingAddress)) {
			if ($this->adyenHelper->isPaymentMethodOpenInvoiceMethod(
				$payment->getAdditionalInformation(AdyenHppDataAssignObserver::BRAND_CODE)
			) && !$this->adyenHelper->isPaymentMethodAfterpayTouchMethod(
					$payment->getAdditionalInformation(AdyenHppDataAssignObserver::BRAND_CODE)
				)) {
				if ($customerEmail = $billingAddress->getEmail()) {
					$result['paymentMethod']['personalDetails']['shopperEmail'] = $customerEmail;
				}

				if ($customerTelephone = trim($billingAddress->getTelephone())) {
					$result['paymentMethod']['personalDetails']['telephoneNumber'] = $customerTelephone;
				}

				if ($firstName = $billingAddress->getFirstname()) {
					$result['paymentMethod']['personalDetails']['firstName'] = $firstName;
				}

				if ($lastName = $billingAddress->getLastname()) {
					$result['paymentMethod']['personalDetails']['lastName'] = $lastName;
				}
			} else {
				if ($customerEmail = $billingAddress->getEmail()) {
					$result['shopperEmail'] = $customerEmail;
				}

				if ($customerTelephone = trim($billingAddress->getTelephone())) {
					$result['telephoneNumber'] = $customerTelephone;
				}

				if ($firstName = $billingAddress->getFirstname()) {
					$result['shopperName']['firstName'] = $firstName;
				}

				if ($lastName = $billingAddress->getLastname()) {
					$result['shopperName']['lastName'] = $lastName;
				}
			}

			if ($countryId = $billingAddress->getCountryId()) {
				$result['countryCode'] = $countryId;
			}

			if ($shopperLocale = $this->adyenHelper->getCurrentLocaleCode($order->getStoreId())) {
                $result['shopperLocale'] = $shopperLocale;
            }

		}

        return $result;
    }
}
