<?php
/**
 * The repository of a MyParcel consignment
 *
 * If you want to add improvements, please create a fork in our GitHub:
 * https://github.com/myparcelnl
 *
 * @author      Reindert Vetter <reindert@myparcel.nl>
 * @copyright   2010-2017 MyParcel
 * @license     http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US  CC BY-NC-ND 3.0 NL
 * @link        https://github.com/myparcelnl/sdk
 * @since       File available since Release v0.1.0
 */
namespace MyParcelNL\Sdk\src\Model\Repository;


use MyParcelNL\Sdk\src\Model\MyParcelConsignment;
/**
 * The repository of a MyParcel consignment
 *
 * Class MyParcelConsignmentRepository
 * @package MyParcelNL\Sdk\Model\Repository
 */
class MyParcelConsignmentRepository extends MyParcelConsignment
{

    /**
     * Regular expression used to split street name from house number.
     *
     * For the full description go to:
     * @link https://gist.github.com/reindert-vetter/a90fdffe7d452f92d1c65bbf759f6e38
     */
    const SPLIT_STREET_REGEX = '~(?P<street>.*?)\s?(?P<street_suffix>(?P<number>[\d]+)[\s|-]?(?P<number_suffix>[a-zA-Z/\s]{0,5}$|[0-9/]{0,5}$|\s[a-zA-Z]{1}[0-9]{0,3}$|\s[0-9]{2}[a-zA-Z]{0,3}$))$~';

    /**
     * Consignment types
     */
    const TYPE_MORNING             = 1;
    const TYPE_STANDARD            = 2;
    const TYPE_NIGHT               = 3;
    const TYPE_RETAIL              = 4;
    const TYPE_RETAIL_EXPRESS      = 5;

    /**
     * @var array
     */
    private $consignment = [];

    /**
     * Get entire street
     *
     * @return string Entire street
     */
    public function getFullStreet()
    {
        $fullStreet = $this->getStreet();

        if ($this->getNumber()) {
            $fullStreet .= ' ' . $this->getNumber();
        }

        if ($this->getNumberSuffix()) {
            $fullStreet .= ' ' . $this->getNumberSuffix();
        }

        return trim($fullStreet);
    }

    /**
     * Splitting a full NL address and save it in this object
     *
     * Required: Yes or use setStreet()
     *
     * @param $fullStreet
     *
     * @return $this
     * @throws \Exception
     */
    public function setFullStreet($fullStreet)
    {
        if ($this->getCountry() === null) {
            throw new \Exception('First set the country code with setCountry() before running setFullStreet()');
        }

        if ($this->getCountry() == 'NL') {
            $streetData = $this->splitStreet($fullStreet);
            $this->setStreet($streetData['street']);
            $this->setNumber($streetData['number']);
            $this->setNumberSuffix($streetData['number_suffix']);
        } else {
            $this->setStreet($fullStreet);
        }
        return $this;
    }

    /**
     * The total weight for all items in whole grams
     *
     * @todo get weight of all items
     *
     * @return int
     */
    public function getTotalWeight()
    {
        return 1;
    }

    /**
     * Encode all the data before sending it to MyParcel
     *
     * @return array
     */
    public function apiEncode()
    {
        $this
            ->encodeBaseOptions()
            ->encodeStreet()
            ->encodeExtraOptions()
            ->encodeCdCountry();

        return $this->consignment;
    }

    /**
     * Decode all the data after the request with the API
     *
     * @param $data
     *
     * @return $this
     */
    public function apiDecode($data)
    {
        $this
            ->decodeBaseOptions($data)
            ->decodeExtraOptions($data)
            ->decodePickup($data);

        return $this;
    }

