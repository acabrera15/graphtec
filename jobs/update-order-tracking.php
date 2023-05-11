<?php
require_once (dirname(__FILE__) . '/../app.php');

echo "\n\nSTART\n\n";

$gp_credentials = new SoapCredentialsConfig();
$gp_credentials->endpoint = GP_ENDPOINT_INQUIRY;
$gp_credentials->password = GP_PASSWORD;
$gp_credentials->user_id = GP_USER_ID;


// map out product SKUs to IDs
$bc_config = new ApiCredentialsConfig();
$bc_config->access_token = BIGCOMMERCE_API_ACCESS_TOKEN;
$bc_config->endpoint = BIGCOMMERCE_V2_API_ENDPOINT;
$bc_client = new BigCommerceRestApiClient($bc_config, 'orders');

try {
    $gp_client = new GpInterfaceClient($gp_credentials);
    $order_updater = new OrderTrackingUpdater($bc_client, $gp_client);
    $order_updater->update_orders();
} catch (Exception $e){
    echo "EXCEPTION on line {$e->getLine()} of file {$e->getFile()}: {$e->getMessage()}\n";
}

echo "\n\nFINISH\n\n";
