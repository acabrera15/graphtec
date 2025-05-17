<?php
require_once ('../app.php');

// log it for now
write_to_webhook_log(file_get_contents("php://input"));
const IMPORT_STATUS_IDS = [
    8, // awaiting pickup
    9, // awaiting shipment
    10, // paid for digital product
    11 // awaiting fulfillment after payment
];

$request = file_get_contents("php://input");
$request_arr = json_decode($request, true);

if (
    is_array($request_arr)
    && !empty($request_arr['data'])
    && !empty($request_arr['data']['type'])
    && $request_arr['data']['type'] === 'order'
    && !empty($request_arr['data']['id'])
    && !empty($request_arr['producer'])
){

    try {
        $bc_config = producer_to_bc_config($request_arr['producer']);
        $translator = new BigCommerceOrderIDOrderTranslator((string) $request_arr['data']['id'], $bc_config);
        $order = $translator->translate();

        // if not correct status error
        if (!in_array($order->status_id, IMPORT_STATUS_IDS)){
            http_response_code(200);
            write_to_webhook_log("Status Code: 200 (not ready for import with status id {$order->status_id})\n");
            exit('This order status is not ready to import into GP');
        }

        // check if it's already been inserted to avoid sending it a duplicate time
        $connection = new PDO('mysql:dbname=' . DB_NAME . ';host=' . DB_HOST . ';port=' . DB_PORT, DB_USER, DB_PASSWORD);
        if (order_in_gp($connection, $order, $bc_config)){
            http_response_code(200);
            write_to_webhook_log("Status Code: 200 (order already in GP)\n");
            exit('This order has already been inserted into GP. No need to send it again.');
        }

        // connect to the GP Web Interface and attempt to send over the order
        $credentials = new SoapCredentialsConfig();
        $credentials->endpoint = GP_ENDPOINT_ORDER;
        $credentials->password = GP_PASSWORD;
        $credentials->user_id = GP_USER_ID;
        $gp = new GpInterfaceClient($credentials, $bc_config->store_id, producer_to_gp_price_level($request_arr['producer']));
        $gp->submit_order($order);
        write_to_webhook_log("Status Code: 200 (success)");
        if (insert_order_into_gp($connection, $order, $bc_config)){
            write_to_webhook_log("\tInserted into DB for record-keeping\n");
        }
    } catch (Exception $e){
        http_response_code(500);
        echo "EXCEPTION: {$e->getMessage()}\n";
        $response = new RestApiResponse();
        $response->status_code = 500;
        $response->body = $e->getMessage();
        send_api_error($response, $request_arr['data']['id']);
    }
} else {
    http_response_code(400); // bad client request; no JSON involved
    write_to_webhook_log("Status Code: 400 (missing required data)\n");
}

function insert_order_into_gp(PDO $connection, Order $order, BigCommerceApiCredentialsConfig $bc_config): bool {
    $gp_order_number = order_to_gp_order_number($order, $bc_config);
    $sql = $connection->prepare("
        INSERT IGNORE INTO bigcommerce_gp_order_xref (store_hash, bc_order_id, gp_order_id) 
        VALUES (?, ?, ?)
    ");
    return $sql->execute([$bc_config->store_id, $order->id, $gp_order_number ]);
}

function order_to_gp_order_number(Order $order, BigCommerceApiCredentialsConfig $bc_config): string {
    $order_translator = new OrderGPOrderTranslator($order, $bc_config->store_id);
    $gp_order = $order_translator->translate();
    return $gp_order['ORDNO'];
}

function order_in_gp(PDO $connection, Order $order, BigCommerceApiCredentialsConfig $bc_config): bool {
    $gp_order_number = order_to_gp_order_number($order, $bc_config);
    $sql = $connection->prepare("
        SELECT * 
        FROM bigcommerce_gp_order_xref 
        WHERE bc_order_id = ? AND gp_order_id = ? AND store_hash = ?
    ");
    $sql->execute([$order->id, $gp_order_number, $bc_config->store_id]);
    $row = $sql->fetch();

    return !empty($row);
}

function producer_to_bc_config(string $producer): BigCommerceApiCredentialsConfig {
    $config = new BigCommerceApiCredentialsConfig();
    switch ($producer){
        case 'stores/' . BIGCOMMERCE_STORE_ID_INSTRUMENTS:
            $config->access_token = BIGCOMMERCE_API_INSTR_ACCESS_TOKEN;
            $config->client_id = BIGCOMMERCE_API_INSTR_CLIENT_ID;
            $config->client_secret = BIGCOMMERCE_API_INSTR_CLIENT_SECRET;
            $config->store_id = BIGCOMMERCE_STORE_ID_INSTRUMENTS;
            break;
        default:
            $config->access_token = BIGCOMMERCE_API_ACCESS_TOKEN;
            $config->client_id = BIGCOMMERCE_API_CLIENT_ID;
            $config->client_secret = BIGCOMMERCE_API_CLIENT_SECRET;
            $config->store_id = BIGCOMMERCE_STORE_ID_MAIN;
    }

    return $config;
}

function producer_to_gp_price_level(string $producer): string {
    return match ($producer) {
        'stores/' . BIGCOMMERCE_STORE_ID_INSTRUMENTS => 'LIST',
        default => 'DEALER',
    };
}

function send_api_error(RestApiResponse $response, mixed $order_id): void
{
    mail(
        WEBMASTER_EMAIL,
        'Error Processing Graphtec America BigCommerce Order!',
        "{$response->body} \nOrder ID #{$order_id}\nStatus Code: {$response->status_code}\n",
        "From:no-reply@graphtecamericastore.com"
    );
    http_response_code(500);
    write_to_webhook_log("Status Code: " . $response->status_code . ' - ' . $response->body . "\n");
}

function write_to_webhook_log(string $message): void {
    file_put_contents(sys_get_temp_dir() . '/bc-process-order.log', date('Y-m-d H:i:s') . " - {$message}\n", FILE_APPEND);
}

