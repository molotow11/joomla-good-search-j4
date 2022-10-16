<?php

/**
 * @package     Articles Good Search
 *
 * @copyright   Copyright (C) 2017 Joomcar extensions. All rights reserved.
 * @license     GNU General Public License version 2 or later.
 */

defined('_JEXEC') or die;

$link = "index.php?option=com_sppagebuilder&view=page&id={$item->id}";
$existingMenu = JFactory::getDBO()->setQuery("SELECT * FROM #__menu WHERE `link` = '{$link}'")->loadObject();
$menu_params = new JRegistry($existingMenu->params);
$item->item_link = $existingMenu ? $existingMenu->path  : JRoute::_($link);
$input = JFactory::getApplication()->input;

if($existingMenu && $menu_params->get("menu-meta_description") != "") {
	$item->introtext = $menu_params->get("menu-meta_description");
}
elseif($item->og_description != "") {
	$item->introtext = $item->og_description;
}
else {
	//add sp text block as introtext
	if($input->get("keyword", "")) {
		$keyword = addslashes($input->get("keyword", ""));
		$pattern = '/(?:"text":")([^"]*'.$keyword.'[^"]*)/smix';
	}
	else {
		$pattern = '/(?:text":")([^"]*(.*?)[^"]*)/smix';
	}
	preg_match($pattern, $item->text, $matches);
	$text = $matches[1];
	$item->introtext = $text;	
}

if($model->module_params->text_limit) {
	preg_match('/(<img[^>]+>)/i', $item->introtext, $images_text);	
	$item->introtext = trim(strip_tags($item->introtext, '<h2><h3>'));
	if(extension_loaded('mbstring')) {
		$item->introtext = mb_strimwidth($item->introtext, 0, $model->module_params->text_limit, '...', 'utf-8');
	}
	else {
		$item->introtext = strlen($item->introtext) > $model->module_params->text_limit ? substr($item->introtext, 0, $model->module_params->text_limit) . '...' : $item->introtext;
	}
	if(count($images_text) && 
		($image_type == "text" || ($image_type == "" && !$ImageIntro))
	) {
		if(strpos($images_text[0], '://') === false) {
			$parts = explode('src="', $images_text[0]);
			$images_text[0] = $parts[0] . 'src="' . JURI::root() . $parts[1];
		}
		$item->introtext = $images_text[0] . $item->introtext;
	}
}

?>

<div class="<?php echo $item_type; ?> item<?php echo $item->featured ? ' featured' : ''; ?> <?php if($columns > 1 && ($items_counter % $columns == 0)) { echo 'unmarged'; } ?> <?php if($columns > 1) { echo 'span' . 12 / $columns; } ?>" itemprop="blogPost" itemscope itemtype="https://schema.org/BlogPosting">
	<h3 itemprop="name" class="item-title">
		<a href="<?php echo $item->item_link; ?>" itemprop="url">
			<?php echo $item->title; ?>
		</a>
	</h3>
	
	<?php echo $item->event->afterDisplayTitle; ?>
	<?php echo $item->event->beforeDisplayContent; ?>
	
	<?php if($model->module_params->show_introtext) { ?>
	<div class="item-body">
		<div class="introtext">
			<?php echo $item->introtext; ?>
		</div>
		<div style="clear: both;"></div>
	</div>
	<?php } ?>

	<?php if($model->module_params->show_readmore) { ?>
	<div class="item-readmore">
		<a class="btn btn-secondary" href="<?php echo $item->item_link; ?>"><?php echo JText::_('MOD_AGS_ITEM_READMORE'); ?></a>
	</div>
	<?php } ?>
	
	<?php echo $item->event->afterDisplayContent; ?>
	<div style="clear: both;"></div>
</div>
<?php if(($items_counter + 1) % $columns == 0) { ?>
<div style="clear: both;"></div>
<?php } ?>