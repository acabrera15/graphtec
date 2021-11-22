<?php
require_once('app.php');

$config = new ApiCredentialsConfig();
$config->access_token = BIGCOMMERCE_API_ACCESS_TOKEN;
$config->client_id = BIGCOMMERCE_API_CLIENT_ID;
$config->client_secret = BIGCOMMERCE_API_CLIENT_SECRET;
$config->endpoint = BIGCOMMERCE_V3_API_ENDPOINT;

$api_client = new BigCommerceRestApiClient($config, 'catalog/products');
print_r($api_client->get([])->body);

$config->endpoint = BIGCOMMERCE_V2_API_ENDPOINT;
$api_client = new BigCommerceRestApiClient($config, 'orders');

// yesterday
$date = new DateTime();
$date->sub(date_interval_create_from_date_string('2 hour'));


$response = $api_client->get(['min_date_modified' => $date->format('c')
]);
$orders_arr = json_decode($response->body, true);

echo "\n\nWe have had " . count($orders_arr) . " orders in the past 2 hours\n\n";

