<?php

namespace Adyen\Payment\Gateway\Http\Client;

use Adyen\Payment\Helper\Data;
use Adyen\Payment\Gateway\Request\Header\HeaderDataBuilder;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Gateway\Http\ClientInterface;

abstract class BaseTransaction implements ClientInterface
{

    /**
     * @var Data
     */
    protected Data $adyenHelper;

    /**
     * BaseTransactionClient constructor.
     * @param Data $adyenHelper
     */
    public function __construct(Data $adyenHelper)
    {
        $this->adyenHelper = $adyenHelper;
    }

    /**
     * Builds the headers if they are not provided.
     *
     * @param TransferInterface $transferObject
     * @return array
     */
    protected function requestHeaders(TransferInterface $transferObject): array
    {
        $headers = $transferObject->getHeaders();

        // If no headers exist, build them using HeaderDataBuilder
        if (empty($headers)) {
            $headerBuilder = new HeaderDataBuilder($this->adyenHelper);
            $headers = $headerBuilder->buildRequestHeaders();
        }

        return $headers;
    }
}
