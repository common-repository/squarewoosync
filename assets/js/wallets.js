async function initWalletPayments(
    payments,
    walletPaymentRequest,
    needsShipping,
    couponApplied
) {
    if (!payments) {
        console.error("Payments object is required for wallet payments.");
        return;
    }

    if (walletPaymentRequest) {
        await attachWalletButtons(walletPaymentRequest, payments, needsShipping, couponApplied); // Attach wallet buttons
    }

    jQuery(document.body).on(
        "wc_cart_totals_updated updated_shipping_method applied_coupon removed_coupon updated_checkout",
        async function () {
            // Re-initialize the wallet payment request
            const updatedWalletPaymentRequest = await initPaymentRequest(
                payments,
                needsShipping,
                couponApplied
            );

            // Re-attach wallet buttons with the updated request
            if (updatedWalletPaymentRequest) {
                await attachWalletButtons(updatedWalletPaymentRequest, payments);
            }
        }
    );
}


async function attachWalletButtons(paymentRequest, payments, needsShipping, couponApplied) {
    // Common function to handle wallet tokenization and verification
    async function handleTokenizationAndVerification(walletInstance, walletType) {
        try {
            const tokenResult = await walletInstance.tokenize();

            if (tokenResult.status === "OK") {
                attachTokenToForm(tokenResult.token);

                // Extract shipping details from tokenResult
                const shippingDetails = tokenResult.details.shipping.contact;

                // Ensure the "Ship to a different address" checkbox is checked
                const shipToDifferentAddressCheckbox = jQuery(
                    "#ship-to-different-address-checkbox"
                );
                if (!shipToDifferentAddressCheckbox.is(":checked")) {
                    shipToDifferentAddressCheckbox.prop("checked", true);
                    jQuery("div.shipping_address").show(); // Ensure the shipping fields are visible
                }

                // Update WooCommerce form fields with shipping info
                updateWooCommerceShippingFields(shippingDetails);

                // Update WooCommerce shipping fields with shipping details from digital wallet
                if (shippingDetails) {
                    jQuery('input[name="shipping_first_name"]').val(
                        shippingDetails.givenName
                    );
                    jQuery('input[name="shipping_last_name"]').val(
                        shippingDetails.familyName
                    );
                    jQuery('input[name="shipping_address_1"]').val(
                        shippingDetails.addressLines[0]
                    );
                    jQuery('input[name="shipping_address_2"]').val(
                        shippingDetails.addressLines[1] || ""
                    );
                    jQuery('input[name="shipping_city"]').val(shippingDetails.city);
                    jQuery('input[name="shipping_postcode"]').val(
                        shippingDetails.postalCode
                    );
                    jQuery('input[name="shipping_country"]').val(shippingDetails.country);
                    jQuery('input[name="shipping_state"]').val(shippingDetails.region);
                    jQuery('input[name="shipping_phone"]').val(shippingDetails.phone);
                }

                // Prepare verification details
                // const billingData = {
                //   familyName: jQuery("#billing_last_name").val(),
                //   givenName: jQuery("#billing_first_name").val(),
                //   email: jQuery("#billing_email").val(),
                //   phone: jQuery("#billing_phone").val(),
                //   country: jQuery("#billing_country").val(),
                //   region: jQuery("#billing_state").val(),
                //   city: jQuery("#billing_city").val(),
                //   postalCode: jQuery("#billing_postcode").val(),
                //   addressLines: [
                //     jQuery("#billing_address_1").val(),
                //     jQuery("#billing_address_2").val(),
                //   ].filter(Boolean),
                // };

                const orderTotal = paymentRequest.total.amount;
                // const verificationDetails = buildVerificationDetails(
                //   billingData,
                //   tokenResult.token,
                //   orderTotal
                // );

                // Verify buyer with the token and verification details
                // const verificationResult = await payments.verifyBuyer(
                //   tokenResult.token,
                //   verificationDetails
                // );
                jQuery("form.checkout").trigger("submit");
                // if (verificationResult.token) {
                //   attachVerificationTokenToForm(verificationResult.token);
                //   jQuery("form.checkout").trigger("submit");
                // } else {
                //   if (verificationResult.status !== "Cancel") {
                //     logPaymentError("Buyer verification failed");
                //   }
                // }
            } else {
                clearStoredTokens()
                if (tokenResult.status !== "Cancel") {
                    logPaymentError(
                        `${walletType} tokenization failed: ${JSON.stringify(
                            tokenResult.errors
                        )}`
                    );
                }
            }
        } catch (error) {
            clearStoredTokens()
            logPaymentError(
                `${walletType} tokenization or verification error: ${error.message}`
            );
        }
    }

    // Apple Pay Initialization
    // Apple Pay Initialization or Update
    if (SquareConfig.applePayEnabled === "yes") {
        try {
            // Check if the Apple Pay instance already exists
            if (!window.existingApplePayInstance) {
                // Initialize Apple Pay instance only once
                window.existingApplePayInstance = await payments.applePay(paymentRequest);

                const applePayButtonContainer = document.querySelector("#apple-pay-button");
                if (applePayButtonContainer) {
                    applePayButtonContainer.style.display = "block";

                    // Add click handler only once
                    jQuery("#apple-pay-button").on("click", async function (e) {
                        e.preventDefault();
                        await handleTokenizationAndVerification(window.existingApplePayInstance, "Apple Pay");
                    });
                }
            } else {
                // Update the existing Apple Pay instance's payment request
                const updatedPaymentRequest = await initPaymentRequest(payments, needsShipping, couponApplied);

                // Update Apple Pay instance if payment request is valid
                if (updatedPaymentRequest) {
                    window.existingApplePayInstance = await payments.applePay(updatedPaymentRequest);
                }
            }

        } catch (error) {
            console.error("Failed to initialize or update Apple Pay:", error);
        }
    }


    // Google Pay Initialization
    if (SquareConfig.googlePayEnabled === "yes") {
        try {
            // Check if the Google Pay instance already exists
            if (!window.existingGooglePayInstance) {
                // Initialize the Google Pay instance only once
                window.existingGooglePayInstance = await payments.googlePay(paymentRequest);
                await window.existingGooglePayInstance.attach("#google-pay-button");

                // Add click handler only once
                jQuery("#google-pay-button").on("click", async function (e) {
                    e.preventDefault();
                    await handleTokenizationAndVerification(window.existingGooglePayInstance, "Google Pay");
                });
            } else {
                // Instead of updating the payment request directly, you need to recreate the payment request
                const updatedPaymentRequest = await initPaymentRequest(
                    payments,
                    needsShipping,
                    couponApplied
                );

                // Check if the new payment request is valid and update the instance
                if (updatedPaymentRequest) {
                    window.existingGooglePayInstance = await payments.googlePay(updatedPaymentRequest);
                }
            }

            // Ensure the button is visible
            document.querySelector("#google-pay-button").style.display = "block";

        } catch (error) {
            console.error("Failed to initialize or update Google Pay:", error);
        }
    }
}

function updateWooCommerceShippingFields(shipping) {
    // Update shipping fields
    jQuery("#shipping_first_name").val(shipping.givenName);
    jQuery("#shipping_last_name").val(shipping.familyName);
    jQuery("#shipping_address_1").val(shipping.addressLines[0]);
    jQuery("#shipping_city").val(shipping.city);
    jQuery("#shipping_postcode").val(shipping.postalCode);
    jQuery("#shipping_state").val(shipping.state);
    jQuery("#shipping_country").val(shipping.countryCode);
    jQuery("#shipping_phone").val(shipping.phone);
}