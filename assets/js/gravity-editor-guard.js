(function (window, document) {
  'use strict';

  var config = window.PWTSR_GRAVITY_EDITOR || {};
  var slug = typeof config.fieldType === 'string' ? config.fieldType : '';
  var title = typeof config.title === 'string' ? config.title : '';
  var warning = typeof config.warning === 'string' ? config.warning : '';

  if (!slug) {
    return;
  }

  function showWarning() {
    var gform = window.gform;
    if (gform && gform.instances && typeof gform.instances.dialogAlert === 'function') {
      gform.instances.dialogAlert(title, warning);
      return;
    }

    window.alert(warning);
  }

  // Gravity Forms runs this filter from CanFieldBeAdded() before adding any field.
  function canFieldBeAdded(canBeAdded, type) {
    if (!canBeAdded || type !== slug || typeof window.GetFieldsByType !== 'function') {
      return canBeAdded;
    }

    if ((window.GetFieldsByType([slug]) || []).length) {
      showWarning();
      return false;
    }

    return canBeAdded;
  }

  function register() {
    if (!window.gform || typeof window.gform.addFilter !== 'function') {
      return false;
    }

    window.gform.addFilter('gform_form_editor_can_field_be_added', canFieldBeAdded);
    return true;
  }

  if (!register() && document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', register);
  }
})(window, document);
