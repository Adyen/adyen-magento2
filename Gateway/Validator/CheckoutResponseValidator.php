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
     * @var \Adyen\Payment\Helper\Data
     */
    private  $adyenHelper;

    /**
     * GeneralResponseValidator constructor.
     *
     * @param \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     */
    public function __construct(
        \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Adyen\Payment\Helper\Data $adyenHelper
    ) {
        $this->adyenLogger = $adyenLogger;
        $this->adyenHelper = $adyenHelper;
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
                case "IdentifyShopper":
                    $payment->setAdditionalInformation('threeDSType', $response['resultCode']);
                    $payment->setAdditionalInformation('threeDS2Token', $response['authentication']['threeds2.fingerprintToken']);
                    $payment->setAdditionalInformation('threeDS2PaymentData', $response['paymentData']);
                    break;
                case "ChallengeShopper":
                    $payment->setAdditionalInformation('threeDSType', $response['resultCode']);
                    $payment->setAdditionalInformation('threeDS2Token', $response['authentication']['threeds2.challengeToken']);
                    $payment->setAdditionalInformation('threeDS2PaymentData', $response['paymentData']);
                    break;
                case "Authorised":
                case "Received":
                    // For banktransfers store all bankTransfer details
                    if (!empty($response['additionalData']['bankTransfer.owner'])) {
                        foreach ($response['additionalData'] as $key => $value) {
                            if (strpos($key, 'bankTransfer') === 0) {
                                $payment->setAdditionalInformation($key, $value);
                            }
                        }
                    } elseif (!empty($response['additionalData']['comprafacil.entity'])) {
                        foreach ($response['additionalData'] as $key => $value) {
                            if (strpos($key, 'comprafacil') === 0) {
                                $payment->setAdditionalInformation($key, $value);
                            }
                        }
                    }

                    // Save cc_type if available in the response
                    if (!empty($response['additionalData']['paymentMethod'])) {
                        $ccType = $this->adyenHelper->getMagentoCreditCartType($response['additionalData']['paymentMethod']);
                        $payment->setAdditionalInformation('cc_type', $ccType);
                        $payment->setCcType($ccType);
                    }
                    $payment->setAdditionalInformation('pspReference', $response['pspReference']);
                    break;
                case "PresentToShopper":
                    $payment->setAdditionalInformation('pspReference', $response['pspReference']);
                    // set additionalData
                    if (isset($response['outputDetails']) && is_array($response['outputDetails'])) {

                        $outputDetails = $response['outputDetails'];
                        if (isset($outputDetails['boletobancario.dueDate'])) {
                            $payment->setAdditionalInformation(
                                'dueDate',
                                $outputDetails['boletobancario.dueDate']
                            );
                        }

                        if (isset($outputDetails['boletobancario.expirationDate'])) {
                            $payment->setAdditionalInformation(
                                'expirationDate',
                                $outputDetails['boletobancario.expirationDate']
                            );
                        }

                        if (isset($outputDetails['boletobancario.url'])) {
                            $payment->setAdditionalInformation(
                                'url',
                                $outputDetails['boletobancario.url']
                            );
                        }
                    }
                    break;
                case "RedirectShopper":

                    $payment->setAdditionalInformation('threeDSType', $response['resultCode']);

                    $redirectUrl = null;
                    $paymentData = null;

                    if (!empty($response['redirect']['url'])) {
                        $redirectUrl = $response['redirect']['url'];
                    }

                    if (!empty($response['redirect']['method'])) {
                        $redirectMethod = $response['redirect']['method'];
                    }

                    if (!empty($response['paymentData'])) {
                        $paymentData = $response['paymentData'];
                    }

                    // If the redirect data is there then the payment is a card payment with 3d secure
                    if (isset($response['redirect']['data']['PaReq']) && isset($response['redirect']['data']['MD'])) {

                        $paReq = null;
                        $md = null;

                        $payment->setAdditionalInformation('3dActive', true);

                        if (!empty($response['redirect']['data']['PaReq'])) {
                            $paReq = $response['redirect']['data']['PaReq'];
                        }

                        if (!empty($response['redirect']['data']['MD'])) {
                            $md = $response['redirect']['data']['MD'];
                        }

                        if ($paReq && $md && $redirectUrl && $paymentData && $redirectMethod) {
                            $payment->setAdditionalInformation('redirectUrl', $redirectUrl);
                            $payment->setAdditionalInformation('redirectMethod', $redirectMethod);
                            $payment->setAdditionalInformation('paRequest', $paReq);
                            $payment->setAdditionalInformation('md', $md);
                            $payment->setAdditionalInformation('paymentData', $paymentData);
                        } else {
                            $isValid = false;
                            $errorMsg = __('3D secure is not valid.');
                            $this->adyenLogger->error($errorMsg);
                            $errorMessages[] = $errorMsg;
                        }
                        // otherwise it is an alternative payment method which only requires the redirect url to be present
                    } else {
                        // Flag to show we are in the checkoutAPM flow
                        $payment->setAdditionalInformation('checkoutAPM', true);

                        if ($redirectUrl && $paymentData && $redirectMethod) {
                            $payment->setAdditionalInformation('redirectUrl', $redirectUrl);
                            $payment->setAdditionalInformation('redirectMethod', $redirectMethod);
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
