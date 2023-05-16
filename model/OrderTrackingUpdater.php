<?php
class OrderTrackingUpdater {

    use GpTranslatorHelper;

    // private constants
    private const BC_ORDER_STATUS_AWTNG_FLFLLMNT = 11;
    private const BC_ORDER_STATUS_AWTNG_PICKUP = 8;
    private const BC_ORDER_STATUS_AWTNG_SHIPMENT = 9;
    private const BC_ORDER_STATUS_PENDING = 1;
    private const BC_ORDER_STATUS_SHIPPED = 2;
    private const BC_UNSHIPPED_ORDER_STATUSES = [
        self::BC_ORDER_STATUS_AWTNG_FLFLLMNT,
        self::BC_ORDER_STATUS_AWTNG_PICKUP,
        self::BC_ORDER_STATUS_AWTNG_SHIPMENT
    ];
    private const DAYS_BACK = 90;
    private const GP_MAX_ORDERS_PER_REQUEST = 200;
    private const GP_ORDER_STATUS_SHIPPED = '3';
    private const SCRIPT_TIME_LIMIT = 7200;
    // end private constants

    // private members
    private BigCommerceRestApiClient    $bc_client;
    /**
     * associative array of integers in which the key is the GP-formatted order ID and the value is the BigCommerce order ID
     * @var Order[]
     */
    private array                       $pending_bc_orders = [];
    private GpInterfaceClient           $gp_client;
    // end private members

    // public functions
    public function __construct(BigCommerceRestApiClient $bc_client, GpInterfaceClient $gp_client){
        $this->bc_client = $bc_client;
        $this->gp_client = $gp_client;
    }

    /**
     * @throws Exception
     */
    public function update_orders(): void {

        // set a long timeout for the script
        set_time_limit(self::SCRIPT_TIME_LIMIT);

        // query the orders from BC
        $this->query_big_commerce_orders();

        // query the statuses from GP
        $orders_count = count($this->pending_bc_orders);
        echo "Found a total of {$orders_count} pending BigCommerce orders.\n";

        // loop through the GP results, and update tracking info, where available
        for ($i = 0; $i < ceil($orders_count / self::GP_MAX_ORDERS_PER_REQUEST); $i++){
            echo "\tSlide $i of the BigCommerce orders...\n";

            // take a slice of the array
            $orders_slice = array_slice(
                $this->pending_bc_orders,
                $i * self::GP_MAX_ORDERS_PER_REQUEST,
                self::GP_MAX_ORDERS_PER_REQUEST
            );
            try {
                $order_data = $this->gp_client->order_statuses($orders_slice);
                echo "\tFound a total of " . count($order_data) . " order statuses in GP for these BC orders...\n";
                foreach ($orders_slice as $order){

                    if (!empty($order_data[$order->id])){

                        echo "\t\tOrder #{$order->id}...\n";

                        // if it's got shipment info, let's attach it
                        if (!empty($order_data[$order->id]->tracking)){

                            echo "\t\t\tIt has tracking info!\n";
                            foreach($order_data[$order->id]->tracking as $tracking){

                                $this->bc_client->set_resource_name('orders/' . $order->id . '/shipping_addresses');
                                $addresses_response = $this->bc_client->get([]);
                                $addresses = json_decode($addresses_response->body, true);
                                echo "\t\t\t\tLooking up the order shipping addresses...\n";

                                echo "\t\t\t\tSending over tracking number {$tracking->tracking_number}\n";
                                    $shipment_data = [
                                        'items' => [],
                                        'order_address_id' => $addresses[0]['id'],
                                        'tracking_number' => $tracking->tracking_number
                                    ];

                                    echo "\t\t\t\tLooking up products in the order to send over...\n";
                                    $this->bc_client->set_resource_name('orders/' . $order->id . '/products');
                                    $products_response = $this->bc_client->get([]);
                                    $products = json_decode($products_response->body, true);
                                    foreach ($products as $product){
                                        $shipment_data['items'][] = [
                                            'order_product_id' => $product['id'],
                                            'product_id' => $product['product_id'],
                                            'quantity' => $product['quantity']
                                        ];
                                    }
                                    $this->bc_client->set_resource_name('orders/' . $order->id . '/shipments');
                                    $shipment_response = $this->bc_client->post($shipment_data);
                                    if ($shipment_response->status_code === 201){
                                        echo "\t\t\t\t\tSUCCESS\n";
                                    } else {
                                        echo "\t\t\t\t\tERROR: Could not send ship tracking info: " . $shipment_response->body . "\n";
                                    }

                            }
                        }

                        // update the order status
                        echo "\t\t\tUpdating status...\n";
                        $this->bc_client->set_resource_name('orders/' . $order->id);
                        $status_response = $this->bc_client->put(['status_id' => $order_data[$order->id]->status]);
                        if (in_array($status_response->status_code, [200, 201])){
                            echo "\t\t\t\tSUCCESS\n";
                        } else {
                            echo "\t\t\t\tERROR: Could not update order status: " . $status_response->body . "\n";
                        }
                    }
                }
            } catch (Exception $e){
                echo "EXCEPTION: {$e->getMessage()}; file: {$e->getFile()}; line: {$e->getLine()}; type: " . get_class($e) . "\n";
            }
        }
    }
    // end public functions

    // private functions
    private function query_big_commerce_orders(): void {
        $min_date_created = date('c', strtotime(self::DAYS_BACK . ' days ago'));
        $page = 1;
        $this->bc_client->set_resource_name('orders');
        while (true){
            $response = $this->bc_client->get(['min_date_created' => $min_date_created, 'page' => $page]);
            $response_arr = json_decode($response->body, true);
            if (empty($response_arr)){
                break;
            }

            echo "Page $page: Found " . count($response_arr) . " orders...\n";
            foreach ($response_arr as $order_data_arr){
                if (in_array($order_data_arr['status_id'], self::BC_UNSHIPPED_ORDER_STATUSES)){

                    $translator = new BigCommerceOrderIDOrderTranslator((string) $order_data_arr['id']);
                    try {
                        $this->pending_bc_orders[] = $translator->translate();
                        echo "\tFound order ID {$order_data_arr['id']} with status_id {$order_data_arr['status_id']}\n";
                    } catch (Exception $e){
                        echo "\tEXCEPTION on order ID {$order_data_arr['id']}: {$e->getMessage()}\n";
                    }
                }
            }
            $page++;
        }
    }
    // end private functions

}