<form name="checkoutapipayment_form" id="checkoutapipayment_form" action="{$link->getModuleLink('checkoutapipayment', 'validation', [], true)|escape:'html'}" method="post">

    <div class="payment-select-txt">{*{l s='Select your payment option' mod='checkoutapipayment'}*}</div>
    <div class="widget-container"  style="height: 50px;"></div>
    <input type="hidden" name="cko_cc_paymenToken" id="cko-cc-paymenToken" value="">
    <input type="hidden" name="cko_card_token" id="cko-card-token" value="">

    {if $paymentToken && $success }

        <script type="text/javascript">
            jQuery(function(){
                jQuery('#click_checkoutapipayment').attr('href','javascript:void(0)');
            });

            var reload = false;
            window.checkoutIntegrationCurrentConfig= {
                debugMode: false,
                renderMode: 0,
                namespace: 'CheckoutIntegration',
                publicKey: '{$publicKey}',
                paymentToken: "{$paymentToken}",
                value: '{$amount}',
                currency: '{$currencyIso}',
                customerEmail: '{$mailAddress}',
                customerName: '{$name}',
                paymentMode: '{$paymentMode}',
                title: '{$store}',
                showMobileIcons : true,
                forceMobileRedirect: false,
                useCurrencyCode: '{$usecurrencycode}',
                cardFormMode: 'cardTokenisation',
                widgetContainerSelector: '.widget-container',
                enableIframePreloading:false,
                styling: {
                    themeColor: '{$themecolor}',
                    buttonColor: '{$buttoncolor}',
                    logoUrl: '{$logourl}',
                    iconColor: '{$iconcolor}'
                },

                paymentTokenExpired: function(){
                    window.location.reload();
                    reload = true;
                },

                lightboxDeactivated: function() {
                    if(reload) {
                        window.location.reload();
                    }
                },

                cardTokenised: function(event){
                    if(typeof event.data.id !== 'undefined' ) {
                        if (document.getElementById('cko-card-token').value.length === 0 || document.getElementById('cko-card-token').value !== event.data.id) {
                            document.getElementById('cko-card-token').value = event.data.id;
                            document.getElementById('checkoutapipayment_form').submit();
                        }
                    }else {
                        if (document.getElementById('cko-card-token').value.length === 0 || document.getElementById('cko-card-token').value !== event.data.cardToken) {
                            document.getElementById('cko-card-token').value = event.data.cardToken;
                            document.getElementById('checkoutapipayment_form').submit();
                        }
                    }
                },

                cardTokenisationFailed: function() {
                    reload = true;
                }
            };

            window.checkoutIntegrationIsReady = window.checkoutIntegrationIsReady || false;
            if (!window.checkoutIntegrationIsReady) {

                window.CKOConfig = {
                    ready: function () {

                        if (window.checkoutIntegrationIsReady) {
                            return false;
                        }

                        if (typeof CKOAPIJS == 'undefined') {
                            return false;
                        }
                        CKOAPIJS.render(window.checkoutIntegrationCurrentConfig);
                        window.checkoutIntegrationIsReady = true;
                    }
                };

                var mode = '{$mode}';
                if(mode == 'sandbox'){
                    src = 'https://cdn.checkout.com/sandbox/js/checkout.js';
                } else {
                    src = 'https://cdn.checkout.com/js/checkout.js';
                }

                var script = document.createElement('script');
                script.src = src;
                script.async = true;
                script.setAttribute('data-namespace', 'CKOAPIJS');
                document.head.appendChild(script);
            } else {
                CKOAPIJS.render(checkoutIntegrationCurrentConfig);
            }

        </script>

    {else}
        {$message}
        {l s='Event id' mod='checkoutapipayment'}: {$eventId}
    {/if}
</form>