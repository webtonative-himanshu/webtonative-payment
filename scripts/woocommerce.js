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