    /**
     * Get delivery type from checkout
     *
     * You can use this if you use the following code in your checkout: https://github.com/myparcelnl/checkout
     *
     * @param string $checkoutData
     * @return int
     * @throws \Exception
     */
    public function getDeliveryTypeFromCheckout($checkoutData)
    {
        if ($checkoutData === null) {
            return self::TYPE_STANDARD;
        }

        $aCheckoutData = json_decode($checkoutData, true);
        $deliveryType = self::TYPE_STANDARD;

        if (key_exists('time', $aCheckoutData) &&
            key_exists('price_comment', $aCheckoutData['time'][0]) &&
            $aCheckoutData['time'][0]['price_comment'] !== null
        ) {
            switch ($aCheckoutData['time'][0]['price_comment']) {
                case 'morning':
                    $deliveryType = self::TYPE_MORNING;
                    break;
                case 'standard':
                    $deliveryType = self::TYPE_STANDARD;
                    break;
                case 'night':
                    $deliveryType = self::TYPE_NIGHT;
                    break;
            }
        } elseif (key_exists('price_comment', $aCheckoutData) && $aCheckoutData['price_comment'] !== null) {
            switch ($aCheckoutData['price_comment']) {
                case 'retail':
                    $deliveryType = self::TYPE_RETAIL;
                    break;
                case 'retailexpress':
                    $deliveryType = self::TYPE_RETAIL_EXPRESS;
                    break;
            }
        }

        return $deliveryType;
    }

    /**
     * Convert delivery date from checkout
     *
     * You can use this if you use the following code in your checkout: https://github.com/myparcelnl/checkout
     *
     * @param string $checkoutData
     * @return $this
     * @throws \Exception
     */
    public function setDeliveryDateFromCheckout($checkoutData)
    {
        $aCheckoutData = json_decode($checkoutData, true);

        if (
            !is_array($aCheckoutData) ||
            !key_exists('date', $aCheckoutData)
        ) {
            return $this;
        }

        if ($this->getDeliveryDate() == null) {
            $this->setDeliveryDate($aCheckoutData['date']);
        }

        return $this;
    }

    /**
     * Convert pickup data from checkout
     *
     * You can use this if you use the following code in your checkout: https://github.com/myparcelnl/checkout
     *
     * @param string $checkoutData
     * @return $this
     * @throws \Exception
     */
    public function setPickupAddressFromCheckout($checkoutData)
    {
        if ($this->getCountry() !== 'NL') {
            return $this;
        }

        $aCheckoutData = json_decode($checkoutData, true);

        if (
            !is_array($aCheckoutData) ||
            !key_exists('location', $aCheckoutData)
        ) {
            return $this;
        }

        if ($this->getDeliveryDate() == null) {
            $this->setDeliveryDate($aCheckoutData['date']);
        }

        if ($aCheckoutData['price_comment'] == 'retail') {
            $this->setDeliveryType(4);
        } else if ($aCheckoutData['price_comment'] == 'retailexpress') {
            $this->setDeliveryType(5);
        } else {
            throw new \Exception('No PostNL location found in checkout data: ' . $checkoutData);
        }

        $this
            ->setPickupPostalCode($aCheckoutData['postal_code'])
            ->setPickupStreet($aCheckoutData['street'])
            ->setPickupCity($aCheckoutData['city'])
            ->setPickupNumber($aCheckoutData['number'])
            ->setPickupLocationName($aCheckoutData['location']);

        return $this;
    }

    /**
     * Splits street data into separate parts for street name, house number and extension.
     *
     * @param string $fullStreet The full street name including all parts
     *
     * @return array
     *
     * @throws \Exception
     */
    private function splitStreet($fullStreet)
    {
        $street = '';
        $number = '';
        $number_suffix = '';

        $result = preg_match(self::SPLIT_STREET_REGEX, $fullStreet, $matches);

        if (!$result || !is_array($matches)) {
            // Invalid full street supplied
            throw new \Exception('Invalid full street supplied: ' . $fullStreet);
        }

        if ($fullStreet != $matches[0]) {
            // Characters are gone by preg_match
            throw new \Exception('Something went wrong with splitting up address ' . $fullStreet);
        }

        if (isset($matches['street'])) {
            $street = $matches['street'];
        }

        if (isset($matches['number'])) {
            $number = $matches['number'];
        }

        if (isset($matches['number_suffix'])) {
            $number_suffix = trim($matches['number_suffix']);
        }

        $streetData = array(
            'street' => $street,
            'number' => $number,
            'number_suffix' => $number_suffix,
        );

        return $streetData;
    }

