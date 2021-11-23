<?php
class Address {

    // private constants
    private const DEFAULT_TYPE = 'COMMERCIAL';
    // end private constants

    // public members
    public ?string  $address1 = null;
    public ?string  $address2 = null;
    public ?string  $address3 = null;
    public ?string  $city = null;
    public ?string  $contact = null;
    public ?string  $country = null;
    public ?string  $id = null;
    public bool     $is_default_billing = false;
    public bool     $is_default_shipping = false;
    public ?string  $phone1 = null;
    public ?string  $phone2 = null;
    public ?string  $state = null;
    public string   $type = self::DEFAULT_TYPE;
    public ?string  $zip = null;
    // end public members
}