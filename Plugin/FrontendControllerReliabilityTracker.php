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
use Adyen\Webhook\Exception\AuthenticationException;
use Adyen\Webhook\Exception\InvalidDataException;
use Exception;
use Magento\Framework\App\FrontController;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NotFoundException;
use Throwable;

class FrontendControllerReliabilityTracker
{
    const ADYEN_CONTROLLER_URI = '/adyen/';

    public function __construct(
        private readonly AnalyticsEventState $analyticsEventState,
        protected readonly ManagerInterface  $eventManager
    ) {}

    /**
     * @throws Exception
     * @throws Throwable
     */
    public function aroundDispatch(
        FrontController $subject,
        callable $proceed,
        RequestInterface $request
    ): ResultInterface {
        $serviceUri = $request->getPathInfo();

        if (str_starts_with($serviceUri, self::ADYEN_CONTROLLER_URI)) {
            $this->analyticsEventState->setTopic($serviceUri);
            $this->eventManager->dispatch(AnalyticsEventState::EVENT_NAME, ['data' => [
                'relationId' => $this->analyticsEventState->getRelationId(),
                'type' => AnalyticsEventTypeEnum::EXPECTED_START->value,
                'topic' => $serviceUri
            ]]);
        }

        try {
            $returnValue = $proceed($request);

            $this->eventManager->dispatch(AnalyticsEventState::EVENT_NAME, ['data' => [
                'relationId' => $this->analyticsEventState->getRelationId(),
                'type' => AnalyticsEventTypeEnum::EXPECTED_END->value,
                'topic' => $serviceUri
            ]]);

            return $returnValue;
        } catch (AdyenException|InvalidDataException|AuthenticationException|LocalizedException $exception) {
            $this->eventManager->dispatch(AnalyticsEventState::EVENT_NAME, ['data' => [
                'relationId' => $this->analyticsEventState->getRelationId(),
                'type' => AnalyticsEventTypeEnum::EXPECTED_END->value,
                'topic' => $serviceUri
            ]]);

            throw $exception;
        } catch (Throwable $exception) {
            $this->eventManager->dispatch(AnalyticsEventState::EVENT_NAME, ['data' => [
                'relationId' => $this->analyticsEventState->getRelationId(),
                'type' => AnalyticsEventTypeEnum::UNEXPECTED_END->value,
                'topic' => $serviceUri,
                'message' => $exception->getMessage()
            ]]);

            throw $exception;
        }
    }
}
