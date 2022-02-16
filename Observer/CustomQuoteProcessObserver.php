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
 * Copyright (c) 2022 Adyen N.V (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Observer;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CustomQuoteProcessObserver implements ObserverInterface
{
    private $request;

    public function __construct(Http $request)
    {
        $this->request = $request;
    }

    public function execute(Observer $observer)
    {
        /** @var $checkoutSession Session */
        $checkoutSession = $observer->getEvent()->getData('checkout_session');
        if ($checkoutSession && $checkoutSession->hasQuote()) {
            return;
        }

        $moduleName = $this->request->getModuleName();
        $controller = $this->request->getControllerName();
        $action = $this->request->getActionName();
        $route = $moduleName . '_' . $controller . '_' . $action;

        // If there's still a pending payment, the customer didn't return through the normal flow
        if ($route === 'checkout_index_index' && $checkoutSession->hasPendingPayment()) {
            $checkoutSession->restoreQuote();
            $checkoutSession->unsPendingPayment();
        }
    }
}
