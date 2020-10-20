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
use Magento\Framework\App\Action\Context;

class Redirect extends Action implements CsrfAwareActionInterface, HttpPostActionInterface
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
        LayoutFactory $resultLayoutFactory)
    {
        $this->adyenLogger = $adyenLogger;
        $this->resultLayoutFactory = $resultLayoutFactory;
        parent::__construct($context);
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

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        $gatewayResponse = $this->getRequest()->getPostValue();
        $this->_adyenLogger->addAdyenDebug(
            'Adyen 3DS1 redirect response'
        );

        $resultLayout = $this->resultLayoutFactory->create();
        $resultLayout->getLayout()->getUpdate()->load(['adyen_transparent_redirect']);

        return $resultLayout;
    }
}
