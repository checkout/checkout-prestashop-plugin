<script async src="{$hppUrl}"></script>
<script type="text/javascript">
    window.CKOConfig = {
        publicKey: '{$publicKey}',
        appMode: 'embedded',
        theme: '{$theme}',
        themeOverride: '{$customCss}',
        cardTokenised: function(event) {
            if (document.getElementById('cko-card-token').value.length === 0) {
                document.getElementById('cko-card-token').value = event.data.cardToken;
                document.getElementById('checkoutapipayment_form').submit();
            }
        },
        lightboxActivated: function(){
                document.getElementById('cko-iframe-id').style.position = "relative";
                $('.cko-md-overlay').remove();
        },
        cardFormValidationChanged: function (event) {
            document.getElementsByClassName('button btn btn-default button-medium')[1].disabled = !Checkout.isCardFormValid();
        },
        ready: function(){
            var submitButton = document.getElementsByClassName('button btn btn-default button-medium')[1];
            submitButton.disabled = true;
           
            submitButton.addEventListener("click", function () {
                if (Checkout.isCardFormValid()) Checkout.submitCardForm();
            });
        }
    };
</script>
<form id="checkoutapipayment_form">
    <input type="hidden" name="cko-card-token" id="cko-card-token" value="">
</form>