<?php

/**
 * @package     Articles Good Search
 *
 * @copyright   Copyright (C) 2017 Joomcar extensions. All rights reserved.
 * @license     GNU General Public License version 2 or later.
 */

defined('_JEXEC') or die;

use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;

?>

<style>
	.table-gsearch .itemlist > div {
		display: grid;
		align-items: center;
		grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
		border-bottom: 1px solid #ccc;
	}
	.table-gsearch .itemlist > div.thead { background: #eee; }
	.table-gsearch .itemlist > div > div { padding: 10px 15px; min-height: 18px; }
	
	.table-gsearch .thead > div { cursor: pointer; }
	.table-gsearch .thead > div.active { text-decoration: underline; }
	.table-gsearch .ordering.direction.asc { transform: rotate(180deg); margin-top: 5px; }
	.table-gsearch .ordering.direction {
		float: right;
		margin: 4px 0 0 6px;
		width: 0px;
		height: 0px;
		display: block;
		border-left: 7px solid transparent;
		border-right: 7px solid transparent;
		border-top: 14px solid #999;
		cursor: pointer;
	}
	.table-gsearch .ordering.direction:after {
		content: " ";
		width: 0px;
		height: 0px;
		margin: -13px 0 0 -5px;
		display: block;
		border-left: 5px solid transparent;
		border-right: 5px solid transparent;
		border-top: 12px solid #ccc;
	}
</style>

<script>
	jQuery(document).ready(function($) {
		var sort_active = "<?php echo JFactory::getApplication()->input->getWord("orderby"); ?>"; 
		var direction_active = "<?php echo JFactory::getApplication()->input->getWord("orderto", $model->module_params->ordering_default_dir); ?>";
		
		$(".table-gsearch .thead > div").each(function() {
			var sortval = $(this).data("val");
			if(sortval == sort_active) {
				$(this).addClass("active");
				$(this).append("<span class='ordering direction "+ direction_active +"'></span>");
			}
		});
		
		$("body").on("click", ".table-gsearch .thead > div", function() {
			var query = window.location.search;
			var sortval = $(this).data("val");
			if(query.indexOf("orderby") != -1) {
				var current = query.split("orderby=")[1];
				current = current.split("&")[0];
				if(current == sortval) {
					changeOrderingDirection();
					return false;
				}
				else {
					query = query.replace("orderby=" + current, "orderby=" + sortval);
				}
			}
			else {
				query += "&orderby=" + sortval;
			}
			window.location.search = query;
		});
		
		function changeOrderingDirection() {
			var query = window.location.search;
			if(query.indexOf("orderto") != -1) {
				var current = query.split("orderto=")[1];
				current = current.split("&")[0];
				if(current == "asc") {
					query = query.replace("orderto=" + current, "orderto=desc");
				}
				else {
					query = query.replace("orderto=" + current, "orderto=asc");
				}
			}
			else {
				query += "&orderto=asc";
			}
			window.location.search = query;
		}
	});
</script>

<div id="gsearch-results" class="table table-gsearch gsearch-results-<?php echo $model->module_id; ?>" itemscope itemtype="https://schema.org/Blog">
	<div class="page-header" style="display: inline-block;">
		<h3>
			<?php
				if(!$model->module_params->resultf) {
					$model->module_params->resultf = JText::_("MOD_AGS_RESULT_PHRASE_DEFAULT");
				}
				echo (count($items) ? JText::_($model->module_params->resultf) . " ({$model->total_items})" : JText::_($model->module_params->noresult)); 
			?>
		</h3>
	</div>
	
	<?php if(count($items)) { ?>
	<div class="gsearch-toolbox" style="float: right; margin-top: 12px;">
		<?php if($model->module_params->layout_show) { ?>
		<div class="gsearch-layout">
		<?php require(dirname(__FILE__). '/gsearch_layout.php'); ?>
		</div>
		<?php } ?>
		<?php if($model->module_params->ordering_show) { ?>
		<div class="gsearch-sorting">
		<?php require(dirname(__FILE__). '/gsearch_sorting.php'); ?>
		</div>
		<?php } ?>
		<div style="clear: both;"></div>
	</div>
	<?php } ?>
	
	<div style="clear: both;"></div>
	
	<div class="itemlist<?php if($columns > 1) { echo ' row-fluid table'; } ?>">
		<div class="thead">
			<?php
				$customSorting = explode("\r\n", $model->module_params->ordering_fields);
				$sortingFields = array();
				foreach($customSorting as $field) {
					if($field == "") continue;
					$tmp = new stdClass;
					$aField = explode(":", $field);
					$tmp->id = $aField[1];
					$flt = explode('{', $field, 2);
					if(!empty($flt[1]) && $flt[1] != '') {
						$extra_params = json_decode('{' . $flt[1]);
						$tmp->name = $extra_params->name;
					}
					if(count($aField) == 1) { // generic fields
						$sortingFields[] = $field;
					}
					else { // custom fields
						$sortingFields[] = $tmp;
					}
				}
			?>
			<?php foreach($sortingFields as $field) { ?>
				<?php if($field->id) { ?>
				<div class="custom-field field<?php echo $field->id; ?>" data-val="field<?php echo $field->id; ?>"><?php echo JText::_($field->name); ?></div>
				<?php } else {
					switch($field) {
						case "title" : ?>
							<div class="title" data-val="title"><?php echo JText::_("MOD_AGS_SORTING_TITLE"); ?></div>
						<?php break;
						case "category" : ?>
							<div class="category" data-val="category"><?php echo JText::_("MOD_AGS_SORTING_CATEGORY"); ?></div>
						<?php break;
						case "created" : ?>
							<div class="date" data-val="created"><?php echo JText::_("MOD_AGS_SORTING_DATE"); ?></div>
						<?php break;
					}
				}
			} 
			?>
		</div>
		<?php foreach($items as $items_counter=>$item) {		
				$item->slug = $item->alias ? ($item->id . ':' . $item->alias) : $item->id;
				$item->parent_slug = ($item->parent_alias) ? ($item->parent_id . ':' . $item->parent_alias) : $item->parent_id;
				if ($item->parent_alias == 'root') {
					$item->parent_slug = null;
				}
				$item->catslug = $item->category_alias ? ($item->catid . ':' . $item->category_alias) : $item->catid;
		
				$item->item_link = JRoute::_(ContentHelperRoute::getArticleRoute($item->slug, $item->catid, $item->language));
				//sp pages links  fix
				if($item->gsearch_item_type == 'sppagebuilder') {
					$link = "index.php?option=com_sppagebuilder&view=page&id={$item->id}";
					$existingMenu = JFactory::getDBO()->setQuery("SELECT * FROM #__menu WHERE `link` = '{$link}'")->loadObject();
					$item->item_link = $existingMenu ? $existingMenu->path  : JRoute::_($link);

					if($existingMenu) {
						$params = new JRegistry($existingMenu->params);
						$item->introtext = $params->get("menu-meta_description");
					}
					else {
						$item->introtext = $item->og_description;
					}					
				}
		?>
		<div class="table-item">
			<?php 
			$aFields = FieldsHelper::getFields('com_content.article', $item, true);
			$tmp = new stdClass;
			foreach($sortingFields as $field) {
				if($field->id) {
					foreach($aFields as $aField) {
						if($field->id == $aField->id) { ?>
							<div class="custom-field field<?php echo $aField->id; ?>"><?php echo $aField->value; ?></div>
					<?php }
					}
				} else {
					switch($field) {
						case "title" : ?>
							<div class="title">
								<a href="<?php echo $item->item_link; ?>" itemprop="url">
									<?php echo $item->title; ?>
								</a>
							</div>
						<?php break;
						case "category" : ?>
							<div class="category">
								<?php foreach($model->getItemCategories($item) as $category) { ?>
								<a href="<?php echo $category->link; ?>">
									<span itemprop="genre">
										<?php echo $category->title; ?>
									</span>
								</a>				
								<?php } ?>
							</div>
						<?php break;
						case "created" : ?>
							<div class="date">
								<time datetime="<?php echo $item->created; ?>" itemprop="dateCreated">
									<?php 
										setlocale(LC_ALL, JFactory::getLanguage()->getLocale());
										$date_format = explode("::", $model->module_params_native->get('date_format', '%e %b %Y::d M yyyy'))[0];
										if(strpos(PHP_OS, 'WIN') !== false) {
											$date_format = str_replace("%e", "%#d", $date_format);
										}
										$date = strftime($date_format, strtotime($item->created));
										if(function_exists("mb_convert_case")) {
											$date = mb_convert_case($date, MB_CASE_TITLE, 'UTF-8');
										}
										echo $date;
									?>		
								</time>
							</div>
						<?php break;
					}
				}
			} ?>
		</div>
		<?php } ?>
	</div>
	
	<div style="clear: both;"></div>
	<?php require(dirname(__FILE__). '/gsearch_paging.php'); ?>
</div>