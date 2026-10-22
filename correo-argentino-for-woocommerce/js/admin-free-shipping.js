document.addEventListener('DOMContentLoaded', function () {
  var checkbox = document.getElementById('corrarfo_free_shipping_enabled');
  var globalAmountRow = document.getElementById('corrarfo_free_shipping_amount_row');
  var modeRow = document.getElementById('corrarfo_free_shipping_mode_row');
  var individualRow = document.getElementById('corrarfo_free_shipping_individual_row');

  if (!checkbox) return;

  function updateUI() {
    var isEnabled = checkbox.checked;
    
    // Toggle Mode selection row
    if (modeRow) {
      modeRow.style.display = isEnabled ? '' : 'none';
      var radios = modeRow.querySelectorAll('input[type="radio"]');
      radios.forEach(function(radio) {
        radio.disabled = !isEnabled;
      });
    }

    // Determine currently selected mode
    var mode = 'global';
    var selectedRadio = document.querySelector('input[name="corrarfo_free_shipping_mode"]:checked');
    if (selectedRadio) {
      mode = selectedRadio.value;
    }

    // Toggle Global Amount row
    if (globalAmountRow) {
      globalAmountRow.style.display = (isEnabled && mode === 'global') ? '' : 'none';
      var amountField = globalAmountRow.querySelector('input[type="text"]');
      if (amountField) {
        amountField.disabled = !isEnabled || mode !== 'global';
      }
    }

    // Toggle Individual Table row
    if (individualRow) {
      individualRow.style.display = (isEnabled && mode === 'individual') ? '' : 'none';
      
      // Also enable/disable the inputs inside individual table
      var checkboxes = individualRow.querySelectorAll('.corrarfo_inst_free_shipping_enabled');
      var amounts = individualRow.querySelectorAll('.corrarfo_inst_free_shipping_amount');
      
      checkboxes.forEach(function(cb) {
        cb.disabled = !isEnabled || mode !== 'individual';
      });
      amounts.forEach(function(amt) {
        amt.disabled = !isEnabled || mode !== 'individual';
      });
    }
  }

  // Bind change events
  checkbox.addEventListener('change', updateUI);
  
  var radios = document.querySelectorAll('.corrarfo_free_shipping_mode_radio');
  radios.forEach(function(radio) {
    radio.addEventListener('change', updateUI);
  });

  // Initial load
  updateUI();
});
