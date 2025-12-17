<?php

namespace Adyen\Payment\Helper;

use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Adyen\Payment\Helper\Util\Uuid;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;

class ShopperConversionId extends AbstractHelper
{
    const SHOPPER_CONVERSION_ID = 'shopper_conversion_id';

    private CartRepositoryInterface $cartRepository;
    private AdyenLogger $adyenLogger;

    /**
     * @param Context $context
     * @param CartRepositoryInterface $cartRepository
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        Context $context,
        CartRepositoryInterface $cartRepository,
        AdyenLogger $adyenLogger
    ) {
        parent::__construct($context);
        $this->cartRepository = $cartRepository;
        $this->adyenLogger = $adyenLogger;
    }

    /**
     * Generate a ShopperConversionID
     *
     * @param Quote $quote
     * @return string|null
     * @throws LocalizedException
     */
    public function getShopperConversionId(Quote $quote): ?string
    {
        try {
            $payment = $quote->getPayment();
            $shopperConversionId = $payment->getAdditionalInformation(self::SHOPPER_CONVERSION_ID);

            if (!empty($shopperConversionId)) {
                return $shopperConversionId;
            }

            $shopperConversionId = Uuid::generateV4();
            $payment->setAdditionalInformation(self::SHOPPER_CONVERSION_ID, $shopperConversionId);
            $quote->setPayment($payment);
            $this->cartRepository->save($quote);

            return $shopperConversionId;
        } catch (\RuntimeException $e) {
            $this->adyenLogger->error('Failed to generate shopperConversionId: ' . $e->getMessage());
            return null;
        }
    }
}
