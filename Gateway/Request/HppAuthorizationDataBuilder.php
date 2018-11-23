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

class HppAuthorizationDataBuilder implements BuilderInterface
{
	/**
	 * @var \Adyen\Payment\Helper\Data
	 */
	private $adyenHelper;

	/**
	 * @var \Magento\Store\Model\StoreManagerInterface
	 */
	private $storeManager;

	/**
	 * CcAuthorizationDataBuilder constructor.
	 *
	 * @param \Adyen\Payment\Helper\Data $adyenHelper
	 */
	public function __construct(
		\Adyen\Payment\Helper\Data $adyenHelper,
		\Magento\Store\Model\StoreManagerInterface $storeManager
	)
	{
		$this->adyenHelper = $adyenHelper;
		$this->storeManager = $storeManager;
	}

	/**
	 * @param array $buildSubject
	 * @return mixed
	 */
	public function build(array $buildSubject)
	{
		/** @var \Magento\Payment\Gateway\Data\PaymentDataObject $paymentDataObject */
		$paymentDataObject =\Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);
		$payment = $paymentDataObject->getPayment();
		$order = $payment->getOrder();
		$request = [];

		// do not send email
		$order->setCanSendNewEmailFlag(false);

		$request['paymentMethod']['type'] = $payment->getAdditionalInformation(AdyenHppDataAssignObserver::BRAND_CODE);

		$request['returnUrl'] = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK) . 'adyen/process/result';

		// update customer based on additionalFields
		if ($payment->getAdditionalInformation("gender")) {
			$order->setCustomerGender(\Adyen\Payment\Model\Gender::getMagentoGenderFromAdyenGender(
				$payment->getAdditionalInformation("gender"))
			);
		}

		if ($payment->getAdditionalInformation("dob")) {
			$order->setCustomerDob($payment->getAdditionalInformation("dob"));
		}

		if ($payment->getAdditionalInformation("telephone")) {
			$order->getBillingAddress()->setTelephone($payment->getAdditionalInformation("telephone"));
		}

		return $request;
	}
}
