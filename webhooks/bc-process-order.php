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
){
    try {
        $translator = new BigCommerceOrderIDOrderTranslator((string) $request_arr['data']['id']);
        $order = $translator->translate();
        if (!in_array($order->status_id, IMPORT_STATUS_IDS)){
            http_response_code(200);
            exit('This order status is not ready to import into GP');
        }

        // connect to the GP Web Interface and attempt to send over the order
        $credentials = new SoapCredentialsConfig();
        $credentials->endpoint = GP_ENDPOINT_ORDER;
        $credentials->password = GP_PASSWORD;
        $credentials->user_id = GP_USER_ID;
        $gp = new GpInterfaceClient($credentials);
        $gp->submit_order($order);
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
    write_to_webhook_log($response->status_code . ' - ' . $response->body . "\n");
}

function write_to_webhook_log(string $message): void {
    file_put_contents('../logs/bc-process-order.log', date('Y-m-d H:i:s') . " - {$message}\n", FILE_APPEND);
}

