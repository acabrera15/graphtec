<?php
class CustomerGPCustomerTranslator {

    use Logger;

    // private constants
    //private const CUST_ID_PREFIX = 'E-'; // was used when a unique customer ID was assigned to each individual customer
    private const CUSTOMER_ID = 'ECOM9999';
    private const DEFAULT_SHIP_METHOD = 'BEST/PPA';
    //private const GUEST_ID_PREFIX = 'G-';  // was used when a unique customer ID was assigned to each individual customer
    // end private constants

    // private members
    private Customer    $customer;
    private array       $gp_customer = [];
    // end private members

    // public functions
    public function __construct(Customer $customer, string $guest_checkout_id = null){
        $this->customer = $customer;
        /*if (empty($customer->id) && !empty($guest_checkout_id)){
            $this->customer->id = self::GUEST_ID_PREFIX . $guest_checkout_id;
        }*/

        $this->write_to_log(get_class($this) . '.log', print_r($this->customer, true) . "Guest checkout ID: {$guest_checkout_id}\n\n");
    }

    public function translate(): array {

        $this->gp_customer['GPCUSTID'] = self::CUSTOMER_ID;
        $this->gp_customer['NAME'] = !empty($this->customer->name) ? $this->customer->name : 'Guest Checkout';
        $this->gp_customer['CUSTCLASS'] = $this->customer->class;
        $this->gp_customer['PRCLEVEL'] = $this->customer->price_level;
        $this->gp_customer['PRADDRID'] = 'PRIMARY';
        $this->gp_customer['BILLTOADDRID'] = !empty($this->customer->primary_address()) ? $this->customer->default_bill_to_address()->id : '0';
        $this->gp_customer['SHIPTOADDRID'] = !empty($this->customer->default_ship_to_address()) ? $this->customer->default_ship_to_address()->id : '0';
        $this->gp_customer['STMNADDRID'] = !empty($this->customer->default_statement_address()) ? $this->customer->default_statement_address()->id : '0';
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
            if (empty($address->type)){
                continue;
            }
            $this->write_to_log(get_class($this) . '.log', "Including the following address: " . print_r($address, true));

            // add an extra entry if it's the primary
            if ($this->customer->primary_address()->id === $address->id){
                /*$this->gp_customer['ADDRESSES'][] = ['ADDRESS' => [
                    'GPCUSTID' => $this->gp_customer['GPCUSTID'],
                    'ADDRID' => 'PRIMARY',
                    'ADDRTYPE' => $address->type,
                    'CONTACT' => $address->contact,
                    'ADDR1' => $address->address1,
                    'ADDR2' => $address->address2,
                    'ADDR3' => $address->address3,
                    'CITY' => $address->city,
                    'STATE' => $this->format_state_code($address->state, $address->country),
                    'ZIP' => $address->zip,
                    'COUNTRY' => $this->format_country_code($address->country),
                    'PHONE1' => $address->phone1,
                    'PHONE2' => $address->phone2,
                    'PHONE3' => $address->phone3,
                    'FAX' => $address->fax,
                    'SHIPMTHD' => self::DEFAULT_SHIP_METHOD

                ]];*/
                $this->gp_customer['ADDRESSES'][] = ['ADDRESS' => [
                    'GPCUSTID' => $this->gp_customer['GPCUSTID'],
                    'ADDRID' => 'PRIMARY',
                    'ADDRTYPE' => Address::TYPE_COMMERCIAL,
                    'CONTACT' => 'Graphtec America Web Customer',
                    'ADDR1' => '17462 Armstrong Ave',
                    'ADDR2' => '',
                    'ADDR3' => '',
                    'CITY' => 'Irvine',
                    'STATE' => 'CA',
                    'ZIP' => '92614',
                    'COUNTRY' => 'USA',
                    'PHONE1' => '(949) 770-6010',
                    'PHONE2' => '',
                    'PHONE3' => '',
                    'FAX' => '',
                    'SHIPMTHD' => self::DEFAULT_SHIP_METHOD

                ]];
            }

            $this->gp_customer['ADDRESSES'][] = ['ADDRESS' => [
                'GPCUSTID' => $this->gp_customer['GPCUSTID'],
                'ADDRID' => $address->id,
                'ADDRTYPE' => $address->type,
                'CONTACT' => $address->contact,
                'ADDR1' => $address->address1,
                'ADDR2' => $address->address2,
                'ADDR3' => $address->address3,
                'CITY' => $address->city,
                'STATE' => $this->format_state_code($address->state, $address->country),
                'ZIP' => $address->zip,
                'COUNTRY' => $this->format_country_code($address->country),
                'PHONE1' => $address->phone1,
                'PHONE2' => $address->phone2,
                'PHONE3' => $address->phone3,
                'FAX' => $address->fax,
                'SHIPMTHD' => self::DEFAULT_SHIP_METHOD
            ]];
        }
    }

    private function add_credit_cards(): void {
        foreach ($this->customer->credit_cards as $card){
            $this->gp_customer['CREDITCARDS'][] = [ 'CREDITCARD' => [
                'DEFAULT' => $card->is_default ? 'true' : 'false',
                'GPCARDNAME' => $card->type,
                'CUSTCARDID' => $card->id,
                'CARDNAME' => $card->name_on_card,
                'CARDNO' => $card->number,
                'EXPMONTH' => $card->exp_month,
                'EXPYEAR' => $card->exp_year,
                'CSC' => $card->cvv,
                'CARDTYPE' => $card->type
            ]];
        }
    }

    private function format_country_code(string $iso_alpha2_code): string {
        $iso_alpha2_code = strtolower($iso_alpha2_code);
        return match ($iso_alpha2_code) {
            'br' => 'BRAZIL',
            'ca' => 'CANADA',
            'ec' => 'ECUADO',
            'ko' => 'KOREA',
            'mx' => 'MX',
            'nl' => 'AMSTER',
            'pa' => 'PANAMA',
            'se' => 'SWEDEN',
            default => 'USA',
        };
    }

    private function format_state_code(string $state, string $country_code): string {
        $countries_arr = (array) json_decode(file_get_contents(dirname(__FILE__) . '/../data/us-canada-states.json'), true);
        foreach ($countries_arr as $country_data){
            if (strtoupper($country_data['abbreviation']) === strtoupper($country_code)){
                foreach ($country_data['states'] as $state_data){
                    if (strtolower($state_data['name']) === strtolower($state)){
                        return $state_data['abbreviation'];
                    }
                }
            }
        }

        return $state;
    }
    // end private functions

}