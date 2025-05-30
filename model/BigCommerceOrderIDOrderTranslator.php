<?php
class BigCommerceOrderIDOrderTranslator {

    use Logger;

    // private members
    private BigCommerceRestApiClient            $bc_api_client;
    private BigCommerceApiCredentialsConfig     $bc_credentials;
    private string                              $bc_order_id;
    private ?string                             $coupon_code = null;
    private array                               $customer_data = [];
    private Order                               $order;
    private array                               $order_data = [];
    private array                               $order_products_data = [];
    private array                               $shipping_address_data = [];
    // end private members

    // public functions
    public function __construct(string $bc_order_id, BigCommerceApiCredentialsConfig $bc_credentials){
        $this->bc_credentials = $bc_credentials;
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
            $this->order_products_data,
            $this->coupon_code
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

        $this->fetch3_customer_data();
    }

    /**
     * @throws Exception
     */
    private function fetch3_customer_data(): void {

        if (!empty($this->order_data['customer_id'])){
            $this->bc_api_client->set_resource_name('customers');
            $response = $this->bc_api_client->get(['id:in' => $this->order_data['customer_id']]);
            $response_data = (array) json_decode($response->body, true);
            if (empty($response_data['data'])){
                $msg = "An error occurred when looking up customer data for customer ID {$this->order_data['customer_id']}.\nPost body: {$response->body}";
                $this->send_api_error($response, $this->bc_order_id, $msg);
                throw new Exception($msg);
            }
            $this->customer_data = $response_data['data'][0];
        } else {
            $this->initialize_guest_customer_data();
        }

        $this->fetch4_order_products_data();
    }

    /**
     * @throws Exception
     */
    private function fetch4_order_products_data(): void {
        $this->bc_api_client->set_resource_name('orders/' . $this->bc_order_id . '/products');
        $response = $this->bc_api_client->get([]);
        $this->order_products_data = (array) json_decode($response->body, true);
        if (empty($this->order_products_data)){
            $msg = "An error occurred when looking up order product data.";
            $this->send_api_error($response, $this->bc_order_id, $msg);
            throw new Exception($msg);
        }

        $this->fetch5_coupon_data();
    }

    private function fetch5_coupon_data(): void {
        if (abs($this->order_data['coupon_discount']) > 0.0001){
            $this->bc_api_client->set_resource_name('orders/' . $this->bc_order_id . '/coupons');
            $response = $this->bc_api_client->get([]);
            $response_data = (array) json_decode($response->body, true);
            if (!empty($response_data)){
                $this->coupon_code = $response_data[0]['code'];
            }
        }
    }

    private function initialize_client(): void {
        $this->bc_api_client = new BigCommerceRestApiClient($this->bc_credentials, 'orders/' . $this->bc_order_id);
    }

    private function initialize_guest_customer_data(): void {
        if (!empty($this->shipping_address_data)){
            $this->customer_data = [
                'addresses' => $this->shipping_address_data,
                'company' => $this->shipping_address_data[0]['company'],
                'customer_group_id' => 0,
                'date_created' => date('T'),
                'email' => $this->shipping_address_data[0]['email'],
                'first_name' => $this->shipping_address_data[0]['first_name'],
                'id' => 'S-' . $this->shipping_address_data[0]['id'],
                'last_name' => $this->shipping_address_data[0]['last_name'],
                'notes' => 'Customer data generated from shipping address data for guest checkout',
                'phone' => $this->shipping_address_data[0]['phone']
            ];
        } else {
            $this->customer_data = [
                'addresses' => [$this->order_data['billing_address']],
                'company' => $this->order_data['billing_address']['company'],
                'customer_group_id' => 0,
                'date_created' => date('T'),
                'email' => $this->order_data['billing_address']['email'],
                'first_name' => $this->order_data['billing_address']['first_name'],
                'id' => 'S-' . $this->order_data['billing_address']['id'],
                'last_name' => $this->order_data['billing_address']['last_name'],
                'notes' => 'Customer data generated from shipping address data for guest checkout',
                'phone' => $this->order_data['billing_address']['phone']
            ];
        }

    }

    private function send_api_error(RestApiResponse $response, mixed $order_id, string $message): void
    {
        $this->write_to_log(
            get_class($this) . '.log',
            "Error Processing Graphtec America BigCommerce Order!\n{$message} \nOrder ID #{$order_id}\nStatus Code: {$response->status_code}\n"
        );
        /*mail(
            WEBMASTER_EMAIL,
            'Error Processing Graphtec America BigCommerce Order!',
            "{$message} \nOrder ID #{$order_id}\nStatus Code: {$response->status_code}\n",
            "From:no-reply@graphtecamericastore.com"
        );*/
    }
    // end private functions


}