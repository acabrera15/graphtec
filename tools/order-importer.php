<?php
session_start();
$error_msg = '';
const PASSWORD = 'uKAtKMgGeHsSysUc';
if (!empty($_POST['password'])){
    if ($_POST['password'] === PASSWORD){
        $_SESSION['logged_in'] = true;
    } else {
        $error_msg = 'Invalid password';
    }
}
$logged_in = !empty($_SESSION['logged_in']);


?>
<html lang="en">
    <head>
        <title>BigCommerce Order Importer</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    </head>
    <body>
        <div class="container-fluid">
            <div class="col-12 col-md-8 col-sm-6 mx-auto">
                <?php if ($logged_in): ?>
                <?php else: ?>
                    <h4>This page is password protected.</h4>
                    <?php if (!empty($error_msg)): ?>
                        <h5 class="text-danger"><?php echo $error_msg; ?></h5>
                    <?php endif; ?>
                    <form>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" value="<?php echo ($_POST['password'] ?? ''); ?>" />
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </body>
</html>
<?php
