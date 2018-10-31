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
use Adyen\Payment\Observer\AdyenCcDataAssignObserver;

class CcAuthorizationDataBuilder implements BuilderInterface
{
    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $adyenHelper;

    /**
     * @var \Magento\Framework\App\State
     */
    private $appState;

	/**
	 * @var \Adyen\Payment\Logger\AdyenLogger
	 */
	protected $_adyenLogger;

	/**
	 * CcAuthorizationDataBuilder constructor.
	 *
	 * @param \Adyen\Payment\Helper\Data $adyenHelper
	 * @param \Magento\Framework\Model\Context $context
	 */
	public function __construct(
		\Adyen\Payment\Helper\Data $adyenHelper,
		\Magento\Framework\Model\Context $context,
		\Adyen\Payment\Logger\AdyenLogger $adyenLogger
	)
	{
		$this->adyenHelper = $adyenHelper;
		$this->appState = $context->getAppState();
		$this->_adyenLogger = $adyenLogger;
	}

	/**
	 * @param array $buildSubject
	 * @return mixed
	 */
	public function build(array $buildSubject)
	{
		/** @var \Magento\Payment\Gateway\Data\PaymentDataObject $paymentDataObject */
		$paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);
		$payment = $paymentDataObject->getPayment();
		$order = $paymentDataObject->getOrder();
		$storeId = $order->getStoreId();
		$request = [];

		$request['paymentMethod']['type'] = "scheme";
		$request['paymentMethod']['encryptedCardNumber'] = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::CREDIT_CARD_NUMBER);
		$request['paymentMethod']['encryptedExpiryMonth'] = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::EXPIRY_MONTH);
		$request['paymentMethod']['encryptedExpiryYear'] = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::EXPIRY_YEAR);
		$request['paymentMethod']['encryptedSecurityCode'] = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::SECURITY_CODE);
		$request['paymentMethod']['holderName'] = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::HOLDER_NAME);

		// Remove from additional data
		$payment->unsAdditionalInformation(AdyenCcDataAssignObserver::CREDIT_CARD_NUMBER);
		$payment->unsAdditionalInformation(AdyenCcDataAssignObserver::EXPIRY_MONTH);
		$payment->unsAdditionalInformation(AdyenCcDataAssignObserver::EXPIRY_YEAR);
		$payment->unsAdditionalInformation(AdyenCcDataAssignObserver::SECURITY_CODE);
		$payment->unsAdditionalInformation(AdyenCcDataAssignObserver::HOLDER_NAME);

		$payment->unsAdditionalInformation(AdyenCcDataAssignObserver::ENCRYPTED_DATA);

		/**
		 * if MOTO for backend is enabled use MOTO as shopper interaction type
		 */
		$enableMoto = $this->adyenHelper->getAdyenCcConfigDataFlag('enable_moto', $storeId);
		if ($this->appState->getAreaCode() === \Magento\Backend\App\Area\FrontNameResolver::AREA_CODE &&
			$enableMoto
		) {
			$request['shopperInteraction'] = "Moto";
		}
		// if installments is set add it into the request
		if ($payment->getAdditionalInformation(AdyenCcDataAssignObserver::NUMBER_OF_INSTALLMENTS) &&
			$payment->getAdditionalInformation(AdyenCcDataAssignObserver::NUMBER_OF_INSTALLMENTS) > 0
		) {
			$request['installments']['value'] = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::NUMBER_OF_INSTALLMENTS);
		}

		return $request;
	}
}