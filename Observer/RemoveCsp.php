<?php
declare(strict_types=1);

namespace Adyen\Payment\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class RemoveCsp implements ObserverInterface
{
    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Framework\App\Response\Http $response */
        $response = $observer->getEvent()->getData('response');
        /** @var \Magento\Framework\App\Request\Http $request */
        $request = $observer->getEvent()->getData('request');
        if (!$response || !$request) {
            return;
        }
        if ('adyen_webhook_index' === $request->getFullActionName()) {
            $response->clearHeader('content-security-policy-report-only')
                ->clearHeader('Content-Security-Policy-Report-Only')
                ->clearHeader('Content-Security-Policy');
        }
    }
}
