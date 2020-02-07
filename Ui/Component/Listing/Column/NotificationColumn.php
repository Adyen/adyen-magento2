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
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2019 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Ui\Component\Listing\Column;

class NotificationColumn extends \Magento\Ui\Component\Listing\Columns\Column
{

    /**
     * @var \Magento\Sales\Api\Data\OrderInterface
     */
    protected $orderInterface;

    /**
     * @var \Magento\Backend\Helper\Data
     */
    protected $backendHelper;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $adyenHelper;

    public function __construct(
        \Magento\Sales\Api\Data\OrderInterface $orderInterface,
        \Magento\Backend\Helper\Data $backendHelper,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Framework\View\Element\UiComponent\ContextInterface $context,
        \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory,
        array $components = [],
        array $data = []
    ) {
        $this->orderInterface = $orderInterface;
        $this->backendHelper = $backendHelper;
        $this->adyenHelper = $adyenHelper;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }


    /**
     * Style and format Adyen notification columns
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {

        if (isset($dataSource['data']['items'])) {

            foreach ($dataSource['data']['items'] as & $item) {

                //Setting success column CSS class
                switch ($item["success"]) {

                    case "true";
                        $class = "grid-severity-notice";
                        break;
                    case "false";
                        $class = "grid-severity-critical";
                        break;
                    default:
                        $class = "grid-severity-minor";
                        break;

                }
                $item["success"] = sprintf('<span class="%s">%s</span>', $class, $item["success"]);

                //Setting Status "fake" column value based on processing and done values
                if ($item["processing"] == 0) {
                    if ($item["done"] == 0) {
                        $item["status"] = "Queued";
                    } else {
                        $item["status"] = "Processed";
                    }
                } else {
                    $item["status"] = "In progress";
                }

                //Adding anchor link to order number and PSP reference if order number exists
                $this->orderInterface->unsetData();
                $order = $this->orderInterface->loadByIncrementId($item["merchant_reference"]);
                if ($order->getId()) {

                    $orderUrl = $this->backendHelper->getUrl("sales/order/view", ["order_id" => $order->getId()]);
                    $item["merchant_reference"] = sprintf(
                        '<a href="%s">%s</a>',
                        $orderUrl,
                        $item["merchant_reference"]
                    );
                    $item["pspreference"] = sprintf(
                        '<a href="%s" target="_blank">%s</a>',
                        \Adyen\Util\Util::getPspReferenceSearchUrl(
                            $item["pspreference"],
                            $item["live"] === 'false' ? 'test' : 'live'
                        ),
                        $item["pspreference"]
                    );

                }

            }

        }

        return $dataSource;
    }
}