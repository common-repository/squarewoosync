// utils.js

let paymentRequestRef = null; // This replaces the useRef in React
let paymentRequest = null; // This replaces the useState in React
/**
 * Builds and returns the payment request object.
 *
 * @param {Object} payments - The Square payments object used to create the payment request.
 * @param {boolean} needsShipping - A value from the Block checkout that indicates whether shipping is required.
 * @param {boolean} couponApplied - Indicates if a coupon has been applied to the checkout.
 * @returns {Promise<Object>} The payment request object or null if not ready.
 */
function initPaymentRequest(payments, needsShipping, couponApplied) {
  if (!payments) {
    console.error("Payments object is required to create payment request.");
    return null;
  }

  // Get the selected payment method from WooCommerce
  const selectedPaymentMethod = jQuery(
    'input[name="payment_method"]:checked'
  ).val();
  const selectedShippingMethod = jQuery(
    'input[name="shipping_method[0]"]:checked'
  ).val();

  // Ensure we are only processing if the selected method is Google Pay or Apple Pay
  if (selectedPaymentMethod !== "squaresync_credit") {
    return null;
  }

  // Fetch and create or update the payment request
  return getPaymentRequest()
    .then((__paymentRequestJson) => {
      try {
        const __paymentRequestObject = JSON.parse(__paymentRequestJson);
        // Filter out other shipping options and include only the selected shipping method
        if (__paymentRequestObject.shippingOptions && selectedShippingMethod) {
          __paymentRequestObject.shippingOptions =
            __paymentRequestObject.shippingOptions.filter((option) => {
              return option.id === selectedShippingMethod;
            });
        }

        if (!paymentRequestRef) {
          // Create a new payment request
          paymentRequestRef = payments.paymentRequest(__paymentRequestObject);
          paymentRequest = paymentRequestRef; // Set state
        } else {
          // Update the existing payment request
          paymentRequestRef.update(__paymentRequestObject);
        }

        return paymentRequestRef; // Return the created/updated payment request
      } catch (error) {
        console.error("Failed to create or update payment request:", error);
        return null;
      }
    })
    .catch((error) => {
      console.error("Error fetching payment request from the server:", error);
      return null;
    });
}

function getPaymentRequest() {
  return new Promise((resolve, reject) => {
    const data = {
      context: SquareConfig.context,
      security: SquareConfig.paymentRequestNonce,
      is_pay_for_order_page: false,
    };

    jQuery.ajax({
      url: SquareConfig.ajax_url,
      type: "POST",
      data: {
        action: "get_payment_request", // Your PHP handler action
        ...data, // Spread the data into the request
      },
      success: function (response) {
        if (response.success) {
          resolve(response.data); // Resolve with the data
        } else {
          reject(response.data); // Reject with the error data
        }
      },
      error: function (error) {
        reject("Error occurred: " + error.statusText);
      },
    });
  });
}

function attachTokenToForm(token) {
  jQuery("<input>")
    .attr({
      type: "hidden",
      name: "square_payment_token",
      value: token,
    })
    .prependTo("form.checkout");
}

function attachVerificationTokenToForm(verificationToken) {
  jQuery("<input>")
    .attr({
      type: "hidden",
      name: "square_verification_token",
      value: verificationToken,
    })
    .prependTo("form.checkout");
}

// Function to check if shipping is required
function checkNeedsShipping() {
  return new Promise((resolve, reject) => {
    jQuery.ajax({
      url: SquareConfig.ajax_url, // WooCommerce AJAX URL
      type: "POST",
      data: {
        action: "get_needs_shipping", // Action defined in your PHP class
      },
      success: function (response) {
        if (response.success) {
          resolve(response.data.needs_shipping);
        } else {
          reject("Failed to retrieve shipping information.");
        }
      },
      error: function (error) {
        reject("Error occurred: " + error);
      },
    });
  });
}

// Utility function to log payment errors to a specific container
function logPaymentError(message) {
  const paymentStatusContainer = document.getElementById(
    "payment-status-container"
  );
  if (paymentStatusContainer) {
    // Clear any previous errors
    paymentStatusContainer.innerHTML = "";

    // Create error message element
    const errorMessage = document.createElement("p");
    errorMessage.classList.add("payment-error-message"); // Add a CSS class for styling
    errorMessage.textContent = message;

    // Append error message to the container
    paymentStatusContainer.appendChild(errorMessage);
  } else {
    console.error("Payment status container not found.");
  }
}

function buildVerificationDetails(billingData, token, total) {
  return {
    intent: "CHARGE",
    amount: total,
    currencyCode: SquareConfig.currency,
    billingContact: billingData, // Use the prepared billing contact details
    token: token,
  };
}

function clearStoredTokens() {
  // Remove hidden token fields if they exist
  jQuery('input[name="square_payment_token"]').remove();
  jQuery('input[name="square_verification_token"]').remove();
}
