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
 * Copyright (c) 2020 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Controller\Transparent;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Request\Http as Http;
use Magento\Framework\View\Result\LayoutFactory;
use Magento\Framework\App\Action\Context;

class Redirect extends Action
{
    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    protected $adyenLogger;

    /**
     * @var LayoutFactory
     */
    private $resultLayoutFactory;

    /**
     * Redirect constructor.
     * @param \Adyen\Payment\Logger\AdyenLogger $_adyenLogger
     * @param LayoutFactory $resultLayoutFactory
     */
    public function __construct(
        Context $context,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        LayoutFactory $resultLayoutFactory
    ) {
        $this->adyenLogger = $adyenLogger;
        $this->resultLayoutFactory = $resultLayoutFactory;
        parent::__construct($context);
        if (interface_exists(\Magento\Framework\App\CsrfAwareActionInterface::class)) {
            $request = $this->getRequest();
            if ($request instanceof Http && $request->isPost()) {
                $request->setParam('isAjax', true);
                $request->getHeaders()->addHeaderLine('X_REQUESTED_WITH', 'XMLHttpRequest');
            }
        }
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        $gatewayResponse = $this->getRequest()->getPostValue();
        $this->adyenLogger->addAdyenDebug(
            'Adyen 3DS1 redirect response'
        );

        $resultLayout = $this->resultLayoutFactory->create();
        $resultLayout->getLayout()->getUpdate()->load(['adyen_transparent_redirect']);

        return $resultLayout;
    }
}
