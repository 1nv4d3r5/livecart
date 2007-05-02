{includeJs file="library/form/Validator.js"}
{includeJs file="library/form/ActiveForm.js"}

{include file="layout/frontend/header.tpl"}
{* include file="layout/frontend/leftSide.tpl" *}
{* include file="layout/frontend/rightSide.tpl" *}

<div id="content" class="left right">
	
	<h1>{t _pay}</h1>
		   	
	<div id="payTotal">
        <div>
			Order total: <span class="subTotal">{$order.formattedTotal.$currency}</span>
		</div>
    </div>
		   	
    <h2>Pay with a credit card</h2>
        
	{form action="controller=checkout action=payCreditCard" handle=$ccForm method="POST" style="float: left;"}
    
	    {error for="creditCardError"}
	    	<div class="errorMsg ccPayment">
	    		{$msg}
	    	</div>
	    {/error}

	    <p>
			<label for="ccNum">Cardholder name:</label>
            <label>{$order.BillingAddress.fullName}</label>
        </p>

	    <p>
			<label for="ccNum">Card number:</label>
            <fieldset class="error">
	            {textfield name="ccNum"}
				<div class="errorText hidden{error for="ccNum"} visible{/error}">{error for="ccNum"}{$msg}{/error}</div>
			</fieldset>
        </p>
        
{*
        <p>
            <label for="ccType">Card type:</label>
            {selectfield name="ccType" options=$ccType}
        </p>
*}
    
        <p>
            <label for="ccExpiryMonth">Card expiration:</label>
            <fieldset class="error">
	            {selectfield name="ccExpiryMonth" options=$months}
	            /
	            {selectfield name="ccExpiryYear" options=$years}
				<div class="errorText hidden{error for="ccExpiryYear"} visible{/error}">{error for="ccExpiryYear"}{$msg}{/error}</div>
			</fieldset>
        </p>
    
        <p>
            <label for="ccCVV">3 or 4 digit code after card # on back of card:</label>
            <fieldset class="error">
	            {textfield name="ccCVV" maxlength="4"} 
				<a class="cvv" href="{link controller=checkout action=cvv}" onclick="Element.show($('cvvHelp')); return false;">{t What Is It?}</a>
				<div class="errorText hidden{error for="ccCVV"} visible{/error}">{error for="ccCVV"}{$msg}{/error}</div>
			</fieldset>
        </p>
        
        <input type="submit" class="submit" value="{tn Complete Order Now}" />
    {/form}
    
    <div id="cvvHelp" style="float: left; width: 40%; padding: 5px; margin-left: 20px; display: none;">
		{include file="checkout/cvvHelp.tpl"}    	
    </div>
    
    <div class="clear"></div> 

    {* <h2>Other payment methods</h2> *}

    <table class="table shipment" id="payItems">            
        <thead>
            <tr>
                <th class="productName">Product</th>
                <th>Price</th>
                <th>Quantity</th>
                <th>Subtotal</th>
            </tr>                            
        </thead>
    {foreach from=$order.shipments key="key" item="shipment"}
        <tbody>
            {foreach from=$shipment.items item="item" name="shipment"}
                <tr{zebra loop="shipment"}>                    
                    <td class="productName"><a href="{productUrl product=$item.Product}">{$item.Product.name_lang}</a></td>
                    <td>{$item.Product.formattedPrice.$currency}</td>
                    <td>{$item.count}</td>
                    <td>{$item.formattedSubTotal.$currency}</td>
                </tr>
            {/foreach}            
        
            <tr>
                <td colspan="3" class="subTotalCaption">{t _shipping} ({$shipment.selectedRate.serviceName}):</td>
                <td>{$shipment.selectedRate.formattedPrice.$currency}</td>                        
            </tr>
    {/foreach}  
      
            <tr>
                <td colspan="3" class="subTotalCaption">{t _total}:</td>
                <td class="subTotal">{$order.formattedTotal.$currency}</td>                        
            </tr>

        </tbody>        
    </table>    
    
</div>

{include file="layout/frontend/footer.tpl"}