<?php
class OrderGPOrderTranslator {

    use CurrencyFormatter;
    use GpTranslatorHelper;
    use Logger;

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
    private const ITEM_OPTION_REQUIRING_SPLIT_ISO17025_ACCREDITED = 'ISO-17025 Accredited Calibration';
    private const ITEM_OPTION_REQUIRING_SPLIT_CALIBRATION = 'NIST Traceable Calibration Certificate';
    private const ITEM_OPTION_REQUIRING_SPLIT_CALIBRATION_WITH_DATA = 'NIST Traceable Calibration Certificate with Data';
    private const ITEM_OPTIONS_REQUIRING_SPLIT = [
        self::ITEM_OPTION_REQUIRING_SPLIT_ISO17025_ACCREDITED,
        self::ITEM_OPTION_REQUIRING_SPLIT_CALIBRATION,
        self::ITEM_OPTION_REQUIRING_SPLIT_CALIBRATION_WITH_DATA,
    ];
    private const PO_NUM_PREFIX = 'ECOM-';
    private const SHIP_METHOD_UPS_READY = 'upsready';
    private const SPLIT_ITEM_SKUS = [
        'GL2000',
        'GL240',
        'GL260',
        'GL840-M',
        'GL840-WV',
        'GL980'
    ];
    // end private constants

    // private members
    private Order   $order;
    private array   $gp_customer;
    private array   $gp_order = [];
    private array   $split_item_default_prices = [];
    private string  $store_id;
    // end private members

    // public functions
    public function __construct(Order $order, string $store_id){
        $this->order = $order;
        $this->store_id = $store_id;
        $guest_checkout_id = !empty($order->customer->id) ? $this->order->id : null;
        $customer_translator = new CustomerGPCustomerTranslator($this->order->customer, $guest_checkout_id);
        $this->gp_customer = $customer_translator->translate();

        $this->write_to_log(get_class($this) . '.log', "Order: " . print_r($this->order, true) . "\n");
    }

