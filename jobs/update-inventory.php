<?php
require_once(dirname(__FILE__) . '/../app.php');

echo "\n\n" . date('Y-m-d H:i:s') . " - START\n";

$gp_credentials = new SoapCredentialsConfig();
$gp_credentials->endpoint = GP_ENDPOINT_INQUIRY;
$gp_credentials->password = GP_PASSWORD;
$gp_credentials->user_id = GP_USER_ID;


// main store
$bc_config = new BigCommerceApiCredentialsConfig();
$bc_config->access_token = BIGCOMMERCE_API_ACCESS_TOKEN;
$bc_config->client_id = BIGCOMMERCE_API_CLIENT_ID;
$bc_config->client_secret = BIGCOMMERCE_API_CLIENT_SECRET;
$bc_config->store_id = BIGCOMMERCE_STORE_ID_MAIN;

echo date('Y-m-d H:i:s') . " - Updating inventory for main store...\n";
updateInventoryForStore($bc_config, $gp_credentials, false);

// instruments store
$bc_config->access_token = BIGCOMMERCE_API_INSTR_ACCESS_TOKEN;
$bc_config->client_id = BIGCOMMERCE_API_INSTR_CLIENT_ID;
$bc_config->client_secret = BIGCOMMERCE_API_INSTR_CLIENT_SECRET;
$bc_config->store_id = BIGCOMMERCE_STORE_ID_INSTRUMENTS;
echo date('Y-m-d H:i:s') . " - Updating inventory for instruments store...\n";
updateInventoryForStore($bc_config, $gp_credentials, true);

echo "\n" . date('Y-m-d H:i:s') . " - FINISH\n\n";


function updateInventoryForStore(BigCommerceApiCredentialsConfig $bc_config, SoapCredentialsConfig $gp_config, bool $verbose_logging): void
{
    $bc_client = new BigCommerceRestApiClient($bc_config, 'catalog/products');

    try {
        $gp_client = new GpInterfaceClient($gp_config, $bc_config->store_id);
        $inventory_updater = new InventoryUpdater($bc_client, $gp_client, $verbose_logging);
        $inventory_updater->update_inventory();
    } catch (Exception $e){
        echo "EXCEPTION on line {$e->getLine()} of file {$e->getFile()}: {$e->getMessage()}\n";
    }
}