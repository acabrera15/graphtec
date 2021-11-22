<?php
require_once ('../app.php');

// log it for now
file_put_contents('../logs/order-updated-json.log', file_get_contents("php://input"), FILE_APPEND);

$request = file_get_contents("php://input");
$request_arr = json_decode($request, true);
if (is_array($request_arr)){

} else {
    http_response_code(400); // bad client request; no JSON involved
}

