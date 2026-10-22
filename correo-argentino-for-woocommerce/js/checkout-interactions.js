jQuery(document).ready(function($) {
    function isAddressIncomplete() {
        const street = $('input[name="billing_address_1"]').val();
        const city = $('input[name="billing_city"]').val();
        const postalCode = $('input[name="billing_postcode"]').val();
        
        // The number field (billing_address_2) might not exist or be hidden by other plugins.
        // We only consider it mandatory if it is actually present in the DOM.
        const $number = $('input[name="billing_address_2"]');
        const number = $number.length ? $number.val() : 'n/a';
    
        return !street || !number || !city || !postalCode;
    }

    function checkShippingMethod() {
        const $checkbox = $('#ship-to-different-address-checkbox');
        const isBranch = $('#corrarfo_container_select_provinces').is(':visible');

        if (isAddressIncomplete()) {
            if ($checkbox.prop('checked') && !isBranch) {
                $checkbox.prop('checked', false).change();
            }
            if (!isBranch) {
                $('.shipping_address').show();
            }
            return;
        }

        $('input[name^="shipping_method"]:checked').each(function() {
            const $shippingRow = $(this).closest('li');
            const label = $shippingRow.length ? $shippingRow.text().toLowerCase() : '';

            if (
                label.includes('correo argentino clásico a domicilio') ||
                label.includes('correo argentino expreso a domicilio')
            ) {
                if ($checkbox.prop('checked')) {
                    $checkbox.prop('checked', false).change();
                }
                $('.shipping_address').hide();
            }
        });
    }
    // Execute when the shipping method changes
    $('form.checkout').on('change', 'input[name^="shipping_method"]', function() {
        checkShippingMethod();
    });

    $('body').on('updated_checkout', function() {
        checkShippingMethod();
    });

    checkShippingMethod();
});
