/**
 * AI Community Admin JavaScript
 */
(function ($) {
  "use strict";

  $(document).ready(function () {
    console.log("AI Community admin loaded");

    // Initialize admin features
    initializeGenerateButton();
    initializeApiTest();
    initializeTabs();
    initializeModals();

    // Color picker initialization
    if (typeof wp !== "undefined" && wp.colorPicker) {
      $(".color-picker").wpColorPicker();
    }
  });

  function initializeGenerateButton() {
    $("#generate-ai-content").on("click", function (e) {
      e.preventDefault();
      var button = $(this);
      var originalText = button.text();

      button.prop("disabled", true).text("Generating...");

      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "ai_community_generate_content",
          nonce: aiCommunityAdmin.nonce,
        },
        success: function (response) {
          if (response.success) {
            showNotification("Content generated successfully!", "success");
            if (response.data) {
              console.log("Generated:", response.data);
            }
          } else {
            showNotification(
              "Error: " + (response.data || "Unknown error"),
              "error"
            );
          }
        },
        error: function (xhr, status, error) {
          console.error("AJAX Error:", error);
          showNotification("Network error occurred", "error");
        },
        complete: function () {
          button.prop("disabled", false).text(originalText);
        },
      });
    });
  }

  function initializeApiTest() {
    $(document).on("click", "#test-api-key", function (e) {
      e.preventDefault();
      var button = $(this);
      var apiKey = $("#openrouter_api_key").val();

      if (!apiKey) {
        showNotification("Please enter an API key first.", "warning");
        return;
      }

      button.prop("disabled", true).text("Testing...");

      // Remove previous results
      $(".api-test-result").remove();

      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "ai_community_test_api",
          api_key: apiKey,
          nonce: aiCommunityAdmin.nonce,
        },
        success: function (response) {
          var resultClass = response.success
            ? "api-test-success"
            : "api-test-error";
          var message = response.success
            ? "API connection successful!"
            : response.data || "API connection failed.";

          var resultDiv = $(
            '<div class="api-test-result ' +
              resultClass +
              '">' +
              message +
              "</div>"
          );
          $("#openrouter_api_key").parent().append(resultDiv);
        },
        error: function () {
          var resultDiv = $(
            '<div class="api-test-result api-test-error">Connection test failed.</div>'
          );
          $("#openrouter_api_key").parent().append(resultDiv);
        },
        complete: function () {
          button.prop("disabled", false).text("Test Connection");
        },
      });
    });
  }

  function initializeTabs() {
    // Handle tab switching
    $(".nav-tab").on("click", function (e) {
      var href = $(this).attr("href");
      if (href && href.indexOf("#") === -1) {
        // Let normal navigation happen
        return true;
      }

      e.preventDefault();
      var target = $(this).attr("href");

      // Update active tab
      $(".nav-tab").removeClass("nav-tab-active");
      $(this).addClass("nav-tab-active");

      // Show/hide content
      $(".tab-content").hide();
      $(target).show();
    });
  }

  function initializeModals() {
    // Modal close handlers
    $(document).on("click", ".modal-close, .modal-backdrop", function (e) {
      if (e.target === this) {
        $(this).closest(".modal-overlay").hide();
      }
    });

    // ESC key to close modals
    $(document).on("keydown", function (e) {
      if (e.key === "Escape") {
        $(".modal-overlay").hide();
      }
    });
  }

  function showNotification(message, type) {
    type = type || "info";
    var notificationClass = "notice-" + type;

    var notification = $(
      '<div class="notice ' +
        notificationClass +
        ' is-dismissible"><p>' +
        message +
        "</p></div>"
    );

    // Insert after the first heading or at the top of content
    var target = $(".wrap h1, .ai-community-admin h1").first();
    if (target.length) {
      target.after(notification);
    } else {
      $(".wrap, .ai-community-admin").first().prepend(notification);
    }

    // Auto dismiss after 5 seconds
    setTimeout(function () {
      notification.fadeOut(function () {
        $(this).remove();
      });
    }, 5000);

    // Make dismissible work
    notification.on("click", ".notice-dismiss", function () {
      notification.fadeOut(function () {
        $(this).remove();
      });
    });
  }

  // Utility functions
  window.aiCommunityAdmin = window.aiCommunityAdmin || {};
  window.aiCommunityAdmin.showNotification = showNotification;

  // Handle settings form validation
  $('form[action="options.php"]').on("submit", function (e) {
    var requiredFields = $(this).find("[required]");
    var isValid = true;

    requiredFields.each(function () {
      if (!$(this).val()) {
        isValid = false;
        $(this).css("border-color", "#dc3232");
      } else {
        $(this).css("border-color", "");
      }
    });

    if (!isValid) {
      e.preventDefault();
      showNotification("Please fill in all required fields.", "error");
    }
  });

  // Auto-save draft functionality for long forms
  var autoSaveTimer;
  $(
    ".ai-community-admin input, .ai-community-admin textarea, .ai-community-admin select"
  ).on("change", function () {
    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(function () {
      // Could implement auto-save here
      console.log("Auto-save triggered");
    }, 30000); // 30 seconds
  });
})(jQuery);
