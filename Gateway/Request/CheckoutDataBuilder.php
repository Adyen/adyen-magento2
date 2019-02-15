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
use Adyen\Payment\Observer\AdyenBoletoDataAssignObserver;

class CheckoutDataBuilder implements BuilderInterface
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
	 * CheckoutDataBuilder constructor.
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
		$storeId = $order->getStoreId();
		$request = [];

        // do not send email
        $order->setCanSendNewEmailFlag(false);

        $request['paymentMethod']['type'] = $payment->getAdditionalInformation(AdyenHppDataAssignObserver::BRAND_CODE);

        // Additional data for payment methods with issuer list
        if ($payment->getAdditionalInformation(AdyenHppDataAssignObserver::ISSUER_ID)) {
            $request['paymentMethod']['issuer'] = $payment->getAdditionalInformation(AdyenHppDataAssignObserver::ISSUER_ID);
        }

        $request['returnUrl'] = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK) . 'adyen/process/result';

		// Additional data for open invoice payment
		if ($payment->getAdditionalInformation("gender")) {
			$order->setCustomerGender(\Adyen\Payment\Model\Gender::getMagentoGenderFromAdyenGender(
				$payment->getAdditionalInformation("gender"))
			);
			$request['paymentMethod']['personalDetails']['gender'] = $payment->getAdditionalInformation("gender");
		}

        if ($payment->getAdditionalInformation("dob")) {
            $order->setCustomerDob($payment->getAdditionalInformation("dob"));

			$request['paymentMethod']['personalDetails']['dateOfBirth']= $this->adyenHelper->formatDate($payment->getAdditionalInformation("dob"), 'Y-m-d') ;
		}

		if ($payment->getAdditionalInformation("telephone")) {
			$order->getBillingAddress()->setTelephone($payment->getAdditionalInformation("telephone"));
			$request['paymentMethod']['personalDetails']['telephoneNumber']= $payment->getAdditionalInformation("telephone");
		}

        if ($payment->getAdditionalInformation("ssn")) {
            $request['paymentMethod']['personalDetails']['socialSecurityNumber']= $payment->getAdditionalInformation("ssn");
        }

        // Additional data for sepa direct debit
        if ($payment->getAdditionalInformation("ownerName")) {
            $request['paymentMethod']['sepa.ownerName'] = $payment->getAdditionalInformation("ownerName");
        }

        if ($payment->getAdditionalInformation("ibanNumber")) {
            $request['paymentMethod']['sepa.ibanNumber'] = $payment->getAdditionalInformation("ibanNumber");
        }

		if ($this->adyenHelper->isPaymentMethodOpenInvoiceMethod(
			$payment->getAdditionalInformation(AdyenHppDataAssignObserver::BRAND_CODE)
		) || $this->adyenHelper->isPaymentMethodAfterpayTouchMethod(
				$payment->getAdditionalInformation(AdyenHppDataAssignObserver::BRAND_CODE)
			)) {
			$openInvoiceFields = $this->getOpenInvoiceData($order);
			$request = array_merge($request, $openInvoiceFields);
		}

        //Boleto data
        if ($payment->getAdditionalInformation("social_security_number")) {
            $request['socialSecurityNumber'] = $payment->getAdditionalInformation("social_security_number");
        }

        if ($payment->getAdditionalInformation("firstname")) {
            $request['shopperName']['firstName'] = $payment->getAdditionalInformation("firstname");
        }

        if ($payment->getAdditionalInformation("lastName")) {
            $request['shopperName']['lastName'] = $payment->getAdditionalInformation("lastName");
        }

        if ($payment->getAdditionalInformation(AdyenBoletoDataAssignObserver::BOLETO_TYPE)) {
            $boletoTypes = $this->adyenHelper->getAdyenBoletoConfigData('boletotypes');
            $boletoTypes = explode(',', $boletoTypes);

            if (count($boletoTypes) == 1) {
                $request['selectedBrand'] = $boletoTypes[0];
                $request['paymentMethod']['type'] = $boletoTypes[0];
            } else {
                $request['selectedBrand'] = $payment->getAdditionalInformation("boleto_type");
                $request['paymentMethod']['type'] = $payment->getAdditionalInformation("boleto_type");
            }

            $deliveryDays = (int)$this->adyenHelper->getAdyenBoletoConfigData("delivery_days", $storeId);
            $deliveryDays = (!empty($deliveryDays)) ? $deliveryDays : 5;
            $deliveryDate = date(
                "Y-m-d\TH:i:s ",
                mktime(
                    date("H"),
                    date("i"),
                    date("s"),
                    date("m"),
                    date("j") + $deliveryDays,
                    date("Y")
                )
            );

            $request['deliveryDate'] = $deliveryDate;

            $order->setCanSendNewEmailFlag(true);
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
            $formattedPriceExcludingTax = $this->adyenHelper->formatAmount($priceExcludingTax, $currency);

            $formFields['lineItems'][] = [
                'amountExcludingTax' => $formattedPriceExcludingTax,
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
            $formattedPriceExcludingTax = $this->adyenHelper->formatAmount($priceExcludingTax, $currency);

            $taxClassId = $this->taxConfig->getShippingTaxClass($this->storeManager->getStore()->getId());

            $formFields['lineItems'][] = [
                'amountExcludingTax' => $formattedPriceExcludingTax,
                'taxAmount' => $this->quote->getShippingAddress()->getShippingTaxAmount(),
                'description' => $order->getShippingDescription(),
                'quantity' => 1,
                'taxPercentage' => $this->quote->getShippingAddress()->getShippingTaxAmount()
            ];
        }

        return $formFields;
    }
}
