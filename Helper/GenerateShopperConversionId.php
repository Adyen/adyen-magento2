<?php

namespace Adyen\Payment\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Adyen\Payment\Helper\Util\Uuid;
use Magento\Checkout\Model\Session as CheckoutSession;

class GenerateShopperConversionId extends AbstractHelper
{
    const SHOPPER_CONVERSION_ID = 'shopper_conversion_id';

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @param Context $context
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Get or generate a ShopperConversionID
     *
     * @return string
     */
    public function getShopperConversionId(): string
    {
        $shopperConversionId = Uuid::generateV4();

        $quote = $this->checkoutSession->getQuote();
        $payment = $quote->getPayment();

        // Store shopperConversionId in additional information
        $payment->setAdditionalInformation(self::SHOPPER_CONVERSION_ID, $shopperConversionId);

        // Save the quote to persist additional_information
        $quote->setPayment($payment);
        $quote->save();

        return $shopperConversionId;
    }
}
