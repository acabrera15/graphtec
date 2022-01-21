<?php
class OrderGPOrderTranslator {

    use CurrencyFormatter;
    use GpTranslatorHelper;

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

    /**
     * @return array
     */
    public function translate(): array {
        $this->gp_order['ORDNO'] = $this->order_id_to_gp_order_number((string) $this->order->id);
        $this->gp_order['BATCHID'] = $this->batch_id();
        $this->gp_order['GPCUSTID'] = $this->gp_customer['GPCUSTID'];
        $this->gp_order['ORDDATE'] = !empty($this->order->date) ? $this->order->date->format('m/d/Y') : date('m/d/Y');
        $this->gp_order['SUBTOTAL'] = $this->format_currency_value($this->order->subtotal(), $this->order->currency_code);
        $this->gp_order['SLSAMNT'] = $this->format_currency_value($this->order->total_sales_amount(), $this->order->currency_code);
        $this->gp_order['AMNTRCVD'] = $this->format_currency_value($this->order->total_paid(), $this->order->currency_code);
        $this->gp_order['DISCAMNT'] = $this->format_currency_value($this->order->discount_amount, $this->order->currency_code);
        $this->gp_order['SLSTAXAMNT'] = $this->format_currency_value($this->order->sales_tax_amount, $this->order->currency_code);
        $this->gp_order['FREIGHTAMNT'] = $this->format_currency_value($this->order->freight_amount, $this->order->currency_code);
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
        $this->gp_order['PAYMENT'] = [];

        $this->add_details();
        $this->add_payments();

        return $this->gp_order;
    }
    // end public functions

    // private functions
    private function add_details(): void {
        foreach ($this->order->items as $item){
            $this->gp_order['DETAILS'][] = ['DETAIL' => [
                'ORDNO' => $this->gp_order['ORDNO'],
                'ITMNO' => $item->sku,
                'UNITPRC' => $this->format_currency_value($item->unit_price, $this->order->currency_code),
                'QTY' => $item->quantity,
                'BAKORDQTY' => $item->backorder_quantity,
                'EXTPRC' => $this->format_currency_value($item->unit_price * $item->quantity, $this->order->currency_code)
            ]];
        }
    }

    private function add_payments(): void {

        foreach ($this->order->payments as $payment){
            $this->gp_order['PAYMENT'][] = [
                'PAYTYPE' => 'Credit Card',
                'GPCARDNAME' => $payment->card->type,
                'CARDNAME' => $payment->card->name_on_card,
                'CARDNO' => $payment->card->number,
                'EXPMONTH' => $payment->card->exp_month,
                'EXPYEAR' => $payment->card->exp_year,
                'CSC' => $payment->card->cvv,
                'CARDTYPE' => $this->translated_card_type(),
                'PAYAUTHCODE' => $payment->transaction_id,
                'AMNTPAID' => $this->format_currency_value($payment->amount, $this->order->currency_code)
            ];
        }
    }

    private function batch_id(): string {
        $date = $this->order->date ?? new DateTime();

        return self::BATCH_PREFIX . $date->format('mdy');
    }

    private function notes(): string {
        $notes = (string) $this->order->notes;
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