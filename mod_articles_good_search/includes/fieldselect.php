<?php

/**
 * @package     Articles Good Search
 *
 * @copyright   Copyright (C) 2017 Joomcar extensions. All rights reserved.
 * @license     GNU General Public License version 2 or later.
 */

defined('_JEXEC') or die('Restricted access');

class JFormFieldFieldSelect extends JFormField {	

	function getInput(){
		return JFormFieldFieldSelect::fetchElement($this->name, $this->value, $this->element, $this->options['control']);
	}
	
	function fetchElement($name, $value, &$node, $control_name) {
	
	        $mitems[] = JHTML::_('select.option', '', '');
			
			$mitems[] = JHTML::_('select.option', 'title', JText::_('MOD_AGS_SORTING_TITLE'));
			$mitems[] = JHTML::_('select.option', 'alias', JText::_('MOD_AGS_SORTING_ALIAS'));
			$mitems[] = JHTML::_('select.option', 'created', JText::_('MOD_AGS_SORTING_DATE'));
			$mitems[] = JHTML::_('select.option', 'publish_up', JText::_('MOD_AGS_SORTING_DATE_PUBLISHING'));
			$mitems[] = JHTML::_('select.option', 'category', JText::_('MOD_AGS_SORTING_CATEGORY'));
			$mitems[] = JHTML::_('select.option', 'hits', JText::_('MOD_AGS_SORTING_POPULAR'));
			$mitems[] = JHTML::_('select.option', 'featured', JText::_('MOD_AGS_SORTING_FEATURED'));
			$mitems[] = JHTML::_('select.option', 'rand', JText::_('MOD_AGS_SORTING_RANDOM'));
			
			$mitems[] = JHTML::_('select.option', '', JText::_('-- Custom Fields --'));
			
			$query = "SELECT f.*, g.title as group_name FROM #__fields as f 
						LEFT JOIN #__fields_groups AS g ON f.group_id = g.id
						WHERE f.context = 'com_content.article'
						ORDER BY g.id, f.label
					";
			try {
				$fields = JFactory::getDBO()->setQuery($query)->loadObjectList();
			}
			catch (Exception $e) {
				$mitems[] = JHTML::_("select.option", "", "Custom Fields available from Joomla 3.8+");
			}
			if(count($fields)) {
				$group = @$fields[0]->group_name;
				array_splice($fields, 0, 0, $group);
				for($i = 1; $i < count($fields); $i++) {
					$new_group = $fields[$i]->group_name;
					if($new_group != $group) {
						array_splice($fields, $i, 0, $new_group);
						$group = $new_group;
					}
				}
				foreach($fields as $field) {
					if(is_object($field)) {
						$field->group_name ? $offset = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" : $offset = "&nbsp;&nbsp;&nbsp;";
						if($field->type == 'radicalmultifield') {
							continue; //do not use for ordering for now
							$field_params = json_decode($field->fieldparams);
							$sub_fields = array();
							foreach($field_params->listtype as $k=>$sub_field) {
								$sub_fields[$k] = new stdClass;
								$sub_fields[$k]->name = $sub_field->name;
								$sub_fields[$k]->title = $sub_field->title;
							}
							$tmp = array_values($sub_fields);
							$sub_fields = array();
							$sub_fields['radicalmultifield_fields'] = $tmp;
							$sub_fields = json_encode($sub_fields);
							
							$mitems[] = JHTML::_("select.option", "field:{$field->id}:{$field->type}:{$sub_fields}", $offset . JText::_("{$field->label} [id: {$field->id}]"));
						}
						else if($field->type == 'repeatable') {
							continue; //do not use for ordering for now
							$field_params = json_decode($field->fieldparams);
							$sub_fields = array();
							foreach($field_params->fields as $k=>$sub_field) {
								$sub_fields[$k] = new stdClass;
								$sub_fields[$k]->name = $sub_field->fieldname;
								$sub_fields[$k]->title = $sub_field->fieldname;
							}
							$tmp = array_values($sub_fields);
							$sub_fields = array();
							$sub_fields['repeatable_fields'] = $tmp;
							$sub_fields = json_encode($sub_fields);
							
							$mitems[] = JHTML::_("select.option", "field:{$field->id}:{$field->type}:{$sub_fields}", $offset . JText::_("{$field->label} [id: {$field->id}]"));
						}
						else {
							$extra = array(
								'name' => $field->label,
							);
							$extra = json_encode($extra);
							$mitems[] = JHTML::_("select.option", "field:{$field->id}:{$field->type}:{$extra}", $offset . JText::_("{$field->label} [id: {$field->id}]"));
						}
					}
					else {
						$mitems[] = JHTML::_("select.option", "", "&nbsp;&nbsp;&nbsp;-- {$field} --");
					}
				}
			}
			
			$output = JHTML::_('select.genericlist',  $mitems, '', 'class="ValueSelect inputbox"', 'value', 'text', '0');		
			$output .= "<div class='clear'></div><ul class='sortableFields fieldselect'></ul>";
			$output .= "<div class='clear'></div>";
			
			if($value == '') {
				$value = "title\nalias\ncreated\ncategory\nhits\nfeatured\nrand";
			}
			
			$output .= "
				<textarea style='display: none;' name='".$name."' class='ValueSelectVal'>".$value."</textarea>
				<style>
					ul.fieldselect select.field_type_select { display: none; }
				</style>
			";

			return $output;
	}
}

?>