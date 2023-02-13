<?php

use JetBrains\PhpStorm\Pure;
use PhpUnit\Framework\Testcase;
require_once ('../app.php');

class GpInterfaceClientTest extends Testcase {

    // private constants
    private const VALID_SKUS = [
        //'792600850' => 45,
        'ST0118' => 45,
        '0190-80982' => 45/*,
        'PHP33-CB15N-HS' => 105,
        'PHP35-CB15-HS' => 138,
        'PHP35-CB09N-HS' => 138,
        //'CUT-MAT-12LT-3T' => 14.99*/
    ];
    // end private constants

    // private members
    private GpInterfaceClient $gp_client;
    // end private members

    // public functions
    public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        try {
            $this->gp_client = new GpInterfaceClient($this->gp_config());
        } catch (Exception){
            static::fail();
        }
    }

    /**
     * @param array $items
     * @param bool $should_succeed
     * @dataProvider data_provider_query_inventory
     * @throws SoapFault
     */
    public function test_query_inventory(array $items, bool $should_succeed){
        $this->gp_client->get_config()->endpoint = GP_ENDPOINT_INQUIRY;
        $this->gp_client->refresh_client();
        if (!$should_succeed){
            $this->expectException(Exception::class);
        }
        static::assertNotEmpty($this->gp_client->query_inventory(GP_SITE_ID, $items));
    }

    /**
     * @dataProvider data_provider_submit_customer
     * @throws SoapFault
     */
    public function test_submit_customer(Customer $customer, bool $should_succeed){
        $this->gp_client->get_config()->endpoint = GP_ENDPOINT_CUSTOMER;
        $this->gp_client->refresh_client();
        if (!$should_succeed){
            $this->expectException(Exception::class);
        }
        $this->gp_client->submit_customer($customer);
        
        static::assertTrue(true);
    }

    /**
     * @param Order $order
     * @param bool $should_succeed
     * @dataProvider data_provider_submit_order
     * @throws SoapFault
     */
    public function test_submit_order(Order $order, bool $should_succeed){
        $this->gp_client->get_config()->endpoint = GP_ENDPOINT_ORDER;
        $this->gp_client->refresh_client();
        if (!$should_succeed){
            $this->expectException(Exception::class);
        }
        $this->gp_client->submit_order($order);
        static::assertTrue(true);
    }

    // data providers
    public function data_provider_query_inventory(): array {
        $return_arr = [];

        // success test
        $items = [];
        for ($i = 0; $i < rand(1, count(self::VALID_SKUS)); $i++){
            $items[] = array_keys(self::VALID_SKUS)[$i];
        }

        $return_arr[] = [
            $items,
            true
        ];

        // fail test
        $return_arr[] = [
            ['FAKE-PRODUCT-1', 'FAKE-PRODUCT-2'],
            false
        ];

        return $return_arr;
    }

    public function data_provider_submit_customer(): array {
        $return_arr = [];

        // good customer
        $customer = $this->valid_customer();
        $return_arr[] = [
            $customer,
            true
        ];

        // bad customer
        $customer2 = new Customer();
        $return_arr[] = [
            $customer2,
            false
        ];

        return $return_arr;
    }

    public function data_provider_submit_order(): array {
        $return_arr = [];

        // TEST 1: a blank order shouldn't work
        $order = new Order();
        $return_arr[] = [
            $order,
            false
        ];

        // TEST 2: a standard order with several items that should work
        $return_arr[] = [
            $this->valid_order(false, false, 1.0),
            true
        ];

        // TEST 3: make sure a discount will go in
        $return_arr[] = [
            $this->valid_order(false, false, rand(50, 99) / 100),
            true
        ];

        // TEST 4: Try one with sales tax
        $return_arr[] = [
            $this->valid_order(true, false, rand(50, 99) / 100),
            true
        ];


        // TEST 5: Try one with a made-up product
        $order_fake_item = $this->valid_order(false, false, 1.0);
        $order_fake_item->items[] = $this->invalid_item();
        $return_arr[] = [$order_fake_item, false];

        // TEST 6: Try one with freight
        $return_arr[] = [
            $this->valid_order(true, true, 1.0),
            true
        ];


        return $return_arr;
    }
    // end data providers

    // end public functions

    // private functions
    #[Pure]
    private function gp_config(): SoapCredentialsConfig {
        $credentials = new SoapCredentialsConfig();
        $credentials->endpoint = GP_ENDPOINT_ORDER;
        $credentials->password = GP_PASSWORD;
        $credentials->user_id = GP_USER_ID;

        return $credentials;
    }

    private function invalid_item(): OrderItem {
        $item = new OrderItem();
        $item->sku = 'FAKEITEMSKU-' . rand(100, 10000);
        $item->unit_price = 0.00;

        return $item;
    }

    private function valid_address(bool $is_default_billing, bool $is_default_shipping): Address {
        $address = new Address();
        $address->address1 = rand(100, 9999) . ' Sesame Street';
        $address->address2 = 'Suite #' . rand(1, 100);
        $address->address3 = 'Extra address stuff';
        $address->city = 'Sandy';
        $address->contact = 'Tony Baloney';
        $address->country = 'USA';
        $address->id = rand(10000, 10000000);
        $address->is_default_billing = $is_default_billing;
        $address->is_default_shipping = $is_default_shipping;
        $address->phone1 = '801-123-4567';
        $address->phone2 = '801-987-6543';
        $address->state = 'UT';
        $address->zip = '84092';

        return $address;
    }

    private function valid_credit_card(bool $is_default): CreditCard {
        $card = new CreditCard();
        $types = [
            $card::TYPE_AMEX,
            $card::TYPE_DISCOVER,
            $card::TYPE_MASTERCARD,
            $card::TYPE_VISA
        ];
        $card->cvv = '9999';
        $card->exp_month = date('m');
        $card->exp_year = (int) date('Y') + 4;
        $card->id = rand(10000, 10000000);
        $card->is_default = $is_default;
        $card->name_on_card = 'Testman Testerson';
        $card->number = '****************';
        $card->type = $types[rand(0, count($types) - 1)];

        return $card;
    }

    private function valid_customer(): Customer {
        $customer = new Customer();
        $customer->addresses[] = $this->valid_address(true, false);
        $customer->addresses[] = $this->valid_address(false, true);
        $customer->addresses[] = $this->valid_address(false, false);
        $customer->class = 'WEB';
        $customer->credit_cards[] = $this->valid_credit_card(true);
        $customer->credit_cards[] = $this->valid_credit_card(false);
        $customer->date_joined = new DateTime();
        $customer->email = 'test-' . time() . '@silhouetteamerica.com';
        $customer->id = rand(10000, 10000000);
        $customer->name = 'Testman Testerson ' . time();
        $customer->price_level = 'LIST';

        return $customer;
    }

    private function valid_item(): OrderItem {
        $item = new OrderItem();
        $item->backorder_quantity = 0;
        $item->quantity = rand(1, 20);
        $item->sku = array_keys(self::VALID_SKUS)[rand(0, count(self::VALID_SKUS) - 1)];
        $item->unit_price = self::VALID_SKUS[$item->sku];

        return $item;
    }

    private function valid_order(bool $include_sales_tax, bool $include_freight_charge, float $discount_multiplier): Order {
        $order = new Order();
        $order->customer = $this->valid_customer();

        $order->billing_address = $order->customer->default_bill_to_address();
        $order->date = new DateTime();

        // figure out the items, so you can calculate the amounts
        for ($i = 0; $i < rand(1, 10); $i++){
            $item = $this->valid_item();
            $order->items[] = $item;

            if ($include_freight_charge){
                $order->freight_amount += (rand(10, 20) / 100) * $item->extended_price();
            }
        }
        $order->discount_amount = $order->subtotal() - ($order->subtotal() * $discount_multiplier);
        $order->sales_tax_amount = $include_sales_tax ? round((rand(5, 10) / 100) * $order->subtotal(), 2) : 0.00;


        $order->id = rand(10000, 10000000);
        $order->notes = "Some notes here";
        $order->payments[0] = new OrderPayment();
        $order->payments[0]->amount = $order->total_sales_amount();
        $order->payments[0]->card = $order->customer->credit_cards[0];
        $order->payments[0]->transaction_id =  rand(1000, 9999) . 'ABC123' . rand(1000, 9999);
        $order->promo_code = $discount_multiplier < 0.9999999999 ? 'MYDISCOUNT' : '';
        $order->shipping_address = $order->customer->default_ship_to_address();
        $order->site_id = GP_SITE_ID;

        return $order;
    }
    // end private functions

}