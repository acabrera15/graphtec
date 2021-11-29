<?php
require_once('config.inc');

// auto-loader
spl_autoload_register(function ($class_name) {
    if (file_exists(dirname(__FILE__) . '/model/' . $class_name . '.php')){
        require_once(dirname(__FILE__) . '/model/' . $class_name . '.php');
    }
});
