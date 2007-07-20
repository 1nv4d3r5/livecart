<div class="userOrders">

{include file="layout/frontend/header.tpl"}

<div id="content" class="left right">
	
	<h1>{t _your_orders}</h1>
	
	{include file="user/userMenu.tpl" current="orderMenu"}

    <div id="userContent">

        <div class="resultStats">
            {if $orders}        
                {if $count > $perPage}
                    {maketext text="Displaying [_1] to [_2] of [_3] found orders." params=$from,$to,$count}
                {else}
                    {maketext text="[quant,_1,order,orders] found" params=$count}
                {/if}            
            {else}
                {t _no_orders_found}
            {/if}
        </div>
   
        {foreach from=$orders item="order"}    
    	    {include file="user/orderEntry.tpl" order=$order}
        {/foreach}   
   
        {if $count > $perPage}
        	{capture assign="url"}{link controller=user action=orders id=0}{/capture}
            <div class="resultPages">
        		Pages: {paginate current=$currentPage count=$count perPage=$perPage url=$url}
        	</div>
        {/if}   
    
    </div>
    
</div>

{include file="layout/frontend/footer.tpl"}

</div>