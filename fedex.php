<?php

ini_set("soap.wsdl_cache_enabled", "0");

/**
 * CodeIgniter Fedex SOAP Class
 *
 * Ability to work with the Fedex SOAP API
 *
 * @package      CodeIgniter
 * @category     Libraries
 * @author       Nithin Meppurathu
 */
Class Fedex {

    var $account_number = '232323';
    var $meter_number   = '232323';
    var $password       = 'ev3rwZOoQ4V3QerD';
    var $key            = 'ssdsdsdsdsdsdsdwererererer';
    var $label_type     = 'pdf'; // pdf , png
    var $label_name     = 'label';
    var $download_location = '/tmp';
    var $countryCode    = 'US';

    var $development    = TRUE;
    var $trace          = FALSE;
    
    // +++++++++++++Constants+++++++++++++ //
    var $timeout        = 90;
    var $connection     = false;
    var $request        = array();
    var $shipper        = array();
    var $recipient      = array();
    var $products       = array();
    var $timestamp      = '';

    var $wsdls_root     = '';
    var $prod_ext       = 'wsdl';
    var $dev_ext        = 'wsdl_beta';

    // +++++++++++++Urls+++++++++++++ //
    var $wsdls = array(
        'AddressValidation' => 'AddressValidationService_v2.wsdl',
        'CloseService'      => 'CloseService_v2.wsdl',
        'LocatorService'    => 'LocatorService_v2.wsdl',
        'PackageMovement'   => 'PackageMovementInformationService_v5.wsdl',
        'PickupService'     => 'PickupService_v3.wsdl',
        'RateService'       => 'RateService_v10.wsdl',
        'ReturnTagService'  => 'ReturnTagService_v1.wsdl',
        'ShipService'       => 'ShipService_v10.wsdl',
        'UploadService'     => 'UploadDocumentService_v1.wsdl',
        'TrackService'      => 'TrackService_v5.wsdl'
    );

    public function __construct()
    {
        $this->wsdls_root = __DIR__ . '/' . 'fedex_api_9';
        $this->defaults();
    }

    public function authenticate()
    {
        $this->request['WebAuthenticationDetail'] = array(
            'UserCredential' =>
                array(
                    'Key' => $this->key,
                    'Password' => $this->password
                )
        );
        $this->request['ClientDetail'] = array(
            'AccountNumber' => $this->account_number, 
            'MeterNumber' => $this->meter_number
        );
    }

    public function defaults()
    {
        $this->timestamp  = date('c');
    }

    public function setEnvironment($environment)
    {
        if($environment == 'production')
        {
            $this->development      = FALSE;
            $this->account_number   = '299200094';
            $this->meter_number     = '103943225';
            $this->password         = 'NyAscptAip7vqm063nmAjEop0';//'4aiVHOWd0IYUqlpBC97R4i7OF';
            $this->key              = 'yB6WJYOIM974RF9U';
        }
        else
            $this->development = TRUE;
    }

    public function setDownloadPath($path)
    {
        $this->download_location = $path;
    }

    /*
     * Ability to set Shipper Information
     *
    */
    public function setShipper($contact = array(), $address = array())
    {
        $this->shipper = array(
             'Contact' => $contact,
             'Address' => $address
         );
        return $this;
    }

    /*
     * Set Recipient
    */
    public function setRecipient($contact = array(), $address = array())
    {
        $this->recipient = array(
             'Contact' => $contact,
             'Address' => $address
        );
        return $this;
    }

    /*
     * Ability to Add Packages to Order
     *
    */
    public function addPackageLineItem($weight = array(), $dimensions = array())
    {
        if(!isset($this->request['RequestedShipment']['RequestedPackageLineItems']))
            $this->request['RequestedShipment']['RequestedPackageLineItems'] = array();

        $package_count = count($this->request['RequestedShipment']['RequestedPackageLineItems']) + 1;
        array_push($this->request['RequestedShipment']['RequestedPackageLineItems'], array(
            'SequenceNumber'=> $package_count,
            'GroupPackageCount'=> $package_count,
            'Weight' => $weight,
            'Dimensions' => $dimensions
        ));

        foreach ($this->request['RequestedShipment']['RequestedPackageLineItems'] as $key => &$value)
        {
            foreach ($value as $key => &$v)
            {
                if($key == 'GroupPackageCount')
                    $v = $package_count;
            }
        }

        return $this;
    }

    private function setTransactionDetail($message)
    {
        $this->request['TransactionDetail'] = array('CustomerTransactionId' => 'Fedex Api - '. $message);
    }

    /*
     * The VersionId element is required and uploads the WSDL version number to FedEx.
     * FedEx provides the latest version number for the service you are using.
     * This number should be updated when you implement a new version of the service.
     *
     * ServiceId - Identifies a system or sub-system which performs an operation.
     * Major - Identifies the service business level.
     * Intermediate - Identifies the service interface level.
     * Minor - Identifies the service code level.
     */
    private function setVersion($service_id, $major = '0', $intermediate = '0', $minor = '0')
    {
        $this->request['Version'] = array(
            'ServiceId' => $service_id, 
            'Major' => $major, 
            'Intermediate' => $intermediate, 
            'Minor' => $minor
        );
    }

    public function setInsurance($amount, $currency='USD')
    {
        $this->request['RequestedShipment']['TotalInsuredValue'] = $this->price($amount,$currency);
        return $this;
    }

    public function contact($name, $phone, $company = 'None')
    {
        return array(
            'PersonName' => $name,
            'PhoneNumber' => $phone,
            'CompanyName' => $company
        );
    }

    public function address($street = array(), $city ,$state, $zip, $country= 'US', $residentional = TRUE)
    {
        return array(
            'StreetLines' => $street,
            'City' => $city,
            'StateOrProvinceCode' => $state,
            'PostalCode' => $zip,
            'CountryCode' => $country,
            'Residential' => $residentional 
        );
    }

    public function date($date = 'now')
    {
        return date('Y-m-d',strtotime($date));
    }

    public function time($date = 'now')
    {
        return strtotime($date);
    }

    public function weight($value, $unit = 'LB')
    {
        return array(
            'Value' => $value,
            'Units' => $unit
        );
    }

    public function dimensions($length, $width, $height, $unit = 'IN')
    {
        return array(
             'Length' => $length,
             'Width' => $width,
             'Height' => $height,
             'Units' => $unit
         );
    }

    public function price($amount, $currency='USD')
    {
        return array(
            'Amount'    => $amount,
            'Currency'  => $currency
        );
    }

    public function serviceAvailability($carrierCode='FDXG', $origin = FALSE, $destination = FALSE, $shipDate)
    {
        $this->setTransactionDetail(__FUNCTION__);
        $this->setVersion('pmis','5');

        $this->request['ShipDate'] = $shipDate;
        if($origin == FALSE)
            $this->request['Origin'] = $this->shipper['Address'];
        if($destination == FALSE)
            $this->request['Destination'] = $this->recipient['Address'];
        else
            $this->request['Destination'] = $destination;

        $this->request['CarrierCode'] = $carrierCode; // valid values FDXC(Cargo), FDXE(Express), FDXG(Ground), FDCC(Custom Critical), FXFR(Freight)

        return $this->process('PackageMovement', __FUNCTION__);
    }

    public function postalCodeInquiry($carrierCode = 'FDXG',$zip,$countryCode = 'US')
    {
        $this->setTransactionDetail(__FUNCTION__);
        $this->setVersion('pmis','5');

        $this->request['CarrierCode'] = $carrierCode; // valid values FDXC(Cargo), FDXE(Express), FDXG(Ground), FDCC(Custom Critical), FXFR(Freight)
        $this->request['PostalCode']  = $zip;
        $this->request['CountryCode'] = $countryCode;

        return $this->process('PackageMovement', __FUNCTION__);
    }

    public function createPickup($carrierCode = 'FDXE', $contact, $address, $weight, $pickupTime,
        $buildingPartCode=FALSE,$buildingPartCodeDescription=FALSE,$packageLocation='FRONT', $companyCloseTime = '20:00:00-05:00',
        $packageCount = '1',$oversizePackageCount = FALSE, $courierRemarks = FALSE)
    {
        $this->setTransactionDetail(__FUNCTION__);
        $this->setVersion('disp','3');

        $this->request['OriginDetail'] = array(
            'PickupLocation' => array(
                'Contact' => $contact,
                'Address' => $address),
            'PackageLocation' => $packageLocation, // valid values NONE, FRONT, REAR and SIDE
            'ReadyTimestamp' => $pickupTime, // Replace with your ready date time
            'CompanyCloseTime' => $companyCloseTime
        );

        if($buildingPartCode)
        {
            $this->request['OriginDetail']['BuildingPartCode'] = $buildingPartCode; // valid values APARTMENT, BUILDING, DEPARTMENT, SUITE, FLOOR and ROOM
            $this->request['OriginDetail']['BuildingPartDescription'] = $buildingPartCodeDescription;
        }

        $this->request['PackageCount'] = $packageCount;
        $this->request['TotalWeight'] = $weight; // valid values LB and KG
        $this->request['CarrierCode'] = $carrierCode;

        if($oversizePackageCount)
        $this->request['OversizePackageCount'] = '1';

        if($courierRemarks)
            $this->request['CourierRemarks'] = $courierRemarks;

        return $this->process('PickupService', __FUNCTION__);
    }

    public function cancelPickup($carrierCode = 'FDXE', $confirmationNumber, $pickupDate, $pickupLocationId, $courierRemarks = FALSE)
    {
        $this->setTransactionDetail(__FUNCTION__);
        $this->setVersion('disp','3');

        $this->request['CarrierCode']   = $carrierCode; // valid values FDXE-Express, FDXG-Ground, etc
        $this->request['PickupConfirmationNumber'] = $confirmationNumber;
        $this->request['ScheduledDate'] = $pickupDate;
        $this->request['Location']      = $pickupLocationId;

        if($courierRemarks)
            $this->request['CourierRemarks'] = $courierRemarks;

        return $this->process('PickupService', __FUNCTION__);
    }

    public function getPickupAvailability($type = array('SAME_DAY', 'FUTURE_DAY'), $address, $dispatchDate=FALSE, $attributes = FALSE, $carriers=array('FDXE','FDXG'), $packageReadyTime=FALSE, $customerCloseTime=FALSE)
    {
        $this->setTransactionDetail(__FUNCTION__);
        $this->setVersion('disp','3');

        $this->request['PickupAddress']       = $address;
        $this->request['PickupRequestType']   = $type;
        $this->request['Carriers']            = $carriers;

        if($dispatchDate)
            $this->request['DispatchDate']        = $dispatchDate;
        if($packageReadyTime)
            $this->request['PackageReadyTime']    = $packageReadyTime;
        if($customerCloseTime)
            $this->request['CustomerCloseTime']   = $customerCloseTime;
        if($attributes)
            $this->request['ShipmentAttributes']  = $attributes;

        return $this->process('PickupService', __FUNCTION__);
    }

    public function fedExLocator($countryCode = 'US', $services = array('Ground'), $address = FALSE, $phone = FALSE)
    {
        $this->setTransactionDetail(__FUNCTION__);
        $this->setVersion('dloc','2');

        if ($phone)
            $this->request['NearToPhoneNumber'] = $phone;
        if ($address)
            $this->rrequest['NearToAddress'] = $address;

        if($services && is_array($services))
        {
            foreach ($services as $value)
            {
               $this->request['DropoffServicesDesired'][$value] = 1;
            }
        }
        
        $this->request['CountryCode'] = $countryCode;
        return $this->process('LocatorService', __FUNCTION__);
    }

    public function addressValidation($addresses = array(), $options = array())
    {
        $this->setTransactionDetail(__FUNCTION__);
        $this->setVersion('aval','2');

        $this->request['RequestTimestamp'] = $this->timestamp;

        $this->request['Options'] = array_merge(array(
            'CheckResidentialStatus' => 1,
            'MaximumNumberOfMatches' => 5,
            'StreetAccuracy' => 'LOOSE',
            'DirectionalAccuracy' => 'LOOSE',
            'CompanyNameAccuracy' => 'LOOSE',
            'ConvertToUpperCase' => 1,
            'RecognizeAlternateCityNames' => 1,
            'ReturnParsedElements' => 1
        ), $options);

        foreach ($addresses as $adress)
           $address_data[] = array('Address' => $adress);

        $this->request['AddressesToValidate'] = $address_data;
        return $this->process('AddressValidation', __FUNCTION__);
    }

    public function track($trackingnumber)
    {
        $this->setTransactionDetail(__FUNCTION__);
        $this->setVersion('trck','5');

        $this->request['PackageIdentifier'] = array(
            'Value' => $trackingnumber,
            'Type' => 'TRACKING_NUMBER_OR_DOORTAG'
        );

        return $this->process('TrackService', __FUNCTION__);
    }

    /*
     * Get Shipper Rates for Item
     */
    public function getRates($dropOffType = 'REGULAR_PICKUP', $serviceType = 'FEDEX_GROUND', $packagingType = 'YOUR_PACKAGING', $rateRequestType = 'LIST')
    {
        $this->validate(array('shipper','recipient'));
        $this->setTransactionDetail(__FUNCTION__);
        $this->setVersion('crs','10');

        $this->request['ReturnTransitAndCommit'] = true;
        $this->request['RequestedShipment'] = array_merge($this->request['RequestedShipment'], array(
            'DropoffType'           => $dropOffType,
            'ShipTimestamp'         => $this->timestamp,
            'ServiceType'           => $serviceType,
            'PackagingType'         => $packagingType,
            'RateRequestTypes'      => $rateRequestType,
        ));

        // Standards
        $this->request['RequestedShipment']['Shipper']   = $this->shipper;
        $this->request['RequestedShipment']['Recipient'] = $this->recipient;
        $this->request['RequestedShipment']['PackageCount'] = count($this->request['RequestedShipment']['RequestedPackageLineItems']);

        return $this->process('RateService', __FUNCTION__);
    }

    /*
     * Get Shipper Rates for Item
     */
    public function validateShipment($dropOffType = 'REGULAR_PICKUP', $serviceType = 'FEDEX_GROUND', $packagingType = 'YOUR_PACKAGING', $rateRequestType = 'LIST')
    {
        $this->validate(array('shipper','recipient'));
        $this->setTransactionDetail(__FUNCTION__);
        $this->setVersion('ship','10');

        $this->request['ReturnTransitAndCommit'] = true;
        $this->request['RequestedShipment'] = array_merge($this->request['RequestedShipment'], array(
            'DropoffType'           => $dropOffType,
            'ShipTimestamp'         => $this->timestamp,
            'ServiceType'           => $serviceType,
            'PackagingType'         => $packagingType,
            'RateRequestTypes'      => $rateRequestType,
        ));

        // Standards
        $this->request['RequestedShipment']['Shipper']   = $this->shipper;
        $this->request['RequestedShipment']['Recipient'] = $this->recipient;
        $this->request['RequestedShipment']['PackageCount'] = count($this->request['RequestedShipment']['RequestedPackageLineItems']);

        $this->setLabelSpecification();

        // International
        if($this->request['RequestedShipment']['Shipper']['Address']['CountryCode'] != $this->countryCode || $this->request['RequestedShipment']['Recipient']['Address']['CountryCode'] != $this->countryCode)
        {
            if(!isset($this->request['RequestedShipment']['CustomsClearanceDetail']))
                throw new Exception('CustomsClearanceDetail must be sent for internation shipments');

            $this->request['RequestedShipment']['CustomerSpecifiedDetail'] = array('MaskedData'=> 'SHIPPER_ACCOUNT_NUMBER');
        }

        return $this->process('ShipService', __FUNCTION__);
    }

    /*
     * Process Fedex Transaction
     *
     */
    public function processShipment($dropOffType = 'REGULAR_PICKUP', $serviceType = 'FEDEX_GROUND', $packagingType = 'YOUR_PACKAGING', $rateRequestType = 'LIST')
    {
        $this->validate(array('shipper','recipient'));
        $this->setTransactionDetail(__FUNCTION__);
        $this->setVersion('ship','10');

        $this->request['ReturnTransitAndCommit'] = true;
        $this->request['RequestedShipment'] = array_merge($this->request['RequestedShipment'], array(
            'DropoffType'           => $dropOffType,
            'ShipTimestamp'         => $this->timestamp,
            'ServiceType'           => $serviceType,
            'PackagingType'         => $packagingType,
            'RateRequestTypes'      => $rateRequestType,
        ));

        // Standards
        $this->request['RequestedShipment']['Shipper']   = $this->shipper;
        $this->request['RequestedShipment']['Recipient'] = $this->recipient;
        $this->request['RequestedShipment']['PackageCount'] = count($this->request['RequestedShipment']['RequestedPackageLineItems']);

        $this->setLabelSpecification();

        // International
        if($this->request['RequestedShipment']['Shipper']['Address']['CountryCode'] != $this->countryCode || $this->request['RequestedShipment']['Recipient']['Address']['CountryCode'] != $this->countryCode)
        {
            if(!isset($this->request['RequestedShipment']['CustomsClearanceDetail']))
                throw new Exception('CustomsClearanceDetail must be sent for internation shipments');

            $this->request['RequestedShipment']['CustomerSpecifiedDetail'] = array('MaskedData'=> 'SHIPPER_ACCOUNT_NUMBER');
        }

        $response = $this->process('ShipService', __FUNCTION__);

        // Download Pdf Label
        $this->downloadLabel($response);

        return $response;
    }

    /*
    TrackingIdType // valid values EXPRESS, GROUND, USPS, etc
     */
    public function deleteShipment($TrackingNumber, $TrackingIdType = 'GROUND', $DeletionControl = 'DELETE_ONE_PACKAGE')
    {
        $this->setTransactionDetail(__FUNCTION__);
        $this->setVersion('ship','10');

        $this->request['ShipTimestamp'] = $this->timestamp;
        $this->request['TrackingId'] = array(
            'TrackingIdType' => $TrackingIdType, 
            'TrackingNumber'=> $$TrackingNumber
        );

        $this->request['DeletionControl'] = $DeletionControl;
        return $this->process('ShipService', __FUNCTION__);
    }

    function downloadLabel($response, $types=array('standard'))
    {
        $tracking_number = $response->CompletedShipmentDetail->CompletedPackageDetails->TrackingIds->TrackingNumber;

        // Make download path just in case
        shell_exec('mkdir -p '.$this->download_location);

        if(in_array('cod', $types))
        {
            $fp = fopen($this->download_location . '/cod_'.$this->label_name.$tracking_number.'.'.$this->label_type, 'wb'); 
            fwrite($fp, $response->CompletedShipmentDetail->CompletedPackageDetails->CodReturnDetail->Label->Parts->Image);
            fclose($fp);
        }

        if(in_array('standard', $types))
        {
            $fp = fopen($this->download_location . '/'.$this->label_name.$tracking_number.'.'.$this->label_type, 'wb');
            fwrite($fp, ($response->CompletedShipmentDetail->CompletedPackageDetails->Label->Parts->Image));
            fclose($fp);
        }
    }

    function addLabelSpecification()
    {
        $labelSpecification = array(
            'LabelFormatType' => 'COMMON2D', // valid values COMMON2D, LABEL_DATA_ONLY
            'ImageType' => 'PDF',  // valid values DPL, EPL2, PDF, ZPLII and PNG
            'LabelStockType' => 'PAPER_7X4.75');
        return $labelSpecification;
    }

    /*
     * Validate required fields are not empty
     */
    private function validate($fields)
    {
        foreach ($fields as $key => $field)
        {
            if(empty($this->{$field}) && $this->{$field} == array())
                throw new Exception('This method requires a '.$field.' to be set');
        }
    }

    /*
     * Process transaction to Fedex
     */
    private function process($service, $method)
    {
        $this->authenticate();

        if($this->trace)
        {
            echo 'Service : '; pp($service);
            echo 'Method : '; pp($method);
            echo 'Request : '; pp($this->request);
        }

        $connection = $this->connection($service);
        if ($connection)
        {
            try
            {
                $response = $connection->{$method}($this->request);
            }
            catch (Exception $e)
            {
                $response = $this->getError($e);
            }
        }

        //$this->clear();
        return $response;
    }

    /*
     * Create Soap connection
     */
    private function connection($connection_method)
    {
        $url = $this->development ? $this->dev_ext : $this->prod_ext;
        $url = $this->wsdls_root . '/' . $url . '/' . $this->wsdls[$connection_method];
        
        if($this->trace)
        {
            echo 'Request Url: '; pp($url);
        }
        
        try 
        {
            $objClient = @new SoapClient($url, array(
                'connection_timeout' => $this->timeout,
                'exceptions' => TRUE, 
                'trace' => $this->trace)
            );
            return $objClient;
        } 
        catch (Exception $e)
        {
            $this->getError($e);
        } 
    }

    /*
     * Render error messages
     */
    private function getError($response)
    {
        pp('<h2>Error returned in processing transaction</h2>');
        pp($response);
        die();
    }

    /*
        Set customs information
    */
    public function setCustomClearanceDetail($paymentType='SENDER', $price, $documentContent='NON_DOCUMENTS', $exportDetail = array('B13AFilingOption' => 'NOT_REQUIRED'))
    {
        switch ($paymentType)
        {
            case 'RECIPIENT':
            case 'THIRD_PARTY':
                $payor_config = array(
                    'PaymentType' => $paymentType
                );
            break;
            
            default:
                $payor_config = array(
                    'PaymentType' => $paymentType,
                    'Payor' => array(
                        'AccountNumber' => $this->account_number,
                        'CountryCode' => $this->countryCode)
                );
            break;
        }

        $this->request['RequestedShipment']['CustomsClearanceDetail'] = array(
            'DutiesPayment' => $payor_config,
            'DocumentContent' => 'NON_DOCUMENTS',                                                                                            
            'CustomsValue' => $price,
            'ExportDetail' => $exportDetail
        );
        $this->request['RequestedShipment']['CustomsClearanceDetail']['Commodities'] = array();
        return $this;
    }

    /*
        Add Commondaties for customs
     */
    public function addCommodities($HarmonizedCode='48201020',$description,$weight,$unit_price,$county='US',$quantity=1, $quantity_unit='EA', $numOfPieces = 1)
    {
        if( ! isset($this->request['RequestedShipment']['CustomsClearanceDetail']))
            throw new Exception('Method addCommodities requires setCustomClearanceDetail');

        array_push($this->request['RequestedShipment']['CustomsClearanceDetail']['Commodities'], array(
            'NumberOfPieces' => $numOfPieces,
            'Description' => $description,
            'Name' => 'Artwork',
            'CountryOfManufacture' => $county,
            'Weight' => $weight,
            'Quantity' => $quantity,
            'QuantityUnits' => $quantity_unit,
            'UnitPrice' => array(
                'Currency' => 'USD', 
                'Amount' => $unit_price
            ),
            'CustomsValue' => array(
                'Currency' => 'USD', 
                'Amount' => $unit_price
            ),
            'HarmonizedCode' => $HarmonizedCode
        ));

        return $this;
    }

    /*
     * Set Shipping Charges to
     array(
            'PaymentType' => 'SENDER',
            'Payor' => array(
                'AccountNumber' => $this->account_number,
                'CountryCode' => 'US')
        );
    */
    public function setShippingChargesPayment($paymentType = 'SENDER')
    {
        switch ($paymentType)
        {
            case 'RECIPIENT':
            case 'THIRD_PARTY':
                $config = array(
                    'PaymentType' => $paymentType,
                    'Payor' => array(
                        'AccountNumber' => $this->account_number,
                        'CountryCode' => $this->countryCode)
                );
            break;
            
            default:
                $config = array(
                    'PaymentType' => $paymentType,
                    'Payor' => array(
                        'AccountNumber' => $this->account_number,
                        'CountryCode' => $this->countryCode)
                );
            break;
        }

        $this->request['RequestedShipment']['ShippingChargesPayment'] = $config;
        return $this;
    }

    public function clear()
    {
        $this->request = array();
        $this->defaults();
    }

    public function setLabelSpecification()
    {
        $this->request['RequestedShipment']['LabelSpecification'] =  array(
            'LabelFormatType' => 'COMMON2D', // valid values COMMON2D, LABEL_DATA_ONLY
            'ImageType' => strtoupper($this->label_type),
            'LabelStockType' => 'PAPER_7X4.75'
        );
    }
}