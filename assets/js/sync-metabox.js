jQuery(document).ready(function ($) {
  'use strict';
  $(document).on("click", "#sync_to_square_button", function (event) {
    event.preventDefault();

    var nonce = swsAjax.nonce;
    // Disable the button and change the text to "Loading"
    $(this).prop("disabled", true).text("Updating...");

    var productId = $(this).data("product-id");
    $.ajax({
      url: ajaxurl,
      type: "post",
      data: {
        action: "sync_to_square",
        product_id: productId,
        nonce: nonce,
      },
      success: function (response) {
        var data = response;
        var noticeClass = data.success ? "notice-success" : "notice-error";
        var noticeHtml =
          '<div class="notice ' +
          noticeClass +
          ' is-dismissible"><p>' +
          data.data.message +
          "</p></div>";

        // Insert the notice at the top of the .wrap container, or after existing notices
        $("#post").prepend(noticeHtml);

        if (data.success) {
          $(".sws-notice")
            .text("Sync completed! Make some more changes to sync again.")
            .css("color", "rgb(0,180,0)");
          // Update the button text on success
          $("#sync_to_square_button")
            .addClass("success-button")
            .text("Successfully Updated");
        } else {
          $("#sync_to_square_button").prop("disabled", true).text("Failed");
        }
      },
      error: function () {
        $("#post").prepend(
          '<div class="notice notice-error is-dismissible"><p>Error occurred while syncing.</p></div>'
        );
        $("#sync_to_square_button").prop("disabled", true).text("Failed");
      },
    });
  });

  $(document).on("click", "#export_to_square_button", function (event) {
    event.preventDefault();

    var nonce = swsAjax.nonce;

    // Disable the button and change the text to "Loading"
    $(this).prop("disabled", true).text("Updating...");

    var productId = $(this).data("product-id");
    $.ajax({
      url: ajaxurl,
      type: "post",
      data: {
        action: "export_to_square",
        product_id: productId,
        nonce: nonce,
      },
      success: function (response) {
        var data = response;
        var noticeClass = data.success ? "notice-success" : "notice-error";
        var noticeHtml =
          '<div class="notice ' +
          noticeClass +
          ' is-dismissible"><p>' +
          data.data.message +
          "</p></div>";

        // Insert the notice at the top of the .wrap container, or after existing notices
        $("#post").prepend(noticeHtml);

        if (data.success) {
          $(".sws-notice")
            .text("Export completed!")
            .css("color", "rgb(0,180,0)");
          // Update the button text on success
          $("#export_to_square_button")
            .addClass("success-button")
            .text("Successfully Exported");
        } else {
          $("#export_to_square_button").prop("disabled", true).text("Failed");
        }
      },
      error: function () {
        $("#post").prepend(
          '<div class="notice notice-error is-dismissible"><p>Error occurred while exporting.</p></div>'
        );
        $("#export_to_square_button").prop("disabled", true).text("Failed");
      },
    });
  });
});
