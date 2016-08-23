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

class GeneralResponseValidator extends AbstractValidator
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
        if ($response && isset($response['resultCode'])) {
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
                    $payment->setAdditionalInformation('3dActive', true);
                    $payment->setAdditionalInformation('pspReference', $response['pspReference']);

                    $issuerUrl = $response['issuerUrl'];
                    $paReq = $response['paRequest'];
                    $md = $response['md'];

                    if (!empty($paReq) && !empty($md) && !empty($issuerUrl)) {
                        $payment->setAdditionalInformation('issuerUrl', $response['issuerUrl']);
                        $payment->setAdditionalInformation('paRequest', $response['paRequest']);
                        $payment->setAdditionalInformation('md', $response['md']);
                    } else {
                        $isValid = false;
                        $errorMsg = __('3D secure is not valid.');
                        $this->adyenLogger->error($errorMsg);;
                        $errorMessages[] = $errorMsg;
                    }
                    break;
                case "Refused":
                    if ($response['refusalReason']) {
                        $refusalReason = $response['refusalReason'];
                        switch($refusalReason) {
                            case "Transaction Not Permitted":
                                $errorMsg = __('The transaction is not permitted.');
                                break;
                            case "CVC Declined":
                                $errorMsg = __('Declined due to the Card Security Code(CVC) being incorrect. Please check your CVC code!');
                                break;
                            case "Restricted Card":
                                $errorMsg = __('The card is restricted.');
                                break;
                            case "803 PaymentDetail not found":
                                $errorMsg = __('The payment is REFUSED because the saved card is removed. Please try an other payment method.');
                                break;
                            case "Expiry month not set":
                                $errorMsg = __('The expiry month is not set. Please check your expiry month!');
                                break;
                            default:
                                $errorMsg = __('The payment is REFUSED.');
                                break;
                        }
                    } else {
                        $errorMsg = __('The payment is REFUSED.');
                    }
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