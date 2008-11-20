<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	 "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">

<head>
	<meta http-equiv="content-type" content="text/html;charset=UTF-8" />
	<meta name="Keywords" content="{$metaKeywords|escape}" />
	<meta name="Description" content="{$metaDescription|escape}" />

	<title>
		{if !$PAGE_TITLE}
			{capture assign="PAGE_TITLE"}
				{block BREADCRUMB_TITLE}
			{/capture}
		{/if}

		{if 'TITLE_FORMAT'|config == 'NAME_FIRST'}
			{'STORE_NAME'|config} {'TITLE_SEPARATOR'|config} {$PAGE_TITLE|@strip_tags}
		{else}
			{$PAGE_TITLE|@strip_tags} {'TITLE_SEPARATOR'|config} {'STORE_NAME'|config}
		{/if}
	</title>

	<base href="{baseUrl}"></base>
	{liveCustomization}

	{if 'FAVICON'|config}
		<link href="{'FAVICON'|config}" rel="shortcut icon" />
	{/if}

	<!-- Css includes -->
	{includeCss file="frontend/Frontend.css"}
	{includeCss file="backend/stat.css"}
	{compiledCss glue=true nameMethod=hash}
	<!--[if IE]>
		<link href="stylesheet/frontend/FrontendIE.css" rel="Stylesheet" type="text/css"/>
	<![endif]-->

	<!-- JavaScript includes -->
	{* <script type="text/javascript" src="firebug/firebug.js"></script> *}
	{compiledJs glue=true nameMethod=hash}
</head>

<body class="{$request.controller}Con {$request.controller}-{$request.action}">
	<div id="container" class="lang_{localeCode}">
		{$ACTION_VIEW}
	</div>
	{liveCustomization action="menu"}
	{block TRACKING}
</body>

</html>