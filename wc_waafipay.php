<?php
/*
 * Plugin Name: WaafiPay Plugin for WooCommerce
 * Author: WaafiPay
 * Description: Accept VISA and Mastercard payments for your virtual store.
 * Version: 1.0.0
 *
  */

//directory access forbidden
if (!defined('ABSPATH')) {
    exit;
}

function wc_gateway_waafipay()
{
    static $plugin;

    if (!isset($plugin)) {
        require_once('includes/class-wc-gateway-waafi-plugin.php');
 
        $plugin = new WC_Gateway_Waafi_Plugin(__FILE__);
    }

    return $plugin;
}
 
wc_gateway_waafipay()->maybe_run();
