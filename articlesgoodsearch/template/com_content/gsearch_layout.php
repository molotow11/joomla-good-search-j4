<?php

/**
 * @package     Articles Good Search
 *
 * @copyright   Copyright (C) 2017 Joomcar extensions. All rights reserved.
 * @license     GNU General Public License version 2 or later.
 */

defined('_JEXEC') or die;

$uri = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

$active = JFactory::getApplication()->input->getWord("search_layout");
if(JFactory::getApplication()->input->getWord("search_layout", $model->module_params->results_template) == "table") {
	$active = "table";
}

?>

<style>
	.gsearch-toolbox > div { float: right; }
	.gsearch-layout { margin-left: 10px; }
	.gsearch-layout a { display: inline-block; width: 22px; height: 22px; margin-top: 4px;
		background: url(<?php echo JURI::root(); ?>plugins/system/articlesgoodsearch/template/com_content/layout.png) no-repeat;
		background-position: top left;
		background-size: cover;
	}
	.gsearch-layout a.columns, .gsearch-layout a.table { background-position: top right; width: 21px; }
	.gsearch-layout a.list { margin: 0 5px 0 15px; }
	.gsearch-layout a.active { opacity: 0.6; }
	.gsearch-layout a span { display: block; text-indent: -10000px; overflow: hidden; }
</style>

<a href="#" data-href="search_layout=list" class="list <?php echo $active == 'list' ? ' active' : ''; ?>">
	<span><?php echo JText::_("List"); ?></span>
</a>
<?php if($model->module_params->results_template == "table") { ?>
<a href="#" data-href="search_layout=table" class="table <?php echo $active == 'table' ? ' active' : ''; ?>">
	<span><?php echo JText::_("Table"); ?></span>
</a>
<?php } else { ?>
<a href="#" data-href="search_layout=columns&columned=2" class="columns <?php echo $active == 'columns' ? ' active' : ''; ?>">
	<span><?php echo JText::_("Columns"); ?></span>
</a>
<?php } ?>

<script>
	jQuery(document).ready(function($) {
		$(".gsearch-layout a").on("click", function() {
			if($(this).hasClass("active")) return false;
			var uri = "<?php echo $uri; ?>";
			uri = uri.replace(/(\?|\&)search_layout=.[^?&]*/gmi, "");
			uri = uri.replace(/(\?|\&)columned=.[^?&]*/gmi, "");
			uri = uri.replace(/[?&]$/gmi, "");
			uri = uri.replace("&search_mode=raw", "");
			if(uri.indexOf('?') === -1) {
				uri += "?";
			}
			else {
				uri += "&";
			}
			uri += $(this).data("href");
			window.location.href = uri;
			return false;
		});
	});
</script>