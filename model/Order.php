<?php
class Order {

    // private constants
    private const DEFAULT_SHIP_METHOD = 'ups';
    private const DEFAULT_SITE_ID = 'IRVINE';
    // end private constants

    // public members
    public float        $amount_received = 0.00;
    public ?Address     $billing_address = null;
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
    public OrderPayment $payment;
    public ?string      $promo_code = null;
    public float        $sales_amount = 0.00;
    public float        $sales_tax_amount = 0.00;
    public string       $ship_method = self::DEFAULT_SHIP_METHOD;
    public ?Address     $shipping_address = null;
    public string       $site_id = self::DEFAULT_SITE_ID;
    public float        $subtotal = 0.00;
    // end public members

    // public functions
    public function __construct(){
        $this->customer = new Customer();
        $this->payment = new OrderPayment();
    }
    // end public functions
}