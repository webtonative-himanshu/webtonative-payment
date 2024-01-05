(function ($) {
  if (!WTN.isAndroidApp && !WTN.isIosApp) {
    return;
  }

  const addCartButton = $(".single_add_to_cart_button, .add_to_cart_button");

  function processPayment() {
    const button = $(this);
    const productId = button.data("product_id") || button.val();

    if (!productId) {
      return;
    }

    const resturl = webtonative_payment_settings.rest_url;
    const nonce = webtonative_payment_settings.nonce;

    function createOrder(data, dataToProcess) {
      let dataToSend = {
        productId: productId,
        platform: WTN.isAndroidApp ? "ANDROID" : "IOS",
        nativeProductId: dataToProcess.productId,
      };
      if (WTN.isIosApp) {
        dataToSend.receiptData = data;
      }
      if (WTN.isAndroidApp) {
        dataToSend = {
          ...dataToSend,
          ...data,
        };
      }
      dataToSend.nativeProductId = "com.w2niapdemo.com.iapdemoproduct";
      dataToSend.receiptData = iosToken;
      dataToSend.platform = "IOS";
      $.ajax({
        url: resturl + "/order",
        type: "POST",
        contentType: "application/json",
        data: JSON.stringify(dataToSend),
        headers: {
          "X-WP-Nonce": nonce,
        },
        success: function (response) {
          if (response.status === "success") {
            button.text("Payment created");
            button.prop("disabled", true);
            return;
          }
          button.prop("disabled", false);
        },
        error: function (error) {
          button.text("Payment failed");
          button.prop("disabled", false);
        },
      });
    }

    button.text("Processing...");
    button.prop("disabled", true);

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
          button.text("Invalid product");
          return;
        }
        WTN.inAppPurchase({
          ...dataToProcess,
          callback: data => paymentCallback(data, dataToProcess),
        });
      },
      error: function (error) {
        button.text("Payment failed");
        alert("Not able to process payment");
      },
    });

    function paymentCallback(data, dataToProcess) {
      if (!data.isSuccess) {
        alert("Payment failed");
        button.text("Buy Now");
        button.prop("disabled", false);
        return;
      }
      const receiptData = data.receiptData;
      createOrder(receiptData, dataToProcess);
    }

    return false;
  }

  addCartButton.off("click");
  addCartButton.on("click", processPayment);
  addCartButton.text("Buy Now");
  addCartButton.removeAttr("data-wc-on--click");
})(jQuery);

const iosToken =
  "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";
