<?php
class Customer {

    // private constants
    private const DEFAULT_CLASS = 'WEB';
    private const DEFAULT_PAYMENT_TERM = "CREDIT CARD";
    private const DEFAULT_PRICE_LEVEL = 'LIST';
    private const DEFAULT_SALESPERSON_ID = 'D-HOUSE';
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
    public string       $salesperson_id = self::DEFAULT_SALESPERSON_ID;
    // end public members

    // public functions
    public function default_bill_to_address(): ?Address {
        foreach ($this->addresses as $address){
            if ($address->is_default_billing){
                return $address;
            }
        }
        if (empty($id) && !empty($this->addresses)){
            return $this->addresses[0];
        }

        return null;
    }

    public function default_ship_to_address(): ?Address {
        foreach ($this->addresses as $address){
            if ($address->is_default_shipping){
                return $address;
            }
        }
        if (empty($id) && !empty($this->addresses)){
            foreach ($this->addresses as $address){
                return $address;
            }
        }

        return null;
    }

    public function default_statement_address(): ?Address {
        return $this->default_bill_to_address();
    }

    public function primary_address(): ?Address {
        return $this->default_ship_to_address();
    }
    // end public functions

}