<?php

/**
 * @package     Articles Good Search
 *
 * @copyright   Copyright (C) 2017 Joomcar extensions. All rights reserved.
 * @license     GNU General Public License version 2 or later.
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

$active = (array)JFactory::getApplication()->input->getWord("tag");
?>

<div class="gsearch-field-select tags">	
	<h3>
		<?php echo JText::_('MOD_AGS_FILTER_TYPE_TAG_CLOUD'); ?>
	</h3>
	<div class="tag-cloud<?php echo $module->id; ?>" style="max-width: 250px;">
		<?php foreach($tags as $tag) { ?>
			<a href="#" data-tag-id="<?php echo $tag->id; ?>" class="label label-primary<?php if(in_array($tag->id, $_REQUEST['tag'])) { ?> active<?php } ?>" style="display: inline-block; margin-bottom: 5px;">
				<?php echo $tag->title; ?>
			</a>
		<?php } ?>
	</div>
	<input type="hidden" class="tag-cloud<?php echo $module->id; ?>-value inputbox" name="tag[]" value="" />
	
	<style>
		.tag-cloud<?php echo $module->id; ?> a.active { background: #555; }
	</style>
	<script>
		jQuery(document).ready(function($) {
			$(".tag-cloud<?php echo $module->id; ?> a").on("click", function() {
				if($(this).hasClass("active")) {
					$(".tag-cloud<?php echo $module->id; ?>-value").val('');
				}
				else {
					$(".tag-cloud<?php echo $module->id; ?>-value").val($(this).data("tag-id"));
				}
				$(".tag-cloud<?php echo $module->id; ?> a").not($(this)).removeClass("active");
				$(this).toggleClass("active");
				submit_form_<?php echo $module->id; ?>();
				return false;
			});
		});
	</script>
</div>

