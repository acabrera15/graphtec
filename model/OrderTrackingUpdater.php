<?php
class OrderTrackingUpdater {

    use GpTranslatorHelper;

    // private constants
    private const DAYS_BACK = 90;
    private const BC_ORDER_STATUS_AWAITING_FULFILLMENT = 11;
    private const BC_ORDER_STATUS_AWAITING_PICKUP = 8;
    private const BC_ORDER_STATUS_AWAITING_SHIPMENT = 9;
    private const BC_UNSHIPPED_ORDER_STATUSES = [
        self::BC_ORDER_STATUS_AWAITING_FULFILLMENT,
        self::BC_ORDER_STATUS_AWAITING_PICKUP,
        self::BC_ORDER_STATUS_AWAITING_SHIPMENT
    ];
    // end private constants

    // private members
    private BigCommerceRestApiClient    $bc_client;
    /**
     * associative array of integers in which the key is the GP-formatted order ID and the value is the BigCommerce order ID
     * @var int[]
     */
    private array                       $pending_bc_order_ids = [];
    private GpInterfaceClient           $gp_client;
    // end private members

    // public functions
    public function __construct(BigCommerceRestApiClient $bc_client, GpInterfaceClient $gp_client){
        $this->bc_client = $bc_client;
        $this->gp_client = $gp_client;
    }

    public function update_orders(): void {

        // query the orders from BC
        $this->query_big_commerce_orders();

        // query the statuses from GP


        // loop through the GP results, and update tracking info, where available


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
                    $this->pending_bc_order_ids[$this->order_id_to_gp_order_number((string) $order_data_arr['id'])] = $order_data_arr['id'];
                    echo "\tFound order ID {$order_data_arr['id']} with status_id {$order_data_arr['status_id']}\n";
                }
            }
            $page++;
        }
    }
    // end private functions

}