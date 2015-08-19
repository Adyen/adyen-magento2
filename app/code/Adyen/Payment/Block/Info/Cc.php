<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Adyen\Payment\Block\Info;

class Cc extends \Magento\Payment\Block\Info
{

    /**
     * @var string
     */
    protected $_template = 'Adyen_Payment::info/cc.phtml';



    /**
     * @return string
     */
//    public function toPdf()
//    {
//        $this->setTemplate('Magento_OfflinePayments::info/pdf/checkmo.phtml');
//        return $this->toHtml();
//    }
}
