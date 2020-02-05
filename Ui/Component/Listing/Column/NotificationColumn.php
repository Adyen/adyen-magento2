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
     * Render Adyen notification Success and Status columns
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {

        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {

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

                $item["success"] = sprintf("<span class='%s'>%s</span>", $class, $item["success"]);

                if ($item["processing"] == 0) {
                    if ($item["done"] == 0) {
                        $item["status"] = "Queued";
                    } else {
                        $item["status"] = "Processed";
                    }
                } else {
                    $item["status"] = "In progress";
                }

            }

        }

        return $dataSource;
    }
}