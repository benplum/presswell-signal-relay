(function() {
  'use strict';

  // Show the description for the selected attribution model only.
  function setupModelDescriptions() {
    const descriptions = document.querySelectorAll('.pwtsr-model-description');
    const radios = document.querySelectorAll('input[name$="[attribution_model]"]');

    if (!descriptions.length || !radios.length) {
      return;
    }

    function update() {
      let selected = '';

      radios.forEach(function(radio) {
        if (radio.checked) {
          selected = radio.value;
        }
      });

      descriptions.forEach(function(description) {
        description.hidden = description.getAttribute('data-pwtsr-model') !== selected;
      });
    }

    radios.forEach(function(radio) {
      radio.addEventListener('change', update);
    });

    update();
  }

  setupModelDescriptions();
})();
