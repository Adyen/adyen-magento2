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
 * Copyright (c) 2021 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Adyen\Payment\Model\Ui\AdyenCcConfigProvider;
use Adyen\Payment\Model\Ui\AdyenOneclickConfigProvider;
use Adyen\Payment\Model\Ui\AdyenPayByLinkConfigProvider;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class ShopperInteractionDataBuilder implements BuilderInterface
{
    /**
     * @var \Magento\Framework\App\State
     */
    private $appState;

    /**
     * RecurringDataBuilder constructor.
     *
     * @param Context $context
     */
    public function __construct(
        Context $context
    ) {
        $this->appState = $context->getAppState();
    }

    /**
     * @param array $buildSubject
     * @return array
     * @throws LocalizedException
     */
    public function build(array $buildSubject)
    {
        /** @var \Magento\Payment\Gateway\Data\PaymentDataObject $paymentDataObject */
        $paymentDataObject = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        $paymentMethod = $payment->getMethodInstance()->getCode();

        $requestBody = [];
        if ($paymentMethod == AdyenCcConfigProvider::CODE &&
            $this->appState->getAreaCode() == \Magento\Framework\App\Area::AREA_ADMINHTML) {
            // Backend CC orders are MOTO
            $requestBody['body']['shopperInteraction'] = "Moto";
        } elseif ($paymentMethod == AdyenOneclickConfigProvider::CODE && true) {
            // OneClick is ContAuth
            $requestBody['body']['shopperInteraction'] = 'ContAuth';
        } elseif ($paymentMethod == AdyenPayByLinkConfigProvider::CODE) {
            // Don't send shopperInteraction for PBL
        } else {
            // Ecommerce is the default shopperInteraction
            $requestBody['body']['shopperInteraction'] = 'Ecommerce';
        }

        return $requestBody;
    }
}
