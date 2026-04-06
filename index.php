<?php
require 'vendor/autoload.php'; 

use Core\Config;
use Core\AppRouter;

date_default_timezone_set(
    Config::get('APP_TIMEZONE', Config::get('app.timezone', 'Asia/Shanghai'))
);

session_start();

AppRouter::run();


