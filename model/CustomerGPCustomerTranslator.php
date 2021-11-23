<?php
class CustomerGPCustomerTranslator {

    // private constants
    private const CUST_ID_PREFIX = 'ECOM';
    private const GUEST_ID_PREFIX = 'GUEST-';
    // end private constants

    // private members
    private Customer    $customer;
    private array       $gp_customer = [];
    // end private members

    // public functions
    public function __construct(Customer $customer, string $guest_checkout_id = null){
        $this->customer = $customer;
        if (empty($customer->id) && !empty($guest_checkout_id)){
            $this->customer->id = self::GUEST_ID_PREFIX . $guest_checkout_id;
        }
    }

    public function translate(): array {

        $this->gp_customer['GPCUSTID'] = self::CUST_ID_PREFIX . $this->customer->id;
        $this->gp_customer['NAME'] = $this->customer->name;
        $this->gp_customer['CUSTCLASS'] = $this->customer->class;
        $this->gp_customer['PRCLEVEL'] = $this->customer->price_level;
        $this->gp_customer['PRADDRID'] = $this->customer->primary_address_id;
        $this->gp_customer['BILLTOADDRID'] = $this->customer->default_bill_to_address_id();
        $this->gp_customer['SHIPTOADDRID'] = $this->customer->default_ship_to_address_id();
        $this->gp_customer['STMNADDRID'] = $this->customer->default_statement_address_id();
        $this->gp_customer['PAYTERM'] = $this->customer->payment_term;
        $this->gp_customer['DATEJOINED'] = !empty($this->customer->date_joined) ? $this->customer->date_joined->format('m/d/Y') : date('m/d/Y');
        $this->gp_customer['SLSPRSID'] = $this->customer->salesperson_id;
        $this->gp_customer['TAXSCHD'] = '';
        $this->gp_customer['SHIPCOMPLETE'] = 'false';
        $this->gp_customer['USERID'] = $this->customer->email;
        $this->gp_customer['CREDITCARDS'] = [];
        $this->gp_customer['ADDRESSES'] = [];

        $this->add_addresses();
        $this->add_credit_cards();

        return $this->gp_customer;
    }
    // end public functions

    // private functions
    private function add_addresses(): void {
        foreach ($this->customer->addresses as $address){
            $id = $address->id === $this->customer->primary_address_id ? 'PRIMARY' : $address->id;

            $this->gp_customer['ADDRESSES'][] = [
                'GPCUSTID' => $this->gp_customer['GPCUSTID'],
                'ADDRID' => $id,
                'ADDRTYPE' => $address->type,
                'CONTACT' => $address->contact,
                'ADDR1' => $address->address1,
                'ADDR2' => $address->address2,
                'ADDR3' => $address->address3,
                'CITY' => $address->city,
                'STATE' => $address->state,
                'ZIP' => $address->zip,
                'COUNTRY' => $address->country,
                'PHONE1' => $address->phone1,
                'PHONE2' => $address->phone2
            ];
        }
    }

    private function add_credit_cards(): void {
        foreach ($this->customer->credit_cards as $card){
            $this->gp_customer['CREDITCARDS'][] = [
                'DEFAULT' => $card->is_default ? 'true' : 'false',
                'GPCARDNAME' => $card->type,
                'CUSTCARDID' => $card->id,
                'CARDNAME' => $card->name_on_card,
                'CARDNO' => $card->number,
                'EXPMONTH' => $card->exp_month,
                'EXPYEAR' => $card->exp_year,
                'CSC' => $card->cvv,
                'CARDTYPE' => $card->type
            ];
        }
    }
    // end private functions

}