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

} else {
    http_response_code(400); // bad client request; no JSON involved
}

