<?php

namespace Adyen\Payment\Gateway\Request;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data as AdyenDataHelper;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Store\Model\StoreManagerInterface;

class InstallmentOptionsDataBuilder implements BuilderInterface
{
    public function __construct(
        private readonly Config $configHelper,
        private readonly SerializerInterface $serializer,
        private readonly StoreManagerInterface $storeManager,
        private readonly AdyenDataHelper $adyenHelper
    ) {
    }

    public function build(array $buildSubject): array
    {
        /** @var PaymentDataObject $paymentDataObject */
        $paymentDataObject = SubjectReader::readPayment($buildSubject);
        $orderAmount = $paymentDataObject->getOrder()->getGrandTotalAmount();

        $storeId = $this->storeManager->getStore()->getId();

        if (!$this->configHelper->getAdyenCcConfigData('enable_installments', $storeId)) {
            return [];
        }

        $raw = $this->configHelper->getAdyenCcConfigData('installments', $storeId);
        if (empty($raw)) {
            return [];
        }

        $installmentsConfig = $this->serializer->unserialize($raw);

        $installmentOptions = $this->formatInstallmentOptions($installmentsConfig, $orderAmount);

        return empty($installmentOptions)
            ? []
            : ['body' => ['installmentOptions' => $installmentOptions]];
    }

    private function formatInstallmentOptions(array $config, float $orderAmount): array
    {
        $brandMap = $this->getBrandMapFromXml();
        $result = [];

        foreach ($config as $brandCode => $rules) {
            $pm = $brandMap[$brandCode] ?? null;
            if (!$pm || !is_array($rules)) {
                continue;
            }

            $thresholds = $this->parseThresholds($rules);
            if (!$thresholds) {
                continue;
            }

            $values = $this->collectEligibleValues($thresholds, $orderAmount);

            $values[] = 1;

            $values = array_values(array_unique(array_filter($values, static fn($v) => $v > 0)));
            sort($values, SORT_NUMERIC);

            if ($values) {
                $result[$pm] = ['values' => $values];
            }
        }

        return $result;
    }

    /**
     * Converts config rules into a sorted map: [minAmount(float) => values(int[])]
     * Accepts common shapes:
     * - [minAmount => [2,3]]
     * - [minAmount => ['values' => [2,3]]]
     * - [minAmount => 3]
     */
    private function parseThresholds(array $rules): array
    {
        $thresholds = [];

        foreach ($rules as $minAmount => $installments) {
            $values = $this->normalizeInstallmentValues($installments);
            if (!$values) {
                continue;
            }

            $thresholds[(float)$minAmount] = $values;
        }

        if (!$thresholds) {
            return [];
        }

        ksort($thresholds, SORT_NUMERIC);
        return $thresholds;
    }

    private function collectEligibleValues(array $thresholds, float $orderAmount): array
    {
        $values = [];

        foreach ($thresholds as $minAmount => $tierValues) {
            if ($orderAmount < (float)$minAmount) {
                break;
            }
            // Append values from each eligible tier
            $values = array_merge($values, $tierValues);
        }

        return $values;
    }

    private function normalizeInstallmentValues(mixed $installments): array
    {
        // If admin config stores something like ['values' => [2,3]]
        if (is_array($installments) && isset($installments['values']) && is_array($installments['values'])) {
            $installments = $installments['values'];
        }

        if (is_numeric($installments)) {
            return [(int)$installments];
        }

        if (is_array($installments)) {
            $out = [];
            foreach ($installments as $v) {
                if (is_numeric($v)) {
                    $out[] = (int)$v;
                }
            }
            return $out;
        }

        return [];
    }

    private function getBrandMapFromXml(): array
    {
        $altData = $this->adyenHelper->getCcTypesAltData();

        $map = [];
        foreach ($altData as $altCode => $data) {
            if (!empty($data['code'])) {
                $map[$data['code']] = $altCode;
            }
        }

        return $map;
    }
}
