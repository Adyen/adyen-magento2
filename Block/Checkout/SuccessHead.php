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

namespace Adyen\Payment\Block\Checkout;

use Magento\Framework\View\Element\Template;

/**
 * Template rendered in success page head for including Adyen scripts
 */
class SuccessHead extends \Magento\Framework\View\Element\Template
{

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $adyenHelper;

    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Adyen\Payment\Helper\Data $adyenHelper,
        Template\Context $context,
        array $data = []
    ) {
        $this->storeManager = $storeManager;
        $this->adyenHelper = $adyenHelper;
        parent::__construct($context, $data);
    }


    /**
     * @return mixed
     */
    public function getCheckoutCardComponentJs()
    {
        return $this->adyenHelper->getCheckoutCardComponentJs($this->storeManager->getStore()->getId());
    }
}
