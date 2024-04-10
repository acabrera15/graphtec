<?php
require_once('app.php');

$config = new BigCommerceApiCredentialsConfig();
$config->access_token = BIGCOMMERCE_API_INSTR_ACCESS_TOKEN;
$config->client_id = BIGCOMMERCE_API_INSTR_CLIENT_ID;
$config->client_secret = BIGCOMMERCE_API_INSTR_CLIENT_SECRET;
$config->store_id = BIGCOMMERCE_STORE_ID_INSTRUMENTS;

/*$api_client = new BigCommerceRestApiClient($config, 'catalog/products');
print_r($api_client->get([])->body);*/

$api_client = new BigCommerceRestApiClient($config, 'orders/1338/products');

$translator = new BigCommerceOrderIDOrderTranslator(1338, $config);
print_r($translator->translate());
exit();

//print_r($api_client->get([]));
exit();

$api_client = new BigCommerceRestApiClient($config, 'shipping/zones');
$zones_response = $api_client->get([]);
$zones_arr = json_decode($zones_response->body);
$shipping_methods = [];
foreach ($zones_arr as $zone){
    $api_client = new BigCommerceRestApiClient($config, 'shipping/zones/' . $zone->id . '/methods');
    $methods_response = $api_client->get([]);
    $methods_arr = json_decode($methods_response->body);
    foreach ($methods_arr as $method){
        if (!isset($shipping_methods[$method->type])){
            $shipping_methods[$method->type] = $method;
        }
    }
}

print_r($shipping_methods);

/*
// yesterday
$date = new DateTime();
$date->sub(date_interval_create_from_date_string('2 hour'));


$response = $api_client->get(['min_date_modified' => $date->format('c')
]);
$orders_arr = json_decode($response->body, true);

echo "\n\nWe have had " . count($orders_arr) . " orders in the past 2 hours\n\n";
*/

