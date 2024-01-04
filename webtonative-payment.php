<?php

/**
 *
 * @link              https://webtonative.com
 * @since             1.0.0
 *
 * @wordpress-plugin
 * Plugin Name:       Integrate Webtonative with WooCommerce
 * Plugin URI:        https://webtonative.com/plugin/Webtonative
 * Description:       Allows customers to use Webtonative payment gateway with the WooCommerce.
 * Version:           1.2.0
 * Author:            webtonative
 * Author URI:        https://webtonative.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       webtonative
 * Domain Path:       /languages
//  * WC requires at least: 3.7
//  * WC tested up to: 	 8.4
 */

defined('ABSPATH') or exit;

if (!defined('WEBTONATIVE_PLUGIN_PATH')) {
    define('WEBTONATIVE_PLUGIN_PATH', plugin_dir_path(__FILE__));
}



class WebToNativePayment
{
    public $plugin_name = 'webtonative';
    public $version = '1.0.0';

    public function __construct()
    {
        add_action('wp_enqueue_scripts', array($this, 'load_scripts'));
    }

    public function load_scripts()
    {
        wp_enqueue_script('webtonative', plugins_url('scripts/webtonative.min.js', __FILE__), array(), '1.0.0', true);
        wp_register_script("webtonative-processing", plugins_url('scripts/woocommerce.js', __FILE__), array("webtonative", "jquery"), '', true);
        wp_localize_script("webtonative-processing", "webtonative_payment_settings", array(
            "rest_url" => get_rest_url(null, $this->plugin_name),
            "nonce" => wp_create_nonce('wp_rest'),
        ));
        wp_enqueue_script('webtonative-processing');
    }
}

$webtonativePayment = new WebToNativePayment();

require_once WEBTONATIVE_PLUGIN_PATH . 'woo-commerce.php';
require_once WEBTONATIVE_PLUGIN_PATH . 'rest.php';
