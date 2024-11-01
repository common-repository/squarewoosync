let card; // Store the card instance

const cardTypeMapping = {
  visa: "VISA",
  mastercard: "MASTERCARD",
  amex: "AMERICAN_EXPRESS",
  discover: "DISCOVER",
  jcb: "JCB",
  diners: "DINERS_CLUB",
  union: "UNIONPAY",
};

// Initialize Credit Card Payment
function initCreditCardPayment(payments, creditCardPaymentRequest) {
  const cardFormContainer = document.getElementById("card-container");

  if (!cardFormContainer) {
    console.error("Credit card form container not found.");
    return;
  }

  if (!payments || !creditCardPaymentRequest) {
    console.error("Payments or creditCardPaymentRequest object is missing.");
    return;
  }

  payments
    .card()
    .then(function (cardInstance) {
      card = cardInstance;
      card.attach("#card-container");
    })
    .catch(function (e) {
      console.error("Failed to initialize card payment:", e);
    });
}

// Process Credit Card Payment
async function processCreditCardPayment(payments, creditCardPaymentRequest) {
  if (!card) {
    console.error("Card form not initialized.");
    logPaymentError("Card form not initialized.");
    return false;
  }

  if (!creditCardPaymentRequest || !creditCardPaymentRequest.total) {
    console.error(
      "Credit Card Payment Request object is not available or total is missing."
    );
    logPaymentError("Payment request object is not available.");
    return false;
  }

  try {
    jQuery("#payment-loader").show();

    const tokenResult = await card.tokenize();

    if (tokenResult.status === "OK") {
      const token = tokenResult.token;
      const cardBrand = tokenResult.details.card.brand;
      // Convert accepted credit cards into the Square SDK card brands
      const acceptedBrands = SquareConfig.availableCardTypes.map(
        (card) => cardTypeMapping[card]
      );

      // Check if the card brand is accepted
      if (!acceptedBrands.includes(cardBrand)) {
        logPaymentError("The card brand " + cardBrand + " is not accepted.");
        jQuery("#payment-loader").hide();
        return false;
      }

      attachTokenToForm(token); // Attach the token to the form

      // Prepare billing data for verification
      const billingData = {
        familyName: jQuery("#billing_last_name").val(),
        givenName: jQuery("#billing_first_name").val(),
        email: jQuery("#billing_email").val(),
        phone: jQuery("#billing_phone").val(),
        country: jQuery("#billing_country").val(),
        region: jQuery("#billing_state").val(),
        city: jQuery("#billing_city").val(),
        postalCode: jQuery("#billing_postcode").val(),
        addressLines: [
          jQuery("#billing_address_1").val(),
          jQuery("#billing_address_2").val(),
        ].filter(Boolean),
      };

      const orderTotal = creditCardPaymentRequest.total.amount;
      const verificationDetails = buildVerificationDetails(
        billingData,
        token,
        orderTotal
      );

      // Verify the buyer
      const verificationResult = await payments.verifyBuyer(
        token,
        verificationDetails
      );

      if (verificationResult.token) {
        attachVerificationTokenToForm(verificationResult.token); // Attach verification token to the form
        jQuery("#payment-loader").hide();
        return true;
      } else {
        logPaymentError("Buyer verification failed.");
        jQuery("#payment-loader").hide();
        clearStoredTokens();
        return false;
      }
    } else {
      logPaymentError(
        "Card tokenization failed: " + JSON.stringify(tokenResult.errors)
      );
      jQuery("#payment-loader").hide();
      clearStoredTokens();
      return false;
    }
  } catch (e) {
    logPaymentError("Tokenization or verification error: " + e.message);
    jQuery("#payment-loader").hide();
    clearStoredTokens();
    return false;
  }
}
