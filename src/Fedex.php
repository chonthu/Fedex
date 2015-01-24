<?php namespace NitMedia\Shipping\Fedex;

use NitMedia\Exceptions\Exception;
use Illuminate\Support\MessageBag;
use SoapClient;

ini_set("soap.wsdl_cache_enabled", "0");

/**
 * Fedex SOAP Class
 *
 * Ability to work with the Fedex SOAP API
 *
 * @category     Libraries
 * @author       Nithin Meppurathu
 */
class Fedex
{
	const TIME_OUT = 90;

	protected $account_number;
	protected $meter_number;
	protected $password;
	protected $key;

	public $label_type = 'pdf'; // pdf , png
	public $label_name = 'label';
	public $download_location = '/tmp';
	public $countryCode = 'US';

	var $development = true;
	var $trace = false;

	var $connection = false;

	var $request = [];
	var $shipper = [];
	var $recipient = [];
	var $products = [];
	var $timestamp;

	var $wsdls_root;
	var $prod_ext = 'wsdl';
	var $dev_ext = 'wsdl_beta';

	/**
	 * WSDLs File Names
	 *
	 * @var array
	 */
	var $wsdls = [
		'AddressValidation' => 'AddressValidationService_v2.wsdl',
		'CloseService' => 'CloseService_v2.wsdl',
		'LocatorService' => 'LocatorService_v2.wsdl',
		'PackageMovement' => 'PackageMovementInformationService_v5.wsdl',
		'PickupService' => 'PickupService_v3.wsdl',
		'RateService' => 'RateService_v10.wsdl',
		'ReturnTagService' => 'ReturnTagService_v1.wsdl',
		'ShipService' => 'ShipService_v10.wsdl',
		'UploadService' => 'UploadDocumentService_v1.wsdl',
		'TrackService' => 'TrackService_v5.wsdl'
	];

	public function __construct($config = [])
	{
		$options = array_merge($this->defaults(), $config);

		// Append to object
		foreach($options as $key => $option){
			$this->{$key} = $option;
		}
	}

	public function authenticate()
	{
		$this->request['WebAuthenticationDetail'] = [
			'UserCredential' =>
				[
					'Key' => $this->key,
					'Password' => $this->password
				]
		];
		$this->request['ClientDetail'] = [
			'AccountNumber' => $this->account_number,
			'MeterNumber' => $this->meter_number
		];
	}

	public function defaults()
	{
		return [
			'timestamp' => time(),
			'wsdls_root' => __DIR__
		];
	}

	/**
	 * Fedex contact information
	 *
	 * @param $name
	 * @param $phone
	 * @param string $company
	 * @return array
	 */
	public static function contact($name, $phone, $company = 'None')
	{
		return [
			'PersonName' => $name,
			'PhoneNumber' => $phone,
			'CompanyName' => $company
		];
	}

	/**
	 * Setup Fedex formatted address
	 *
	 * @param array $street
	 * @param $city
	 * @param $state
	 * @param $zip
	 * @param string $country
	 * @param bool $residentional
	 * @return array
	 */
	public static function address(array $street = [], $city, $state, $zip, $country = 'US', $residentional = true)
	{
		return [
			'StreetLines' => $street,
			'City' => $city,
			'StateOrProvinceCode' => $state,
			'PostalCode' => $zip,
			'CountryCode' => $country,
			'Residential' => $residentional
		];
	}

	/**
	 * Return formatted date
	 *
	 * @param string $date
	 * @return bool|string
	 */
	public static function date($date = 'now')
	{
		return date('Y-m-d', strtotime($date));
	}

	/**
	 * Return formatted time
	 *
	 * @param string $date
	 * @return int
	 */
	public static function time($date = 'now')
	{
		return strtotime($date);
	}

	/**
	 * Return weight format
	 *
	 * @param $value
	 * @param string $unit
	 * @return array
	 */
	public static function weight($value, $unit = 'LB')
	{
		return [
			'Value' => $value,
			'Units' => $unit
		];
	}

	/**
	 * Return dimensions format
	 *
	 * @param $length
	 * @param $width
	 * @param $height
	 * @param string $unit
	 * @return array
	 */
	public static function dimensions($length, $width, $height, $unit = 'IN')
	{
		return [
			'Length' => $length,
			'Width' => $width,
			'Height' => $height,
			'Units' => $unit
		];
	}