    /**
     * Check if the address is outside the EU
     *
     * @return bool
     */
    private function isCdCountry()
    {
        return !in_array(
            $this->getCountry(),
            self::EU_COUNTRIES
        );
    }

    /**
     * @return $this
     */
    private function encodeBaseOptions()
    {
        $this->consignment = [
            'recipient' => [
                'cc' => $this->getCountry(),
                'person' => $this->getPerson(),
                'postal_code' => $this->getPostalCode(),
                'city' => (string)$this->getCity(),
                'email' => (string)$this->getEmail(),
                'phone' => (string)$this->getPhone(),
            ],
            'options' => [
                'package_type' => $this->getPackageType()?:2,
                'label_description' => $this->getLabelDescription(),
            ],
            'carrier' => 1,
        ];

        if ($this->getReferenceId()) {
            $this->consignment['reference_identifier'] = $this->getReferenceId();
        }

        if ($this->getCompany()) {
            $this->consignment['recipient']['company'] = $this->getCompany();
        }

        return $this;
    }

    /**
     * @return $this
     */
    private function encodeStreet()
    {
        if ($this->getCountry() == 'NL') {
            $this->consignment = array_merge_recursive(
                $this->consignment,
                [
                    'recipient' => [
                        'street' => $this->getStreet(),
                        'number' => $this->getNumber(),
                        'number_suffix' => $this->getNumberSuffix(),
                    ],
                ]
            );
        } else {
            $this->consignment['recipient']['street'] = $this->getFullStreet();
        }

        return $this;
    }

    /**
     * @return $this
     */
    private function encodeExtraOptions() {
        if ($this->getCountry() == 'NL') {
            $this->consignment = array_merge_recursive(
                $this->consignment,
                [
                    'options' => [
                        'large_format' => $this->isLargeFormat() ? 1 : 0,
                        'only_recipient' => $this->isOnlyRecipient() ? 1 : 0,
                        'signature' => $this->isSignature() ? 1 : 0,
                        'return' => $this->isReturn() ? 1 : 0,
                        'delivery_type' => $this->getDeliveryType(),
                    ],
                ]
            );
            $this
                ->encodePickup()
                ->encodeInsurance();
        }

        if ($this->getDeliveryDate()) {
            $this->consignment['options']['delivery_date'] = $this->getDeliveryDate();
        }

        return $this;
    }

    private function encodePickup()
    {
        // Set pickup address
        if (
            $this->getPickupPostalCode() !== null &&
            $this->getPickupStreet() !== null &&
            $this->getPickupCity() !== null &&
            $this->getPickupNumber() !== null &&
            $this->getPickupLocationName() !== null
        ) {
            $this->consignment['pickup'] = [
                'postal_code' => $this->getPickupPostalCode(),
                'street' => $this->getPickupStreet(),
                'city' => $this->getPickupCity(),
                'number' => $this->getPickupNumber(),
                'location_name' => $this->getPickupLocationName(),
            ];
        }

        return $this;
    }

    /**
     * @return $this
     */
    private function encodeInsurance()
    {
        // Set insurance
        if ($this->getInsurance() > 1) {
            $this->consignment['options']['insurance'] = [
                'amount' => (int) $this->getInsurance() * 100,
                'currency' => 'EUR',
            ];
        }

        return $this;
    }

