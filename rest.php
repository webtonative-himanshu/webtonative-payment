<?php


defined('ABSPATH') or exit;


class WebtonativePaymentRestController
{

    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    function register_routes()
    {
        global $webtonativePayment;
        register_rest_route($webtonativePayment->plugin_name, '/product', array(
            'methods' => 'POST',
            'callback' => array($this, 'get_native_id'),
        ));
        register_rest_route($webtonativePayment->plugin_name, '/order', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_woocommerce_order'),
        ));
    }

    function verify_apple_payment(string $product_id, string $receipt_data)
    {
        $payment_gateway_id = 'webtonative';
        $payment_gateways   = WC_Payment_Gateways::instance()->payment_gateways();
        if (!isset($payment_gateways[$payment_gateway_id])) {
            return false;
        }
        $payment_gateway = $payment_gateways[$payment_gateway_id];
        if (empty($payment_gateway->appStoreSecret)) {
            return false;
        }
        $isTest = $payment_gateway->isTest === 'yes';
        $url = $isTest ? 'https://sandbox.itunes.apple.com/verifyReceipt' : 'https://buy.itunes.apple.com/verifyReceipt';
        $data = json_encode(array(
            'receipt-data' => $receipt_data,
            'password' => $payment_gateway->appStoreSecret,
            'exclude-old-transactions' => true,
        ));
        $response = wp_remote_post($url, array(
            'method' => 'POST',
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => $data,
            'cookies' => array(),
        ));
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            throw new Exception("Something went wrong: $error_message");
        }
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        $receipt = $response_data['receipt'];
        $in_app = $receipt['in_app'];
        $product_data = null;
        foreach ($in_app as $key => $value) {
            if ($value['product_id'] === $product_id) {
                $product_data = $value;
            }
        }
        if (empty($product_data)) {
            return false;
        }
        $in_app_ownership_type = $product_data['in_app_ownership_type'];

        if ($in_app_ownership_type === 'PURCHASED') {
            return true;
        }
        return false;
    }

    function create_android_order(string $product_id, $request_body)
    {

        $current_user = wp_get_current_user();
        if (
            !isset($request_body['orderId']) ||
            !isset($request_body['packageName']) ||
            !isset($request_body['purchaseTime']) ||
            !isset($request_body['purchaseState']) ||
            !isset($request_body['purchaseToken']) ||
            !isset($request_body['quantity']) ||
            !isset($request_body['acknowledged'])
        ) {
            return new WP_REST_Response(array('error' => 'Invalid Request'), 400);
        }

        // data from google play 
        $native_order_id = $request_body["orderId"];
        $native_product_id = $request_body["nativeProductId"];
        $package_name = $request_body["packageName"];
        $purchase_time = $request_body["purchaseTime"];
        $purchase_state = $request_body["purchaseState"];
        $purchase_token = $request_body["purchaseToken"];
        $quantity = $request_body["quantity"];
        $acknowledged = $request_body["acknowledged"];

        $native_order = array(
            "productId" => $native_product_id,
            "orderId" => $native_order_id,
            "packageName" => $package_name,
            "purchaseTime" => $purchase_time,
            "purchaseState" => $purchase_state,
            "purchaseToken" => $purchase_token,
            "quantity" => $quantity,
            "acknowledged" => $acknowledged,
            "platform" => "ANDROID"
        );

        $order = wc_create_order();
        $order->add_product(wc_get_product($product_id));
        $order->set_payment_method('webtonative');
        // $order->set_status('wc-completed');
        $order->set_customer_id($current_user->ID);
        $order->calculate_totals();
        $order->save();

        $note = 'Webtonative Payment Data <br>';
        foreach ($native_order as $key => $value) {
            $note .= $key . ' : ' . $value . '<br>';
        }
        $order->add_order_note($note);
        $order_id = $order->get_id();
        update_post_meta($order_id, '_wtn_payment_data', json_encode($native_order));
        return new WP_REST_Response($order->get_id(), 200);
    }

    function create_ios_order(string $product_id, $request_body)
    {
        // data from apple
        $receipt_data = $request_body["receiptData"];
        $native_product_id = $request_body["nativeProductId"];
        $current_user = wp_get_current_user();

        // check type of receipt data is string
        if (!is_string($receipt_data)) {
            return new WP_REST_Response(array('error' => 'Invalid Request'), 400);
        }
        $is_valid = $this->verify_apple_payment($native_product_id, $receipt_data);
        if (!$is_valid) {
            return new WP_REST_Response(array('error' => 'Invalid Request'), 400);
        }

        $order = wc_create_order();
        $order->add_product(wc_get_product($product_id));
        $order->set_payment_method('webtonative');
        $order->set_status('wc-completed');
        $order->set_customer_id($current_user->ID);
        $order->calculate_totals();
        $order->save();

        $note = 'Webtonative Payment Data <br>';

        $native_order = array(
            "productId" => $native_product_id,
            "receiptData" => $receipt_data,
            "platform" => "IOS"
        );
        foreach ($native_order as $key => $value) {
            $note .= $key . ' : ' . $value . '<br>';
        }
        $order->add_order_note($note);
        $order_id = $order->get_id();
        update_post_meta($order_id, '_wtn_payment_data', json_encode($native_order));
        return new WP_REST_Response($order->get_id(), 200);
    }

    function get_native_id(WP_REST_Request $request)
    {

        $request_body = json_decode($request->get_body(), true);

        if (
            !isset($request_body['productId']) ||
            !isset($request_body['platform'])
        ) {
            return new WP_REST_Response(array('error' => 'Product not found'), 404);
        }

        $product_id = $request_body["productId"];
        $platform = $request_body["platform"];

        $googlePlay = get_post_meta($product_id, "_wtn_google_play_product_id", true);
        $appStore = get_post_meta($product_id, "_wtn_app_store_product_id", true);
        $productType = get_post_meta($product_id, "_wtn_product_type", true);
        $isConsumable = get_post_meta($product_id, "_wtn_is_consumable", true);
        $nativeProductId = '';
        if ($platform === "ANDROID") {
            $nativeProductId = $googlePlay;
        }
        if ($platform === "IOS") {
            $nativeProductId = $appStore;
        }
        $response_data = array(
            "productId" => $nativeProductId,
            "productType" => $productType,
            "isConsumable" => $isConsumable,
        );

        // Return the product data in the response
        return new WP_REST_Response($response_data, 200);
    }

    function create_woocommerce_order(WP_REST_Request $request)
    {
        $request_body = json_decode($request->get_body(), true);

        if (!is_user_logged_in()) {
            return new WP_REST_Response(array('error' => 'User not logged in'), 401);
        }

        if (
            !isset($request_body['productId']) ||
            !isset($request_body['platform']) ||
            !isset($request_body['nativeProductId'])
        ) {
            return new WP_REST_Response(array('error' => 'Invalid Request'), 400);
        }

        $platform = $request_body["platform"];
        $product_id = $request_body["productId"];

        if ($platform === "ANDROID") {
            return $this->create_android_order($product_id, $request_body);
        }

        if ($platform === "IOS") {
            return $this->create_ios_order($product_id, $request_body);
        }

        return new WP_REST_Response(array('error' => 'Invalid Request'), 400);
    }
}


new WebtonativePaymentRestController();
