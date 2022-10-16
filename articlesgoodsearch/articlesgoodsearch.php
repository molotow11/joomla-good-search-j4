<?php

/**
 * @package     Articles Good Search
 *
 * @copyright   Copyright (C) 2017 Joomcar extensions. All rights reserved.
 * @license     GNU General Public License version 2 or later.
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Plugin\CMSPlugin;

class plgSystemArticlesGoodSearch extends CMSPlugin {

	function onAfterDispatch() {
		if(isset($_REQUEST['K2ContentBuilder'])) return;
	
		$init_parameter = JFactory::getApplication()->input->getInt('gsearch');

		if($init_parameter) {
			error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED & ~E_STRICT);
			
			$doc = JFactory::getDocument();
			
			$search_type = JFactory::getApplication()->input->getWord("search_type", "com_content");
			$format = JFactory::getApplication()->input->getWord("search_mode", "html");
			switch($search_type) {
				case "com_content" :
					require_once(dirname(__FILE__)."/view/com_content/view.{$format}.php");
					$view = new ArticlesViewGoodSearch;
					$template = $view->display($search_type);
				break;
				case "search_stats" :
					switch($format) {
						case "save" :
							require_once(JPATH_SITE . "/plugins/system/articlesgoodsearch/models/com_content/model.php");
							$model = new ArticlesModelGoodSearch;
							$model->saveSearchStats();
							exit;
						break;
						case "list" :
							$this->check_logged_admin();
							ob_start();
								require_once(dirname(__FILE__)."/template/search_stats/list.php");
								$template = ob_get_contents();
							ob_end_clean();
						break;
						case "keyword" :
							$this->check_logged_admin();
							ob_start();
								require_once(dirname(__FILE__)."/template/search_stats/keyword.php");
								$template = ob_get_contents();
							ob_end_clean();
						break;
						case "delete" :
							$this->check_logged_admin();
							$id = JFactory::getApplication()->input->getInt("id");
							$query = "DELETE FROM #__content_search_stats WHERE id = {$id}";
							@JFactory::getDBO()->setQuery($query)->query();
							$query = "DELETE FROM #__content_search_stats_users WHERE keyword_id = {$id}";
							@JFactory::getDBO()->setQuery($query)->query();
							ob_start();
								require_once(dirname(__FILE__)."/template/search_stats/deleted.php");
								$deleted = ob_get_contents();
							ob_end_clean();
							echo $deleted;
							exit; //raw output for ajax
						break;
						case "reset" :
							$this->check_logged_admin();
							$id = JFactory::getApplication()->input->getInt("id");
							$query = "TRUNCATE TABLE #__content_search_stats";
							@JFactory::getDBO()->setQuery($query)->query();							
							$query = "TRUNCATE TABLE #__content_search_stats_users";
							@JFactory::getDBO()->setQuery($query)->query();
							echo JText::_("Done.");
							exit; //raw output for ajax
						break;
					}
				break;
			}
			if($_GET['raw']) {
				echo $template; 
				exit;
			}
			else {
				$doc->setBuffer($template, "component");
			}
		}
	}	
	
	function onAfterRoute() {
		$app = JFactory::getApplication();
		$init_parameter = JFactory::getApplication()->input->getWord('gsearch');
		if($init_parameter) {
			if($app->isClient('administrator')) return;
			JFactory::getApplication()->input->set("option", "com_content");
			JFactory::getApplication()->input->set("view", "featured");
			//can be enabled for increase a speed
			//JFactory::getApplication()->input->set("option", "com_contact"); //false code for disable standard component output
		}
	}
	
	function check_logged_admin() {
		$user = JFactory::getUser();
		if(!in_array(7, $user->groups) && !in_array(8, $user->groups)) {
			echo JText::_("Restricted only for admins <br /> Try to log in first"); 
			exit;
		}
	}
}

?>