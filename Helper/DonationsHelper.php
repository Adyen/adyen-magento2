<?php

namespace Adyen\Payment\Helper;

use Adyen\Model\Checkout\DonationCampaignsRequest;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Adyen\Payment\Helper\Data;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;

class DonationsHelper extends AbstractHelper
{
    const DONATION_CAMPAIGN_AMOUNTS = 'donationCampaignAmounts';
    const DONATION_TYPE_FIXED_AMOUNTS = 'fixedAmounts';
    const DONATION_TYPE_ROUNDUP = 'roundup';

    private Data $adyenHelper;

    /**
     * @var AdyenLogger
     */
    protected $adyenLogger;

    public function __construct(
        Context $context,
        Data $adyenHelper,
        AdyenLogger $adyenLogger
    ) {
        parent::__construct($context);
        $this->adyenHelper = $adyenHelper;
        $this->adyenLogger = $adyenLogger;
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function fetchDonationCampaigns(array $payloadData, int $storeId): array
    {
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
            return [];
        }

        $firstCampaign = $campaignList[0];

        //Doing this workaround for now, Will be fixed with next API Library version
        //RoundUp works with donation.type and not with donation.donationType
        $firstCampaign['donation']['type'] = $firstCampaign['donation']['donationType'] ?? '';
        return [
                'nonprofitName' => $firstCampaign['nonprofitName'] ?? '',
                'nonprofitDescription' => $firstCampaign['nonprofitDescription'] ?? '',
                'nonprofitUrl' => $firstCampaign['nonprofitUrl'] ?? '',
                'logoUrl' => $firstCampaign['logoUrl'] ?? '',
                'bannerUrl' => $firstCampaign['bannerUrl'] ?? '',
                'termsAndConditionsUrl' => $firstCampaign['termsAndConditionsUrl'] ?? '',
                'donation' => $firstCampaign['donation'] ?? [],
                'causeName' => $firstCampaign['causeName'] ?? '',
            ];
    }

    /**
     * @deprecated Use setDonationCampaign() instead, which also stores the permitted donation amounts.
     */
    public function setDonationCampaignId(Order $order, $campaignId): void
    {
        $order->getPayment()->setAdditionalInformation('donationCampaignId', $campaignId);
        $order->save();
    }

    public function setDonationCampaign(Order $order, array $campaign): void
    {
        $donation = $campaign['donation'] ?? [];

        $payment = $order->getPayment();
        $payment->setAdditionalInformation('donationCampaignId', $campaign['id'] ?? null);
        $payment->setAdditionalInformation(self::DONATION_CAMPAIGN_AMOUNTS, [
            'type' => $donation['donationType'] ?? $donation['type'] ?? null,
            'currency' => $donation['currency'] ?? null,
            'values' => array_map('intval', $donation['values'] ?? []),
            'maxRoundupAmount' => isset($donation['maxRoundupAmount'])
                ? (int) $donation['maxRoundupAmount']
                : null
        ]);

        $order->save();
    }

    public function isDonationAmountValid(mixed $campaignAmounts, array $amount, int $originalPaymentAmount): bool
    {
        if (!is_array($campaignAmounts) || empty($campaignAmounts)) {
            return $this->rejectDonationAmount('No donation campaign amounts found for the order.');
        }

        $value = $amount['value'] ?? null;

        if (!is_int($value) || $value <= 0) {
            return $this->rejectDonationAmount('Donation amount value must be a positive integer.');
        }

        if (($amount['currency'] ?? null) !== ($campaignAmounts['currency'] ?? null)) {
            return $this->rejectDonationAmount('Donation currency does not match the donation campaign currency.');
        }

        switch ($campaignAmounts['type'] ?? null) {
            case self::DONATION_TYPE_FIXED_AMOUNTS:
                $isValid = in_array($value, $campaignAmounts['values'] ?? [], true);
                break;
            case self::DONATION_TYPE_ROUNDUP:
                $maxRoundupAmount = (int) ($campaignAmounts['maxRoundupAmount'] ?? 0);
                // Matches the Adyen Web Donation component; an exact multiple donates the full maxRoundupAmount.
                $isValid = $maxRoundupAmount > 0
                    && $value === $maxRoundupAmount - ($originalPaymentAmount % $maxRoundupAmount);
                break;
            default:
                return $this->rejectDonationAmount('Unsupported donation campaign type.');
        }

        if (!$isValid) {
            return $this->rejectDonationAmount(
                sprintf('Donation amount %s is not permitted by the donation campaign.', $value)
            );
        }

        return true;
    }

    private function rejectDonationAmount(string $reason): bool
    {
        $this->adyenLogger->addAdyenWarning('Donation rejected: ' . $reason);

        return false;
    }

}
