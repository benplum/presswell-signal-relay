(function (window, document) {
  'use strict';

  var config = window.presswellSignalRelayGravityEditor || {};
  var slug = typeof config.fieldType === 'string' ? config.fieldType : '';
  var warning = typeof config.warning === 'string' ? config.warning : '';

  if (!slug) {
    return;
  }

  function guardSingleField() {
    if (typeof window.StartAddField !== 'function') {
      return false;
    }

    if (window.StartAddField._presswellTransceiverGuard) {
      return true;
    }

    var originalStartAddField = window.StartAddField;
    window.StartAddField = function (type) {
      if (type === slug && typeof window.GetFieldsByType === 'function') {
        var existing = window.GetFieldsByType([slug]) || [];
        if (existing.length) {
          window.alert(warning);
          return;
        }
      }

      return originalStartAddField.apply(this, arguments);
    };

    window.StartAddField._presswellTransceiverGuard = true;
    return true;
  }

  if (guardSingleField()) {
    return;
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', guardSingleField);
  }

  var attempts = 0;
  var maxAttempts = 20;
  var timer = window.setInterval(function () {
    attempts += 1;

    if (guardSingleField() || attempts >= maxAttempts) {
      window.clearInterval(timer);
    }
  }, 250);
})(window, document);
