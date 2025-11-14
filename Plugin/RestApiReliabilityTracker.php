<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Plugin;

use Adyen\AdyenException;
use Adyen\Payment\Api\Data\AnalyticsEventTypeEnum;
use Adyen\Payment\Helper\AnalyticsEventState;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Logger\AdyenLogger;
use Exception;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Webapi\Controller\Rest\InputParamsResolver;
use Magento\Webapi\Controller\Rest\SynchronousRequestProcessor;
use Throwable;

class RestApiReliabilityTracker
{
    const ADYEN_PAYMENT_NAMESPACE = 'Adyen\Payment';
    const ADYEN_EXPRESS_NAMESPACE = 'Adyen\ExpressCheckout';

    /**
     * @param AnalyticsEventState $analyticsEventState
     * @param InputParamsResolver $inputParamsResolver
     * @param ManagerInterface $eventManager
     * @param Config $configHelper
     * @param StoreManagerInterface $storeManager
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        private readonly AnalyticsEventState $analyticsEventState,
        private readonly InputParamsResolver $inputParamsResolver,
        private readonly ManagerInterface $eventManager,
        private readonly Config $configHelper,
        private readonly StoreManagerInterface $storeManager,
        private readonly AdyenLogger $adyenLogger
    ) {}

    /**
     * @throws Exception
     * @throws Throwable
     */
    public function aroundProcess(SynchronousRequestProcessor $subject, callable $proceed, Request $request)
    {
        $route = $this->inputParamsResolver->getRoute();
        $serviceClassName = $route->getServiceClass();

        $storeId = $this->storeManager->getStore()->getId();
        $isReliabilityDataCollectionEnabled = $this->configHelper->isReliabilityDataCollectionEnabled($storeId);

        if ($isReliabilityDataCollectionEnabled && (str_starts_with($serviceClassName, self::ADYEN_PAYMENT_NAMESPACE) ||
            str_starts_with($serviceClassName, self::ADYEN_EXPRESS_NAMESPACE))) {
            try {
                $this->analyticsEventState->setTopic($serviceClassName);
                $this->eventManager->dispatch(AnalyticsEventState::EVENT_NAME, ['data' => [
                    'relationId' => $this->analyticsEventState->getRelationId(),
                    'type' => AnalyticsEventTypeEnum::EXPECTED_START->value,
                    'topic' => $serviceClassName
                ]]);
            } catch (Exception $exception) {
                $this->adyenLogger->error(sprintf(
                    'Error occurred while dispatching analytics event: %s',
                    $exception->getMessage()
                ));
            }

            try {
                $returnValue = $proceed($request);

                $this->eventManager->dispatch(AnalyticsEventState::EVENT_NAME, ['data' => [
                    'relationId' => $this->analyticsEventState->getRelationId(),
                    'type' => AnalyticsEventTypeEnum::EXPECTED_END->value,
                    'topic' => $serviceClassName
                ]]);

                return $returnValue;
            } catch (AdyenException|NotFoundException $exception) {
                $this->eventManager->dispatch(AnalyticsEventState::EVENT_NAME, ['data' => [
                    'relationId' => $this->analyticsEventState->getRelationId(),
                    'type' => AnalyticsEventTypeEnum::EXPECTED_END->value,
                    'topic' => $serviceClassName
                ]]);

                throw $exception;
            } catch (Throwable $exception) {
                $this->eventManager->dispatch(AnalyticsEventState::EVENT_NAME, ['data' => [
                    'relationId' => $this->analyticsEventState->getRelationId(),
                    'type' => AnalyticsEventTypeEnum::UNEXPECTED_END->value,
                    'topic' => $serviceClassName,
                    'message' => $exception->getMessage()
                ]]);

                throw $exception;
            }
        } else {
            return $proceed($request);
        }
    }
}
