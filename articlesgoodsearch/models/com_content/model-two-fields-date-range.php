<?php
/**
 * @package     Articles Good Search
 *
 * @copyright   Copyright (C) 2017 Joomcar extensions. All rights reserved.
 * @license     GNU General Public License version 2 or later.
 */
 
// no direct access
defined('_JEXEC') or die('Restricted access');

class ArticlesModelGoodSearch extends JModelList {
	var $input;
	var $module_id;
	var $module_helper;
	var $module_params;
	var $module_params_native;
	var $limit;
	var $limitstart;
	var $total_items;
	var $search_query;
	
	function __construct() {		
		$this->input = JFactory::getApplication()->input;
		require_once(JPATH_SITE . "/modules/mod_articles_good_search/helper.php");
		$this->module_id = $this->input->get("moduleId", "", "int");
		$this->module_helper = new modArticlesGoodSearchHelper;
		$this->module_params = $this->module_helper->getModuleParams($this->module_id);
		$this->module_params_native = $this->module_helper->getModuleParams($this->module_id, true);
		
		if($this->module_params->savesearch && !JFactory::getApplication()->input->getWord("initial")) {
			$this->saveSearchSession();
		}
		
		if($this->module_params->savesearch && JFactory::getSession()->get("SaveSearchValues")
			&& $_GET['applySaved']
		) {
			$skip = array("option", "task", "view", "Itemid", "search_mode", "dynobox", "field_id", "field_type", "initial");
			foreach(JFactory::getSession()->get("SaveSearchValues") as $key=>$value) {
				if(in_array($key, $skip)) continue;
				JFactory::getApplication()->input->set($key, $value);
			}
		}
		
		$this->search_query = $this->getSearchQuery();
		$this->total_items = $this->getItems(true);
	}
	
	function getItems($total = false) {		
		$db = JFactory::getDBO();
		
		if($total) {
			$query = "SELECT COUNT(DISTINCT i.id)";
		}
		else {
			$featuredFirst = false;
			switch($this->module_params->include_featured) {
				case "First" :
					$featuredFirst = true;
				break;
				case "Only" : 
					$query .= " AND i.featured = 1";
				break;
				case "No" :
					$query .= " AND i.featured = 0";
				break;
			}
			
			$default_ordering = $featuredFirst ? 'featured' : $this->module_params->ordering_default;
			$orderby = JFactory::getApplication()->input->getWord("orderby", $default_ordering);
			$orderto = JFactory::getApplication()->input->getWord("orderto", $this->module_params->ordering_default_dir);
			
			$query = "SELECT i.*, GROUP_CONCAT(tm.tag_id) as tags, cat.title as category";
			//select field ordering value
			if($featuredFirst) {
				preg_match('/^field([0-9]+)$/', $this->module_params->ordering_default, $matches);
			}
			else {
				preg_match('/^field([0-9]+)$/', $orderby, $matches);
			}
			if(count($matches)) {
				$query .= ", fv2.value as {$matches[0]}";
			}
		}
		
		$query .= " FROM #__content as i";
		$query .= " LEFT JOIN #__categories AS cat ON cat.id = i.catid";
		
		//added for compatibility with cv multicategories plugin
		if (JPluginHelper::isEnabled('system', 'cwmulticats')) {
			$query .= " LEFT JOIN #__content_multicats as multicats ON multicats.content_id = i.id";
		}
		
		if(JFactory::getApplication()->input->getWord("keyword")) {
			//left join all fields values for keyword search
			//commented for prevent slow loading with big databases
			//$query .= " LEFT JOIN #__fields_values AS fv ON fv.item_id = i.id";
		}
		
		$query .= " LEFT JOIN #__contentitem_tag_map AS tm 
						ON tm.content_item_id = i.id 
							AND type_alias = 'com_content.article'
				";
		
		if(!$total) {
			//left join field ordering value
			if($featuredFirst) {
				preg_match('/^field([0-9]+)$/', $this->module_params->ordering_default, $matches);
			}
			else {
				preg_match('/^field([0-9]+)$/', $orderby, $matches);
			}
			if(count($matches)) {
				$query .= " LEFT JOIN #__fields_values AS fv2 ON fv2.item_id = i.id AND fv2.field_id = {$matches[1]}";
			}
		}
			
		$query .= " WHERE i.state = 1";
		
		//publish up/down
		$timezone = new DateTimeZone(JFactory::getConfig()->get('offset'));
		$time = new DateTime(date("Y-m-d H:i:s"), $timezone);
		$time = $time->format('Y-m-d H:i:s');
		$query .= " AND i.publish_up <= '{$time}' AND (i.publish_down > '{$time}' OR i.publish_down = '0000-00-00 00:00:00')";

		//category restriction
		if($this->module_params->restrict) {
			$module_params_native = $this->module_helper->getModuleParams($this->module_id, true);
			$category_restriction = $this->module_helper->getCategories(0, $module_params_native);
			if(count($category_restriction)) {
				$ids = Array();
				foreach($category_restriction as $c) {
					$ids[] = $c->id;
				}
				//added for compatibility with cv multicategories plugin
				if (JPluginHelper::isEnabled('system', 'cwmulticats')) {
					$query .= " AND (
									multicats.catid IN (".implode(",", $ids).")
									OR i.catid IN (".implode(",", $ids).")
								)";
				}
				else {
					$query .= " AND i.catid IN (".implode(",", $ids).")";
				}
			}			
		}
		
