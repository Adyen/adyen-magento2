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
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Result\LayoutFactory;

class Redirect extends Action implements CsrfAwareActionInterface, HttpPostActionInterface
{
    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    protected $_adyenLogger;

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
        \Adyen\Payment\Logger\AdyenLogger $_adyenLogger,
        LayoutFactory $resultLayoutFactory)
    {
        $this->_adyenLogger = $_adyenLogger;
        $this->resultLayoutFactory = $resultLayoutFactory;
    }

    /**
     * @inheritdoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function execute()
    {
        $gatewayResponse = (array)$this->getRequest()->getPostValue();
        $this->_adyenLogger->addAdyenDebug(
            ['Adyen 3DS1 redirect:' => $gatewayResponse]
        );

        $resultLayout = $this->resultLayoutFactory->create();
        $resultLayout->addDefaultHandle();
        $resultLayout->getLayout()->getUpdate()->load(['transparent_payment_redirect']);

        return $resultLayout;
    }
}
