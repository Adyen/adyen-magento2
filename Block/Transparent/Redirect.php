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

namespace Adyen\Payment\Block\Transparent;

use Magento\Framework\View\Element\Template;
use Magento\Framework\UrlInterface;

class Redirect extends Template
{
    /**
     * @var UrlInterface
     */
    private $url;

    /**
     * Redirect constructor.
     * @param Template\Context $context
     * @param \Magento\Framework\Url $url
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        \Magento\Framework\Url $url,
        array $data = []
    ) {
        $this->url = $url;
        parent::__construct($context, $data);
    }

    /**
     * Returns url for redirect.
     * @return string|null
     */
    public function getRedirectUrl()
    {
        return $this->url->getUrl("adyen/process/redirect");
    }

    /**
     * Returns params to be redirected.
     * @return array
     */
    public function getPostParams()
    {
        return (array)$this->_request->getPostValue();
    }
}
