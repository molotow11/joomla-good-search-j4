<?php

/**
 * @package     Articles Good Search
 *
 * @copyright   Copyright (C) 2017 Joomcar extensions. All rights reserved.
 * @license     GNU General Public License version 2 or later.
 */

defined('_JEXEC') or die;

?>
	<style>
		.pagination.ags { text-align: center; float: none !important; width: 100%; margin: 40px 0 0 0; }
		.pagination.ags ul { display: inline-block !important; margin: 0 auto !important; float: none !important; }
		.pagination.ags ul > li { display: inline-block !important; }
	</style>
	
	<div class="pagination ags">
		<?php 
			$pagination = $model->getPagination();
			$PagesLinks = $pagination->getPagesLinks();
			$PagesLinks = preg_replace('/&amp;limitstart=0/', '', $PagesLinks);
			$PagesLinks = preg_replace('/&amp;page-start=.[0-9]*/', '', $PagesLinks);
			$PagesLinks = preg_replace('/&amp;start=/', '&amp;page-start=', $PagesLinks);
			$PagesLinks = preg_replace('/&amp;limitstart=/', '&amp;page-start=', $PagesLinks);
			$PagesLinks = preg_replace('/\?limitstart=0/', '', $PagesLinks);
			$PagesLinks = preg_replace('/\?page-start=.[0-9]*/', '', $PagesLinks);
			$PagesLinks = preg_replace('/\?start=/', '?page-start=', $PagesLinks);
			if(strpos($PagesLinks, "?") === false) {
				$PagesLinks = preg_replace('/&amp;page-start=/', '?page-start=', $PagesLinks);
			}			
			if(strpos($PagesLinks, "page-start") === false) { // sh404sef fix
				$PagesLinks = preg_replace_callback(
					'/(title="([^"]*)"[^>]*gsearch=1)/smix', 
					function($matches) use($model) {
						if((int)$matches[2] != 0) { // is page number
							return $matches[1] . '&page-start=' . ($matches[2] - 1) * $model->limit;
						}
						else if($matches[2] == "Prev") {
							return $matches[1] . '&page-start=' . ($model->input->get("page-start") - $model->limit);
						}
						else if($matches[2] == "Next") {
							return $matches[1] . '&page-start=' . ($model->input->get("page-start") + $model->limit);
						}
						else if($matches[2] == "End") {
							return $matches[1] . '&page-start=' . ($model->total_items - 1);
						}
						else {
							return $matches[0];
						}
					}, 
					$PagesLinks
				);
			}
			echo $PagesLinks; 
		?>
		<div style="clear: both;"></div>
		<?php echo $pagination->getPagesCounter(); ?>
	</div>