<?php

/**
 * @package     Articles Good Search
 *
 * @copyright   Copyright (C) 2017 Joomcar extensions. All rights reserved.
 * @license     GNU General Public License version 2 or later.
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

class pkg_articles_good_searchInstallerScript {
	
	public function postflight($route, $_this) {
		  $db = JFactory::getDbo();
		  try
		  {
			 $q = $db->getQuery(true);
			 $q->update('#__extensions');
			 $q->set(array('enabled = 1'));
			 $q->where("element = 'ArticlesGoodSearch'");
			 $q->where("type = 'plugin'", 'AND');
			 $db->setQuery($q);
			 method_exists($db, 'execute') ? $db->execute() : $db->query();
		  }
		  catch (Exception $e)
		  {
			 throw $e;
		  }
    }
	
}
 
?>