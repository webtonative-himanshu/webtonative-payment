(function ($) {
  if (!WTN.isNativeApp) {
    return;
  }

  const addCartButton = $(".single_add_to_cart_button, .add_to_cart_button");

  function processPayment(e) {
    e.stopPropagation();
    e.preventDefault();

    const productId = $(this).data("product_id") || $(this).val();

    if (!productId) {
      return;
    }

    const resturl = webtonative_payment_settings.rest_url;
    const nonce = webtonative_payment_settings.nonce;

    function createOrder(data) {
      $.ajax({
        url: resturl + "/order",
        type: "POST",
        contentType: "application/json",
        data: JSON.stringify({
          productId: productId,
          platform: "ANDROID",
          ...data,
        }),
        headers: {
          "X-WP-Nonce": nonce,
        },
        success: function (response) {},
        error: function (error) {},
      });
    }

    $.ajax({
      url: resturl + "/product",
      type: "POST",
      contentType: "application/json",
      data: JSON.stringify({
        productId: productId,
        platform: WTN.isAndroidApp ? "ANDROID" : "IOS",
      }),
      headers: {
        "X-WP-Nonce": nonce,
      },
      success: function (response) {
        const dataToProcess = {
          productId: response.productId,
          productType: response.productType === "SUBS" ? "SUBS" : "INAPP",
          isConsumable: response.isConsumable === "yes",
        };
        if (
          !dataToProcess.productId ||
          !dataToProcess.productType ||
          !dataToProcess.isConsumable
        ) {
          alert("Invalid product");
          return;
        }
        WTN.inAppPurchase({
          ...dataToProcess,
          callback: paymentCallback,
        });
      },
      error: function (error) {},
    });

    function paymentCallback(data) {
      if (!data.isSuccess) {
        alert("Payment failed");
        return;
      }
      const receiptData = data.receiptData;
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
      createOrder(receiptData);
    }

    return false;
  }

  if (!WTN.isNativeApp) {
    addCartButton.off("click");
    addCartButton.on("click", processPayment);
    addCartButton.text("Buy Now");
    addCartButton.removeAttr("data-wc-on--click");
  }
})(jQuery);
