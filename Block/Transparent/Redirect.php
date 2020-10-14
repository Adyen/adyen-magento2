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
     * Route path key to make redirect url.
     */
    const ROUTE_PATH = 'route_path';

    /**
     * @var UrlInterface
     */
    private $url;

    /**
     * @param Template\Context $context
     * @param UrlInterface $url
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
     *
     * @return string
     * @since 100.3.5
     */
    public function getRedirectUrl()
    {
        //todo Change the controller path in the $this->getUrl( in this file Block/Redirect/Redirect.php to adyen/transparent/redirect
        return $this->url->getUrl($this->getData(self::ROUTE_PATH));
    }

    /**
     * Returns params to be redirected.
     *
     * Encodes invalid UTF-8 values to UTF-8 to prevent character escape error.
     * Some payment methods, send data in merchant defined language encoding
     * which can be different from the system character encoding (UTF-8).
     *
     * @return array
     * @since 100.3.5
     */
    public function getPostParams()
    {
        $params = [];
        foreach ($this->_request->getPostValue() as $name => $value) {
            if (!empty($value) && mb_detect_encoding($value, 'UTF-8', true) === false) {
                $value = utf8_encode($value);
            }
            $params[$name] = $value;
        }
        return $params;
    }
}
