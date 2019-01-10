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
	 * @var \Magento\Checkout\Model\Session
	 */
	private $checkoutSession;

	/**
	 * @var \Magento\Quote\Model\Quote
	 */
	private $quote;

	/**
	 * @var \Magento\Tax\Model\Config
	 */
	protected $taxConfig;


	/**
	 * HppAuthorizationDataBuilder constructor.
	 *
	 * @param \Adyen\Payment\Helper\Data $adyenHelper
	 * @param \Magento\Store\Model\StoreManagerInterface $storeManager
	 * @param \Magento\Checkout\Model\Session $checkoutSession
	 * @param \Magento\Tax\Model\Config $taxConfig
	 */
	public function __construct(
		\Adyen\Payment\Helper\Data $adyenHelper,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Magento\Checkout\Model\Session $checkoutSession,
		\Magento\Tax\Model\Config $taxConfig
	)
	{
		$this->adyenHelper = $adyenHelper;
		$this->storeManager = $storeManager;
		$this->checkoutSession = $checkoutSession;
		$this->quote = $checkoutSession->getQuote();
		$this->taxConfig = $taxConfig;
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

		if ($payment->getAdditionalInformation(AdyenHppDataAssignObserver::ISSUER_ID)) {
			$request['paymentMethod']['issuer'] = $payment->getAdditionalInformation(AdyenHppDataAssignObserver::ISSUER_ID);
		}

		$request['returnUrl'] = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK) . 'adyen/process/result';

		// update customer based on additionalFields
		if ($payment->getAdditionalInformation("gender")) {
			$order->setCustomerGender(\Adyen\Payment\Model\Gender::getMagentoGenderFromAdyenGender(
				$payment->getAdditionalInformation("gender"))
			);
			$request['shopperName']['gender'] = $payment->getAdditionalInformation("gender");
		}

		if ($payment->getAdditionalInformation("dob")) {
			$order->setCustomerDob($payment->getAdditionalInformation("dob"));

			$request['dateOfBirth']= $this->adyenHelper->formatDate($payment->getAdditionalInformation("dob"), 'Y-m-d') ;
		}

		if ($payment->getAdditionalInformation("telephone")) {
			$order->getBillingAddress()->setTelephone($payment->getAdditionalInformation("telephone"));
			$request['telephoneNumber']= $payment->getAdditionalInformation("telephone");
		}

		if ($this->adyenHelper->isPaymentMethodOpenInvoiceMethod(
			$payment->getAdditionalInformation(AdyenHppDataAssignObserver::BRAND_CODE)
		)) {
			$openInvoiceFields = $this->getOpenInvoiceData($order);
			$request = array_merge($request, $openInvoiceFields);
		}

		return $request;
	}

	/**
	 * @param $formFields
	 * @return mixed
	 */
	protected function getOpenInvoiceData($order)
	{
		$formFields = [
			'lineItems' => []
		];

		$currency = $this->quote->getCurrency();

		$discountAmount = 0;

		foreach ($this->quote->getAllVisibleItems() as $item) {

			$numberOfItems = (int)$item->getQtyOrdered();

			// Summarize the discount amount item by item
			$discountAmount += $item->getDiscountAmount();

			$priceExcludingTax = $item->getPriceInclTax() - $item->getTaxAmount();

			$formFields['lineItems'][] = [
				'amountExcludingTax' => $priceExcludingTax,
				'taxAmount' => $item->getTaxAmount(),
				'description' => $item->getName(),
				'id' => $item->getId(),
				'quantity' => $item->getQty(),
				'taxCategory' => $item->getProduct()->getAttributeText('tax_class_id'),
				'taxPercentage' => $item->getTaxPercent()
			];
		}

		// Discount cost
		if ($discountAmount != 0) {

			$description = __('Total Discount');
			$itemAmount = $this->adyenHelper->formatAmount($discountAmount, $currency);
			$itemVatAmount = "0";
			$itemVatPercentage = "0";
			$numberOfItems = 1;

			$formFields['lineItems'][] = [
				'amountExcludingTax' => $itemAmount,
				'taxAmount' => $itemVatAmount,
				'description' => $description,
				'quantity' => $numberOfItems,
				'taxCategory' => 'None',
				'taxPercentage' => $itemVatPercentage
			];
		}

		// Shipping cost
		if ($this->quote->getShippingAddress()->getShippingAmount() > 0 || $this->quote->getShippingAddress()->getShippingTaxAmount() > 0) {

			$priceExcludingTax = $this->quote->getShippingAddress()->getShippingAmount() - $this->quote->getShippingAddress()->getShippingTaxAmount();

			$taxClassId = $this->taxConfig->getShippingTaxClass($this->storeManager->getStore()->getId());

			$formFields['lineItems'][] = [
				'amountExcludingTax' => $priceExcludingTax,
				'taxAmount' => $this->quote->getShippingAddress()->getShippingTaxAmount(),
				'description' => $order->getShippingDescription(),
				'quantity' => 1,
				'taxPercentage' => $this->quote->getShippingAddress()->getShippingTaxAmount()
			];
		}

		return $formFields;
	}
}
