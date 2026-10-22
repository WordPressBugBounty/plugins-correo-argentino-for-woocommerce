document.addEventListener('DOMContentLoaded', function () {
  var toggle = document.querySelector('#corrarfo_default_enabled');
  var inputs = document.querySelectorAll('.corrarfo-default-dimension-field');

  function toggleFields() {
    if (!toggle) return;
    inputs.forEach(function (input) {
      input.disabled = !toggle.checked;
    });
  }

  if (toggle) {
    toggle.addEventListener('change', toggleFields);
    toggleFields();
  }
});
