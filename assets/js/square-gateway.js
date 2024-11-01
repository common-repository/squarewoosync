let payments; // Declare a global variable for payments
let creditCardPaymentRequest;
let walletPaymentRequest;

(function ($) {
  jQuery(document).ready(async function () {
    if (typeof Square !== "undefined") {
      try {
        const applicationId = SquareConfig.applicationId;
        const locationId = SquareConfig.locationId;
        payments = await Square.payments(applicationId, locationId);

        if (!payments) {
          throw new Error("Square payments initialization failed.");
        }

        const needsShipping = await checkNeedsShipping();
        const couponApplied = false;

        walletPaymentRequest = await initPaymentRequest(
          payments,
          needsShipping,
          couponApplied
        );

        waitForForm("#payment-form", function () {
          initCreditCardPayment(payments, walletPaymentRequest);
          if (
            SquareConfig.applePayEnabled === "yes" ||
            SquareConfig.googlePayEnabled === "yes"
          ) {
            initWalletPayments(
              payments,
              walletPaymentRequest,
              needsShipping,
              couponApplied
            );
          }
        });
      } catch (error) {
        console.error("Error during initialization:", error);
      }
    } else {
      console.error("Square Web Payments SDK is not loaded.");
    }
  });
})(jQuery);

function waitForForm(selector, callback, interval = 100) {
  const formCheck = setInterval(() => {
    if (jQuery(selector).length > 0) {
      clearInterval(formCheck);
      callback();
    }
  }, interval);
}

jQuery(document).ready(function () {
  let paymentInProgress = false;

  jQuery(document).on("click", "#place_order", function (e) {
    const selectedPaymentMethod = jQuery(
      'input[name="payment_method"]:checked'
    ).val();

    if (selectedPaymentMethod === "squaresync_credit") {
      if (paymentInProgress) return;

      e.preventDefault();
      paymentInProgress = true;

      jQuery(document.body).trigger("checkout_place_order_squaresync_credit");
    }
  });

  jQuery(document.body).on(
    "checkout_place_order_squaresync_credit",
    async function () {
      const success = await processCreditCardPayment(payments, paymentRequest);

      if (success) {
        paymentInProgress = false;
        jQuery("form.checkout").trigger("submit");
      } else {
        paymentInProgress = false;
        // logPaymentError("Payment failed, please try again.");
      }
    }
  );
});
