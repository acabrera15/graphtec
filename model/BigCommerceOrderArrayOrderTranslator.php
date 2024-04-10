<?php
class BigCommerceOrderArrayOrderTranslator {

    use Logger;

    // private members
    private array   $bc_order_array;
    private ?Order  $order;
    // end private members

    // public functions
    public function __construct(
        array $bc_order_array,
        array $bc_shipping_addresses,
        array $bc_customer_array,
        array $bc_order_products,
        ?string $coupon_code
    ){
        $this->bc_order_array = $bc_order_array;
        $this->bc_order_array['shipping_addresses'] = $bc_shipping_addresses;
        $this->bc_order_array['coupon_code'] = $coupon_code;
        $this->bc_order_array['customer'] = $bc_customer_array;
        $this->bc_order_array['products'] = $bc_order_products;

        $this->write_to_log(
            get_class($this) . '.log',
            "BC order array: " . print_r($this->bc_order_array, true)
        );
    }

    /**
     * @return Order
     * @throws Exception
     */
    public function translate(): Order {
        $this->order = new Order();

        $this->order->id = (string) $this->bc_order_array['id'];
        $this->order->currency_code = $this->bc_order_array['currency_code'];
        $this->order->date = new DateTime($this->bc_order_array['date_created']);
        $this->order->discount_amount = (float) $this->bc_order_array['discount_amount'] + (float) $this->bc_order_array['coupon_discount'];
        $this->order->freight_amount = (float) $this->bc_order_array['shipping_cost_ex_tax'];
        $this->order->notes = "Staff Notes: " . $this->bc_order_array['staff_notes'] . "\n\nCustomer Message: {$this->bc_order_array['customer_message']}";
        $this->order->promo_code = $this->bc_order_array['coupon_code'];
        $this->order->sales_tax_amount = (float) ($this->bc_order_array['total_inc_tax'] - $this->bc_order_array['total_ex_tax']);
        $this->order->status_id = $this->bc_order_array['status_id'];

        $this->add_shipping_address_and_ship_method();
        $this->add_items();
        $this->add_billing_address();

        // keep this at the end, as they rely upon data built above
        $this->add_payments();
        $this->add_customer();

        return $this->order;
    }
    // end public functions

    // private functions
    private function add_billing_address(): void {
        $address_translator = new BigCommerceAddressArrayAddressTranslator(
            $this->bc_order_array['billing_address'],
            true,
            $this->bc_order_array['customer_id'],
            $this->bc_order_array['id']
        );
        $this->order->billing_address = $address_translator->translate();
    }

    /**
     * @throws Exception
     */
    private function add_customer(): void {

        if (!isset($this->bc_order_array['customer']['date_created'])){
            $this->write_to_log(get_class($this) . '.log', "No date created for the customer, so we are skipping\n");
            return;
        }

        // add our created billing and shipping addresses
        $this->order->customer->addresses[] = $this->order->billing_address;
        $this->order->customer->addresses[] = $this->order->shipping_address;

        // add any other addresses for the customer
        if (!empty($this->bc_order_array['customer']['addresses'])){
            foreach ($this->bc_order_array['customer']['addresses'] as $address_arr){
                $address = new Address();
                $address->address1 = $address_arr['address1'];
                $address->address2 = $address_arr['address2'];
                $address->city = $address_arr['city'];
                $address->contact = trim("{$address_arr['first_name']} {$address_arr['last_name']} {$address_arr['company']}");
                $address->country = $address_arr['country_code'];
                $address->id = $address_arr['id'];
                $address->is_default_billing = false;
                $address->is_default_shipping = false;
                $address->phone1 = $address_arr['phone'];
                $address->state = $address_arr['state_or_province'];
                $address->type = strtoupper($address_arr['address_type']);
                $address->zip = $address_arr['postal_code'];

                $this->order->customer->addresses[] = $address;
            }
        } else {
            $this->write_to_log(get_class($this) . '.log', "Customer address array is empty");
        }

        $this->order->customer->credit_cards[] = $this->order->payments[0]->card;
        $this->order->customer->date_joined = new DateTime($this->bc_order_array['customer']['date_created']);
        $this->order->customer->email = $this->bc_order_array['customer']['email'];
        $this->order->customer->id = $this->bc_order_array['customer']['id'];
        $this->order->customer->name = trim("{$this->bc_order_array['customer']['first_name']} {$this->bc_order_array['customer']['last_name']} "
            . "{$this->bc_order_array['customer']['company']}");
    }

    private function add_items(): void {
        foreach ($this->bc_order_array['products'] as $product_arr){
            $item = new OrderItem();
            $item->backorder_quantity = 0;
            $item->product_id = $product_arr['product_id'];
            $item->quantity = $product_arr['quantity'];
            $item->sku = trim($product_arr['sku']);
            $item->unit_price = $product_arr['base_price'];
            if (!empty($product_arr['product_options'])){
                foreach ($product_arr['product_options'] as $option){
                    $option = new OrderProductOption($option['id'], $option['option_id'], $option['display_value']);
                    $item->options[] = $option;
                }
            }

            $this->order->items[] = $item;
        }
    }

    private function add_payments(): void {
        $payment = new OrderPayment();
        $payment->amount = (float) $this->bc_order_array['total_inc_tax'];
        $payment->card = new CreditCard();
        $payment->card->cvv = '1111';
        $payment->card->exp_month = '01';
        $payment->card->exp_year = (string) ((int) date('Y') + 5);
        $payment->card->id = $this->bc_order_array['customer_id'] . '-' . $this->bc_order_array['id'];
        $payment->card->is_default = true;
        $payment->card->name_on_card = $this->order->billing_address->contact;
        $payment->card->number = '4444333322221111';
        $payment->transaction_id = $this->order->id . '-authorize-net';

        $this->order->payments[] = $payment;
    }

    private function add_shipping_address_and_ship_method(): void {
        if (!empty($this->bc_order_array['shipping_addresses'])){
            $address_translator = new BigCommerceAddressArrayAddressTranslator(
                $this->bc_order_array['shipping_addresses'][0],
                false,
                $this->bc_order_array['customer_id'],
                $this->bc_order_array['id']
            );
            $this->order->shipping_address = $address_translator->translate();
            $this->order->ship_method = $this->bc_order_array['shipping_addresses'][0]['shipping_method'];
        }
    }
    // end private functions
}