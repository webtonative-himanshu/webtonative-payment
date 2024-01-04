<?php


if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}



class Webtonative_WC_Config
{

    public function __construct()
    {
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_fields'));
    }

    function add_fields()
    {
        global $woocommerce, $post;
        echo '<div class="product_custom_field">';

        woocommerce_wp_text_input(
            array(
                'id' => '_wtn_google_play_product_id',
                'placeholder' => 'Google Play Product ID',
                'label' => __('Google Play Product ID', 'woocommerce'),
                'desc_tip' => 'true'
            )
        );

        woocommerce_wp_text_input(
            array(
                'id' => '_wtn_app_store_product_id',
                'placeholder' => 'App Store Product ID',
                'label' => __('App Store Product ID', 'woocommerce'),
                'desc_tip' => 'true'
            )
        );

        woocommerce_wp_select(
            array(
                'id' => '_wtn_product_type',
                'label' => __('Product Type', 'woocommerce'),
                'options' => array(
                    'INAPP' => __('In-App Purchase', 'woocommerce'),
                    'SUBS' => __('Subscription', 'woocommerce')
                )
            )
        );

        woocommerce_wp_checkbox(
            array(
                'id' => '_wtn_is_consumable',
                'label' => __('Is Consumable', 'woocommerce'),
                'desc_tip' => 'true'
            )
        );
        echo '</div>';
    }


    function save_fields($post_id)
    {
        $wtn_google_play_product_id = $_POST['_wtn_google_play_product_id'];
        if (!empty($wtn_google_play_product_id))
            update_post_meta($post_id, '_wtn_google_play_product_id', esc_attr($wtn_google_play_product_id));

        $wtn_app_store_product_id = $_POST['_wtn_app_store_product_id'];
        if (!empty($wtn_app_store_product_id))
            update_post_meta($post_id, '_wtn_app_store_product_id', esc_attr($wtn_app_store_product_id));

        $wtn_product_type = $_POST['_wtn_product_type'];
        if (!empty($wtn_product_type))
            update_post_meta($post_id, '_wtn_product_type', esc_attr($wtn_product_type));

        $wtn_is_consumable = isset($_POST['_wtn_is_consumable']) ? 'yes' : 'no';
        update_post_meta($post_id, '_wtn_is_consumable', $wtn_is_consumable);
    }

    function add_gatway($gateways)
    {
        $gateways[] = 'WC_Webtonative_Gateway';
        return $gateways;
    }
}

function wc_webtonative_gateway_init()
{

    class WC_Webtonative_Gateway extends WC_Payment_Gateway
    {

        /**
         * Class constructor
         */
        public function __construct()
        {

            $this->id = 'webtonative'; // payment gateway plugin ID
            $this->icon = apply_filters('phonepe_icon', plugin_dir_url(dirname(__FILE__)) . 'assets/phonepe.svg'); // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = false; // in case you need a custom credit card form
            $this->method_title = 'Webtonatuve Payment Gateway';
            $this->method_description = 'Pay using Google Play and Apple App Store'; // will be displayed on the options page


            $this->supports = array(
                'products'
            );

            // Method with all the options fields
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();
            $this->enabled = $this->get_option('enabled');

            // This action hook saves the settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // We need custom JavaScript to obtain a token
            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        }

        /**
         * Plugin options, we deal 
         */
        public function init_form_fields()
        {

            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable Webtonative',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
            );
        }

        /**
         * You will need it if you want your custom credit card form 
         */
        public function payment_fields()
        {
        }

        /*
    * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
    */
        public function payment_scripts()
        {
        }

        /*
     * Fields validation
    */
        public function validate_fields()
        {
        }

        /*
    * We're processing the payments here
    */
        public function process_payment($order_id)
        {
        }

        /*
    * In case you need a webhook
    */
        public function webhook()
        {
        }
    }
}

function wc_webtonative_add_to_gateways($gateways)
{
    $gateways[] = 'WC_Webtonative_Gateway';
    return $gateways;
}

$webtonative_wc_config = new Webtonative_WC_Config();

add_filter('woocommerce_payment_gateways',  array($webtonative_wc_config, 'add_gatway'));
add_action('plugins_loaded', 'wc_webtonative_gateway_init', 11);
