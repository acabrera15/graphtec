<?php
class Order {

    // public members
    public float        $amount_received = 0.00;
    public Customer     $customer;
    public ?DateTime    $date = null;
    public float        $discount_amount = 0.00;
    public float        $freight_amount = 0.00;
    public ?string      $id = null;
    public float        $sales_amount = 0.00;
    public float        $sales_tax_amount = 0.00;
    public float        $subtotal = 0.00;
    // end public members

    // public functions
    public function __construct(){
        $this->customer = new Customer();
    }
    // end public functions
}