	/**
	 * Return price format
	 *
	 * @param $amount
	 * @param string $currency
	 * @return array
	 */
	public static function price($amount, $currency = 'USD')
	{
		return [
			'Amount' => $amount,
			'Currency' => $currency
		];
	}

	/**
	 * Set path of where downloads should be placed
	 *
	 * @param $path
	 */
	public function setDownloadPath($path)
	{
		$this->download_location = $path;
	}

	/**
	 * Ability to set Shipper Information
	 */
	public function setShipper($address = [], $contact = false)
	{
        if($contact) {
            $this->shipper['Contact'] = $contact;
        }
        $this->shipper['Address'] = $address;
		return $this;
	}

	/**
	 * Set Recipient
	 */
	public function setRecipient($address = [], $contact = false)
	{
        if($contact) {
            $this->recipient['Contact'] = $contact;
        }
        $this->recipient['Address'] = $address;
        return $this;
	}

	/**
	 * Ability to Add Packages to Order
	 *
	 */
	public function addPackageLineItem($weight = [], $dimensions = [])
	{
		if (!isset($this->request['RequestedShipment']['RequestedPackageLineItems']))
			$this->request['RequestedShipment']['RequestedPackageLineItems'] = [];

		$package_count = count($this->request['RequestedShipment']['RequestedPackageLineItems']) + 1;
		array_push(
			$this->request['RequestedShipment']['RequestedPackageLineItems'],
			[
				'SequenceNumber' => $package_count,
				'GroupPackageCount' => $package_count,
				'Weight' => $weight,
				'Dimensions' => $dimensions
			]
		);

		foreach ($this->request['RequestedShipment']['RequestedPackageLineItems'] as &$value)
		{
			foreach ($value as $key2 => &$v)
			{
				if ($key2 == 'GroupPackageCount')
					$v = $package_count;
			}
		}

		return $this;
	}

	/**
	 * Set the detail of the transaction message
	 *
	 * @param $message
	 */
	private function setTransactionDetail($message)
	{
		$this->request['TransactionDetail'] = ['CustomerTransactionId' => 'Fedex Api - ' . $message];
	}

	/**
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
		$this->request['Version'] = [
			'ServiceId' => $service_id,
			'Major' => $major,
			'Intermediate' => $intermediate,
			'Minor' => $minor
		];
	}

	/**
	 * Add Insurance to package
	 *
	 * @param $amount
	 * @param string $currency
	 * @return $this
	 */
	public function setInsurance($amount, $currency = 'USD')
	{
		$this->request['RequestedShipment']['TotalInsuredValue'] = $this->price($amount, $currency);
		return $this;
	}

	/**
	 * @param string $carrierCode FDXC(Cargo), FDXE(Express), FDXG(Ground), FDCC(Custom Critical), FXFR(Freight)
	 * @param bool $origin
	 * @param bool $destination
	 * @param $shipDate
	 * @return array|MessageBag
	 */
	public function serviceAvailability($carrierCode = 'FDXG', $origin = false, $destination = false, $shipDate)
	{
		$this->setTransactionDetail(__FUNCTION__);
		$this->setVersion('pmis', '5');

		$this->request['ShipDate'] = $shipDate;
		if ($origin == false)
			$this->request['Origin'] = $this->shipper['Address'];
		if ($destination == false)
			$this->request['Destination'] = $this->recipient['Address'];
		else
			$this->request['Destination'] = $destination;

		$this->request['CarrierCode'] = $carrierCode;

		return $this->process('PackageMovement', __FUNCTION__);
	}

	/**
	 * @param string $carrierCode [FDXC(Cargo), FDXE(Express), FDXG(Ground), FDCC(Custom Critical), FXFR(Freight)]
	 * @param $zip
	 * @param string $countryCode
	 * @return array|MessageBag
	 */
	public function postalCodeInquiry($carrierCode = 'FDXG', $zip, $countryCode = 'US')
	{
		$this->setTransactionDetail(__FUNCTION__);
		$this->setVersion('pmis', '5');

		$this->request['CarrierCode'] = $carrierCode;
		$this->request['PostalCode'] = $zip;
		$this->request['CountryCode'] = $countryCode;

		return $this->process('PackageMovement', __FUNCTION__);
	}

