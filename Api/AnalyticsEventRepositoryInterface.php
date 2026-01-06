<?php
namespace Adyen\Payment\Api;

use Adyen\Payment\Api\Data\AnalyticsEventInterface;

interface AnalyticsEventRepositoryInterface
{
    public function save(AnalyticsEventInterface $analyticsEvent): AnalyticsEventInterface;

    public function getById(int $id): AnalyticsEventInterface;

    public function delete(AnalyticsEventInterface $analyticsEvent): void;

    public function deleteById(int $id): void;
}