		//language filter
		$language = JFactory::getLanguage();
		$defaultLang = $language->getDefault();
		$currentLang = $language->getTag();
		$query .= " AND i.language IN ('*', '{$currentLang}')";

		//general search query build
		$query .= $this->search_query;

		if(!$total) {
			$query .= " GROUP BY i.id";
			$query .= " ORDER BY ";
			switch($orderby) {
				case "title" :
					if(JFactory::getApplication()->input->getWord("orderto") == "") {
						$orderto = "ASC";
						JFactory::getApplication()->input->set("orderto", "asc");
					}
					$query .= "i.title {$orderto}";
				break;
				case "alias" :
					$query .= "i.alias {$orderto}";
				break;				
				case "created" :
					$query .= "i.created {$orderto}";
				break;				
				case "publish_up" :
					$query .= "i.publish_up {$orderto}";
				break;
				case "category" :
					$query .= "category {$orderto}";
				break;
				case "hits" :
					$query .= "i.hits {$orderto}";
				break;
				case "featured" :
					$query .= "i.featured {$orderto}";
					//order by field value
					preg_match('/^field([0-9]+)$/', $this->module_params->ordering_default, $matches);
					if(count($matches)) {
						$query .= ", {$this->module_params->ordering_default} {$orderto}";
					}
					else {
						$query .= ", i.{$this->module_params->ordering_default} {$orderto}";
					}
				break;
				case "rand" :
					$currentSession = JFactory::getSession();    
					$sessionNum = substr(preg_replace('/[^0-9]/i','',$currentSession->getId()),2,3); 
					$query .= "RAND({$sessionNum})";
				break;
				case "id" :
				default :
					//order by field value
					preg_match('/^field([0-9]+)$/', $orderby, $matches);
					if(count($matches)) {
						$query .= "{$orderby} {$orderto}";
					}
					else {
						$query .= "i.id {$orderto}";
					}
			}
		}

