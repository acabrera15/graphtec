<?php

use JetBrains\PhpStorm\ArrayShape;

class GpInterfaceClient {

    use Logger;

    // private constants
    private const DEFAULT_PRCLEVEL = 'DEALER';
    private const LOG = 'class.GpInterfaceClient.log';
    private const SEVERITY_SUCCESS = 'SUCCESS';
    // end private constants

    // private members
    private SoapClient              $client;
    private SoapCredentialsConfig   $config;
    private string                  $message = '';
    // end private members

    // public functions
    public function get_config(): SoapCredentialsConfig { return $this->config; }
    public function get_message(): string { return $this->message; }

    /**
     * @throws SoapFault
     */
    public function __construct(SoapCredentialsConfig $config){
        $this->config = $config;
        $this->init_client();
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
                    'PRCLEVEL' => self::DEFAULT_PRCLEVEL
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

        // DELETE THIS TO GO LIVE
        $return_arr = [];

        $item1 = new InventoryItem();
        $item1->quantity = rand(100, 10000);
        $item1->sku = 'CB09UB-K60';
        $return_arr[] = $item1;

        $item2 = new InventoryItem();
        $item2->quantity = rand(50, 500);
        $item2->sku = 'CP-001';
        $return_arr[] = $item2;
        // END DELETE THIS TO GO LIVE

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

        $order_translator = new OrderGPOrderTranslator($order);
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
            $xml = simplexml_load_string("<?xml version='1.0' encoding='utf-8'?><{$root_node_name} />");
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
                    $msg .= "{$message}\n";
                }
            }

            throw new Exception("SEVERITY: {$result->NOTIFICATIONS->SEVERITY}\nMessages: {$msg}");
        }
    }

    /**
     * @throws SoapFault
     */
    private function init_client(): void {
        $this->client = new SoapClient(
            $this->config->endpoint,
            ['connection_timeout' => 10, 'exceptions' => 1, 'trace' => 1, 'verify_peer' => false]
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
        print_r($xml_arr);
        $response_obj = $this->client->{$method}($xml_arr);

        return simplexml_load_string($response_obj->{$method . 'Result'});
    }
    // end private functions

}