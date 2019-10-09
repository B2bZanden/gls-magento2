<?php
/**
 *
 *          ..::..
 *     ..::::::::::::..
 *   ::'''''':''::'''''::
 *   ::..  ..:  :  ....::
 *   ::::  :::  :  :   ::
 *   ::::  :::  :  ''' ::
 *   ::::..:::..::.....::
 *     ''::::::::::::''
 *          ''::''
 *
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright   Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license     http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */
namespace TIG\GLS\Model\Config\Source\Carrier;

use Magento\Framework\Option\ArrayInterface;

class CutOffTime implements ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];

        for ($hour = 0; $hour < 24; $hour++) {
            $options = array_merge($options, $this->addHour($hour));
        }

        return $options;
    }

    /**
     * @param $hour
     *
     * @return array
     */
    private function addHour($hour)
    {
        $hour    = str_pad($hour, 2, '0', STR_PAD_LEFT);
        $options = [];

        // @codingStandardsIgnoreStart
        $options[] = ['value' => $hour . ':00:00', 'label' => __($hour . ':00')];
        $options[] = ['value' => $hour . ':30:00', 'label' => __($hour . ':30')];
        // @codingStandardsIgnoreEnd

        return $options;
    }
}
