<?php
class BigCommerceOrderIDOrderTranslator {

    // private members
    private BigCommerceRestApiClient    $bc_api_client;
    private string                      $bc_order_id;
    private array                       $customer_data = [];
    private Order                       $order;
    private array                       $order_data = [];
    private array                       $order_products_data = [];
    private array                       $shipping_address_data = [];
    // end private members

    // public functions
    public function __construct(string $bc_order_id){
        $this->bc_order_id = $bc_order_id;
        $this->order = new Order();
        $this->initialize_client();
    }

    /**
     * @return Order
     * @throws Exception
     */
    public function translate(): Order {

        // set off chain of data fetching
        $this->fetch1_basic_order_data();
        $translator = new BigCommerceOrderArrayOrderTranslator(
            $this->order_data,
            $this->shipping_address_data,
            $this->customer_data,
            $this->order_products_data
        );
        $this->order = $translator->translate();

        return $this->order;
    }
    // end public functions

    // private functions
    /**
     * @throws Exception
     */
    private function fetch1_basic_order_data(): void {

        $response = $this->bc_api_client->get([]);
        $this->order_data = (array) json_decode($response->body, true);
        if (empty($this->order_data)){
            $msg = "An error occurred when looking up order details.";
            $this->send_api_error($response, $this->bc_order_id, $msg);
            throw new Exception($msg);
        }

        $this->fetch2_shipping_address_data();
    }

    /**
     * @throws Exception
     */
    private function fetch2_shipping_address_data(): void {
        $this->bc_api_client->set_resource_name('orders/' . $this->bc_order_id . '/shipping_addresses');
        $response = $this->bc_api_client->get([]);
        $this->shipping_address_data = (array) json_decode($response->body, true);
        if (empty($this->shipping_address_data)){
            $msg = "An error occurred when looking up order shipping addresses.";
            $this->send_api_error($response, $this->bc_order_id, $msg);
            throw new Exception($msg);
        }

        $this->fetch3_customer_data();
    }

    /**
     * @throws Exception
     */
    private function fetch3_customer_data(): void {
        $bc_config = new ApiCredentialsConfig();
        $bc_config->access_token = BIGCOMMERCE_API_ACCESS_TOKEN;
        $bc_config->endpoint = BIGCOMMERCE_V3_API_ENDPOINT;
        $this->bc_api_client->set_config($bc_config);
        $this->bc_api_client->set_resource_name('customers');
        $response = $this->bc_api_client->get(['id' => [$this->order_data['customer_id']]]);
        $this->customer_data = (array) json_decode($response->body, true);
        if (empty($this->customer_data)){
            $msg = "An error occurred when looking up customer data.";
            $this->send_api_error($response, $this->bc_order_id, $msg);
            throw new Exception($msg);
        }

        $this->fetch4_order_products_data();
    }

    /**
     * @throws Exception
     */
    private function fetch4_order_products_data(): void {
        $bc_config = new ApiCredentialsConfig();
        $bc_config->access_token = BIGCOMMERCE_API_ACCESS_TOKEN;
        $bc_config->endpoint = BIGCOMMERCE_V2_API_ENDPOINT;
        $this->bc_api_client->set_config($bc_config);
        $this->bc_api_client->set_resource_name('orders/' . $this->bc_order_id . '/products');
        $response = $this->bc_api_client->get([]);
        $this->order_products_data = (array) json_decode($response->body, true);
        if (empty($this->customer_data)){
            $msg = "An error occurred when looking up order product data.";
            $this->send_api_error($response, $this->bc_order_id, $msg);
            throw new Exception($msg);
        }
    }

    private function initialize_client(): void {
        $bc_config = new ApiCredentialsConfig();
        $bc_config->access_token = BIGCOMMERCE_API_ACCESS_TOKEN;
        $bc_config->endpoint = BIGCOMMERCE_V2_API_ENDPOINT;
        $this->bc_api_client = new BigCommerceRestApiClient($bc_config, 'orders/' . $this->bc_order_id);
    }

    private function send_api_error(RestApiResponse $response, mixed $order_id, string $message){
        mail(
            WEBMASTER_EMAIL,
            'Error Processing Graphtec America BigCommerce Order!',
            "{$message} \nOrder ID #{$order_id}\nStatus Code: {$response->status_code}\n",
            "From:no-reply@graphtecamericastore.com"
        );
    }
    // end private functions


}