	public function createPickup(
		$carrierCode = 'FDXE',
		$contact,
		$address,
		$weight,
		$pickupTime,
		$buildingPartCode = false,
		$buildingPartCodeDescription = false,
		$packageLocation = 'FRONT',
		$companyCloseTime = '20:00:00-05:00',
		$packageCount = '1',
		$oversizePackageCount = false,
		$courierRemarks = false)
	{
		$this->setTransactionDetail(__FUNCTION__);
		$this->setVersion('disp', '3');

		$this->request['OriginDetail'] = [
			'PickupLocation' => [
				'Contact' => $contact,
				'Address' => $address
			],
			'PackageLocation' => $packageLocation, // valid values NONE, FRONT, REAR and SIDE
			'ReadyTimestamp' => $pickupTime, // Replace with your ready date time
			'CompanyCloseTime' => $companyCloseTime
		];

		if ($buildingPartCode)
		{
			$this->request['OriginDetail']['BuildingPartCode'] = $buildingPartCode; // valid values APARTMENT, BUILDING, DEPARTMENT, SUITE, FLOOR and ROOM
			$this->request['OriginDetail']['BuildingPartDescription'] = $buildingPartCodeDescription;
		}

		$this->request['PackageCount'] = $packageCount;
		$this->request['TotalWeight'] = $weight; // valid values LB and KG
		$this->request['CarrierCode'] = $carrierCode;

		if ($oversizePackageCount)
			$this->request['OversizePackageCount'] = '1';

		if ($courierRemarks)
			$this->request['CourierRemarks'] = $courierRemarks;

		return $this->process('PickupService', __FUNCTION__);
	}

	public function cancelPickup(
		$carrierCode = 'FDXE',
		$confirmationNumber,
		$pickupDate,
		$pickupLocationId,
		$courierRemarks = false)
	{
		$this->setTransactionDetail(__FUNCTION__);
		$this->setVersion('disp', '3');

		$this->request['CarrierCode'] = $carrierCode; // valid values FDXE-Express, FDXG-Ground, etc
		$this->request['PickupConfirmationNumber'] = $confirmationNumber;
		$this->request['ScheduledDate'] = $pickupDate;
		$this->request['Location'] = $pickupLocationId;

		if ($courierRemarks)
			$this->request['CourierRemarks'] = $courierRemarks;

		return $this->process('PickupService', __FUNCTION__);
	}

	public function getPickupAvailability(
		$type = ['SAME_DAY', 'FUTURE_DAY'],
		$address,
		$dispatchDate = false,
		$attributes = false,
		$carriers = ['FDXE', 'FDXG'],
		$packageReadyTime = false,
		$customerCloseTime = false)
	{
		$this->setTransactionDetail(__FUNCTION__);
		$this->setVersion('disp', '3');

		$this->request['PickupAddress'] = $address;
		$this->request['PickupRequestType'] = $type;
		$this->request['Carriers'] = $carriers;

		if ($dispatchDate)
			$this->request['DispatchDate'] = $dispatchDate;
		if ($packageReadyTime)
			$this->request['PackageReadyTime'] = $packageReadyTime;
		if ($customerCloseTime)
			$this->request['CustomerCloseTime'] = $customerCloseTime;
		if ($attributes)
			$this->request['ShipmentAttributes'] = $attributes;

		return $this->process('PickupService', __FUNCTION__);
	}

	public function fedExLocator($countryCode = 'US', $services = ['Ground'], $address = false, $phone = false)
	{
		$this->setTransactionDetail(__FUNCTION__);
		$this->setVersion('dloc', '2');

		if ($phone)
			$this->request['NearToPhoneNumber'] = $phone;
		if ($address)
			$this->request['NearToAddress'] = $address;

		if ($services && is_array($services))
		{
			foreach ($services as $value)
			{
				$this->request['DropoffServicesDesired'][$value] = 1;
			}
		}

		$this->request['CountryCode'] = $countryCode;
		return $this->process('LocatorService', __FUNCTION__);
	}

	public function addressValidation($addresses = [], $options = [])
	{
		$this->setTransactionDetail(__FUNCTION__);
		$this->setVersion('aval', '2');

		$this->request['RequestTimestamp'] = $this->timestamp;

		$this->request['Options'] = array_merge(
			[
				'CheckResidentialStatus' => 1,
				'MaximumNumberOfMatches' => 5,
				'StreetAccuracy' => 'LOOSE',
				'DirectionalAccuracy' => 'LOOSE',
				'CompanyNameAccuracy' => 'LOOSE',
				'ConvertToUpperCase' => 1,
				'RecognizeAlternateCityNames' => 1,
				'ReturnParsedElements' => 1
			],
			$options
		);

		$address_data = [];
		foreach ($addresses as $adress)
		{
			$address_data[] = ['Address' => $adress];
		}

		$this->request['AddressesToValidate'] = $address_data;
		return $this->process('AddressValidation', __FUNCTION__);
	}

