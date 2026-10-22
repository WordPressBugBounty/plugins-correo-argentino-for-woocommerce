jQuery(document).ready(function ($) {
    var $sandboxCb = $('#woocommerce_correoargentino_shipping_method_sandbox');
    var $debugRow = $('.corrarfo-wc-debug-toggle-row').closest('tr');
    var $debugCb = $('#woocommerce_correoargentino_shipping_method_shipping_debug');

    if ($sandboxCb.length === 0 || $debugRow.length === 0) return;

    function updateDebugToggle() {
        if ($sandboxCb.is(':checked')) {
            $debugRow.show();
        } else {
            $debugRow.hide();
            // Desmarcar al ocultar, si no está disabled
            if (!$debugCb.prop('disabled')) {
                $debugCb.prop('checked', false);
            }
        }
    }

    $sandboxCb.on('change', updateDebugToggle);
    updateDebugToggle(); // Inicializar estado
});
