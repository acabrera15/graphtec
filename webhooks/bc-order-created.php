<?php
require_once ('../app.php');

// log it for now
file_put_contents('../logs/order-created-json.log', file_get_contents("php://input") . "\n", FILE_APPEND);

$request = file_get_contents("php://input");
$request_arr = json_decode($request, true);
if (
    is_array($request_arr)
    && !empty($request_arr['data'])
    && !empty($request_arr['data']['type'])
    && $request_arr['data']['type'] === 'order'
    && !empty($request_arr['data']['id'])
){

    // fetch the order ID via the REST API
    $bc_config = new ApiCredentialsConfig();
    $bc_config->access_token = BIGCOMMERCE_API_ACCESS_TOKEN;
    $bc_config->endpoint = BIGCOMMERCE_V2_API_ENDPOINT;
    $bc_client = new BigCommerceRestApiClient($bc_config, 'orders/' . $request_arr['data']['id']);
    $response = $bc_client->get([]);
    $response_arr = json_decode($response->body, true);
    if (!empty($response_arr)){

        // look up shipping addresses for the order
        $bc_client->set_resource_name('orders/' . $request_arr['data']['id'] . '/shipping_addresses');
        $shipping_addrs_resp = $bc_client->get([]);
        $shipping_addrs_resp_arr = json_decode($shipping_addrs_resp->body, true);
        if (!empty($shipping_addrs_resp_arr)){

            // get the customer data
            $bc_config->endpoint = BIGCOMMERCE_V3_API_ENDPOINT;
            $bc_client->set_config($bc_config);
            $bc_client->set_resource_name('customers');
            $response = $bc_client->get(['id' => [$response_arr['customer_id']]]);
            $customer_resp_arr = json_decode($response->body, true);
            if (!empty($customer_resp_arr) && is_array($customer_resp_arr[0])){

                // get the products from the order
                $bc_config->endpoint = BIGCOMMERCE_V2_API_ENDPOINT;
                $bc_client->set_config($bc_config);
                $bc_client->set_resource_name('orders/' . $request_arr['data']['id'] . '/products');
                $response = $bc_client->get([]);
                $products_resp_arr = json_decode($response->body, true);
                if (!empty($products_resp_arr)){

                    // translate the order from BigCommerce format to internal Order object
                    $order_translator = new BigCommerceOrderArrayOrderTranslator($response_arr, $shipping_addrs_resp_arr, $customer_resp_arr[0], $products_resp_arr);
                    try {
                        $order = $order_translator->translate();

                        // connect to the GP Web Interface and attempt to send over the order
                        $credentials = new SoapCredentialsConfig();
                        $credentials->endpoint = GP_ENDPOINT_ORDER;
                        $credentials->password = GP_PASSWORD;
                        $credentials->user_id = GP_USER_ID;
                        $gp = new GpInterfaceClient($credentials);
                        $gp->submit_order($order);

                    } catch (Exception $e) {
                        $response = new RestApiResponse();
                        $response->status_code = 500;
                        send_api_error(
                            $response,
                            $request_arr['data']['id'],
                            "Exception occurred when sending the BigCommerce order to GP: {$e->getMessage()}"
                        );
                    }
                } else {
                    send_api_error($response, $request_arr['data']['id'], "An error occurred when looking up order items.");
                }
            } else {
                send_api_error($response, $request_arr['data']['id'], "An error occurred when looking up customer data.");
            }
        } else {
            // email someone to address it
            send_api_error($response, $request_arr['data']['id'], "An error occurred when looking up order shipping addresses.");
        }
    } else {

        // email someone to address it
        send_api_error($response, $request_arr['data']['id'], "An error occurred when looking up order details.");
    }
} else {
    http_response_code(400); // bad client request; no JSON involved
}

function send_api_error(RestApiResponse $response, mixed $order_id, string $message){
    mail(
        WEBMASTER_EMAIL,
        'Error Processing Graphtec America BigCommerce Order!',
        "{$message} \nOrder ID #{$order_id}\nStatus Code: {$response->status_code}\n",
        "From:no-reply@graphtecamericastore.com"
    );
    http_response_code(500);
}

