<?php

use JetBrains\PhpStorm\Pure;

class Order {

    use CurrencyFormatter;

    // private constants
    private const DEFAULT_CURRENCY_CODE = 'USD';
    private const DEFAULT_SHIP_METHOD = 'ups';
    private const DEFAULT_SITE_ID = 'IRVINE';
    // end private constants

    // public members
    public ?Address     $billing_address = null;
    public string       $currency_code = self::DEFAULT_CURRENCY_CODE;
    public Customer     $customer;
    public ?DateTime    $date = null;
    public float        $discount_amount = 0.00;
    public float        $freight_amount = 0.00;
    public ?string      $id = null;
    /**
     * @var OrderItem[]
     */
    public array        $items = [];
    public ?string      $notes = null;
    /**
     * @var OrderPayment[]
     */
    public array        $payments = [];
    public ?string      $promo_code = null;
    public float        $sales_tax_amount = 0.00;
    public string       $ship_method = self::DEFAULT_SHIP_METHOD;
    public ?Address     $shipping_address = null;
    public string       $site_id = self::DEFAULT_SITE_ID;
    // end public members

    // public functions
    #[Pure]
    public function __construct(){
        $this->customer = new Customer();
        $this->payments = [];
    }

    public function subtotal(): float {
        $subtotal = 0.0;
        foreach ($this->items as $item){
            $subtotal += $item->extended_price();
        }

        return $this->format_currency_value($subtotal, $this->currency_code);
    }

    public function total_paid(): float {
        $total = 0.00;
        foreach ($this->payments as $payment){
            $total += $payment->amount;
        }

        return $this->format_currency_value($total, $this->currency_code);
    }

    public function total_sales_amount(): float {
        return $this->format_currency_value(
            ($this->subtotal() + $this->freight_amount + $this->sales_tax_amount) - $this->discount_amount,
            $this->currency_code
        );
    }

    // end public functions
}