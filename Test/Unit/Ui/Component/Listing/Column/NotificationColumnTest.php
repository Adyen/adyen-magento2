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
 * Adyen Payment Module
 *
 * Copyright (c) 2020 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Ui\Component\Listing\Column;

use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use Adyen\Payment\Ui\Component\Listing\Column\NotificationColumn;
use Magento\Sales\Model\Order;
use Magento\Backend\Helper\Data;
use Adyen\Payment\Helper\Data as AdyenHelper;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;

class NotificationColumnTest extends TestCase
{

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|Order
     */
    private $orderMock;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|Data
     */
    private $dataMock;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|AdyenHelper
     */
    private $adyenHelperMock;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|ContextInterface
     */
    private $contextMock;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|UiComponentFactory
     */
    private $uiComponentMock;

    /**
     * @var NotificationColumn
     */
    private $notificationColumn;

    public function setUp()
    {
        $this->orderMock = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->orderMock->method("loadByIncrementId")
            ->willReturnSelf();
        $this->orderMock->method("getId")
            ->willReturn(1);
        $this->dataMock = $this->getMockBuilder(Data::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->dataMock->method("getUrl")
            ->with("sales/order/view", ["order_id" => 1])
            ->willReturn(
                'https://test.com/index.php/admin/sales/order/view/order_id
                /7/key/90713b7af1b7d29bc585626042062a9c514473d1c692c69be03eb1a8bfb00f74/'
            );
        $this->adyenHelperMock = $this->getMockBuilder(AdyenHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->adyenHelperMock->method("getPspReferenceSearchUrl")
            ->willReturnCallback(
                function ($pspReference) {
                    return "https://ca-test.adyen.com/ca/ca/accounts/showTx.shtml?pspReference=$pspReference";
                }
            );
        $this->contextMock = $this->getMockBuilder(ContextInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->uiComponentMock = $this->getMockBuilder(UiComponentFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->notificationColumn = new NotificationColumn(
            $this->orderMock,
            $this->dataMock,
            $this->adyenHelperMock,
            $this->contextMock,
            $this->uiComponentMock
        );
    }

    /**
     * @dataProvider dataSourceProvider
     * @throws \Exception
     */
    public function testPrepareDataSource($originalDataSource, $modifiedDataSource)
    {
        $resultDataSource = $this->notificationColumn->prepareDataSource($originalDataSource);
        $this->assertEquals($modifiedDataSource, $resultDataSource);
    }

    public function dataSourceProvider()
    {
        return [
            [
                [
                    'data' =>
                        [
                            'items' =>
                                [
                                    0 =>
                                        [
                                            'id_field_name' => 'entity_id',
                                            'entity_id' => '6779',
                                            'pspreference' => '852559045255812F',
                                            'original_reference' => '882559045109501K',
                                            'merchant_reference' => '000000007',
                                            'event_code' => 'REFUND_FAILED',
                                            'success' => 'false',
                                            'payment_method' => 'visa',
                                            'amount_value' => '1000',
                                            'amount_currency' => 'EUR',
                                            'reason' => 'Modification in different currency than authorisation',
                                            'live' => 'false',
                                            'additional_data' => 'a:1:{s:14:"shopperCountry";s:2:"NL";}',
                                            'done' => '1',
                                            'processing' => '0',
                                            'created_at' => '2019-05-28 12:08:53',
                                            'updated_at' => '2020-02-07 09:02:35',
                                            'orig_data' => null,
                                        ],
                                    1 =>
                                        [
                                            'id_field_name' => 'entity_id',
                                            'entity_id' => '6780',
                                            'pspreference' => '852559045465860A',
                                            'original_reference' => '882559045109501K',
                                            'merchant_reference' => '000000007',
                                            'event_code' => 'REFUND',
                                            'success' => 'false',
                                            'payment_method' => 'visa',
                                            'amount_value' => '1500',
                                            'amount_currency' => 'EUR',
                                            'reason' => 'Modification in different currency than authorisation',
                                            'live' => 'false',
                                            'additional_data' => 'a:1:{s:14:"shopperCountry";s:2:"NL";}',
                                            'done' => '1',
                                            'processing' => '0',
                                            'created_at' => '2019-05-28 12:12:05',
                                            'updated_at' => '2020-02-07 09:02:35',
                                            'orig_data' => null,
                                        ],
                                    2 =>
                                        [
                                            'id_field_name' => 'entity_id',
                                            'entity_id' => '6809',
                                            'pspreference' => '882559051426497E',
                                            'original_reference' => '8825562829109806',
                                            'merchant_reference' => '000000007',
                                            'event_code' => 'REFUND',
                                            'success' => 'false',
                                            'payment_method' => 'mc',
                                            'amount_value' => '15000',
                                            'amount_currency' => 'USD',
                                            'reason' => 'Insufficient balance on payment',
                                            'live' => 'false',
                                            'additional_data' => 'a:1:{s:14:"shopperCountry";s:2:"GB";}',
                                            'done' => '1',
                                            'processing' => '0',
                                            'created_at' => '2019-05-28 13:51:19',
                                            'updated_at' => '2020-02-07 09:02:35',
                                            'orig_data' => null,
                                        ],
                                ],
                            'totalRecords' => 3,

                        ]
                ],
                [
                    'data' =>
                        [
                            'items' =>
                                [
                                    0 =>
                                        [
                                            'id_field_name' => 'entity_id',
                                            'entity_id' => '6779',
                                            'pspreference' => '<a href="https://ca-test.adyen.com/ca/ca/accounts/showTx.shtml?pspReference=852559045255812F" target="_blank">852559045255812F</a>',
                                            'original_reference' => '882559045109501K',
                                            'merchant_reference' => '<a href="https://test.com/index.php/admin/sales/order/view/order_id
                /7/key/90713b7af1b7d29bc585626042062a9c514473d1c692c69be03eb1a8bfb00f74/">000000007</a>',
                                            'event_code' => 'REFUND_FAILED',
                                            'success' => '<span class="grid-severity-critical">false</span>',
                                            'payment_method' => 'visa',
                                            'amount_value' => '1000',
                                            'amount_currency' => 'EUR',
                                            'reason' => 'Modification in different currency than authorisation',
                                            'live' => 'false',
                                            'additional_data' => 'a:1:{s:14:"shopperCountry";s:2:"NL";}',
                                            'done' => '1',
                                            'processing' => '0',
                                            'created_at' => '2019-05-28 12:08:53',
                                            'updated_at' => '2020-02-07 09:02:35',
                                            'orig_data' => null,
                                            'status' => 'Processed',
                                        ],
                                    1 =>
                                        [
                                            'id_field_name' => 'entity_id',
                                            'entity_id' => '6780',
                                            'pspreference' => '<a href="https://ca-test.adyen.com/ca/ca/accounts/showTx.shtml?pspReference=852559045465860A" target="_blank">852559045465860A</a>',
                                            'original_reference' => '882559045109501K',
                                            'merchant_reference' => '<a href="https://test.com/index.php/admin/sales/order/view/order_id
                /7/key/90713b7af1b7d29bc585626042062a9c514473d1c692c69be03eb1a8bfb00f74/">000000007</a>',
                                            'event_code' => 'REFUND',
                                            'success' => '<span class="grid-severity-critical">false</span>',
                                            'payment_method' => 'visa',
                                            'amount_value' => '1500',
                                            'amount_currency' => 'EUR',
                                            'reason' => 'Modification in different currency than authorisation',
                                            'live' => 'false',
                                            'additional_data' => 'a:1:{s:14:"shopperCountry";s:2:"NL";}',
                                            'done' => '1',
                                            'processing' => '0',
                                            'created_at' => '2019-05-28 12:12:05',
                                            'updated_at' => '2020-02-07 09:02:35',
                                            'orig_data' => null,
                                            'status' => 'Processed',
                                        ],
                                    2 =>
                                        [
                                            'id_field_name' => 'entity_id',
                                            'entity_id' => '6809',
                                            'pspreference' => '<a href="https://ca-test.adyen.com/ca/ca/accounts/showTx.shtml?pspReference=882559051426497E" target="_blank">882559051426497E</a>',
                                            'original_reference' => '8825562829109806',
                                            'merchant_reference' => '<a href="https://test.com/index.php/admin/sales/order/view/order_id
                /7/key/90713b7af1b7d29bc585626042062a9c514473d1c692c69be03eb1a8bfb00f74/">000000007</a>',
                                            'event_code' => 'REFUND',
                                            'success' => '<span class="grid-severity-critical">false</span>',
                                            'payment_method' => 'mc',
                                            'amount_value' => '15000',
                                            'amount_currency' => 'USD',
                                            'reason' => 'Insufficient balance on payment',
                                            'live' => 'false',
                                            'additional_data' => 'a:1:{s:14:"shopperCountry";s:2:"GB";}',
                                            'done' => '1',
                                            'processing' => '0',
                                            'created_at' => '2019-05-28 13:51:19',
                                            'updated_at' => '2020-02-07 09:02:35',
                                            'orig_data' => null,
                                            'status' => 'Processed',
                                        ],
                                ],
                            'totalRecords' => 3,
                        ]
                ]
            ]
        ];
    }
}
