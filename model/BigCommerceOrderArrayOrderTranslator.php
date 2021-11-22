<?php
class BigCommerceOrderArrayOrderTranslator {

    // private members
    private array   $bc_order_array;
    private ?Order  $order;
    // end private members

    // public functions
    public function __construct(array $bc_order_array){
        $this->bc_order_array = $bc_order_array;
    }

    public function translate(): Order {
        $this->order = new Order();

        // TODO: implement the logic to translate the BC order data into our object
        $this->order->id = (string) $this->bc_order_array['id'];

        return $this->order;
    }
    // end public functions
}