		if($total) {
			$db->setQuery($query);	
			return $db->loadResult();
		}
		else {
			$this->limitstart = $this->input->get("page-start", 0, "int");			
			if($_GET['debug']) {
				echo "<br />";
				echo "<br />";
				//echo "Full query: <br />";
				//echo $query;
			}
			$db->setQuery($query, $this->limitstart, $this->limit);
			return $db->loadObjectList();
		}
	}
	
	function getSearchQuery() {
		$timezone = new DateTimeZone(JFactory::getConfig()->get('offset'));
		$query = "";

		//keyword
		if(JFactory::getApplication()->input->getWord("keyword")) {
			$keyword = strtoupper(JFactory::getApplication()->input->getWord("keyword"));
			$keyword = addslashes($keyword);
			$keyword = str_replace("/", "\\\\\\\/", $keyword);
			$keyword = str_replace("(", "\\\\(", $keyword);
			$keyword = str_replace(")", "\\\\)", $keyword);
			$keyword = str_replace("*", "\\\\*", $keyword);
			if($_GET['match'] == 'any') {
				$query .= " AND (";
				foreach(explode(" ", $keyword) as $k=>$word) {
					$query .= $k > 0 ? " OR " : "";
					$query .= "UPPER(i.title) LIKE '%{$word}%'";
				}
				$query .= ")";
			}
			else {
				$query .= " AND (UPPER(i.title) LIKE '%{$keyword}%'";
					$query .= "  OR UPPER(i.introtext) LIKE '%{$keyword}%'";
					//commented for prevent slow loading with big databases
					//$query .= "OR GROUP_CONCAT(fv.value SEPARATOR ', ') LIKE '%{$keyword}%'";
				$query .= ")";
			}
		}		
		
		//category
		if(JFactory::getApplication()->input->getInt("category")) {
			$categories = JFactory::getApplication()->input->getInt("category");
			if($categories[0] != "") {
				if($this->module_params->restsub) {
					foreach($categories as $category) {
						$subs = (array)$this->module_helper->getSubCategories($category);
						$categories = array_merge($categories, $subs);
					}
				}
				//added for compatibility with cv multicategories plugin
				if (JPluginHelper::isEnabled('system', 'cwmulticats')) {
					$query .= " AND (
									multicats.catid IN (".implode(",", $categories).")
									OR i.catid IN (".implode(",", $categories).")
								)";
								
				}
				else {
					$query .= " AND i.catid IN (".implode(",", $categories).")";
				}
			}
		}
		
		//tag
		if(JFactory::getApplication()->input->getInt("tag")) {
			$query .= " AND tm.tag_id IN (".implode(",", JFactory::getApplication()->input->getInt("tag")).")";
		}
		if(JFactory::getApplication()->input->getInt("j2store_tag")) {
			$query .= " AND tm.tag_id = ".JFactory::getApplication()->input->getInt("j2store_tag");
		}

		//author
		if(JFactory::getApplication()->input->getInt("author")) {
			$query .= " AND i.created_by IN (".implode(",", JFactory::getApplication()->input->getInt("author")).")";
		}
		
		//date
		if(JFactory::getApplication()->input->getWord("date-from")) {
			$query .= " AND i.created >= '".JFactory::getApplication()->input->getWord("date-from")." 00:00:00'";
		}
		if(JFactory::getApplication()->input->getWord("date-to")) {
			$query .= " AND i.created <= '".JFactory::getApplication()->input->getWord("date-to")." 23:59:59'";
		}
		
		//fields search
		require_once(JPATH_SITE . '/modules/mod_articles_good_search/helper.php');
		$module_helper = new modArticlesGoodSearchHelper;

		foreach($_GET as $param=>$value) {
			preg_match('/^field([0-9]+)$/', $param, $matches);
			$field_id = $matches[1];
			$query_params = JFactory::getApplication()->input->getWord("field{$field_id}");
			$sub_query = "SELECT DISTINCT item_id FROM #__fields_values WHERE 1";
			
			//text / date
			if(!is_array($query_params) && $query_params != "") {
				$query_params = addslashes($query_params);
				$sub_query .= " AND field_id = {$field_id}";
				$field_params = $module_helper->getCustomField($field_id);
				if($field_params->type == "calendar") {
					$date = \JFactory::getDate($query_params)->setTimezone($timezone);
					$sub = $date->getOffsetFromGmt();
					// get date with timezone offset
					$query_params = date("Y-m-d", strtotime($date->format('Y-m-d H:i:s')) - $sub);
					$sub_query .= " AND value LIKE '%{$query_params}%'";
				}
				else {
					$sub_query .= " AND value LIKE '%{$query_params}%'";
				}
			}
			
			//list values
			if(is_array($query_params) && $query_params[0] != "") {
				$sub_query .= " AND field_id = {$field_id}";
				$sub_query .= " AND (";
				foreach($query_params as $k=>$query_param) {
					$query_param = addslashes($query_param);
					$sub_query .= "value = '{$query_param}'";
					if(($k+1) != count($query_params)) {
						if($_GET['match'] == "all") {
							$sub_query .= " AND ";
						}
						else {
							$sub_query .= " OR ";
						}
					}
				}
				$sub_query .= ")";
			}
			
			//added
			
				//text range / date range
				// from
				preg_match('/^field([0-9]+)-from$/', $param, $matches);
				$field_id = $matches[1];
				if($field_id == 1) {
					$query_params = JFactory::getApplication()->input->getWord("field{$field_id}-from");
					$query_params = addslashes($query_params);
					$date_search = new DateTime($query_params, $timezone);
					$query_from = $date_search->format('Y-m-d');
					
					$query_values = "
							SELECT item_id, GROUP_CONCAT(value SEPARATOR '->') as date_range
								FROM #__fields_values
							WHERE field_id IN (1,5)
							GROUP BY item_id
							ORDER BY item_id ASC
					";
					
					$ids = array();
					$items = JFactory::getDBO()->setQuery($query_values)->loadObjectList();
					if(count($items)) {
						foreach($items as $item) {
							$dates = explode("->", $item->date_range);
							$date_from = $dates[0];
							$date_to = $dates[1];
							
							if($date_from >= "{$query_from} 00:00:00"
								|| $date_to >= "{$query_from} 00:00:00"
							) {
								if(JFactory::getApplication()->input->getWord("field{$field_id}-to") != "") {
									$query_params = JFactory::getApplication()->input->getWord("field{$field_id}-to");
									$query_params = addslashes($query_params);
									$date_search = new DateTime($query_params, $timezone);
									$query_to = $date_search->format('Y-m-d');									
								
									if($date_from <= "{$query_to} 23:00:00"
										|| $date_to <= "{$query_to} 23:00:00"
									) {
										$ids[] = $item->item_id;
									}
								}
								else {
									$ids[] = $item->item_id;
								}
							}
							
							//echo $item->item_id . " - {$date_from} - {$date_to} <br />";
						}
					}
					
					if(count($ids)) {
						$query .= " AND i.id IN(" . implode(",", $ids) . ")";
					}
					else {
						$query .= " AND i.id = 0";
					}
					return $query;
				}
				else {
					if(JFactory::getApplication()->input->getWord("field{$field_id}-from") != "") {
						$sub_query .= " AND field_id = {$field_id}";
						$field_params = $module_helper->getCustomField($field_id);
						$query_params = JFactory::getApplication()->input->getWord("field{$field_id}-from");
						$query_params = addslashes($query_params);
						if($field_params->type == "calendar") {
							$date_search = new DateTime($query_params, $timezone);
							$query_params = $date_search->format('Y-m-d');
							$sub_query .= " AND value >= '{$query_params} 00:00:00'";
						}
						else {
							if(is_numeric($query_params)) {
								$query_params = trim(preg_replace('/\s+/i', '', $query_params));
							}
							else {
								$query_params = "'" . $query_params . "'";
							}
							$sub_query .= " AND value >= {$query_params}";
						}
					}	
				}

				//text range / date range
				// To
				preg_match('/^field([0-9]+)-to$/', $param, $matches);
				$field_id = $matches[1];
				if($field_id == 5) {
					// nothing					
				}
				else {
					if(JFactory::getApplication()->input->getWord("field{$field_id}-to") != "") {
						$sub_query .= " AND field_id = {$field_id}";
						$field_params = $module_helper->getCustomField($field_id);
						$query_params = JFactory::getApplication()->input->getWord("field{$field_id}-to");
						$query_params = addslashes($query_params);
						if($field_params->type == "calendar") {
							$date_search = new DateTime($query_params, $timezone);
							$query_params = $date_search->format('Y-m-d');
							$sub_query .= " AND value <= '{$query_params} 23:59:59'";
						}
						else {
							if(is_numeric($query_params)) {
								$query_params = trim(preg_replace('/\s+/i', '', $query_params));
							}
							else {
								$query_params = "'" . $query_params . "'";
							}
							$sub_query .= " AND value <= {$query_params}";
						}
					}
				}
			
			// Execute query and get item ids
			if($query_params != "" && $query_params[0] != "") {
				$ids = JFactory::getDBO()->setQuery($sub_query)->loadColumn();
				if(count($ids)) {
					$query .= " AND i.id IN(" . implode(",", $ids) . ")";
				}
				else {
					$query .= " AND i.id = 0";
				}
			}
		}
		
		//added for compatibility with radical multifield
		foreach($_GET as $param=>$value) {
			preg_match('/^multifield([0-9]+)-([^-]*)(.*)/i', $param, $matches);
			$field_id = $matches[1];
			$sub_field = $matches[2];
			$isRange = $matches[3] != '' ? true : false;
			if(!$field_id || !$sub_field) continue;
			$field_params = $module_helper->getCustomField($field_id);
			
			$uri_params = JFactory::getApplication()->input->getWord($param);		
			$sub_query = "SELECT DISTINCT item_id FROM #__fields_values WHERE 1";
			
			//text / date
			if(!is_array($uri_params) && $uri_params != "" && !$isRange) {
				$sub_query .= " AND field_id = {$field_id}";
				if($field_params->type == "calendar") {
					$date_search = new DateTime($uri_params, $timezone);
					$uri_params = $date_search->format('Y-m-d');
					$sub_query .= " AND value LIKE '%{$uri_params}%'";
				}
				else {
					$sub_query .= " AND value REGEXP '\"{$sub_field}\":\"{$uri_params}\"'";
				}
			}
			
			//text range / date range
			if($matches[3] == '-from') {
				$range_query = "SELECT * FROM #__fields_values WHERE field_id = {$field_id}";
				$values = JFactory::getDBO()->setQuery($range_query)->loadObjectList();
				$ids_to_include = array();
				foreach($values as $value) {
					$item_id = $value->item_id;
					$value = json_decode($value->value);
					foreach($value as $val) {
						if($val->{$sub_field} >= $uri_params) { //check for more or equal
							$ids_to_include[] = $item_id;
						}
					}
				}
				$ids_to_include = array_values(array_unique($ids_to_include));
				if(count($ids_to_include)) {
					$sub_query .= " AND item_id IN(" . implode(",", $ids_to_include) . ")";
				}
				else {
					$sub_query .= " AND item_id = 0";
				}
			}
			if($matches[3] == '-to') {
				$range_query = "SELECT * FROM #__fields_values WHERE field_id = {$field_id}";
				$values = JFactory::getDBO()->setQuery($range_query)->loadObjectList();
				$ids_to_include = array();
				foreach($values as $value) {
					$item_id = $value->item_id;
					$value = json_decode($value->value);
					foreach($value as $val) {
						if($val->{$sub_field} <= $uri_params
							&& $val->{$sub_field} != ''
						) { //check for less or equal
							$ids_to_include[] = $item_id;
						}
					}
				}
				$ids_to_include = array_values(array_unique($ids_to_include));
				if(count($ids_to_include)) {
					$sub_query .= " AND item_id IN(" . implode(",", $ids_to_include) . ")";
				}
				else {
					$sub_query .= " AND item_id = 0";
				}
			}
			
			// Execute query and get item ids
			if($uri_params != "" && $uri_params[0] != "") {
				$ids = JFactory::getDBO()->setQuery($sub_query)->loadColumn();
				if(count($ids)) {
					$query .= " AND i.id IN(" . implode(",", $ids) . ")";
				}
				else {
					$query .= " AND i.id = 0";
				}
			}
		}
		
		//added for compatibility with repeatable field
		foreach($_GET as $param=>$value) {
			preg_match('/^repeatable([0-9]+)-([^-]*)(.*)/i', $param, $matches);
			$field_id = $matches[1];
			$sub_field_number = $matches[2];
			$isRange = $matches[3] != '' ? true : false;
			if(!$field_id || $sub_field_number === NULL) continue;
			$field_params = $module_helper->getCustomField($field_id);
			
			$uri_params = JFactory::getApplication()->input->getWord($param);		
			$sub_query = "SELECT DISTINCT item_id FROM #__fields_values WHERE 1";
			
			$sub_field_values = json_decode($field_params->fieldparams);
			$sub_field_name = $sub_field_values->fields->{"fields".$sub_field_number}->fieldname;
			
			//text / date
			if(!is_array($uri_params) && $uri_params != "" && !$isRange) {
				$sub_query .= " AND field_id = {$field_id}";
				if($field_params->type == "calendar") {
					$date_search = new DateTime($uri_params, $timezone);
					$uri_params = $date_search->format('Y-m-d');
					$sub_query .= " AND value LIKE '%{$uri_params}%'";
				}
				else {
					$sub_query .= " AND value REGEXP '\"{$sub_field_name}\":\"{$uri_params}\"'";
				}
			}
			
			//text range / date range
			if($matches[3] == '-from') {
				$range_query = "SELECT * FROM #__fields_values WHERE field_id = {$field_id}";
				$values = JFactory::getDBO()->setQuery($range_query)->loadObjectList();
				$ids_to_include = array();
				foreach($values as $value) {
					$item_id = $value->item_id;
					$value = json_decode($value->value);
					foreach($value as $val) {
						if($val->{$sub_field_name} >= $uri_params) { //check for more or equal
							$ids_to_include[] = $item_id;
						}
					}
				}
				$ids_to_include = array_values(array_unique($ids_to_include));
				if(count($ids_to_include)) {
					$sub_query .= " AND item_id IN(" . implode(",", $ids_to_include) . ")";
				}
				else {
					$sub_query .= " AND item_id = 0";
				}
			}
			if($matches[3] == '-to') {
				$range_query = "SELECT * FROM #__fields_values WHERE field_id = {$field_id}";
				$values = JFactory::getDBO()->setQuery($range_query)->loadObjectList();
				$ids_to_include = array();
				foreach($values as $value) {
					$item_id = $value->item_id;
					$value = json_decode($value->value);
					foreach($value as $val) {
						if($val->{$sub_field_name} <= $uri_params
							&& $val->{$sub_field_name} != ''
						) { //check for less or equal
							$ids_to_include[] = $item_id;
						}
					}
				}
				$ids_to_include = array_values(array_unique($ids_to_include));
				if(count($ids_to_include)) {
					$sub_query .= " AND item_id IN(" . implode(",", $ids_to_include) . ")";
				}
				else {
					$sub_query .= " AND item_id = 0";
				}
			}
			
			// Execute query and get item ids
			if($uri_params != "" && $uri_params[0] != "") {
				$ids = JFactory::getDBO()->setQuery($sub_query)->loadColumn();
				if(count($ids)) {
					$query .= " AND i.id IN(" . implode(",", $ids) . ")";
				}
				else {
					$query .= " AND i.id = 0";
				}
			}
		}

		return $query;
	}
	
	function getPagination() {
		jimport('joomla.html.pagination');
		$pagination = new JPagination($this->total_items, $this->limitstart, $this->limit);
		foreach($_GET as $param=>$value) {
			if(in_array($param, Array("id", "start", "option", "view", "task"))) continue;
			if(is_array($value)) {
				foreach($value as $k=>$val) {
					$pagination->setAdditionalUrlParam($param . "[{$k}]", $val);
				}
			}
			else {
				$pagination->setAdditionalUrlParam($param, $value);
			}
		}
		return $pagination;
	}
	
	function execPlugins(&$item) {
		$app = JFactory::getApplication('site');
		$params = $app->getParams();
		$dispatcher = JEventDispatcher::getInstance();
		$item->event   = new stdClass;

		// Old plugins: Ensure that text property is available
		$item->text = $item->introtext;
		
		JPluginHelper::importPlugin('content');
		$dispatcher->trigger('onContentPrepare', array ('com_content.category', &$item, &$item->params, 0));

		// Old plugins: Use processed text as introtext
		$item->introtext = $item->text;
		
		$item->params = new JRegistry($item->attribs);
		
		$results = $dispatcher->trigger('onContentBeforeDisplay', array('com_content.category', &$item, &$item->params, 0));
		$item->event->beforeDisplayContent = trim(implode("\n", $results));
		
		$results = $dispatcher->trigger('onContentAfterTitle', array('com_content.category', &$item, &$item->params, 0));
		$item->event->afterDisplayTitle = trim(implode("\n", $results));

		$results = $dispatcher->trigger('onContentAfterDisplay', array('com_content.category', &$item, &$item->params, 0));
		$item->event->afterDisplayContent = trim(implode("\n", $results));
	}
	
	function getAuthorById($id) {
		$db = JFactory::getDBO();
		$query = "SELECT * FROM #__users WHERE id = {$id}";
		$db->setQuery($query);
		return $db->loadObject();
	}
	
	function getCategoryById($id) {
		$db = JFactory::getDBO();
		$query = "SELECT * FROM #__categories WHERE id = {$id}";
		$db->setQuery($query);
		return $db->loadObject();
	}
	
	function getItemCategories($aItem) {
		$aCategories = array();
		$catids = array();
		//added for compatibility with cv multicategories plugin
		if (JPluginHelper::isEnabled('system', 'cwmulticats')) {
			$catids = JFactory::getDBO()->setQuery("SELECT catid FROM #__content_multicats WHERE content_id = {$aItem->id} ORDER BY ordering ASC")->loadColumn();
		}
		else {
			$catids = array($aItem->catid);
		}
		if(!count($catids)) {
			$catids = array($aItem->catid);
		}
		$catids = array_unique($catids);
		require_once(JPATH_SITE . '/components/com_content/helpers/route.php');
		foreach($catids as $id) {
			$category = $this->getCategoryById($id);
			$category->link = JRoute::_(ContentHelperRoute::getCategoryRoute($category->id));
			$aCategories[] = $category;
		}
		return $aCategories;
	}
	
	function saveSearchSession() {
		if(!$_GET['gsearch']) return;
		JFactory::getSession()->set("SaveSearchValues", $_GET);
	}
	
	function saveSearchStats() {
		$this->searchStatsTableCreate();
		$data = json_decode($_GET['data_stats']);
		$keyword = $data[0]->title;
		$search_link = $data[0]->link;
		
		//save keyword
		$query = "SELECT search_count FROM #__content_search_stats WHERE url = '{$search_link}'";
		$count = intval(JFactory::getDBO()->setQuery($query)->loadResult());
		$config = JFactory::getConfig();
		$tzoffset = $config->get('offset');
		$date = JFactory::getDate('', $tzoffset)->format("Y-m-d H:i:s");
		if($count) {
			$query = "UPDATE #__content_search_stats SET search_count = (search_count + 1), last_search_date = '{$date}' WHERE url = '{$search_link}'";
			JFactory::getDBO()->setQuery($query)->query();
			$query = "SELECT id FROM #__content_search_stats WHERE url = '{$search_link}'";
			$keyword_id = JFactory::getDBO()->setQuery($query)->loadResult();
		} else {
			$query = "INSERT INTO #__content_search_stats VALUES ('', '{$keyword}', '{$search_link}', '{$date}', 1)";
			JFactory::getDBO()->setQuery($query)->query();
			$keyword_id = JFactory::getDBO()->insertid();
		}
		//save user
		$user = JFactory::getUser();
		$query = "SELECT search_count FROM #__content_search_stats_users WHERE keyword_id = {$keyword_id} AND user_id = {$user->id}";
		$count = intval(JFactory::getDBO()->setQuery($query)->loadResult());
		$ip_address = $_SERVER['REMOTE_ADDR'];
		if($count) {
			$query = "UPDATE #__content_search_stats_users SET search_count = (search_count + 1), last_search_date = '{$date}', ip_address = '{$ip_address}' WHERE keyword_id = {$keyword_id} AND user_id = {$user->id}";
			JFactory::getDBO()->setQuery($query)->query();
		} else {
			$query = "INSERT INTO #__content_search_stats_users VALUES ('', {$user->id}, {$keyword_id}, '{$date}', 1, '{$ip_address}')";
			JFactory::getDBO()->setQuery($query)->query();
		}		
	}

	function getStatsList($total = false) {
		$this->searchStatsTableCreate();
		$db = JFactory::getDBO();
		$limitstart = $this->limit;
	
		if($total) {
			$query = "SELECT COUNT(DISTINCT id) FROM #__content_search_stats";
		}
		else {
			$query = "SELECT * FROM #__content_search_stats";
			$order = addslashes(JFactory::getApplication()->input->getWord("orderby", "last_search_date"));
			$query .= " ORDER BY {$order} DESC";
		}
		
		if($total) {
			$db->setQuery($query);	
			return $db->loadResult();
		}
		else {
			$db->setQuery($query, JFactory::getApplication()->input->getInt("limitstart", 0), 10);
			return $db->loadObjectList();
		}
	}
	
	function getStatsListPagination() {
		$total_items = $this->getStatsList(true);
		jimport('joomla.html.pagination');
		$pagination = new JPagination($total_items, JFactory::getApplication()->input->getInt("limitstart", 0), 10);
		foreach($_GET as $param=>$value) {
			if(in_array($param, Array("id", "start", "option", "view", "task", "limit", "featured"))) continue;
			if(is_array($value)) {
				foreach($value as $k=>$val) {
					$pagination->setAdditionalUrlParam($param . "[{$k}]", $val);
				}
			}
			else {
				$pagination->setAdditionalUrlParam($param, $value);
			}
		}
		return $pagination;
	}	

	function getStatsKeywordList($total = false) {
		$this->searchStatsTableCreate();
		$db = JFactory::getDBO();
		$limitstart = $this->limit;	
		$keyword_id = JFactory::getApplication()->input->getInt("id");
	
		if($total) {
			$query = "SELECT COUNT(DISTINCT id) FROM #__content_search_stats_users WHERE keyword_id = {$keyword_id}";
		}
		else {
			$query = "SELECT * FROM #__content_search_stats_users WHERE keyword_id = {$keyword_id}";
			$order = addslashes(JFactory::getApplication()->input->getWord("orderby", "last_search_date"));
			$query .= " ORDER BY {$order} DESC";
		}
		
		if($total) {
			$db->setQuery($query);	
			return $db->loadResult();
		}
		else {
			$db->setQuery($query, JFactory::getApplication()->input->getInt("limitstart", 0), 10);
			return $db->loadObjectList();
		}
	}
	
	function getStatsKeywordListPagination() {
		$total_items = $this->getStatsKeywordList(true);
		jimport('joomla.html.pagination');
		$pagination = new JPagination($total_items, JFactory::getApplication()->input->getInt("limitstart", 0), 10);
		foreach($_GET as $param=>$value) {
			if(in_array($param, Array("id", "start", "option", "view", "task", "limit", "featured"))) continue;
			if(is_array($value)) {
				foreach($value as $k=>$val) {
					$pagination->setAdditionalUrlParam($param . "[{$k}]", $val);
				}
			}
			else {
				$pagination->setAdditionalUrlParam($param, $value);
			}
		}
		return $pagination;
	}
	
	function searchStatsTableCreate() {	
		$query = "CREATE TABLE IF NOT EXISTS `#__content_search_stats` (";
			$query .= "`id` int(21) NOT NULL AUTO_INCREMENT PRIMARY KEY,";
			$query .= "`keyword` varchar(255) NOT NULL,";
			$query .= "`url` tinytext NOT NULL,";
			$query .= "`last_search_date` varchar(255) NOT NULL,";
			$query .= "`search_count` int(11) NOT NULL";
		$query .= ") ENGINE=MyISAM  DEFAULT CHARSET=utf8;";
		JFactory::getDBO()->setQuery($query)->query();
		
		$query = "CREATE TABLE IF NOT EXISTS `#__content_search_stats_users` (";
			$query .= "`id` int(21) NOT NULL AUTO_INCREMENT PRIMARY KEY,";
			$query .= "`user_id` int(21) NOT NULL,";
			$query .= "`keyword_id` int(21) NOT NULL,";
			$query .= "`last_search_date` varchar(255) NOT NULL,";
			$query .= "`search_count` int(21) NOT NULL,";
			$query .= "`ip_address` varchar(255) NOT NULL";
		$query .= ") ENGINE=MyISAM  DEFAULT CHARSET=utf8;";
		JFactory::getDBO()->setQuery($query)->query();
	}	
}

?>