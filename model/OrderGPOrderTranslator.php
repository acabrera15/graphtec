<?php
class OrderGPOrderTranslator {

    // private constants
    private const BATCH_PREFIX = 'WEB-R-';
    private const CARD_TYPE_AMEX = 'AMEX';
    private const CARD_TYPE_DISCOVER = 'DISCOVER';
    private const CARD_TYPE_MASTERCARD = 'MASTER';
    private const CARD_TYPE_VISA = 'VISA';
    private const DEFAULT_ORDER_TYPE = 'NASO';
    private const DEFAULT_PAYMENT_TERM = 'CREDIT CARD';
    private const DEFAULT_PAYMENT_TYPE = 'CREDIT_CARD';
    private const GP_SHIP_METHOD_BEST = 'BEST/PPA';
    private const ID_PREFIX = 'BC-';
    private const PO_NUM_PREFIX = 'ECOM-';
    private const SALES_TAX_SCHD = 'WEB';
    private const SHIP_METHOD_UPS_READY = 'upsready';
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
        $this->gp_order['SHPMTHD'] = self::GP_SHIP_METHOD_BEST;
        $this->gp_order['CUSTCARACCTNO'] = '';
        $this->gp_order['BILLTOADDRID'] = !empty($this->order->billing_address) ? $this->order->billing_address->id : '';
        $this->gp_order['SHIPTOADDRID'] = !empty($this->order->shipping_address) ? $this->order->shipping_address->id : '';
        $this->gp_order['PAYTERM'] = self::DEFAULT_PAYMENT_TERM;
        $this->gp_order['PAYTYPE'] = self::DEFAULT_PAYMENT_TYPE;
        $this->gp_order['PROMOID'] = $this->order->promo_code;
        $this->gp_order['SLSPRSID'] = $this->gp_customer['SLSPRSID'];
        $this->gp_order['NOTE'] = $this->notes();
        $this->gp_order['SITEID'] = $this->order->site_id;
        $this->gp_order['USERID'] = $this->gp_customer['USERID'];
        $this->gp_order['DETAILS'] = [];

        $this->add_details();
        $this->add_payment();

        return $this->gp_order;
    }
    // end public functions

    // private functions
    private function add_details(): void {
        foreach ($this->order->items as $item){
            $this->gp_order['DETAILS'][] = ['DETAIL' => [
                'ORDNO' => $this->gp_order['ORDNO'],
                'ITMNO' => $item->sku,
                'UNITPRC' => $item->unit_price,
                'QTY' => $item->quantity,
                'BAKORDQTY' => $item->backorder_quantity,
                'EXTPRC' => $item->unit_price * $item->quantity
            ]];
        }
    }

    private function add_payment(): void {

        if (!empty($this->order->payment->card)){
            $this->gp_order['PAYMENT'] = [
                'PAYTYPE' => 'Credit Card',
                'GPCARDNAME' => $this->order->payment->card->type,
                'CARDNAME' => $this->order->payment->card->name_on_card,
                'CARDNO' => $this->order->payment->card->number,
                'EXPMONTH' => $this->order->payment->card->exp_month,
                'EXPYEAR' => $this->order->payment->card->exp_year,
                'CSC' => $this->order->payment->card->cvv,
                'CARDTYPE' => $this->translated_card_type(),
                'PAYAUTHCODE' => $this->order->payment->transaction_id,
                'AMNTPAID' => $this->order->payment->amount
            ];
        } else {
            $this->gp_order['PAYMENT'] = [
                'PAYTYPE' => 'Credit Card',
                'GPCARDNAME' => '',
                'CARDNAME' => '',
                'CARDNO' => '',
                'EXPMONTH' => '',
                'EXPYEAR' => '',
                'CSC' => '',
                'CARDTYPE' => '',
                'PAYAUTHCODE' => $this->order->payment->transaction_id,
                'AMNTPAID' => $this->order->payment->amount
            ];
        }

    }

    private function batch_id(): string {
        $date = $this->order->date ?? new DateTime();

        return self::BATCH_PREFIX . $date->format('mdy');
    }

    private function notes(): string {
        $notes = $this->order->notes;
        if ($this->order->ship_method === self::SHIP_METHOD_UPS_READY){
            $notes .= "2nd-day air chosen\n\n";
        }

        return $notes;
    }

    private function translated_card_type(): string {
        if (empty($this->order->payment->card)){
            return '';
        }

        return match ($this->order->payment->card->type) {
            $this->order->payment->card::TYPE_AMEX => self::CARD_TYPE_AMEX,
            $this->order->payment->card::TYPE_DISCOVER => self::CARD_TYPE_DISCOVER,
            $this->order->payment->card::TYPE_MASTERCARD => self::CARD_TYPE_MASTERCARD,
            $this->order->payment->card::TYPE_VISA => self::CARD_TYPE_VISA,
            default => '',
        };
    }
    // end private functions

}