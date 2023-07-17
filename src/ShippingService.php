<?php

namespace App;

use App\Data\AbstractOrder;
use App\Data\BuyerInterface;

class ShippingService implements ShippingServiceInterface
{
    const SHIPPING_CATEGORY_STANDARD = 'Standard';
    const FULFILLMENT_ACTION_SHIP = 'Ship';

   /**
    * @param AbstractOrder $order
    * @param BuyerInterface $buyer
    * @return string
    * @throws ShippingServiceException
    */
    public function ship(AbstractOrder $order, BuyerInterface $buyer): string
    {
        $tracking = [];

        $address = $this->parseAddress($buyer);
        $items = $this->parseItems($order->data);

        $orderId = $order->data['order_unique'] ?? null;
        if (!$orderId) {
            throw new ShippingServiceException('Order ID not set!');
        }

        $comment = $order->data['comments'] ?? null;
        if (!$comment) {
            throw new ShippingServiceException('Order Comment not set!');
        }

        $this->createFulfillmentOrder($orderId, $items, $address, $comment);

        $orderInfo = $this->getFulfillmentOrder($orderId);

        $fulfillmentShipments = $orderInfo['fulfillmentShipments'] ?? [];
        foreach ($fulfillmentShipments as $fulfillmentShipment) {
            $packages = $fulfillmentShipment['fulfillmentShipmentPackage'] ?? [];
            foreach ($packages as $package) {
                $tracking[] = $package['trackingNumber'];
            }
        }

        return implode("\n", array_values(array_unique($tracking)));
    }

   /**
    * @param BuyerInterface $buyer
    * @return array
    * @throws ShippingServiceException
    */
    public function parseAddress(BuyerInterface $buyer): array
    {
        list(
            $name,
            $addressLine1,
            $city,
            $state,
            $postalCode
        ) = explode("\n", $buyer->address);
        $postalCode = preg_replace('/[^0-9]/', '', $postalCode);

        if (!$name
            || !$addressLine1
            || !$city
            || !$state
            || !$postalCode
        ) {
            throw new ShippingServiceException('Address is wrong!');
        }

        return [
            'name' => $name,
            'addressLine1' => $addressLine1,
            'city' => $city,
            'stateOrRegion' => $state,
            'countryCode' => $buyer->country_code,
            'postalCode' => $postalCode,
        ];
    }

   /**
    * @param array $data
    * @return array
    * @throws ShippingServiceException
    */
    public function parseItems(array $data = []): array
    {
        $result = [];
        $products = $data['products'] ?? [];
        foreach ($products as $item) {
            $result[] = [
                'sellerSku' => $item['sku'],
                'sellerFulfillmentOrderItemId' => $item['product_code'],
                'quantity' => $item['ammount'],
            ];
        }

        if (!$result) {
            throw new ShippingServiceException('Order Items not set!');
        }

        return $result;
    }

   /**
    * @param string $orderId
    * @param array $items
    * @param array $address
    * @param string $comment
    * @param string $category
    * @param string $action
    * @param string $displayableOrderDate
    * @return void
    * @throws ShippingServiceException
    */
    public function createFulfillmentOrder(
        string $orderId,
        array  $items,
        array  $address,
        string $comment,
        string $category = self::SHIPPING_CATEGORY_STANDARD,
        string $action = self::FULFILLMENT_ACTION_SHIP,
        string $displayableOrderDate = ''
    ): void {
        $this->request(
            'https://sellingpartnerapi-na.amazon.com/fba/outbound/2020-07-01/fulfillmentOrders',
            [
                'sellerFulfillmentOrderId' => $orderId,
                'displayableOrderId' => $orderId,
                'displayableOrderDate' => $displayableOrderDate ?: date('c'),
                'displayableOrderComment' => $comment,
                'shippingSpeedCategory' => $category,
                'fulfillmentAction' => $action,
                'destinationAddress' => $address,
                'items' => $items
            ]
        );
    }

   /**
    * @param string $orderId
    * @return array
    * @throws ShippingServiceException
    */
    public function getFulfillmentOrder(string $orderId): array
    {
        return $this->request(
            "https://sellingpartnerapi-na.amazon.com/fba/outbound/2020-07-01/fulfillmentOrders/{$orderId}"
        );
    }

   /**
    * @param string $url
    * @param array $postFields
    * @param array $headers
    * @param array $opts
    * @return array
    * @throws ShippingServiceException
    */
    public function request(string $url, array $postFields = [], array $headers = [], array $opts = []): array
    {
        $response = $this->processRequest($url, $postFields, $headers, $opts);
        return $this->parseResult($response);
    }

    /**
    * @param string $url
    * @param array $postFields
    * @param array $headers
    * @param array $opts
    * @return string
    * @throws ShippingServiceException
    */
    public function processRequest(string $url, array $postFields = [], array $headers = [], array $opts = []): string
    {
        $handle = curl_init();

        $opts[CURLOPT_URL] = $url;
        $opts[CURLOPT_RETURNTRANSFER] = 1;

        if (!$headers) {
            $headers[] = 'Expect: ';
        }

        if ($headers) {
            $opts[CURLOPT_HTTPHEADER] = $headers;
        }

        if ($postFields) {
            $opts[CURLOPT_POST] = 1;
            $opts[CURLOPT_POSTFIELDS] = $postFields;
        }

        foreach ($opts as $opt => $value) {
            curl_setopt($handle, $opt, $value);
        }

        $result = curl_exec($handle);
        $errno = curl_errno($handle);
        $error = curl_error($handle);
        if ($result === false) {
            throw new ShippingServiceException("{$errno}: {$error}");
        }
        $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        if ($code >= 400) {
            throw new ShippingServiceException("Error {$code}: {$result}", $code);
        }
        curl_close($handle);

        return $result;
    }

   /**
    * @param string $response
    * @return array
    * @throws ShippingServiceException
    */
    private function parseResult(string $response): array
    {
        $result = json_decode($response, true);
        if (isset($result['errors'])) {
            $this->processErrors($result['errors']);
        }

        return $result['payload'] ?? $result;
    }

   /**
    * @param array $errors
    * @return mixed
    * @throws ShippingServiceException
    */
    private function processErrors(array $errors)
    {
        $msg = [];
        foreach ($errors as $error) {
            $msg[] = "{$error['code']}: {$error['message']} ({$error['details']})";
        }

        throw new ShippingServiceException(implode("\n", $msg));
    }
}
