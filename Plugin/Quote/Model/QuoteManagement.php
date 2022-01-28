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

namespace TIG\GLS\Plugin\Quote\Model;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use TIG\GLS\Model\Config\Provider\Carrier;
use TIG\GLS\Service\DeliveryOptions\Services as ServicesService;

class QuoteManagement
{
    /** @var CartRepositoryInterface $cartRepository */
    private $cartRepository;

    /** @var OrderRepositoryInterface $orderRepository */
    private $orderRepository;

    /** @var ServicesService $services */
    private $services;

    /** @var Carrier $carrier */
    private $carrier;

    /**
     * QuoteManagement constructor.
     *
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        OrderRepositoryInterface $orderRepository,
        ServicesService $services,
        Carrier $carrier
    ) {
        $this->cartRepository  = $cartRepository;
        $this->orderRepository = $orderRepository;
        $this->services = $services;
        $this->carrier = $carrier;
    }

    /**
     * @param $subject
     * @param $cartId
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    // @codingStandardsIgnoreLine
    public function beforePlaceOrder($subject, $cartId)
    {
        $quote           = $this->cartRepository->getActive($cartId);
        $shippingAddress = $quote->getShippingAddress();
        $billingAddress  = $quote->getBillingAddress();
        $deliveryOption  = $shippingAddress->getGlsDeliveryOption();
        $apiOrder = false;

        if (!$deliveryOption) {
            if($this->carrier->getAllowApiOrderService()) {
                $deliveryOption = $this->getDeliveryOptionsForApiOrder($shippingAddress, $billingAddress);
                if (!isset($deliveryOption) || !$deliveryOption) {
                    return;
                }
                $apiOrder = true;
            } else {
                return;
            }
        }

        $deliveryOption = json_decode($deliveryOption);
        $type           = $deliveryOption->type;

        if (!isset($deliveryOption->deliveryAddress)) {
            $deliveryOption->deliveryAddress = $this->mapDeliveryAddress($shippingAddress, $billingAddress);
            $shippingAddress->setGlsDeliveryOption(json_encode($deliveryOption));
        }
        if($apiOrder) {
            $shippingAddress->setGlsDeliveryOption(json_encode($deliveryOption));
        }

        if ($type == Carrier::GLS_DELIVERY_OPTION_PARCEL_SHOP_LABEL) {
            $this->changeShippingAddress($deliveryOption->details, $shippingAddress);
        }
    }

    /**
     * If order is placed through Magento API and auto-select is enabled,
     * order gets first available delivery time.
     *
     * @param $shippingAddress
     * @return false|string
     */
    public function getDeliveryOptionsForApiOrder($shippingAddress, $billingAddress)
    {
        $countryCode = $languageCode = $shippingAddress->getCountryId();
        $postcode = $shippingAddress->getPostcode();
        $services = $this->services->getDeliveryOptions($countryCode, 'NL', $postcode);
        $deliveryOptions = (isset($services['deliveryOptions'])) ? $services['deliveryOptions'] : null;
        if(isset($services['deliveryOptions'])) {
            $deliveryAddress = $this->mapDeliveryAddress($shippingAddress, $billingAddress);
            $autoSelectDelivery = $deliveryOptions[0];
            $autoSelectDelivery['isService'] = false;
            $autoSelectDelivery['hasSubOptions'] = false;
            $autoSelectDelivery['fee'] = null;
            $autoSelectDeliveryResultsArray = array(
                'type' => 'deliveryService',
                'details' => $autoSelectDelivery,
                'deliveryAddress' => $deliveryAddress
            );
            return json_encode($autoSelectDeliveryResultsArray);
        } else {
            return null;
        }
    }

    /**
     * We're saving the DeliveryAddress in the format required by GLS API, so we
     * can always provide it in the same way for either service type.
     *
     * @param $shipping
     *
     * @return object
     */
    public function mapDeliveryAddress($shipping, $billing)
    {
        $company = $shipping->getCompany();
        $shippingName = $shipping->getName();
        if(strlen($shippingName) > 30) {
            $shippingName = substr($shippingName, 0, 30);
        }
        if(isset($company) && strlen($company) > 2) {
            if(strlen($company) > 30) {
                $company = substr($company, 0, 30);
            }
            return (object)[
                'name1' => $company,
                'street' => $shipping->getStreetLine(1),
                'houseNo' => substr($shipping->getStreetLine(2), 0, 10),
                'name2' => $shippingName,
                'name3' => $shipping->getStreetLine(3),
                'countryCode' => $shipping->getCountryId(),
                'zipCode' => $shipping->getPostcode(),
                'city' => $shipping->getCity(),
                // If Shipping Address is same as Billing Address, Email is only saved in Billing.
                'email' => $shipping->getEmail() ?: $billing->getEmail(),
                'phone' => $shipping->getTelephone() ?: '+00000000000',
                'addresseeType' => $shipping->getCompany() ? 'b' : 'p'
            ];
        } else {
            return (object)[
                'name1' => $shippingName,
                'street' => $shipping->getStreetLine(1),
                'houseNo' => substr($shipping->getStreetLine(2), 0, 10),
                'name2' => $shipping->getStreetLine(2),
                'name3' => $shipping->getStreetLine(3),
                'countryCode' => $shipping->getCountryId(),
                'zipCode' => $shipping->getPostcode(),
                'city' => $shipping->getCity(),
                // If Shipping Address is same as Billing Address, Email is only saved in Billing.
                'email' => $shipping->getEmail() ?: $billing->getEmail(),
                'phone' => $shipping->getTelephone() ?: '+00000000000',
                'addresseeType' => $shipping->getCompany() ? 'b' : 'p'
            ];
        }
    }

    /**
     * @param $newAddress
     * @param $shippingAddress
     *
     * @return mixed
     */
    private function changeShippingAddress($newAddress, $shippingAddress)
    {
        $shippingAddress->setStreet($newAddress->street . ' ' . $newAddress->houseNo);
        $shippingAddress->setCompany($newAddress->name);
        $shippingAddress->setPostcode($newAddress->zipcode);
        $shippingAddress->setCity($newAddress->city);
        $shippingAddress->setCountryId($newAddress->countryCode);

        return $shippingAddress;
    }

    /**
     * @param $subject
     * @param $orderId
     * @param $quoteId
     *
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    // @codingStandardsIgnoreLine
    public function afterPlaceOrder($subject, $orderId, $quoteId)
    {
        $order = $this->orderRepository->get($orderId);

        if ($order->getGlsDeliveryOption()) {
            return $orderId;
        }

        $quote          = $this->cartRepository->get($quoteId);
        $address        = $quote->getShippingAddress();
        $deliveryOption = $address->getGlsDeliveryOption();

        if (!$deliveryOption) {
            return $orderId;
        }

        $order->setGlsDeliveryOption($deliveryOption);
        $order->save();

        return $orderId;
    }
}
