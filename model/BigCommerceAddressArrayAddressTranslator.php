<?php

use JetBrains\PhpStorm\Pure;

class BigCommerceAddressArrayAddressTranslator {

    // private constants
    private const BILLING_ADDRESS_PREFIX = 'B';
    private const SHIPPING_ADDRESS_PREFIX = 'S';
    // end private constants

    // private members
    private int     $additional_id;
    private Address $address;
    private array   $bc_address_arr;
    private int     $customer_id;
    private bool    $is_billing_address;
    // end private members

    // public functions
    #[Pure]
    public function __construct(array $bc_address_arr, bool $is_billing_address, int $customer_id, int $additional_id){
        $this->bc_address_arr = $bc_address_arr;
        $this->is_billing_address = $is_billing_address;
        $this->customer_id = $customer_id;
        $this->additional_id = $additional_id;
        $this->address = new Address();
    }

    public function translate(): Address {

        $this->address = new Address();
        $this->address->address1 = $this->bc_address_arr['street_1'];
        $this->address->address2 = $this->bc_address_arr['street_2'];
        $this->address->city = $this->bc_address_arr['city'];
        $this->address->contact = trim($this->bc_address_arr['first_name']
            . ' ' . $this->bc_address_arr['last_name'] . ' ' . $this->bc_address_arr['company']);
        $this->address->country = $this->bc_address_arr['country_iso2'];
        $this->address->id = $this->prefix() . $this->customer_id . '-' . $this->additional_id;
        $this->address->is_default_billing = true;
        $this->address->is_default_shipping = false;
        $this->address->phone1 = $this->bc_address_arr['phone'];
        $this->address->shipping_method = $this->bc_address_arr['shipping_method'] ?? null;
        $this->address->state = $this->bc_address_arr['state'];
        $this->address->type = !empty($this->bc_address_arr['company'])
            ? $this->address::TYPE_COMMERCIAL : $this->address::TYPE_RESIDENTIAL;
        $this->address->zip = $this->bc_address_arr['zip'];

        return $this->address;
    }
    // end public functions

    // private functions
    private function prefix(): string {
        return $this->is_billing_address ? self::BILLING_ADDRESS_PREFIX : self::SHIPPING_ADDRESS_PREFIX;
    }
    // end private functions
}