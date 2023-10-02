<?php
require_once (dirname(__FILE__) . '/../app.php');

echo "\n\n" . date('Y-m-d H:i:s') . " - START\n";

$gp_credentials = new SoapCredentialsConfig();
$gp_credentials->endpoint = GP_ENDPOINT_INQUIRY;
$gp_credentials->password = GP_PASSWORD;
$gp_credentials->user_id = GP_USER_ID;

// update order tracking for main site
$bc_config = new BigCommerceApiCredentialsConfig();
$bc_config->access_token = BIGCOMMERCE_API_ACCESS_TOKEN;
$bc_config->client_id = BIGCOMMERCE_API_CLIENT_ID;
$bc_config->client_secret = BIGCOMMERCE_API_CLIENT_SECRET;
$bc_config->store_id = BIGCOMMERCE_STORE_ID_MAIN;
echo date('Y-m-d H:i:s') . " - Updating order tracking for main store\n";
updateOrderTrackingForStore($bc_config, $gp_credentials, 'DEALER');

// update order tracking for instruments site
$bc_config = new BigCommerceApiCredentialsConfig();
$bc_config->access_token = BIGCOMMERCE_API_INSTR_ACCESS_TOKEN;
$bc_config->client_id = BIGCOMMERCE_API_INSTR_CLIENT_ID;
$bc_config->client_secret = BIGCOMMERCE_API_INSTR_CLIENT_SECRET;
$bc_config->store_id = BIGCOMMERCE_STORE_ID_INSTRUMENTS;
echo date('Y-m-d H:i:s') . " - Updating order tracking for instruments store\n";
updateOrderTrackingForStore($bc_config, $gp_credentials, 'LIST');


function updateOrderTrackingForStore(BigCommerceApiCredentialsConfig $bc_config, SoapCredentialsConfig $gp_config, string $price_level): void
{
    $bc_client = new BigCommerceRestApiClient($bc_config, 'orders');

    try {
        $gp_client = new GpInterfaceClient($gp_config, $bc_config->store_id, $price_level);
        $order_updater = new OrderTrackingUpdater($bc_client, $gp_client);
        $order_updater->update_orders();
    } catch (Exception $e){
        echo "EXCEPTION on line {$e->getLine()} of file {$e->getFile()}: {$e->getMessage()}\n";
    }
}

echo "\n" . date('Y-m-d H:i:s') . " - FINISH\n\n";
