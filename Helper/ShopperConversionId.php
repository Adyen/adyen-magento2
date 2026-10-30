<?php

namespace Adyen\Payment\Helper;

use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Adyen\Payment\Helper\Util\Uuid;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote\Payment as QuotePaymentResource;

class ShopperConversionId extends AbstractHelper
{
    const SHOPPER_CONVERSION_ID = 'shopper_conversion_id';

    private QuotePaymentResource $quotePaymentResource;
    private AdyenLogger $adyenLogger;

    /**
     * @param Context $context
     * @param QuotePaymentResource $quotePaymentResource
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        Context $context,
        QuotePaymentResource $quotePaymentResource,
        AdyenLogger $adyenLogger
    ) {
        parent::__construct($context);
        $this->quotePaymentResource = $quotePaymentResource;
        $this->adyenLogger = $adyenLogger;
    }

    /**
     * Generate a ShopperConversionID
     *
     * @param Quote $quote
     * @return string|null
     */
    public function getShopperConversionId(Quote $quote): ?string
    {
        $payment = null;
        $isGenerated = false;

        try {
            $payment = $quote->getPayment();
            $shopperConversionId = $payment->getAdditionalInformation(self::SHOPPER_CONVERSION_ID);

            if (!empty($shopperConversionId)) {
                return $shopperConversionId;
            }

            if (!$quote->getId()) {
                return null;
            }

            $shopperConversionId = Uuid::generateV4();
            $payment->setAdditionalInformation(self::SHOPPER_CONVERSION_ID, $shopperConversionId);
            $isGenerated = true;
            $this->quotePaymentResource->save($payment);

            return $shopperConversionId;
        } catch (\Exception $e) {
            if ($isGenerated) {
                $payment->unsAdditionalInformation(self::SHOPPER_CONVERSION_ID);
            }

            $this->adyenLogger->error('Failed to generate shopperConversionId: ' . $e->getMessage());
            return null;
        }
    }
}
