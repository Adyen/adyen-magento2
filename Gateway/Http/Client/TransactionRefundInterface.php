<?php

namespace Adyen\Payment\Gateway\Http\Client;

use Magento\Payment\Gateway\Http\ClientInterface;

interface TransactionRefundInterface extends ClientInterface
{
    const REFUND_AMOUNT = 'refund_amount';
    const REFUND_CURRENCY = 'refund_currency';
    const ORIGINAL_REFERENCE = 'original_reference';
    const PSPREFERENCE = 'pspReference';
}
