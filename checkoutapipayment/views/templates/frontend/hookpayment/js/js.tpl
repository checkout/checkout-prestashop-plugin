<form name="checkoutapipayment_form" id="checkoutapipayment_form" action="{$link->getModuleLink('checkoutapipayment', 'validation', [], true)|escape:'html'}" method="post">
    <div class="payment-select-txt">{l s='Click on Pay now to enter your credit card details' mod='checkoutprestashop'}</div>
    <div class="widget-container"></div>
    <input type="hidden" name="cko_cc_paymenToken" id="cko-cc-paymenToken" value="">
    {if $paymentToken && $success }

    <script type="text/javascript">
        jQuery(function(){
            jQuery('#click_checkoutprestashop').attr('href','javascript:void(0)');

        });
        var reload = false;
        window.CKOConfig = {
            debugMode: false,
            renderMode: 0,
            namespace: 'CheckoutIntegration',
            publicKey: '{$publicKey}',
            paymentToken: "{$paymentToken}",
            value: {$amount},
            currency: '{$currencyIso}',
            customerEmail: '{$mailAddress}',
            customerName: '{$name}',
            paymentMode: '{$paymentMode}',
            title: '{$store}',
            forceMobileRedirect: true,
            useCurrencyCode: {$usecurrencycode},
            subtitle:'{l s='Please enter your credit card details' mod='checkoutprestashop'}',
            widgetContainerSelector: '.widget-container',
            styling: {
                themeColor: '{$themecolor}',
                buttonColor: '{$buttoncolor}',
                logoUrl: '{$logourl}',
                iconColor: '{$iconcolor}'
            },
            cardCharged: function(event){
                document.getElementById('cko-cc-paymenToken').value = event.data.paymentToken;
                document.getElementById('checkoutapipayment_form').submit();
            },
            ready: function() {
                 if(typeof CheckoutIntegration !='undefined') {
                    if(!CheckoutIntegration.isMobile()){
                        jQuery('.checkoutapi-button').hide();    
                    }
                    else {
                        jQuery('.widget-container,.payment-select-txt').hide();
                        jQuery('#click_checkoutprestashop').attr('href', CheckoutIntegration.getRedirectionUrl());
                    }
                }
            },
            paymentTokenExpired: function(){
                window.location.reload();
                reload = true;
            },
            lightboxDeactivated: function() {

                if(reload) {
                    window.location.reload();
                }

            }
        };
    </script>
    {if $mode == 'live' }
        <script src="https://www.checkout.com/cdn/js/checkout.js" async ></script>

        {else}
        <script src="https://sandbox.checkout.com/js/v1/checkout.js" async ></script>
    {/if}
    {else}
        {$message}
        {l s='Event id' mod='checkoutprestashop'}: {$eventId}
    {/if}
</form>