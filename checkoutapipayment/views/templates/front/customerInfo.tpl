{capture name=path}
    <a href="{$link->getPageLink('my-account', true)|escape:'html':'UTF-8'}">
        {l s='My account'}
    </a>
    <span class="navigation-pipe">
        {$navigationPipe}
    </span>
    <span class="navigation_page">
        {l s='My Saved Card'}
    </span>
{/capture}

<div class="box">
    <h1 class="page-subheading">
        {l s='My Saved Card'}
    </h1>

<form name="checkoutapipayment_form" id="checkoutapipayment_form" action="{$link->getModuleLink('checkoutapipayment', 'customer', [], true)|escape:'html'}" method="post">
	<ul class="payment_methods">
		{if !empty($cardLists)}
			{foreach name=outer item=card_number from=$cardLists}
			  {foreach key=key item=item from=$card_number}
			    {if $key == 'card_number'}
			        {assign var="card_number" value="{$item}"}
			    {/if}

			    {if $key == 'card_type'}
			        {assign var="card_type" value="{$item}"}
			    {/if}

			    {if $key == 'entity_id'}
			        {assign var="entity_id" value="{$item}"}
			    {/if}

			    {/foreach}

			    <div class="out">
				    <li>  
				        <input id="{$entity_id}" class="checkoutapipayment-saved-card" type="checkbox" name="checkoutapipayment-saved-card[]" value="{$entity_id}"/>
				        <label for="{$entity_id}" style="padding-left: 25px;">xxxx-{$card_number}-{$card_type}</label>  
				    </li>
				</div>
			{/foreach}
		{/if}
	</ul>
</form>

 <button class="save-card-pay-button" type="button" >Remove Card</button>

</div>

<script type="text/javascript">
	var submitButton = document.getElementsByClassName('save-card-pay-button')[0];
        submitButton.onclick = function(){
               document.getElementById('checkoutapipayment_form').submit();
        };
</script>
