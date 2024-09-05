<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Response;

use Adyen\Payment\Helper\Vault;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment;

class VaultDetailsHandler implements HandlerInterface
{
    /**
     * @var Vault
     */
    private Vault $vaultHelper;

    /**
     * @param Vault $vaultHelper
     */
    public function __construct(Vault $vaultHelper)
    {
        $this->vaultHelper = $vaultHelper;
    }

    /**
     * @param array $handlingSubject
     * @param array $responseCollection
     * @return void
     * @throws LocalizedException
     */
    public function handle(array $handlingSubject, array $responseCollection): void
    {
        // for (non-) partial payments, the non-giftcard payment is always last.
        $response = array_last($responseCollection);

        // payments without additional data or only giftcards should be ignored.
        if (empty($response['additionalData']) || $responseCollection['hasOnlyGiftCards']) {
            return;
        }

        /** @var PaymentDataObject $orderPayment */
        $orderPayment = SubjectReader::readPayment($handlingSubject);
        /** @var Payment $payment */
        $payment = $orderPayment->getPayment();

        // Handle recurring details
        $this->vaultHelper->handlePaymentResponseRecurringDetails($payment, $response);
    }
}
