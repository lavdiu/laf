<?php
ob_start("ob_gzhandler");
/**
 * including configs
 */
require_once(__DIR__.'/../../app/config/config.php');
session_name("intrepicure_pref");
session_set_cookie_params(0, '/', '.'.$settings->getProperty('base_servername'), ($settings->getProperty('protocol') == 'https'));
session_start();
require_once(__DIR__ . '/../../app/config/intrepicure.com_bootstrap.php');


