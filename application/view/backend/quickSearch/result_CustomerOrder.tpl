<a href="{link controller="backend.customerOrder query="rt=`$randomToken`"}#order_{$record.ID}#tabOrderInfo__" onclick="try {literal}{{/literal} return footerToolbar.tryToOpenItemWithoutReload({$record.ID},'order');{literal}}catch(e){}{/literal}">{$record.invoiceNumber|escape|mark_substring:$query}</a>
<span>({$record.formattedTotalAmount})</span>