<?php

namespace unit;

use App\Data\AbstractOrder;
use App\Data\BuyerInterface;
use App\ShippingServiceException;
use PHPUnit\Framework\TestCase;
use App\ShippingService;

class ShippingServiceTest extends TestCase
{
    private $service;
    private $buyerMock;
    private $orderMock;

    public function setUp() : void
    {
        parent::setUp();
        $orderDataJson = file_get_contents(APPLICATION_PATH . "/mock/order.16400.json");
        $buyerDataJson = file_get_contents(APPLICATION_PATH . "/mock/buyer.29664.json");

        $this->orderMock = $this->getMockBuilder(AbstractOrder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->orderMock->data = json_decode($orderDataJson, true);

        $this->buyerMock = $this->getMockBuilder(BuyerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $buyerData = json_decode($buyerDataJson);
        $this->buyerMock->address = $buyerData->address;
        $this->buyerMock->country_code = $buyerData->country_code;

        $this->service = $this->getMockBuilder(ShippingService::class)
          ->disableOriginalConstructor()
          ->onlyMethods(['processRequest', 'createFulfillmentOrder'])
          ->getMock();
    }

   /**
    * @return void
    * @throws ShippingServiceException
    */
    public function testParseAddress()
    {
        $address = $this->service->parseAddress($this->buyerMock);

        $this->assertEquals([
            'name' => 'Bfirstname Blastname',
            'addressLine1' => '25 buyer Rd',
            'city' => 'Lock Haven',
            'stateOrRegion' => 'PA',
            'countryCode' => "US",
            'postalCode' => 17745,
        ], $address);
    }

   /**
    * @return void
    * @throws ShippingServiceException
    */
    public function testParseItems()
    {
        $items = $this->service->parseItems($this->orderMock->data);

        $this->assertEquals([
            [
                'sellerSku' => 'RIMN02SGR-10',
                'sellerFulfillmentOrderItemId' => 'RIMN02SI0GRRDRIMN02SGR-10',
                'quantity' => '1',
            ],
            [
                'sellerSku' => 'RIWF04SCQ-10',
                'sellerFulfillmentOrderItemId' => 'RIWF04SI0QUCHRIWF04SCQ-10',
                'quantity' => 1,
            ]
        ], $items);
    }

   /**
    * @return void
    * @throws ShippingServiceException
    */
    public function testShip()
    {
        $this->service->expects(self::once())->method('createFulfillmentOrder');
        $response = <<<JSON
{
  "payload": {
    "fulfillmentOrder": {
      "sellerFulfillmentOrderId": "CONSUMER-2022921-145045",
      "marketplaceId": "ATVPDKIKX0DER",
      "displayableOrderId": "CONSUMER-2022921-145045",
      "displayableOrderDate": "2022-09-21T14:48:15Z",
      "displayableOrderComment": "TestOrder1",
      "shippingSpeedCategory": "Standard",
      "destinationAddress": {
        "name": "Mary Major",
        "addressLine1": "123 Any Street",
        "city": "Any Town",
        "stateOrRegion": "VA",
        "countryCode": "US",
        "postalCode": "22308"
      },
      "fulfillmentAction": "Ship",
      "fulfillmentPolicy": "FillAllAvailable",
      "receivedDate": "2022-09-21T14:50:45Z",
      "fulfillmentOrderStatus": "Complete",
      "statusUpdatedDate": "2022-09-22T03:44:35Z",
      "notificationEmails": [
        "email@email.com"
      ],
      "featureConstraints": [
        {
          "featureName": "BLANK_BOX",
          "featureFulfillmentPolicy": "NotRequired"
        }
      ]
    },
    "fulfillmentOrderItems": [
      {
        "sellerSku": "LT110WHTAM",
        "sellerFulfillmentOrderItemId": "CONSUMER-2022921-145045-0",
        "quantity": "1",
        "fulfillmentNetworkSku": "X002ZKH36D",
        "orderItemDisposition": "Sellable",
        "cancelledQuantity": "0",
        "unfulfillableQuantity": "0",
        "estimatedShipDate": "2022-09-22T06:59:59Z",
        "estimatedArrivalDate": "2022-09-26T06:59:59Z",
        "perUnitDeclaredValue": {
          "currencyCode": "USD",
          "value": "100.00"
        }
      }
    ],
    "fulfillmentShipments": [
      {
        "amazonShipmentId": "T7mfkbDX5",
        "fulfillmentCenterId": "TUL2",
        "fulfillmentShipmentStatus": "SHIPPED",
        "shippingDate": "2022-09-22T03:39:19Z",
        "estimatedArrivalDate": "2022-09-26T06:59:59Z",
        "fulfillmentShipmentItem": [
          {
            "sellerSku": "LT110WHTAM",
            "sellerFulfillmentOrderItemId": "CONSUMER-2022921-145045-0",
            "quantity": "1",
            "packageNumber": "1681854637",
            "serialNumber": "355313088062664"
          }
        ],
        "fulfillmentShipmentPackage": [
          {
            "packageNumber": "1681854637",
            "carrierCode": "Amazon Logistics",
            "trackingNumber": "TBA303037991486",
            "estimatedArrivalDate": "2022-09-26T03:00:00Z"
          }
        ]
      }
    ],
    "returnItems": [],
    "returnAuthorizations": []
  }
}
JSON;
        $this->service->method('processRequest')->willReturn($response);
        $actual = $this->service->ship($this->orderMock, $this->buyerMock);
        $expected = "TBA303037991486";
        $this->assertEquals($expected, $actual);
    }

    public function testShipException()
    {
        $this->expectException(ShippingServiceException::class);
        $this->expectExceptionMessage("Test");
        $this->service->expects(self::once())->method('createFulfillmentOrder');
        $this->service->method('processRequest')->will($this->throwException(new ShippingServiceException("Test")));
        $this->service->ship($this->orderMock, $this->buyerMock);
    }

    public function testShipError()
    {
      $this->expectException(ShippingServiceException::class);
      $this->expectExceptionMessage("111: Test Error (Test error details)");
      $this->service->expects(self::once())->method('createFulfillmentOrder');
      $error = <<<JSON
{
    "errors": [
        {
            "code": "111",
            "message": "Test Error",
            "details": "Test error details"
        }
    ]
}
JSON;

      $this->service->method('processRequest')->willReturn($error);
      $this->service->ship($this->orderMock, $this->buyerMock);
    }
}
