<?php
class OrderGPOrderTranslator {

    // private constants
    private const BATCH_PREFIX = 'WEB-R-';
    private const DEFAULT_ORDER_TYPE = 'NASO';
    private const SALES_TAX_SCHD = 'WEB';
    private const ID_PREFIX = 'BC-';
    private const PO_NUM_PREFIX = 'ECOM-';
    // end private constants

    // private members
    private Order $order;
    private array $gp_customer;
    private array $gp_order = [];
    // end private members

    // public functions
    public function __construct(Order $order){
        $this->order = $order;
        $guest_checkout_id = !empty($order->customer->id) ? $this->order->id : null;
        $customer_translator = new CustomerGPCustomerTranslator($this->order->customer, $guest_checkout_id);
        $this->gp_customer = $customer_translator->translate();
    }

    public function translate(): array {
        $this->gp_order['ORDNO'] = self::ID_PREFIX . $this->order->id;
        $this->gp_order['BATCHID'] = $this->batch_id();
        $this->gp_order['GPCUSTID'] = $this->gp_customer['GPCUSTID'];
        $this->gp_order['ORDDATE'] = !empty($this->order->date) ? $this->order->date->format('m/d/Y') : date('m/d/Y');
        $this->gp_order['SUBTOTAL'] = $this->order->subtotal;
        $this->gp_order['SLSAMNT'] = $this->order->sales_amount;
        $this->gp_order['AMNTRCVD'] = $this->order->amount_received;
        $this->gp_order['DISCAMNT'] = $this->order->discount_amount;
        $this->gp_order['SLSTAXAMNT'] = $this->order->sales_tax_amount;
        $this->gp_order['FREIGHTAMNT'] = $this->order->freight_amount;
        $this->gp_order['TAXSCHD'] = $this->order->sales_tax_amount > 0.00001 ? self::SALES_TAX_SCHD : '';
        $this->gp_order['CUSTCLASS'] = $this->gp_customer['CUSTCLASS'];
        $this->gp_order['ORDTYPE'] = self::DEFAULT_ORDER_TYPE;
        $this->gp_order['CUSTPONO'] = self::PO_NUM_PREFIX . $this->order->id;
        $this->gp_order['SHPMTHD'] = $this->shipping_method();

        return $this->gp_order;
    }
    // end public functions

    // private functions
    private function batch_id(): string {
        $date = $this->order->date ?? new DateTime();

        return self::BATCH_PREFIX . $date->format('mdy');
    }

    private function shipping_method(): string {
        return ''; // TODO: implement shipping_method()
    }
    // end private functions

}