	public function track($trackingnumber)
	{
		$this->setTransactionDetail(__FUNCTION__);
		$this->setVersion('trck', '5');

		$this->request['PackageIdentifier'] = [
			'Value' => $trackingnumber,
			'Type' => 'TRACKING_NUMBER_OR_DOORTAG'
		];

		return $this->process('TrackService', __FUNCTION__);
	}

	/*
	 * Get Shipper Rates for Item
	 */
	public function getRates(
		$dropOffType = 'REGULAR_PICKUP',
		$serviceType = 'FEDEX_GROUND',
		$packagingType = 'YOUR_PACKAGING',
		$rateRequestType = 'LIST')
	{
		$this->validate(['shipper', 'recipient']);
		$this->setTransactionDetail(__FUNCTION__);
		$this->setVersion('crs', '10');
		$this->request['ReturnTransitAndCommit'] = true;
		$this->request['RequestedShipment'] = array_merge(
			$this->request['RequestedShipment'],
			[
				'DropoffType' => $dropOffType,
				'ShipTimestamp' => $this->timestamp,
				//'ServiceType' => $serviceType,
				'PackagingType' => $packagingType,
				'RateRequestTypes' => $rateRequestType,
			]
		);

		// Standards
		$this->request['RequestedShipment']['Shipper'] = $this->shipper;
		$this->request['RequestedShipment']['Recipient'] = $this->recipient;
		$this->request['RequestedShipment']['PackageCount'] = count(
			$this->request['RequestedShipment']['RequestedPackageLineItems']
		);

		return $this->process('RateService', __FUNCTION__);
	}

	/*
	 * Get Shipper Rates for Item
	 */
	public function validateShipment(
		$dropOffType = 'REGULAR_PICKUP',
		$serviceType = 'FEDEX_GROUND',
		$packagingType = 'YOUR_PACKAGING',
		$rateRequestType = 'LIST')
	{
		$this->validate(['shipper', 'recipient']);
		$this->setTransactionDetail(__FUNCTION__);
		$this->setVersion('ship', '10');

		$this->request['ReturnTransitAndCommit'] = true;
		$this->request['RequestedShipment'] = array_merge(
			$this->request['RequestedShipment'],
			[
				'DropoffType' => $dropOffType,
				'ShipTimestamp' => $this->timestamp,
				'ServiceType' => $serviceType,
				'PackagingType' => $packagingType,
				'RateRequestTypes' => $rateRequestType,
			]
		);

		// Standards
		$this->request['RequestedShipment']['Shipper'] = $this->shipper;
		$this->request['RequestedShipment']['Recipient'] = $this->recipient;
		$this->request['RequestedShipment']['PackageCount'] = count(
			$this->request['RequestedShipment']['RequestedPackageLineItems']
		);

		$this->setLabelSpecification();

		// International
		if ($this->request['RequestedShipment']['Shipper']['Address']['CountryCode'] != $this->countryCode || $this->request['RequestedShipment']['Recipient']['Address']['CountryCode'] != $this->countryCode)
		{
			if (!isset($this->request['RequestedShipment']['CustomsClearanceDetail']))
				throw new Exception('CustomsClearanceDetail must be sent for internation shipments');

			$this->request['RequestedShipment']['CustomerSpecifiedDetail'] = ['MaskedData' => 'SHIPPER_ACCOUNT_NUMBER'];
		}

		return $this->process('ShipService', __FUNCTION__);
	}

