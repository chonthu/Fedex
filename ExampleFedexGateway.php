<?php namespace App\Shipping;

use NitMedia\Fedex\Fedex;

class FedexGateway implements ShippingInterface
{
    /**
     * @var Fedex
     */
    protected $shipper;

    public function __construct(array $config = [])
    {
        $this->shipper = new Fedex($config);
    }

    public function setFromAddress(array $address, $city, $state, $zip, $country)
    {
        $this->shipper->setShipper(
            Fedex::address([$address[0],$address[1]], $city, $state, $zip, $country)
        );
    }

    public function setToAddress(array $address, $city, $state, $zip, $country)
    {
        $this->shipper->setRecipient(
            Fedex::address([$address[0],$address[1]], $city, $state, $zip, $country)
        );
    }

    public function setPackageDetails($weight, $width, $height, $depth)
    {
        $this->shipper = $this->shipper->addPackageLineItem(
            Fedex::weight($weight),
            Fedex::dimensions($width, $height, $depth)
        )->setShippingChargesPayment('SENDER');
    }

    public function getRates()
    {
        $rates = [];
        $response = $this->shipper->getRates('REGULAR_PICKUP', 'GROUND_HOME_DELIVERY');

        if (!in_array($response->HighestSeverity, ['FAILURE', 'ERROR']))
        {
            foreach ($response->RateReplyDetails as $services)
            {
                $key = ucwords(strtolower(str_replace('_', ' ', $services->ServiceType)));
                $rates[$key] = $services->RatedShipmentDetails[0]->ShipmentRateDetail->TotalNetCharge->Amount;
            }
        }
        else
        {
            if (!empty($response->Notifications))
            {
                $data = [];
                foreach ($response->Notifications as $message)
                {
                    $data[] = $message->Message;
                }
                return $data;
            }
            else
            {
                return ["Unable to connect with Fedex"];
            }
        }

        return $rates;
    }
}
