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
 * Adyen Payment Module
 *
 * Copyright (c) 2021 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Checkout\Multishipping;

use Adyen\Payment\Helper\PaymentMethods;
use Magento\Payment\Helper\Data;
use Adyen\Payment\Helper\PaymentResponseHandler;
use Adyen\Payment\Model\PaymentResponse;
use Adyen\Payment\Model\ResourceModel\PaymentResponse\Collection;
use Adyen\Payment\Model\Ui\AdyenMultishippingConfigProvider;
use Magento\Checkout\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Multishipping\Model\Checkout\Type\Multishipping;
use Magento\Payment\Model\Checks\SpecificationFactory;
use Magento\Payment\Model\Method\SpecificationInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class Billing extends \Magento\Multishipping\Block\Checkout\Billing
{
    /**
     * @var PaymentMethods
     */
    public $paymentMethodsHelper;

    public $adyenPaymentMethods;

    public function __construct(
        Context $context,
        Data $paymentHelper,
        SpecificationFactory $methodSpecificationFactory,
        Multishipping $multishipping,
        Session $session,
        SpecificationInterface $paymentSpecification,
        PaymentMethods $paymentMethodsHelper
    ) {

        parent::__construct($context, $paymentHelper, $methodSpecificationFactory, $multishipping, $session, $paymentSpecification);

        $this->paymentMethodsHelper = $paymentMethodsHelper;
        $this->setAdyenPaymentMethods();
    }

    public function setAdyenPaymentMethods()
    {
        $quoteId = $this->getQuote()->getId();
        $countryId = $this->getQuote()->getBillingAddress()->getCountryId();

        $paymentMethods = $this->paymentMethodsHelper->getPaymentMethods($quoteId, $countryId);

        if ($paymentMethods) {
            $paymentMethods = json_decode($paymentMethods, true);
            $this->adyenPaymentMethods = $paymentMethods['paymentMethodsResponse']['paymentMethods'];
        }
    }
}
