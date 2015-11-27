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
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Info;

use Magento\Framework\View\Element\Template;

class AbstractInfo extends \Magento\Payment\Block\Info
{

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * Constructor
     *
     * @param Template\Context $context
     * @param array $data
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        Template\Context $context,
        array $data = []
    )
    {
        parent::__construct($context, $data);
        $this->_adyenHelper = $adyenHelper;
    }


    public function getAdyenPspReference()
    {
        return $this->getMethod()->getInfoInstance()->getAdyenPspReference();
    }

    public function isDemoMode()
    {
        $storeId = $this->getMethod()->getInfoInstance()->getOrder()->getStoreId();
        return $this->_adyenHelper->getAdyenAbstractConfigDataFlag('demo_mode', $storeId);
    }





}