	/*
	 * Process Fedex Transaction
	 *
	 */
	public function processShipment(
		$dropOffType = 'REGULAR_PICKUP',
		$serviceType = 'FEDEX_GROUND',
		$packagingType = 'YOUR_PACKAGING',
		$rateRequestType = 'LIST')
	{
		$this->validate(['shipper', 'recipient']);
		$this->setTransactionDetail(__FUNCTION__);
		$this->setVersion('ship', '10');

		$this->request['ReturnTransitAndCommit'] = true;
		$this->request['RequestedShipment'] = array_merge(
			$this->request['RequestedShipment'],
			[
				'DropoffType' => $dropOffType,
				'ShipTimestamp' => $this->timestamp,
				'ServiceType' => $serviceType,
				'PackagingType' => $packagingType,
				'RateRequestTypes' => $rateRequestType,
			]
		);

		// Standards
		$this->request['RequestedShipment']['Shipper'] = $this->shipper;
		$this->request['RequestedShipment']['Recipient'] = $this->recipient;
		$this->request['RequestedShipment']['PackageCount'] = count(
			$this->request['RequestedShipment']['RequestedPackageLineItems']
		);

		$this->setLabelSpecification();

		// International
		if ($this->request['RequestedShipment']['Shipper']['Address']['CountryCode'] != $this->countryCode || $this->request['RequestedShipment']['Recipient']['Address']['CountryCode'] != $this->countryCode)
		{
			if (!isset($this->request['RequestedShipment']['CustomsClearanceDetail']))
				throw new Exception('CustomsClearanceDetail must be sent for internation shipments');

			$this->request['RequestedShipment']['CustomerSpecifiedDetail'] = ['MaskedData' => 'SHIPPER_ACCOUNT_NUMBER'];
		}

		$response = $this->process('ShipService', __FUNCTION__);

		// Download Pdf Label
		$this->downloadLabel($response);

		return $response;
	}

	/**
	 * TrackingIdType // valid values EXPRESS, GROUND, USPS, etc
	 */
	public function deleteShipment($TrackingNumber, $TrackingIdType = 'GROUND', $DeletionControl = 'DELETE_ONE_PACKAGE')
	{
		$this->setTransactionDetail(__FUNCTION__);
		$this->setVersion('ship', '10');

		$this->request['ShipTimestamp'] = $this->timestamp;
		$this->request['TrackingId'] = [
			'TrackingIdType' => $TrackingIdType,
			'TrackingNumber' => $TrackingNumber
		];

		$this->request['DeletionControl'] = $DeletionControl;
		return $this->process('ShipService', __FUNCTION__);
	}

	function downloadLabel($response, $types = ['standard'])
	{
		$tracking_number = $response->CompletedShipmentDetail->CompletedPackageDetails->TrackingIds->TrackingNumber;

		// Make download path just in case
		shell_exec('mkdir -p ' . $this->download_location);

		if (in_array('cod', $types))
		{
			$fp = fopen(
				$this->download_location . '/cod_' . $this->label_name . $tracking_number . '.' . $this->label_type,
				'wb'
			);
			fwrite(
				$fp,
				$response->CompletedShipmentDetail->CompletedPackageDetails->CodReturnDetail->Label->Parts->Image
			);
			fclose($fp);
		}

		if (in_array('standard', $types))
		{
			$fp = fopen(
				$this->download_location . '/' . $this->label_name . $tracking_number . '.' . $this->label_type,
				'wb'
			);
			fwrite($fp, ($response->CompletedShipmentDetail->CompletedPackageDetails->Label->Parts->Image));
			fclose($fp);
		}
	}

	function addLabelSpecification()
	{
		$labelSpecification = [
			'LabelFormatType' => 'COMMON2D', // valid values COMMON2D, LABEL_DATA_ONLY
			'ImageType' => 'PDF', // valid values DPL, EPL2, PDF, ZPLII and PNG
			'LabelStockType' => 'PAPER_7X4.75'
		];
		return $labelSpecification;
	}

	/**
	 * Validate required fields are not empty
	 */
	private function validate($fields)
	{
		foreach ($fields as $field)
		{
			if (empty($this->{$field}) && $this->{$field} == [])
				throw new Exception('This method requires a ' . $field . ' to be set');
		}
	}

	/**
	 * Process transaction to Fedex
	 */
	private function process($service, $method)
	{
		$this->authenticate();
		$response = [];

		if ($this->trace)
		{
			echo 'Service : ';
			print_r($service);
			echo 'Method : ';
			print_r($method);
			echo 'Request : ';
			print_r($this->request);
		}

		$connection = $this->connection($service);
		if ($connection)
		{
			try
			{
				$response = $connection->{$method}($this->request);
			} catch (Exception $e)
			{
				$response = $this->getError($e);
			}
		}

		return $response;
	}

