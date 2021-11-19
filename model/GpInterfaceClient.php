<?php
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
    /**
     * @throws SoapFault
     */
    public function __construct(SoapCredentialsConfig $config){
        $this->config = $config;
        $this->client = new SoapClient(
            $this->config->endpoint,
            ['connection_timeout' => 10, 'exceptions' => 1, 'trace' => 1, 'verify_peer' => false]
        );
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
        print_r($return_arr);
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

    public function submit_order(Order $order): bool {

        // TODO: implement the actual logic to submit orders; for now, just spoof a response
        if (time() % 5 === 0){
            $this->message = 'Error submitting order: Fake error message that appears one out of five times';
            $this->write_to_log(self::LOG, $this->message);

            return false;
        } else {
            $this->message = 'Submitted order successfully';

            return true;
        }
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
                foreach ($result->NOTIFICATIONS->MESSAGES as $message){
                    $msg .= "{$message}\n";
                }
            }

            throw new Exception("SEVERITY: {$result->NOTIFICATIONS->SEVERITY}\nMessages: {$msg}");
        }
    }

    private function soap_call(string $method, array $data): SimpleXMLElement {
        $response_obj = $this->client->{$method}(['XMLStr' => $this->array_to_xml_string($data, 'REQUEST')]);

        return simplexml_load_string($response_obj->{$method . 'Result'});
    }
    // end private functions

}