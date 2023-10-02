<?php

use JetBrains\PhpStorm\ArrayShape;

class GpInterfaceClient {

    use Logger, GpTranslatorHelper;

    // private constants
    private const BC_ORDER_STATUS_AWTNG_FLFLLMNT = 11;
    private const BC_ORDER_STATUS_AWTNG_PICKUP = 8;
    private const BC_ORDER_STATUS_AWTNG_SHIPMENT = 9;
    private const BC_ORDER_STATUS_PENDING = 1;
    private const BC_ORDER_STATUS_SHIPPED = 2;
    private const DEFAULT_PRCLEVEL = 'DEALER';
    private const GP_ORDER_STATUS_PENDING = 'PENDING';
    private const GP_ORDER_STATUS_PROCESSING = 'PROCESSING';
    private const GP_ORDER_STATUS_SHIPPED = 'SHIPPED';
    private const LOG = 'class.GpInterfaceClient.log';
    private const SEVERITY_SUCCESS = 'SUCCESS';
    // end private constants

    // private members
    private SoapClient              $client;
    private SoapCredentialsConfig   $config;
    private string                  $message = '';
    private string                  $price_level;
    private string                  $store_id;
    // end private members

    // public functions
    public function get_config(): SoapCredentialsConfig { return $this->config; }
    public function get_message(): string { return $this->message; }

    /**
     * @throws SoapFault
     */
    public function __construct(SoapCredentialsConfig $config, string $store_id, string $price_level = self::DEFAULT_PRCLEVEL){
        $this->config = $config;
        $this->init_client();
        $this->store_id = $store_id;
        $this->price_level = $price_level;
    }

    /**
     * @param Order[] $orders
     * @return OrderStatus[]
     * @throws Exception
     */
    public function order_statuses(array $orders): array {

        $return_arr = [];

        $data = [
            'AUTHENTICATION' => $this->authentication_array(),
            'ORDERSTATUSES' => []
        ];
        foreach ($orders as $order){
            $translator = new OrderGPOrderTranslator($order, $this->store_id);
            $gp_order_arr = $translator->translate();
            $data['ORDERSTATUSES'][] = [
                'ORDERSTATUS' => [
                    'GPCUSTID' => $gp_order_arr['GPCUSTID'],
                    'ORDNO' => $gp_order_arr['ORDNO'],
                    'CUSTPONO' => null
                ]
            ];
        }
        $result = $this->soap_call('getOrderStatus', $data);
        $this->write_to_log('update-order-tracking.log', "\tORDER STATUSES: " . print_r($result, true) . "\n");
        $this->check_response_for_errors($result);
        if (!empty($result->ORDERSTATUSES) && !empty($result->ORDERSTATUSES->ORDERSTATUS)){
            foreach ($result->ORDERSTATUSES->ORDERSTATUS as $gp_order){

                // skip if it doesn't have a status
                if (empty($gp_order->STATUS)){
                    $this->write_to_log('update-order-tracking.log', "\t\tEmpty status for this order");
                    continue;
                }

                $order_status = new OrderStatus();
                $order_status->order_id = $this->gp_order_number_to_order_id($gp_order->ORDNO, $this->store_id);
                $order_status->status = self::BC_ORDER_STATUS_AWTNG_FLFLLMNT;
                $order_status->tracking = [];
                switch ($gp_order->STATUS){
                    case self::GP_ORDER_STATUS_PENDING:
                        $order_status->status = self::BC_ORDER_STATUS_AWTNG_FLFLLMNT;
                        break;
                    case self::GP_ORDER_STATUS_PROCESSING:
                        $order_status->status = self::BC_ORDER_STATUS_AWTNG_SHIPMENT;
                        break;
                    case self::GP_ORDER_STATUS_SHIPPED:
                        $order_status->status = self::BC_ORDER_STATUS_SHIPPED;
                        if (!empty($gp_order->TRACKINGNOS)){
                            foreach ($gp_order->TRACKINGNOS->TRACKNO as $tracking_number){
                                $tracking = new OrderShipTracking();
                                $tracking->method = $gp_order->SHPMTHD;
                                $tracking->tracking_number = $tracking_number;
                                $order_status->tracking[] = $tracking;
                            }
                        }
                }
                $return_arr[$order_status->order_id] = $order_status;
            }
        } else {
            $this->write_to_log('update-order-tracking.log', "\tNo statuses found for this batch.");
        }

        return $return_arr;
    }

    /**
     * @param string $site_id
     * @param array $item_numbers
     * @return InventoryItem[]
     * @throws Exception
     */
    public function query_inventory(string $site_id, array $item_numbers): array {

        $return_arr = [];
        $data = [
            'AUTHENTICATION' => $this->authentication_array(),
            'INVENTORY' => [
                'ITEMS' => []
            ]
        ];
        foreach ($item_numbers as $item_number){
            $data['INVENTORY']['ITEMS'][] = [
                'ITEM' => [
                    'SITEID' => $site_id,
                    'ITMNMBR' => $item_number,
                    'PRCLEVEL' => $this->price_level
                ]
            ];
        }

        $result = $this->soap_call('getInventory', $data);
        $this->check_response_for_errors($result);

        if (!isset($result->INVENTORY) || !isset($result->INVENTORY->ITEMS) || !isset($result->INVENTORY->ITEMS->ITEM)){
            throw new Exception('Inventory response from GP missing');
        }

        foreach ($result->INVENTORY->ITEMS->ITEM as $gp_item){
            $item = new InventoryItem();
            $item->quantity = (int) $gp_item->QTYAVAIL ?? 0;
            $item->sku = $gp_item->ITMNMBR ?? '???';
            $return_arr[] = $item;
        }
        $this->message = 'Inventory queried successfully';

        return $return_arr;
    }

