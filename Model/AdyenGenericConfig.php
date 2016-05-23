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
namespace Adyen\Payment\Model;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;
use Psr\Log\LoggerInterface;
use Magento\Payment\Model\Config as PaymentConfig;
use Magento\Framework\View\Asset\Source;

class AdyenGenericConfig
{
    /**
     * @var Repository
     */
    protected $_assetRepo;

    /**
     * @var RequestInterface
     */
    protected $_request;

    /**
     * @var \Magento\Framework\View\Asset\Source
     */
    protected $_assetSource;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * AdyenGenericConfig constructor.
     * 
     * @param Repository $assetRepo
     * @param RequestInterface $request
     * @param Source $assetSource
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(
        Repository $assetRepo,
        RequestInterface $request,
        Source $assetSource,
        \Adyen\Payment\Helper\Data $adyenHelper
    ) {
        $this->_assetRepo = $assetRepo;
        $this->_request = $request;
        $this->_assetSource = $assetSource;
        $this->_adyenHelper = $adyenHelper;
    }

    /**
     * Create a file asset that's subject of fallback system
     *
     * @param string $fileId
     * @param array $params
     * @return \Magento\Framework\View\Asset\File
     */
    public function createAsset($fileId, array $params = [])
    {
        $params = array_merge(['_secure' => $this->_request->isSecure()], $params);
        return $this->_assetRepo->createAsset($fileId, $params);
    }

    /**
     * @param $asset
     * @return bool|string
     */
    public function findRelativeSourceFilePath($asset)
    {
        return $this->_assetSource->findRelativeSourceFilePath($asset);
    }

    /**
     * @return bool
     */
    public function showLogos()
    {
        $showLogos = $this->_adyenHelper->getAdyenAbstractConfigData('title_renderer');
        if ($showLogos == \Adyen\Payment\Model\Config\Source\RenderMode::MODE_TITLE_IMAGE) {
            return true;
        }
        return false;
    }
}