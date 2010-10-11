<h1>{$fileName}</h1>

{form handle=$form action="controller=backend.cssEditor action=save" method="POST" class="templateForm" id="templateForm_`$tabid`"}

	{if $new || $template.isCustomFile}
		<p>
			{{err for="fileName"}}
				<label style="margin-top: 9px;">{t _template_file_name}:</label>
				{textfield class="text"}
			{/err}
		</p>
		<div class="clear" style="margin-bottom: 1em;"></div>
	{/if}

	{textarea name="code" id="code_{$tabid}" class="code"}
	{hidden name="file" id="file_{$tabid}"}

	{if $new}
		{hidden name="new" value="true"}
	{/if}

	<fieldset class="controls" {denied role="template.save"}style="display: none;"{/denied}>
		<span class="progressIndicator" style="display: none;"></span>
		<input type="submit" class="submit" value="{tn _save_css}" />
		{t _or}
		<a id="cancel_{$tabid}" class="cancel" href="{link controller="backend.cssEditor"}">{t _cancel}</a>
	</fieldset>
{/form}

{literal}
<script type="text/javascript">
	$('code_{/literal}{$tabid}{literal}').value = {/literal}decode64("{$code}");{literal};
	editAreaLoader.baseURL = "{/literal}{baseUrl}javascript/library/editarea/{literal}";
</script>
{/literal}