<?php

namespace Adyen\Payment\Helper;

use Adyen\Model\Checkout\DonationCampaignsRequest;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Adyen\Payment\Helper\Data;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class DonationsHelper extends AbstractHelper
{
    private Data $adyenHelper;

    /**
     * @var Config
     */
    private Config $configHelper;
    /**
     * @var AdyenLogger
     */
    protected $adyenLogger;

    public function __construct(
        Context $context,
        Data $adyenHelper,
        Config $configHelper,
        AdyenLogger $adyenLogger
    ) {
        parent::__construct($context);
        $this->adyenHelper = $adyenHelper;
        $this->configHelper = $configHelper;
        $this->adyenLogger = $adyenLogger;
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function fetchDonationCampaigns(array $payloadData, int $storeId): array
    {
        $payloadData['merchantAccount'] = $this->configHelper->getMerchantAccount($storeId);
        $request = new DonationCampaignsRequest($payloadData);

        try {
            $client = $this->adyenHelper->initializeAdyenClient($storeId);
            $service = $this->adyenHelper->initializeDonationsApi($client);
            return $service->donationCampaigns($request)->toArray();
        } catch (\Adyen\AdyenException $e) {
            $this->adyenLogger->error('Error fetching donation campaigns', ['exception' => $e]);
            throw new LocalizedException(__('Unable to retrieve donation campaigns. Please try again later.'));
        }
    }

    //Return the data of the first campaign only.
    public function formatCampaign(array $donationCampaignsResponse): array
    {
        $campaignList = $donationCampaignsResponse['donationCampaigns'] ?? [];

        if (empty($campaignList)) {
            return ['donationCampaigns' => []];
        }

        $firstCampaign = $campaignList[0];

        return [
            'donationCampaigns' => [[
                'nonprofitName' => $firstCampaign['nonprofitName'] ?? '',
                'description' => $firstCampaign['nonprofitDescription'] ?? '',
                'reference' => $firstCampaign['id'] ?? '',
                'nonprofitUrl' => $firstCampaign['nonprofitUrl'] ?? '',
                'logoUrl' => $firstCampaign['logoUrl'] ?? '',
                'bannerUrl' => $firstCampaign['bannerUrl'] ?? '',
                'termsAndConditionsUrl' => $firstCampaign['termsAndConditionsUrl'] ?? '',
                'donation' => $firstCampaign['donation'] ?? []
            ]]
        ];
    }

}