	/*
	 * Create Soap connection
	 */
	private function connection($connection_method)
	{
		$url = $this->development ? $this->dev_ext : $this->prod_ext;
		$url = $this->wsdls_root . '/' . $url . '/' . $this->wsdls[$connection_method];

		if ($this->trace)
		{
			echo 'Request Url: ';
			print_r($url);
		}

		try
		{
			$objClient = @new SoapClient(
				$url, [
					'connection_timeout' => self::TIME_OUT,
					'exceptions' => true,
					'trace' => $this->trace
				]
			);

			return $objClient;

		} catch (Exception $e)
		{
			$this->getError($e);
		}

        return false;
	}

	/**
	 * Render error messages
	 */
	private function getError($response)
	{
		return new MessageBag(array_merge(['Error returned in processing transaction'], $response));
	}

	/**
	 * Set customs information
	 */
	public function setCustomClearanceDetail(
		$paymentType = 'SENDER',
		$price,
		$documentContent = 'NON_DOCUMENTS',
		$exportDetail = ['B13AFilingOption' => 'NOT_REQUIRED'])
	{
		switch ($paymentType)
		{
			case 'RECIPIENT':
			case 'THIRD_PARTY':
				$payor_config = [
					'PaymentType' => $paymentType
				];
				break;

			default:
				$payor_config = [
					'PaymentType' => $paymentType,
					'Payor' => [
						'AccountNumber' => $this->account_number,
						'CountryCode' => $this->countryCode
					]
				];
				break;
		}

		$this->request['RequestedShipment']['CustomsClearanceDetail'] = [
			'DutiesPayment' => $payor_config,
			'DocumentContent' => 'NON_DOCUMENTS',
			'CustomsValue' => $price,
			'ExportDetail' => $exportDetail
		];
		$this->request['RequestedShipment']['CustomsClearanceDetail']['Commodities'] = [];

		return $this;
	}

	/**
	 * Add Commondaties for customs
	 */
	public function addCommodities(
		$HarmonizedCode = '48201020',
		$description,
		$weight,
		$unit_price,
		$county = 'US',
		$quantity = 1,
		$quantity_unit = 'EA',
		$numOfPieces = 1)
	{
		if (!isset($this->request['RequestedShipment']['CustomsClearanceDetail']))
		{
			throw new Exception('Method addCommodities requires setCustomClearanceDetail');
		}

		array_push(
			$this->request['RequestedShipment']['CustomsClearanceDetail']['Commodities'],
			[
				'NumberOfPieces' => $numOfPieces,
				'Description' => $description,
				'Name' => 'Artwork',
				'CountryOfManufacture' => $county,
				'Weight' => $weight,
				'Quantity' => $quantity,
				'QuantityUnits' => $quantity_unit,
				'UnitPrice' => [
					'Currency' => 'USD',
					'Amount' => $unit_price
				],
				'CustomsValue' => [
					'Currency' => 'USD',
					'Amount' => $unit_price
				],
				'HarmonizedCode' => $HarmonizedCode
			]
		);

		return $this;
	}

	/**
	 * Set Shipping Charges to
	 *
	 * array(
	 *		'PaymentType' => 'SENDER',
	 *		'Payer' => array(
	 *			'AccountNumber' => $this->account_number,
	 *			'CountryCode' => 'US')
	 *	);
	 */
	public function setShippingChargesPayment($paymentType = 'SENDER')
	{
		switch ($paymentType)
		{
			case 'RECIPIENT':
			case 'THIRD_PARTY':
				$config = [
					'PaymentType' => $paymentType,
					'Payor' => [
						'AccountNumber' => $this->account_number,
						'CountryCode' => $this->countryCode
					]
				];
				break;

			default:
				$config = [
					'PaymentType' => $paymentType,
					'Payor' => [
						'AccountNumber' => $this->account_number,
						'CountryCode' => $this->countryCode
					]
				];
				break;
		}

		$this->request['RequestedShipment']['ShippingChargesPayment'] = $config;

		return $this;
	}

	/**
	 * Clear internals for new transaction
	 */
	public function clear()
	{
		$this->request = [];
		$this->defaults();
	}

	/**
	 * Specify label specs
	 */
	public function setLabelSpecification()
	{
		$this->request['RequestedShipment']['LabelSpecification'] = [
			'LabelFormatType' => 'COMMON2D', // valid values COMMON2D, LABEL_DATA_ONLY
			'ImageType' => strtoupper($this->label_type),
			'LabelStockType' => 'PAPER_7X4.75'
		];
	}
}