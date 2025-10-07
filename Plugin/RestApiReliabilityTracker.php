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
use Exception;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Webapi\Controller\Rest\InputParamsResolver;
use Magento\Webapi\Controller\Rest\SynchronousRequestProcessor;
use Throwable;

class RestApiReliabilityTracker
{
    const ADYEN_PAYMENT_NAMESPACE = 'Adyen\Payment';
    const ADYEN_EXPRESS_NAMESPACE = 'Adyen\ExpressCheckout';

    public function __construct(
        private readonly AnalyticsEventState $analyticsEventState,
        private readonly InputParamsResolver $inputParamsResolver,
        protected readonly ManagerInterface  $eventManager
    ) {}

    /**
     * @throws Exception
     * @throws Throwable
     */
    public function aroundProcess(SynchronousRequestProcessor $subject, callable $proceed, Request $request): array
    {
        $route = $this->inputParamsResolver->getRoute();
        $serviceClassName = $route->getServiceClass();

        if (str_starts_with($serviceClassName, self::ADYEN_PAYMENT_NAMESPACE) ||
            str_starts_with($serviceClassName, self::ADYEN_EXPRESS_NAMESPACE)) {
            $this->analyticsEventState->setTopic($serviceClassName);
            $this->eventManager->dispatch(AnalyticsEventState::EVENT_NAME, ['data' => [
                'relationId' => $this->analyticsEventState->getRelationId(),
                'type' => AnalyticsEventTypeEnum::EXPECTED_START->value,
                'topic' => $serviceClassName
            ]]);
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
    }
}
