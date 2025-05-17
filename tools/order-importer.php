<?php
session_start();
require_once('../app.php');
$error_msg = '';
$success_msg = '';
const PASSWORD = 'uKAtKMgGeHsSysUc';
if (!empty($_POST['password'])){
    if ($_POST['password'] === PASSWORD){
        $_SESSION['logged_in'] = true;
        header('Location: https://' . $_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME'] . '?logged_in=1');
        exit();
    } else {
        $error_msg = 'Invalid password';
    }
}
$logged_in = !empty($_SESSION['logged_in']);
if (!empty($_POST['order_number']) && !empty($_POST['store_id'])){
    $list_price = 'DEALER';
    $config = new BigCommerceApiCredentialsConfig();
    switch ($_POST['store_id']){
        case BIGCOMMERCE_STORE_ID_INSTRUMENTS:
            $list_price = 'LIST';
            $config->access_token = BIGCOMMERCE_API_INSTR_ACCESS_TOKEN;
            $config->client_id = BIGCOMMERCE_API_INSTR_CLIENT_ID;
            $config->client_secret = BIGCOMMERCE_API_INSTR_CLIENT_SECRET;
            $config->store_id = BIGCOMMERCE_STORE_ID_INSTRUMENTS;
            break;
        case BIGCOMMERCE_STORE_ID_MAIN:
            $config->access_token = BIGCOMMERCE_API_ACCESS_TOKEN;
            $config->client_id = BIGCOMMERCE_API_CLIENT_ID;
            $config->client_secret = BIGCOMMERCE_API_CLIENT_SECRET;
            $config->store_id = BIGCOMMERCE_STORE_ID_MAIN;
            break;
        default:
            $error_msg = 'Unknown store selected';
    }

    if (empty($error_msg)){
        try {
            $translator = new BigCommerceOrderIDOrderTranslator((string) $_POST['order_number'], $config);
            $order = $translator->translate();

            // connect to the GP Web Interface and attempt to send over the order
            $credentials = new SoapCredentialsConfig();
            $credentials->endpoint = GP_ENDPOINT_ORDER;
            $credentials->password = GP_PASSWORD;
            $credentials->user_id = GP_USER_ID;
            $gp = new GpInterfaceClient($credentials, $config->store_id ?? '', $list_price);
            $gp->submit_order($order);
            $success_msg = 'Order submitted successfully!';
        } catch (Exception $e) {
            $error_msg = 'ERROR: ' . $e->getMessage();
        }
    }
}

?>
<html lang="en">
    <head>
        <title>BigCommerce Order Importer</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    </head>
    <body>
        <div class="container-fluid">
            <div class="col-12 col-md-8 col-lg-6 mx-auto p-4">
                <?php if (!empty($error_msg)): ?>
                    <h5 class="text-danger"><?php echo $error_msg; ?></h5>
                <?php endif; ?>
                <?php if (!empty($success_msg)): ?>
                    <h5 class="text-success"><?php echo $success_msg; ?></h5>
                <?php endif; ?>
                <h1>Graphtec BigCommerce Order Importer</h1>
                <?php if ($logged_in): ?>
                    <form action="" method="post">
                        <div class="mb-3">
                            <label for="store-select" class="form-label">Store</label>
                            <select class="form-select" id="store-select" name="store_id">
                                <option value="<?php echo BIGCOMMERCE_STORE_ID_MAIN; ?>">Main Store</option>
                                <option value="<?php echo BIGCOMMERCE_STORE_ID_INSTRUMENTS; ?>">Instruments Store</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="order-number" class="form-label">BigCommerce Order #</label>
                            <input
                                    class="form-control"
                                    id="order-number"
                                    name="order_number"
                                    placeholder="12345"
                                    type="text"
                                    value=""
                            />
                        </div>
                        <div class="mb-3">
                            <button class="btn btn-primary" type="submit">SEND TO GP</button>
                        </div>
                    </form>
                <?php else: ?>
                    <h4>This page is password protected.</h4>
                    <form action="" method="post">
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" value="<?php echo ($_POST['password'] ?? ''); ?>" />
                        </div>
                        <div class="mb-3">
                            <button class="btn btn-primary" type="submit">LOG IN</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </body>
</html>
<?php