    /**
     * @return array
     */
    public function translate(): array {
        $this->gp_order['ORDNO'] = $this->order_id_to_gp_order_number((string) $this->order->id, $this->store_id);
        $this->gp_order['BATCHID'] = $this->batch_id();
        $this->gp_order['GPCUSTID'] = $this->gp_customer['GPCUSTID'];
        $this->gp_order['ORDDATE'] = !empty($this->order->date) ? $this->order->date->format('m/d/Y') : date('m/d/Y');
        $this->gp_order['SUBTOTAL'] = $this->format_currency_value($this->order->subtotal(), $this->order->currency_code);
        $this->gp_order['SLSAMNT'] = $this->format_currency_value($this->order->total_sales_amount(), $this->order->currency_code);
        $this->gp_order['AMNTRCVD'] = $this->format_currency_value($this->order->total_paid(), $this->order->currency_code);
        $this->gp_order['DISCAMNT'] = $this->format_currency_value($this->order->discount_amount, $this->order->currency_code);
        $this->gp_order['SLSTAXAMNT'] = $this->format_currency_value($this->order->sales_tax_amount, $this->order->currency_code);
        $this->gp_order['FREIGHTAMNT'] = $this->format_currency_value($this->order->freight_amount, $this->order->currency_code);
        $this->gp_order['TAXSCHD'] = $this->format_tax_schedule_id();
        $this->gp_order['CUSTCLASS'] = $this->gp_customer['CUSTCLASS'];
        $this->gp_order['ORDTYPE'] = self::DEFAULT_ORDER_TYPE;
        $this->gp_order['CUSTPONO'] = self::PO_NUM_PREFIX . $this->order->id;
        $this->gp_order['SHPMTHD'] = $this->format_shipping_method();
        $this->gp_order['CUSTCARACCTNO'] = '';
        $this->gp_order['BILLTOADDRID'] = !empty($this->order->billing_address) ? $this->order->billing_address->id : '';
        $this->gp_order['SHIPTOADDRID'] = !empty($this->order->shipping_address) ? $this->order->shipping_address->id : $this->order->billing_address->id;
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
            $split_item_option_val = $this->split_item_option_val($item);
            if (empty($split_item_option_val)){
                $this->add_details_for_single_item($item);
            } else {
                $this->add_details_for_split_item($item, $split_item_option_val);
            }
        }
    }

    private function add_details_for_single_item(OrderItem $item): void {
        $this->gp_order['DETAILS'][] = ['DETAIL' => [
            'ORDNO' => $this->gp_order['ORDNO'],
            'ITMNO' => $item->sku,
            'UNITPRC' => $this->format_currency_value($item->unit_price, $this->order->currency_code),
            'QTY' => $item->quantity,
            'BAKORDQTY' => $item->backorder_quantity,
            'EXTPRC' => $this->format_currency_value($item->unit_price * $item->quantity, $this->order->currency_code)
        ]];
    }

    private function add_details_for_split_item(OrderItem $item, string $option_val): void {
        $add_on_sku = $this->sku_to_add_for_split_item($item->sku, $option_val);
        if (empty($add_on_sku)){
            $this->add_details_for_single_item($item);
            return;
        }

        $this->load_split_item_default_prices();

        $base_item_unit_price = $this->split_item_default_prices[$item->sku] ?? $item->unit_price;
        $add_on_item_unit_price = $item->unit_price - $base_item_unit_price;
        if ($add_on_item_unit_price < 0.000001){
            $add_on_item_unit_price = 0.00;
        }
        $this->gp_order['DETAILS'][] = ['DETAIL' => [
            'ORDNO' => $this->gp_order['ORDNO'],
            'ITMNO' => $item->sku,
            'UNITPRC' => $this->format_currency_value($base_item_unit_price, $this->order->currency_code),
            'QTY' => $item->quantity,
            'BAKORDQTY' => $item->backorder_quantity,
            'EXTPRC' => $this->format_currency_value($base_item_unit_price * $item->quantity, $this->order->currency_code)
        ]];
        $this->gp_order['DETAILS'][] = ['DETAIL' => [
            'ORDNO' => $this->gp_order['ORDNO'],
            'ITMNO' => $add_on_sku,
            'UNITPRC' => $this->format_currency_value($add_on_item_unit_price, $this->order->currency_code),
            'QTY' => $item->quantity,
            'BAKORDQTY' => $item->backorder_quantity,
            'EXTPRC' => $this->format_currency_value($add_on_item_unit_price * $item->quantity, $this->order->currency_code)
        ]];
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

    private function format_shipping_method(): string {
        if (empty($this->order->shipping_address)){
            return self::GP_SHIP_METHOD_BEST;
        }

        return match ($this->order->shipping_address->shipping_method) {
            'FedEx (2 Day)' => 'FEDX 2DAY/PPA',
            'FedEx (FedEx 2 Day)' => 'FEDX 2DAY/PPA',
            'FedEx (Ground)' => 'FEDXGROUND/PPA',
            'FedEx (FedEx Ground)' => 'FEDXGROUND/PPA',
            'FedEx (International Economy)' => 'FEDXINTLECONPPA ',
            'FedEx (FedEx International Economy)' => 'FEDXINTLECONPPA ',
            'FedEx (International Priority)' => 'FEX/PRI/INTL/PP ',
            'FedEx (FedEx International Priority)' => 'FEX/PRI/INTL/PP ',
            'FedEx (Standard Overnight)' => 'FED-X/STD/PPA',
            'FedEx (FedEx Standard Overnight)' => 'FED-X/STD/PPA',
            'UPS® (UPS 2nd Day Air®)' => 'UPS/2 DAY/PPA',
            'UPS® (UPS® Ground)' => 'UPS/GND/PPA',
            'UPS® (UPS Standard℠)' => 'UPSINTLSTD/PPA',
            'UPS® (UPS Next Day Air®)' => 'UPS/NEXTDAY/PPA',
            'UPS® (UPS Worldwide Expedited®)' => 'UPSINTLWEXP/PPA',
            'UPS® (UPS Worldwide Saver®)' => 'UPSINTLWSAV/PPA',
            default => self::GP_SHIP_METHOD_BEST,
        };

    }

    private function format_tax_schedule_id(): string {
        if ($this->order->sales_tax_amount < 0.00001){
            return '';
        }

        if (!empty($this->order->shipping_address)){
            return match (strtoupper($this->order->shipping_address->state)) {
                'FL', 'FLORIDA' => 'WEB-FL',
                'KY', 'KENTUCKY' => 'WEB-KY',
                'MA', 'MASSACHUSETTS' => 'WEB-MA',
                'NJ', 'NEW JERSEY' => 'WEB-NJ',
                'OH', 'OHIO' => 'WEB-OH',
                'TN', 'TENNESSEE' => 'WEB-TN',
                'WA', 'WASHINGTON' => 'WEB-WA',
                'WI', 'WISCONSIN' => 'WEB-WI',
                default => 'WEB-CA',
            };
        } else {
            return match (strtoupper($this->order->billing_address->state)) {
                'FL', 'FLORIDA' => 'WEB-FL',
                'KY', 'KENTUCKY' => 'WEB-KY',
                'MA', 'MASSACHUSETTS' => 'WEB-MA',
                'NJ', 'NEW JERSEY' => 'WEB-NJ',
                'OH', 'OHIO' => 'WEB-OH',
                'TN', 'TENNESSEE' => 'WEB-TN',
                'WA', 'WASHINGTON' => 'WEB-WA',
                'WI', 'WISCONSIN' => 'WEB-WI',
                default => 'WEB-CA',
            };
        }
    }

    private function load_split_item_default_prices(): void {
        if (empty($this->split_item_default_prices)){
            $bc_config = new BigCommerceApiCredentialsConfig();
            $bc_config->access_token = BIGCOMMERCE_API_INSTR_ACCESS_TOKEN;
            $bc_config->client_id = BIGCOMMERCE_API_INSTR_CLIENT_ID;
            $bc_config->client_secret = BIGCOMMERCE_API_INSTR_CLIENT_SECRET;
            $bc_config->store_id = BIGCOMMERCE_STORE_ID_INSTRUMENTS;
            $bc_client = new BigCommerceRestApiClient($bc_config, 'catalog/products');
            $response = $bc_client->get(['sku:in' => implode(',', self::SPLIT_ITEM_SKUS)]);
            $response_data = (array) json_decode($response->body, true);
            if (!empty($response_data['data']) && is_array($response_data['data'])){
                foreach ($response_data['data'] as $product){
                    $this->split_item_default_prices[$product['sku']] = $product['price'];
                }
            }
        }
    }

    private function notes(): string {
        $notes = (string) $this->order->notes;
        if ($this->order->ship_method === self::SHIP_METHOD_UPS_READY){
            $notes .= "2nd-day air chosen\n\n";
        }

        return $notes;
    }

    private function sku_to_add_for_split_item(string $sku, string $option_val): string|null {
        switch ($option_val){
            case self::ITEM_OPTION_REQUIRING_SPLIT_CALIBRATION:
                return 'NIST CALIBRATION';
            case self::ITEM_OPTION_REQUIRING_SPLIT_CALIBRATION_WITH_DATA:
                return 'NIST CALIBRATION WITH DATA';
            case self::ITEM_OPTION_REQUIRING_SPLIT_ISO17025_ACCREDITED:
                switch ($sku){
                    case 'GL2000':
                    case 'GL980':
                        return 'ISO17025-GL980/GL2000';
                    case 'GL240':
                        return 'ISO17025-GL240';
                    case 'GL260':
                        return 'ISO17025-GL260';
                    case 'GL840-M':
                        return 'ISO17025-GL840-M';
                    case 'GL840-WV':
                        return 'ISO17025-GL840-WV';
                }
                break;
        }

        return null;
    }

    private function split_item_option_val(OrderItem $item): string|null {
        if (empty($item->options)){
            return null;
        }

        foreach ($item->options as $option){
            if (in_array($option->value, self::ITEM_OPTIONS_REQUIRING_SPLIT)){
                return $option->value;
            }
        }

        return null;
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