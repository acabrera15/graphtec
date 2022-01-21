<?php
class OrderTrackingUpdater {

    use GpTranslatorHelper;

    // private constants
    private const BC_ORDER_STATUS_AWTNG_FLFLLMNT = 11;
    private const BC_ORDER_STATUS_AWTNG_PICKUP = 8;
    private const BC_ORDER_STATUS_AWTNG_SHIPMENT = 9;
    private const BC_UNSHIPPED_ORDER_STATUSES = [
        self::BC_ORDER_STATUS_AWTNG_FLFLLMNT,
        self::BC_ORDER_STATUS_AWTNG_PICKUP,
        self::BC_ORDER_STATUS_AWTNG_SHIPMENT
    ];
    private const DAYS_BACK = 90;
    private const GP_MAX_ORDERS_PER_REQUEST = 200;
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

    public function update_orders(): void {

        // set a long timeout for the script
        set_time_limit(self::SCRIPT_TIME_LIMIT);

        // query the orders from BC
        $this->query_big_commerce_orders();

        // query the statuses from GP
        $orders_count = count($this->pending_bc_orders);

        // loop through the GP results, and update tracking info, where available
        for ($i = 0; $i < ceil($orders_count / self::GP_MAX_ORDERS_PER_REQUEST); $i++){

            // take a slice of the array
            $orders_slice = array_slice(
                $this->pending_bc_orders,
                $i * self::GP_MAX_ORDERS_PER_REQUEST,
                self::GP_MAX_ORDERS_PER_REQUEST
            );
            $order_data = $this->gp_client->order_statuses($orders_slice);


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

            echo "Page {$page}: Found " . count($response_arr) . " orders...\n";
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