<?php declare(strict_types=1);

/**
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
namespace Adyen\Payment\Test\Unit;

use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\ResourceModel\Order\Payment\Collection;
use Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory as OrderPaymentCollectionFactory;
use Magento\Sales\Model\Order as MagentoOrder;
use Magento\Sales\Model\ResourceModel\Order\Status\Collection as OrderStatusCollection;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory as OrderStatusCollectionFactory;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

abstract class AbstractAdyenTestCase extends TestCase
{
    /**
     * Create a mock with a mix of methods that already exist and others that do not exist.
     * If conditions are requireed so that MockBuilder does not set $this->emptyMethodsArray = 1
     * This was done since setMethods is deprecated
     */
    protected function createMockWithMethods(
        string $originalClassName,
        array $existingMethods,
        array $nonExistingMethods
    ): MockObject {
        $className = $this->createClassWithMagicMethods($originalClassName, $nonExistingMethods);
        $mockBuilder = $this->getMockBuilder($className)->disableOriginalConstructor();

        $methods = array_values(array_unique(array_merge($existingMethods, $nonExistingMethods)));

        if (!empty($methods)) {
            $mockBuilder = $mockBuilder->onlyMethods($methods);
        }

        return $mockBuilder->getMock();
    }

    /**
     * PHPUnit 12 removed MockBuilder::addMethods(), so magic methods (e.g. Magento data getters)
     * cannot be doubled anymore. Declare them on a generated subclass instead and double that.
     *
     * @param string $originalClassName
     * @param string[] $magicMethods
     * @return string Class name to be doubled
     */
    protected function createClassWithMagicMethods(string $originalClassName, array $magicMethods): string
    {
        $originalClassName = ltrim($originalClassName, '\\');

        $magicMethods = array_values(array_filter(
            array_unique($magicMethods),
            fn (string $method): bool => !method_exists($originalClassName, $method)
        ));

        if (empty($magicMethods)) {
            return $originalClassName;
        }

        sort($magicMethods);
        $doubleName = 'MagicMethodDouble_' . hash('sha256', $originalClassName . '::' . implode(',', $magicMethods));
        $doubleClassName = __NAMESPACE__ . '\\' . $doubleName;

        if (class_exists($doubleClassName, false)) {
            return $doubleClassName;
        }

        $declarations = '';

        foreach ($magicMethods as $method) {
            if (!preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $method)) {
                throw new InvalidArgumentException(sprintf('"%s" is not a valid method name.', $method));
            }

            $declarations .= sprintf(
                "    public function %s(...\$arguments)\n    {\n        return null;\n    }\n",
                $method
            );
        }

        $isInterface = interface_exists($originalClassName);
        $isAbstract = !$isInterface && (new ReflectionClass($originalClassName))->isAbstract();

        $code = sprintf(
            "<?php\n\nnamespace %s;\n\n%sclass %s %s \\%s\n{\n%s}\n",
            __NAMESPACE__,
            $isInterface || $isAbstract ? 'abstract ' : '',
            $doubleName,
            $isInterface ? 'implements' : 'extends',
            $originalClassName,
            $declarations
        );

        require_once $this->writeGeneratedClass($doubleName, $code);

        return $doubleClassName;
    }

    /**
     * Writes a generated class to the system temporary directory and returns its path.
     *
     * @param string $doubleName
     * @param string $code
     * @return string
     */
    private function writeGeneratedClass(string $doubleName, string $code): string
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'adyen-payment-test-doubles';

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create the directory "%s".', $directory));
        }

        $file = $directory . DIRECTORY_SEPARATOR . $doubleName . '.php';

        if (!is_file($file) || file_get_contents($file) !== $code) {
            $temporaryFile = tempnam($directory, 'double_');
            file_put_contents($temporaryFile, $code);
            rename($temporaryFile, $file);
        }

        return $file;
    }

    /**
     * @psalm-template RealInstanceType of object
     *
     * @psalm-param class-string<RealInstanceType> $originalClassName
     *
     * @psalm-return MockObject&RealInstanceType
     */
    protected function createGeneratedMock(
        string $originalClassName,
        array $existingMethods = [],
        array $nonExistingMethods = []
    ): MockObject {
        $mockBuilder = $this->getMockBuilder(
            $this->createClassWithMagicMethods($originalClassName, $nonExistingMethods)
        );

        $methods = array_values(array_unique(array_merge($existingMethods, $nonExistingMethods)));

        if (!empty($methods)) {
            $mockBuilder = $mockBuilder->onlyMethods($methods);
        }

        return $mockBuilder->disableOriginalConstructor()
            ->disableOriginalClone()
            ->getMock();
    }

    protected function createOrder(?string $status = null)
    {
        $orderPaymentMock = $this->createConfiguredMock(MagentoOrder\Payment::class, ['getMethod' => 'adyen_cc']);

        return $this->createConfiguredMock(MagentoOrder::class, [
            'getStatus' => $status,
            'getPayment' => $orderPaymentMock,
        ]);
    }

    protected function createWebhook(
        ?string $originalReference = null,
        ?string $pspReference = null,
        ?int $value = 1000
    ) {
        return $this->createConfiguredMock(Notification::class, [
            'getAmountValue' => $value,
            'getEventCode' => 'AUTHORISATION',
            'getAmountCurrency' => 'EUR',
            'getOriginalReference' => $originalReference,
            'getPspreference' => $pspReference
        ]);
    }

    protected function createOrderStatusCollection($state): MockObject
    {
        $orderStatus = $this->createMockWithMethods(MagentoOrder\Status::class, [], ['getState']);
        $orderStatus->method('getState')->willReturn($state);

        $orderStatusCollection = $this->createConfiguredMock(OrderStatusCollection::class, []);
        $orderStatusCollection->method('addFieldToFilter')->willReturn($orderStatusCollection);
        $orderStatusCollection->method('joinStates')->willReturn($orderStatusCollection);
        $orderStatusCollection->method('addStateFilter')->willReturn($orderStatusCollection);
        $orderStatusCollection->method('getFirstItem')->willReturn($orderStatus);

        $orderStatusCollectionFactory = $this->createGeneratedMock(OrderStatusCollectionFactory::class, ['create']);
        $orderStatusCollectionFactory->method('create')->willReturn($orderStatusCollection);

        return $orderStatusCollectionFactory;
    }

    protected function createAdyenOrderPaymentCollection(?int $entityId = null): MockObject
    {
        $adyenOrderPayment = $this->createConfiguredMock(OrderPaymentInterface::class, ['getEntityId' => $entityId]);

        $adyenOrderPaymentCollection = $this->createConfiguredMock(Collection::class, [
            'getFirstItem' => $adyenOrderPayment
        ]);
        $adyenOrderPaymentCollection->method('addFieldToFilter')->willReturn($adyenOrderPaymentCollection);

        $adyenOrderPaymentCollectionFactory = $this->createGeneratedMock(
            OrderPaymentCollectionFactory::class,
            ['create']
        );
        $adyenOrderPaymentCollectionFactory->method('create')->willReturn($adyenOrderPaymentCollection);

        return $adyenOrderPaymentCollectionFactory;
    }

    protected function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);

        return $method->invokeArgs($object, $parameters);
    }
}
