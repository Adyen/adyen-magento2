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

namespace Adyen\Payment\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;

class CheckoutResponseValidator extends AbstractValidator
{
	/**
	 * @var \Adyen\Payment\Logger\AdyenLogger
	 */
	private $adyenLogger;

	/**
	 * GeneralResponseValidator constructor.
	 *
	 * @param \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory
	 * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
	 */
	public function __construct(
		\Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory,
		\Adyen\Payment\Logger\AdyenLogger $adyenLogger
	) {
		$this->adyenLogger = $adyenLogger;
		parent::__construct($resultFactory);
	}

	/**
	 * @param array $validationSubject
	 * @return \Magento\Payment\Gateway\Validator\ResultInterface
	 */
	public function validate(array $validationSubject)
	{
		$response = \Magento\Payment\Gateway\Helper\SubjectReader::readResponse($validationSubject);
		$paymentDataObjectInterface = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($validationSubject);
		$payment = $paymentDataObjectInterface->getPayment();

		$payment->setAdditionalInformation('3dActive', false);
		$isValid = true;
		$errorMessages = [];

		// validate result
		if (isset($response['resultCode'])) {
			switch ($response['resultCode']) {
				case "Authorised":
					$payment->setAdditionalInformation('pspReference', $response['pspReference']);
					break;
				case "Received":
					$payment->setAdditionalInformation('pspReference', $response['pspReference']);
					// set additionalData
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
				case "RedirectShopper":

					$redirectUrl = null;
					$paymentData = null;

					if (!empty($response['redirect']['url'])) {
						$redirectUrl = $response['redirect']['url'];
					}

					if (!empty($response['paymentData'])) {
						$paymentData = $response['paymentData'];
					}

					// If the redirect data is there then the payment is a card payment with 3d secure
					if (isset($response['redirect']['data'])) {
						$paReq = null;
						$md = null;

						$payment->setAdditionalInformation('3dActive', true);

						if (!empty($response['redirect']['data']['PaReq'])) {
							$paReq = $response['redirect']['data']['PaReq'];
						}

						if (!empty($response['redirect']['data']['MD'])) {
							$md = $response['redirect']['data']['MD'];
						}

						if ($paReq && $md && $redirectUrl && $paymentData) {
							$payment->setAdditionalInformation('issuerUrl', $redirectUrl);
							$payment->setAdditionalInformation('paRequest', $paReq);
							$payment->setAdditionalInformation('md', $md);
							$payment->setAdditionalInformation('paymentData', $paymentData);
						} else {
							$isValid = false;
							$errorMsg = __('3D secure is not valid.');
							$this->adyenLogger->error($errorMsg);;
							$errorMessages[] = $errorMsg;
						}
					// otherwise it is an alternative payment method which only requires the redirect url to be present
					} else {
						$payment->setAdditionalInformation('CheckoutAPM', true);

						if ($redirectUrl && $paymentData) {
							$payment->setAdditionalInformation('redirectUrl', $redirectUrl);
							$payment->setAdditionalInformation('paymentData', $paymentData);
						} else {
							$isValid = false;
							$errorMsg = __('Payment method is not valid.');
							$this->adyenLogger->error($errorMsg);;
							$errorMessages[] = $errorMsg;
						}
					}

					break;
				case "Refused":
					$errorMsg = __('The payment is REFUSED.');
					// this will result the specific error
					throw new \Magento\Framework\Exception\LocalizedException(__($errorMsg));
					break;
				default:
					$errorMsg = __('Error with payment method please select different payment method.');
					throw new \Magento\Framework\Exception\LocalizedException(__($errorMsg));
					break;
			}
		} else {
			$errorMsg = __('Error with payment method please select different payment method.');
			throw new \Magento\Framework\Exception\LocalizedException(__($errorMsg));
		}

		return $this->createResult($isValid, $errorMessages);
	}
}
