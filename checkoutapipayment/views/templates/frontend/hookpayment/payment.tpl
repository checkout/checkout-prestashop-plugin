<p class="payment_module" >
    {if $hasError == 1}
        <p class="error">
            {if !empty($smarty.get.message)}
                {l s='Error detail from Checkout.com : ' mod='checkoutapipayment'}
                {$smarty.get.message|htmlentities}
            {else}
                {l s='Error, please verify the card details' mod='checkoutapipayment'}
            {/if}

        </p>
    {/if}
    <div style="" class="checkoutapi-info">
			<a id="click_checkoutprestashop" href="{$link->getModuleLink('checkoutapipayment', 'payment', [], true)|escape:'html'}" title="{l s='Pay with Checkout.com' mod='checkoutapipayment'}" style="">
                <img src="https://www.checkout.com/signature.jpg" alt="Pay through Checkout.com" border="0" align="absmiddle" class="img-logo"/>
                <span class="span-desc">{l s='' mod='checkoutapipayment'}</span>
                {if isset($template) }
                     {include file="../hookpayment/js/$template"}
                {/if}

            </a>
    </div>
</p>
