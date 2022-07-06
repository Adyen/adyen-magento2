<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Config\Backend;

class Toggle extends \Magento\Framework\App\Config\Value
{
    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param \Magento\Framework\Math\Random $mathRandom
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }
//    Angel suggested to me using the backend model for sending the value of the checkbox to the DB as it is a cleaner
//    solution than using two input fields in the toggle.phtml file - one with type hidden and one with type checkbox

//    However, the problem with the checkbox is that if it is not checked, it doesn't send anything to the DB,
//    so we can't save the value that we assign to the un-checked checkbox to the DB


    /**
     * Prepare data before save
     *
     * @return int
     */

    // putting a breakpoint in line 53, we can see that if the checkbox is unchecked in the admin panel, the debugger isn't stopping at this breakpoint
    // it is stopping at this breakpoint if we send the value to the DB (using the Save Config button) with the checked checkbox

    public function beforeSave()

        // I tried to use beforeSave to set the value of 0 to the checkbox that is unchecked so we can actually send the
        // value of 0 to the DB, but it doesn't work. This block is fully skipped if the checkbox isn't checked.

    {
        $value = $this->getValue();
        if(empty($value)) {
            return "0";
        }else{
         return "1";
        }

    }


    protected function _afterLoad()
    {
        $value = $this->getValue();
        if (empty($value)) {
            return "0";
        }else{
            return "1";
        }
    }
}