    /**
     * @return $this
     */
    private function encodeCdCountry()
    {
        if ($this->isCdCountry()) {
            $this->consignment = array_merge_recursive(
                $this->consignment, [
                    'customs_declaration' => [
                        'contents' => 1,
                        'weight' => $this->getTotalWeight(),
                        'items' => [
                            [
                                'description' => 'Product',
                                'amount' => 1,
                                'weight' => 0,
                                'classification' => '0000',
                                'country' => 'NL',
                                'item_value' =>
                                    [
                                        'amount' => 100,
                                        'currency' => "EUR",
                                    ],
                            ]
                        ],
                        'invoice' => $this->getLabelDescription(),
                    ],
                    'physical_properties' => [
                        'weight' => $this->getTotalWeight()
                    ]
                ]
            );
        }

        return $this;
    }

    /**
     * @param array $data
     * @return $this
     */
    private function decodeBaseOptions($data)
    {
        $recipient = $data['recipient'];
        $options = $data['options'];

        $this
            ->setMyParcelConsignmentId($data['id'])
            ->setReferenceId($data['reference_identifier'])
            ->setBarcode($data['barcode'])
            ->setStatus($data['status'])
            ->setCountry($recipient['cc'])
            ->setPerson($recipient['person'])
            ->setPostalCode($recipient['postal_code'])
            ->setStreet($recipient['street'])
            ->setCity($recipient['city'])
            ->setEmail($recipient['email'])
            ->setPhone($recipient['phone'])
            ->setPackageType($options['package_type'])
            ->setLabelDescription($options['label_description'])
        ;

        return $this;
    }

    /**
     * @param array $data
     * @return $this
     */
    private function decodeExtraOptions($data)
    {
        $recipient = $data['recipient'];
        $options = $data['options'];

        if (key_exists('company', $recipient)) {
            $this->setCompany($recipient['company']);
        }

        if (key_exists('only_recipient', $recipient)) {
            $this->setOnlyRecipient($recipient['only_recipient']);
        }

        if (key_exists('signature', $recipient)) {
            $this->setSignature($recipient['signature']);
        }

        if (key_exists('return', $recipient)) {
            $this->setReturn($recipient['return']);
        }

        if (key_exists('number', $recipient)) {
            $this->setNumber($recipient['number']);
        }

        if (key_exists('number_suffix', $recipient)) {
            $this->setNumberSuffix($recipient['number_suffix']);
        }

        // Set options
        if (key_exists('insurance', $options)) {
            $insuranceAmount = $options['insurance']['amount'];
            $this->setInsurance($insuranceAmount / 100);
        }

        if (key_exists('delivery_date', $options)) {
            $this->setDeliveryDate($options['delivery_date']);
        }

        if (key_exists('delivery_type', $options)) {
            $this->setDeliveryType($options['delivery_type']);
        }

        return $this;
    }

    /**
     * @param array $data
     * @return $this
     */
    private function decodePickup($data)
    {
        // Set pickup
        if (key_exists('pickup', $data) && $data['pickup'] !== null) {
            $pickup = $data['pickup'];
            if (key_exists('pickup_postal_code', $data['pickup'])) {
                $this->setPickupPostalCode($pickup['pickup_postal_code']);
            }

            if (key_exists('pickup_street', $pickup)) {
                $this->setPickupStreet($pickup['pickup_street']);
            }

            if (key_exists('pickup_city', $pickup)) {
                $this->setPickupCity($pickup['pickup_city']);
            }

            if (key_exists('pickup_number', $pickup)) {
                $this->setPickupNumber($pickup['pickup_number']);
            }

            if (key_exists('pickup_location_name', $pickup)) {
                $this->setPickupLocationName($pickup['pickup_location_name']);
            }
        } else {
            $this
                ->setPickupPostalCode(null)
                ->setPickupStreet(null)
                ->setPickupCity(null)
                ->setPickupNumber(null)
                ->setPickupLocationName(null);
        }

        return $this;
    }
}