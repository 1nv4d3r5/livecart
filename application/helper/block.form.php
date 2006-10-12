<?php

/**
 * Smarty form helper
 * 
 * <code>
 * </code>
 *
 * @package application.helper
 * @author Saulius Rupainis <saulius@integry.net>
 * 
 * @todo Include javascript validator source
 */
function smarty_block_form($params, $content, $smarty, &$repeat) 
{
	$handle = $params['handle'];
	unset($params['handle']);
	if (!($handle instanceof Form)) 
	{
		throw new HelperException("Form must have a Form instance assigned!");
	}
	
	$formAction = $params['action'];
	unset($params['action']);
	$vars = explode(" ", $formAction);
	$URLVars = array();
	
	foreach ($vars as $var)
	{
		$parts = explode("=", $var);
		$URLVars[$parts[0]] = $parts[1];
	}
	
	$router = Router::getInstance();
	$actionURL = $router->createURL($URLVars);
	
	$formAttributes ="";
	foreach ($params as $param => $value)
	{
		$formAttributes .= $param . '="' . $value . '"';
	}
	
	$form = '<form action="'.$actionURL.'" '.$formAttributes.'>';
	$form .= $content;
	$form .= "</form>";
	return $form;
}

?>