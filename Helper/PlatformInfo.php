<?php

namespace Adyen\Payment\Helper;

use Adyen\Client;
use Adyen\Model\Checkout\ApplicationInfo;
use Adyen\Model\Checkout\CommonField;
use Adyen\Payment\Gateway\Request\Header\HeaderDataBuilderInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;

class PlatformInfo
{
    const MODULE_NAME = 'adyen-magento2';

    /**
     * @var ComponentRegistrarInterface
     */
    private $componentRegistrar;

    /**
     * @var ProductMetadataInterface
     */
    private ProductMetadataInterface $productMetadata;

    /**
     * @var Http
     */
    private Http $request;

    public function __construct(
        ComponentRegistrarInterface $componentRegistrar,
        ProductMetadataInterface $productMetadata,
        HTTP $request
    ) {
        $this->componentRegistrar = $componentRegistrar;
        $this->productMetadata = $productMetadata;
        $this->request = $request;
    }

    /**
     * Get adyen magento module's name sent to Adyen
     *
     * @return string
     */
    public function getModuleName()
    {
        return (string)self::MODULE_NAME;
    }

    /**
     * Get adyen magento module's version from composer.json
     *
     * @return string
     */
    public function getModuleVersion()
    {
        $moduleDir = $this->componentRegistrar->getPath(
            ComponentRegistrar::MODULE,
            'Adyen_Payment'
        );

        $composerJson = file_get_contents($moduleDir . '/composer.json');
        $composerJson = json_decode($composerJson, true);

        if (empty($composerJson['version'])) {
            return "Version is not available in composer.json";
        }

        return $composerJson['version'];
    }

    public function getMagentoDetails()
    {
        return [
            'name' => $this->productMetadata->getName(),
            'version' => $this->productMetadata->getVersion(),
            'edition' => $this->productMetadata->getEdition(),
        ];
    }

    public function buildApplicationInfo(Client $client) :ApplicationInfo
    {
        $applicationInfo =  new ApplicationInfo();

        $adyenLibrary['name'] = $client->getLibraryName(); // deprecated but no alternative was given.
        $adyenLibrary['version'] = $client->getLibraryVersion(); // deprecated but no alternative was given.

        $applicationInfo->setAdyenLibrary(new CommonField($adyenLibrary));

        if ($adyenPaymentSource = $client->getConfig()->getAdyenPaymentSource()) {
            $applicationInfo->setAdyenPaymentSource(new CommonField($adyenPaymentSource));
        }

        if ($externalPlatform = $client->getConfig()->getExternalPlatform()) {
            $applicationInfo->setExternalPlatform($externalPlatform);
        }

        if ($merchantApplication = $client->getConfig()->getMerchantApplication()) {
            $applicationInfo->setMerchantApplication(new CommonField($merchantApplication));
        }

        return $applicationInfo;
    }

    public function buildRequestHeaders($payment = null)
    {
        $magentoDetails = $this->getMagentoDetails();

        $headers = [
            HeaderDataBuilderInterface::EXTERNAL_PLATFORM_NAME => $magentoDetails['name'],
            HeaderDataBuilderInterface::EXTERNAL_PLATFORM_VERSION => $magentoDetails['version'],
            HeaderDataBuilderInterface::EXTERNAL_PLATFORM_EDITION => $magentoDetails['edition'],
            HeaderDataBuilderInterface::MERCHANT_APPLICATION_NAME => $this->getModuleName(),
            HeaderDataBuilderInterface::MERCHANT_APPLICATION_VERSION  => $this->getModuleVersion()
        ];

        if (isset($payment)) {
            $frontendType = $payment->getAdditionalInformation(HeaderDataBuilderInterface::ADDITIONAL_DATA_FRONTEND_TYPE_KEY);
            if (is_null($frontendType)) {
                // Check the request URI
                $requestPath = $this->request->getOriginalPathInfo();
                $requestMethod = $this->request->getMethod();
                if ($requestPath === '/graphql' && $requestMethod === 'POST') {
                    $frontendType = 'headless-graphql';
                } else {
                    $frontendType = 'headless-rest';
                }
            }
            $headers[HeaderDataBuilderInterface::EXTERNAL_PLATFORM_FRONTEND_TYPE] = $frontendType;
        }

        return $headers;
    }
}
