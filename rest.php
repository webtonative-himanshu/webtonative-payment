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

        $current_user = wp_get_current_user();

        //   {
        //     "orderId": "GPA.3393-1665-2705-05335",
        //     "packageName": "com.akash.inapppurchasetest",
        //     "productId": "inappproduct11",
        //     "purchaseTime": 1704368364134,
        //     "purchaseState": 0,
        //     "purchaseToken": "fklghbbljgcdngpcmhigmnfj.AO-J1OxSmspTsCK2_QFwYMxooQ5dOmChoxarMtWeGunWX1uQXH-KItPM6KHMlr6CVgBZhpkXTgYzXgljB76LNkFOsoYa6GzMZw1Olo7Ky-sC530JYdTDlm0",
        //     "quantity": 1,
        //     "acknowledged": false
        // }
        if (
            !isset($request_body['productId']) ||
            !isset($request_body['platform']) ||
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

        $product_id = $request_body["productId"];
        $platform = $request_body["platform"];

        // data from google play and app store
        $native_order_id = $request_body["orderId"];
        $package_name = $request_body["packageName"];
        $purchase_time = $request_body["purchaseTime"];
        $purchase_state = $request_body["purchaseState"];
        $purchase_token = $request_body["purchaseToken"];
        $quantity = $request_body["quantity"];
        $acknowledged = $request_body["acknowledged"];

        $native_order = array(
            "orderId" => $native_order_id,
            "packageName" => $package_name,
            "purchaseTime" => $purchase_time,
            "purchaseState" => $purchase_state,
            "purchaseToken" => $purchase_token,
            "quantity" => $quantity,
            "acknowledged" => $acknowledged,
            "platform" => $platform,
        );

        $order = wc_create_order();
        $order->add_product(wc_get_product($product_id));
        $order->set_payment_method('webtonative');
        // $order->set_status('wc-completed');
        $order->set_customer_id($current_user->ID);
        $order->calculate_totals();
        $order->save();

        $note = '';
        foreach ($native_order as $key => $value) {
            $note .= $key . ' : ' . $value . '<br>';
        }

        $order->add_order_note($note);
        $order_id = $order->get_id();
        update_post_meta($order_id, '_wtn_payment_data', json_encode($native_order));
        return new WP_REST_Response($order->get_id(), 200);
    }
}


new WebtonativePaymentRestController();
