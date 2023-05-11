<?php
require_once(dirname(__FILE__) . '/../app.php');

echo "\n\nSTART\n\n";

$gp_credentials = new SoapCredentialsConfig();
$gp_credentials->endpoint = GP_ENDPOINT_INQUIRY;
$gp_credentials->password = GP_PASSWORD;
$gp_credentials->user_id = GP_USER_ID;


// map out product SKUs to IDs
$bc_config = new ApiCredentialsConfig();
$bc_config->access_token = BIGCOMMERCE_API_ACCESS_TOKEN;
$bc_config->endpoint = BIGCOMMERCE_V3_API_ENDPOINT;
$bc_client = new BigCommerceRestApiClient($bc_config, 'catalog/products');

try {
    $gp_client = new GpInterfaceClient($gp_credentials);
    $inventory_updater = new InventoryUpdater($bc_client, $gp_client);
    $inventory_updater->update_inventory();
} catch (Exception $e){
    echo "EXCEPTION on line {$e->getLine()} of file {$e->getFile()}: {$e->getMessage()}\n";
}

echo "\n\nFINISH\n\n";