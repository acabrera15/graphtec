<?php
require_once ('../app.php');

// log it for now
file_put_contents('../logs/order-updated-json.log', file_get_contents("php://input"), FILE_APPEND);