    /**
     * @throws SoapFault
     */
    public function refresh_client(): void {
        $this->init_client();
    }

    /**
     * @param Customer $customer
     * @throws Exception
     */
    public function submit_customer(Customer $customer): void {
        $customer_translator = new CustomerGPCustomerTranslator($customer);
        $customer_data = [
            'AUTHENTICATION' => $this->authentication_array(),
            'CUSTOMER' => $customer_translator->translate()
        ];

        $result = $this->soap_call('ImportCustomer', $customer_data);
        $this->check_response_for_errors($result);

        $this->message = 'Customer interfaced successfully';
    }

    /**
     * throws an Exception if there was an error submitting the order
     * @param Order $order
     * @return void
     * @throws Exception
     */
    public function submit_order(Order $order): void {

        $customer_translator = new CustomerGPCustomerTranslator($order->customer);
        $customer_data = [
            'AUTHENTICATION' => $this->authentication_array(),
            'CUSTOMER' => $customer_translator->translate()
        ];

        $order_translator = new OrderGPOrderTranslator($order, $this->store_id);
        $order_data = [
            'AUTHENTICATION' => $this->authentication_array(),
            'SALESORDER' => $order_translator->translate()
        ];

        $result = $this->soap_call('ImportCustomerAndSalesOrder', $customer_data, 'custXMLStr', $order_data, 'soXMLStr');
        $this->check_response_for_errors($result);

        $this->message = 'Order interfaced successfully';
    }
    // end public functions

    // private functions
    private function array_to_xml_string(array $data, ?string $root_node_name = 'data', SimpleXMLElement $xml = null): string {
        // turn off compatibility mode as simple xml throws a wobbly if you don't.
        if (ini_get('zend.ze1_compatibility_mode') == 1){
            ini_set ('zend.ze1_compatibility_mode', 0);
        }

        // set the initial element if needed
        if (is_null($xml)):
            $xml = simplexml_load_string("<?xml version='1.0' encoding='utf-8'?><$root_node_name />");
        endif;

        // iterate the data
        foreach ($data as $key => $val):
            if (is_array($val)):
                if (!is_numeric($key)):
                    $sub_node = $xml->addChild($key);
                    $this->array_to_xml_string($val, null, $sub_node);
                else:
                    $this->array_to_xml_string($val, null, $xml);
                endif;
            else:
                $xml->addChild($key, htmlspecialchars($val));
            endif;
        endforeach;

        // pass back string
        return $xml->asXML();
    }

    #[ArrayShape(['USERCREDENTIAL' => "array"])]
    private function authentication_array(): array {
        return [
            'USERCREDENTIAL' => [
                'ID' => $this->config->user_id,
                'PASSWORD' => $this->config->password
            ]
        ];
    }

    /**
     * @param SimpleXMLElement $result
     * @throws Exception
     */
    private function check_response_for_errors(SimpleXMLElement $result): void {
        if (
            empty($result->NOTIFICATIONS)
            || empty($result->NOTIFICATIONS->SEVERITY)
            || (string) $result->NOTIFICATIONS->SEVERITY !== self::SEVERITY_SUCCESS
        ){
            $msg = '';
            if (!empty($result->NOTIFICATIONS->MESSAGES)){
                foreach ($result->NOTIFICATIONS->MESSAGES->MESSAGE as $message){
                    $msg .= "$message\n";
                }
            }

            // some order statuses are okay to skip over
            if (trim($msg) === 'The order no is duplicate.'){
                return;
            }

            throw new Exception("SEVERITY: {$result->NOTIFICATIONS->SEVERITY}\nMessages: $msg");
        }
    }

    /**
     * @throws SoapFault
     */
    private function init_client(): void {
        $this->client = new SoapClient(
            $this->config->endpoint,
            ['connection_timeout' => 30, 'exceptions' => 1, 'trace' => 1, 'verify_peer' => false]
        );
    }

    private function soap_call(
        string $method,
        array $data,
        string $xml_key1 = 'XMLStr',
        array $data2 = null,
        string $xml_key2 = null
    ): SimpleXMLElement {
        $xml_arr = [$xml_key1 => $this->array_to_xml_string($data, 'REQUEST')];
        if (!empty($xml_key2) && is_array($data2)){
            $xml_arr[$xml_key2] = $this->array_to_xml_string($data2, 'REQUEST');
        }

        $this->write_to_log(self::LOG, "REQUEST\n" . print_r($xml_arr, true));

        $response_obj = $this->client->{$method}($xml_arr);

        $this->write_to_log(self::LOG, "REPLY: " . print_r($response_obj, true) . "\n");

        return simplexml_load_string($response_obj->{$method . 'Result'});
    }
    // end private functions

}