<?php 

/**
 * @package     Articles Good Search
 *
 * @copyright   Copyright (C) 2017 Joomcar extensions. All rights reserved.
 * @license     GNU General Public License version 2 or later.
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

class modArticlesGoodSearchHelper {
	
	var $params;
	var $db;
	
	function __construct($params = null) {
		if(!$params && JFactory::getApplication()->input->getInt("moduleId")) { // front
			$moduleId = JFactory::getApplication()->input->getInt("moduleId", 0);
			$params = $this->getModuleParams($moduleId, true);
		}
		else if(!$params && $_GET['option'] == 'com_modules' && $_GET['view'] == 'module') { // admin
			$moduleId = JFactory::getApplication()->input->getInt("id");
			$params = $this->getModuleParams($moduleId, true);
		}
		if(!$params) { //failed to get params or new module instance
			$params = new JRegistry();
		}
		$this->params = $params;
		$this->db = JFactory::getDBO();
	}

	function getModuleParams($id, $native = false) {
		if(!$id) return;
		$db = JFactory::getDBO();
		$query = "SELECT * FROM #__modules WHERE id = {$id}";
		$db->setQuery($query);
		$result = $db->loadObject();
		
		if($native) {
			$moduleParams = new JRegistry($result->params);
		}
		else {
			$moduleParams = json_decode($result->params);
		}
		return $moduleParams;
	}
	
	function getCategories($parent = 0, $params = null) {
		$db = JFactory::getDBO();
		$categories = array();
		$categories_restriction = array();

		$extensions = array("'com_content'");
		if($this->params->get('search_sppagebuilder')) {
			$extensions[] = "'com_sppagebuilder'";
		}
		
		if($parent) {
			$query = "SELECT id, title, level, extension FROM #__categories WHERE extension IN(".implode(",", $extensions).") AND parent_id = {$parent} AND published = 1 ORDER BY lft DESC, title ASC";
		}
		else {
			if($params) {
				if($params->get("restrict")) {
					$categories_restriction = $this->getCategoriesRestriction($params);
				}
			}
			if($params && count($categories_restriction)) {
				$query = "SELECT id, title, level, extension FROM #__categories WHERE extension IN(".implode(",", $extensions).") AND id IN (".implode(",", $categories_restriction).") ORDER BY lft DESC, title ASC";				
			}
			else {
				$query = "SELECT id, title, level, extension FROM #__categories WHERE extension IN(".implode(",", $extensions).") AND level = 1 AND published = 1 ORDER BY lft DESC, title ASC";
			}
		}
		
		try {
			$db->setQuery($query);
			$results = $db->loadObjectList();
		}
		catch (Exception $e) {
			echo $e->getMessage();
			echo "<br /><br />" . $query;
		}
		
		foreach($results as $category) {
			$categories[] = $category;
			if(JFactory::getApplication()->isClient('site') && $params) {
				if($params->get("restrict")) {
					if($params->get("restsub")) {
						$subs = (array)$this->getCategories($category->id, $params);
						if(count($subs)) {
							$categories = array_merge($categories, $subs);
						}
					}
				}
				else {
					$subs = (array)$this->getCategories($category->id, $params);
					if(count($subs)) {
						$categories = array_merge($categories, $subs);
					}					
				}
			}
			else {
				$subs = (array)$this->getCategories($category->id, $params);
				if(count($subs)) {
					$categories = array_merge($categories, $subs);
				}				
			}
		}
		
		return $categories;
	}
	
	function getCategoriesRestriction($params, $module_id = null) {
		$categories = Array();
		switch($params->get("restmode")) {
			case 0 : //selected
				if($params->get("restcat") == "") return array();
				$categories = explode("\r\n", $params->get("restcat")); 
			break;
			
			case 1 : //auto
				if(in_array(JFactory::getApplication()->input->getWord("view"), Array("featured", "category"))) {
					$categories[] = JFactory::getApplication()->input->getInt("catid") ? JFactory::getApplication()->input->getInt("catid") : JFactory::getApplication()->input->getInt("id");
				}
				if(JFactory::getApplication()->input->getWord("view") == "article") {
					$aId = JFactory::getApplication()->input->getInt("id");
					$categories[] = (int)$this->db->setQuery("SELECT catid FROM #__content WHERE id = {$aId}")->loadResult();
				}
			break;
		}
		return $categories;
	}
	
	function getSubCategories($parent) {
		$db = JFactory::getDBO();
		
		$extensions = array("'com_content'");
		if($this->params->get('search_sppagebuilder')) {
			$extensions[] = "'com_sppagebuilder'";
		}
		
		$query = "SELECT id FROM #__categories WHERE extension IN(".implode(",", $extensions).") AND parent_id = {$parent} AND published = 1";
		$db->setQuery($query);
		$results = $db->loadColumn();		
		$categories = array();
		foreach($results as $catid) {
			$categories[] = $catid;
			$subs = (array)$this->getSubCategories($catid);
			$categories = array_merge($categories, $subs);
		}
		return $categories;		
	}
	
	//function for mix categories from Content and e.g. SP Page builder
	function mixCategories($categories) {
		$namesExists = array();
		foreach($categories as $k=>$cat) {
			$cat->slug = $this->slugify($cat->title);
			if(array_key_exists($cat->slug, $namesExists)) {
				$categories[$namesExists[$cat->slug]]->id .= ',' . $cat->id;
				unset($categories[$k]);
			}
			else {
				$namesExists[$cat->slug] = $k;
			}
		}
		return $categories;
	}
	
	function slugify($text) {
	  // replace non letter or digits by -
	  $text = preg_replace('~[^\pL\d]+~u', '-', $text);

	  // transliterate
	  $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

	  // remove unwanted characters
	  $text = preg_replace('~[^-\w]+~', '', $text);

	  // trim
	  $text = trim($text, '-');

	  // remove duplicate -
	  $text = preg_replace('~-+~', '-', $text);

	  // lowercase
	  $text = strtolower($text);

	  if (empty($text)) {
		return 'n-a';
	  }

	  return $text;
	}
	
	function getTags($params) {
		$items = Array();
		$db = JFactory::getDBO();
		$query = "SELECT id FROM #__content WHERE state = 1";
		if($params->get("restrict")) {
			$categories = $this->getCategories(0, $params);
			if(count($categories)) {
				$ids = Array();
				foreach($categories as $c) {
					$ids[] = $c->id;
				}
				$query .= " AND catid IN (".implode(",", $ids).")";
			}
		}

		try {
			$db->setQuery($query);
			$items = $db->loadColumn();
		}
		catch (Exception $e) {
			echo $e->getMessage();
			echo "<br /><br />" . $query;
		}
		
		$query = "SELECT DISTINCT(t.id), t.title, t.parent_id FROM #__tags as t";
		$query .= " LEFT JOIN #__contentitem_tag_map AS tm ON t.id = tm.tag_id";
		$query .= " WHERE published = 1";
		if(count($items)) {
			$query .= " AND content_item_id IN (".implode(",", $items).")";
			$query .= " ORDER BY t.title ASC";
		}
		else {
			return array(); // no tags if no items
		}
		$db->setQuery($query);
		return $db->loadObjectList();
	}
	
	function getAuthors($params) {
		$items = Array();
		$db = JFactory::getDBO();
		$query = "SELECT created_by FROM #__content WHERE state = 1";
		if($params->get("restrict")) {
			$categories = $this->getCategoriesRestriction($params);
			if(count($categories)) {
				$query .= " AND catid IN (".implode(",", $categories).")";
			}
		}
		$db->setQuery($query);
		$items = $db->loadColumn();
		
		$authors = Array();
		if(count($items)) {
			foreach($items as $created_by) {
				$authors[$created_by] = $created_by;
			}
			$query = "SELECT id, name FROM #__users WHERE id IN (".implode(',', $authors).")";
		}
		else {
			return $authors; // no authors if no items
		}
		$db->setQuery($query);
		return $db->loadObjectList();
	}
	
	function getCustomField($id) {
		$db = JFactory::getDBO();
		$query = "SELECT * FROM #__fields WHERE id = {$id}";
		$db->setQuery($query);
		return $db->loadObject();
	}
	
	function getFieldValuesFromText($field_id, $type = "int", $module_id) {
		$db = JFactory::getDBO();
		$query = "SELECT i.id, i.catid, GROUP_CONCAT(DISTINCT field{$field_id}.value SEPARATOR '|') as value";
		$query .= " FROM #__content as i";
		$query .= " LEFT JOIN #__fields_values AS field{$field_id} ON field{$field_id}.item_id = i.id AND field{$field_id}.field_id = {$field_id}";
		$query .= " WHERE state = 1";
		
		//category restriction
		$module_params = $this->getModuleParams($module_id, true);
		if($module_params->get('restrict')) {
			$category_restriction = $this->getCategoriesRestriction($module_params);
			if($module_params->get('restsub')) {
				$tmp = array();
				foreach($category_restriction as $catid) {
					$cats = $this->getSubCategories($catid);
					$cats[] = $catid;
					$tmp = array_merge($tmp, $cats);
				}
				$category_restriction = $tmp;
			}
			if(count($category_restriction)) {
				$query .= " AND i.catid IN (".implode(",", $category_restriction).")";
			}
		}
		
		$query .= " GROUP BY i.id";
		$db->setQuery($query);
		$result = $db->loadObjectList();

		$return = Array();
		if(count($result)) {
			foreach($result as $item) {
				if($item->value) {
					$value = explode("|", $item->value);
					foreach($value as $val) {
						$return[] = $type == "text" ? $val : (int)$val;
					}
				}
			}
			sort($return);			
			$return = array_unique($return);
			$return = array_values($return);
		}
		return $return;
	}
	
	function getMultiFieldValuesFromText($field_id, $sub_field, $type = "int", $module_id) {
		$db = JFactory::getDBO();
		$query = "SELECT i.id, i.catid, GROUP_CONCAT(DISTINCT field{$field_id}.value SEPARATOR '|') as value";
		$query .= " FROM #__content as i";
		$query .= " LEFT JOIN #__fields_values AS field{$field_id} ON field{$field_id}.item_id = i.id AND field{$field_id}.field_id = {$field_id}";
		$query .= " WHERE state = 1";
		
		//category restriction
		$module_params = $this->getModuleParams($module_id, true);
		if($module_params->get('restrict')) {
			$category_restriction = $this->getCategoriesRestriction($module_params);
			if($module_params->get('restsub')) {
				$tmp = array();
				foreach($category_restriction as $catid) {
					$cats = $this->getSubCategories($catid);
					$cats[] = $catid;
					$tmp = array_merge($tmp, $cats);
				}
				$category_restriction = $tmp;
			}
			if(count($category_restriction)) {
				$query .= " AND i.catid IN (".implode(",", $category_restriction).")";
			}
		}
		
		$query .= " GROUP BY i.id";
		$db->setQuery($query);
		$result = $db->loadObjectList();

		$return = Array();
		if(count($result)) {
			foreach($result as $item) {
				if($item->value) {
					$value = explode("|", $item->value);
					foreach($value as $val) {
						$val = json_decode($val);
						foreach($val as $repeatable) {
							if($repeatable->{$sub_field}) {
								$return[] = $type == "text" ? $repeatable->{$sub_field} : (int)$repeatable->{$sub_field};
							}
						}
					}
				}
			}
			sort($return);			
			$return = array_unique($return);
			$return = array_values($return);
		}
		return $return;
	}
	
	function getItemExtraFields($iId) {
		$query = "SELECT field_id as id, GROUP_CONCAT(value SEPARATOR ';;') AS value FROM #__fields_values WHERE item_id = {$iId} GROUP BY field_id";
		return JFactory::getDBO()->setQuery($query)->loadObjectList();
	}
	
	function getItemTags($iId) {
		$query = "SELECT m.tag_id as id, t.title as name FROM #__contentitem_tag_map as m 
					LEFT JOIN #__tags as t ON t.id = m.tag_id 
					WHERE m.type_alias = 'com_content.article' AND content_item_id = {$iId}
					";
		return JFactory::getDBO()->setQuery($query)->loadObjectList();
	}
	
	function getItemsTitles($params) {
		$db = JFactory::getDBO();
		$query = "SELECT i.title";
		$query .= " FROM #__content as i";
		$query .= " WHERE state = 1";
		
		//category restriction
		if($params->get('restrict')) {
			$category_restriction = $this->getCategoriesRestriction($params);
			if($params->get('restsub')) {
				$tmp = array();
				foreach($category_restriction as $catid) {
					$cats = $this->getSubCategories($catid);
					$cats[] = $catid;
					$tmp = array_merge($tmp, $cats);
				}
				$category_restriction = $tmp;
			}
			if(count($category_restriction)) {
				$query .= " AND i.catid IN (".implode(",", $category_restriction).")";
			}
		}
		
		$query .= " ORDER BY i.title ASC";
		
		$db->setQuery($query);
		return $db->loadColumn();
	}
}
