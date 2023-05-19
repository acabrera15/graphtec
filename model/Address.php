<?php
class Address {

    // public constants
    public const TYPE_COMMERCIAL = 'COMMERCIAL';
    public const TYPE_RESIDENTIAL = 'RESIDENTIAL';
    // end public constants

    // public members
    public ?string  $address1 = null;
    public ?string  $address2 = null;
    public ?string  $address3 = null;
    public ?string  $city = null;
    public ?string  $contact = null;
    public ?string  $country = null;
    public ?string  $fax = null;
    public ?string  $id = null;
    public bool     $is_default_billing = false;
    public bool     $is_default_shipping = false;
    public ?string  $phone1 = null;
    public ?string  $phone2 = null;
    public ?string  $phone3 = null;
    public ?string  $shipping_method = null;
    public ?string  $state = null;
    public string   $type = self::TYPE_COMMERCIAL;
    public ?string  $zip = null;
    // end public members
}