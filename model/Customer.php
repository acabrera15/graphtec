<?php
class Customer {

    // private constants
    private const DEFAULT_CLASS = 'RESELLER';
    private const DEFAULT_PAYMENT_TERM = "CREDIT CARD";
    private const DEFAULT_PRICE_LEVEL = 'DEALER';
    private const DEFAULT_SALESPERSON_ID = 'WEB';
    // end private constants

    // public members
    /**
     * @var Address[]
     */
    public array        $addresses = [];
    public string       $class = self::DEFAULT_CLASS;
    /**
     * @var CreditCard[]
     */
    public array        $credit_cards = [];
    public ?DateTime    $date_joined = null;
    public ?string      $email = null;
    public ?string      $id = null;
    public ?string      $name = null;
    public string       $payment_term = self::DEFAULT_PAYMENT_TERM;
    public string       $price_level = self::DEFAULT_PRICE_LEVEL;
    public string       $primary_address_id = "PRIMARY";
    public string       $salesperson_id = self::DEFAULT_SALESPERSON_ID;
    // end public members

    // public functions
    public function default_bill_to_address_id(): ?string {
        $id = null;
        foreach ($this->addresses as $address){
            if ($address->is_default_billing){
                $id = $address->id;
                break;
            }
        }

        return $id;
    }

    public function default_ship_to_address_id(): ?string {
        $id = null;
        foreach ($this->addresses as $address){
            if ($address->is_default_shipping){
                $id = $address->id;
                break;
            }
        }

        return $id;
    }

    public function default_statement_address_id(): ?string {
        return $this->default_bill_to_address_id();
    }
    // end public functions

}