<?php

/**
 * @package     Articles Good Search
 *
 * @copyright   Copyright (C) 2017 Joomcar extensions. All rights reserved.
 * @license     GNU General Public License version 2 or later.
 */

defined('_JEXEC') or die('Restricted access');

class JFormFieldCategorySelect extends JFormField {	

	function getInput(){
		return JFormFieldCategorySelect::fetchElement($this->name, $this->value, $this->element, $this->options['control']);
	}
	
	function fetchElement($name, $value, &$node, $control_name) {
	
	        $document = JFactory::getDocument();
			$document->addStyleSheet(JURI::root(true).'/modules/mod_articles_good_search/assets/filter.css');
			
			$mitems[] = JHTML::_('select.option', '', '');
			
			require_once(JPATH_SITE . "/modules/mod_articles_good_search/helper.php");
			$helper = new modArticlesGoodSearchHelper; 
			$categories = $helper->getCategories();

			foreach($categories as $category) {
				$indent = "";
				for($i = 1; $i < $category->level; $i++) { $indent .= " - "; }
				$mitems[] = JHTML::_('select.option', $category->id, $indent . $category->title);
			}
			
			$output = JHTML::_('select.genericlist',  $mitems, '', 'class="ValueSelect inputbox"', 'value', 'text', '0');		
			$output .= "<div class='clear'></div><ul class='sortableFields'></ul>";
			$output .= "<div class='clear'></div>";
			$output .= "<textarea style='display: none;' name='".$name."' class='ValueSelectVal'>".$value."</textarea>";
			$output .= "
			
			<script type='text/javascript'>
				
				var FilterPath = '".JURI::root(true)."/modules/mod_articles_good_search/assets/';				
				
				if(typeof jQuery == 'undefined') {
					var script = document.createElement('script');
					script.type = 'text/javascript';
					script.src = 'https://code.jquery.com/jquery-1.11.3.min.js';
					document.getElementsByTagName('head')[0].appendChild(script);
				   
					if (script.readyState) { //IE
						script.onreadystatechange = function () {
							if (script.readyState == 'loaded' || script.readyState == 'complete') {
								script.onreadystatechange = null;
								load_ui();
							}
						};
					} else { //Others
						script.onload = function () {
							load_ui();
						};
					}
				}
				else {
					load_ui();
				}
				
				function load_ui() {				
					if(typeof jQuery.ui == 'undefined') {
					   var script = document.createElement('script');
					   script.type = 'text/javascript';
					   script.src = 'https://code.jquery.com/ui/1.11.4/jquery-ui.min.js';
					   document.getElementsByTagName('head')[0].appendChild(script);
										   
					   var style = document.createElement('link');
					   style.rel = 'stylesheet';
					   style.type = 'text/css';
					   style.href = 'https://code.jquery.com/ui/1.11.4/themes/ui-lightness/jquery-ui.css';
					   document.getElementsByTagName('head')[0].appendChild(style);
					   
						if (script.readyState) { //IE
							script.onreadystatechange = function () {
								if (script.readyState == 'loaded' || script.readyState == 'complete') {
									script.onreadystatechange = null;
									load_base();
								}
							};
						} else { //Others
							script.onload = function () {
								load_base();
							};
						}		   
					}
					else {
						load_base();
					}
				}
				
				function load_base() {			
					var base_script = document.createElement('script');
					base_script.type = 'text/javascript';
					base_script.src = FilterPath+'js/filter.admin.js?v=1.2.1';
					document.getElementsByTagName('head')[0].appendChild(base_script);					
				}
			</script>
			
			";

			return $output;
	}
	
	function addOptions(&$mitems, $category) {
		while($category->subs) {
			foreach($category->subs as $category) {
				$indent = "";
				for($i=0; $i < $category->level; $i++) { $indent .= " - "; } 
				$mitems[] = JHTML::_('select.option', $category->id, $indent . $category->title);
				$this->addOptions($category);
			}
		}
	}
}

?>