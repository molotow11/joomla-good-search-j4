<?php 

/**
 * @package     Articles Good Search
 *
 * @copyright   Copyright (C) 2017 Joomcar extensions. All rights reserved.
 * @license     GNU General Public License version 2 or later.
 */

defined('_JEXEC') or die;

$moduleclass_sfx = $params->get('moduleclass_sfx', '');

?>
		<script type="text/javascript">
			function clearSearch_<?php echo $module->id; ?>() {
				$ = jQuery.noConflict();
				$("#GSearch<?php echo $module->id; ?>").find(".inputbox").each(function(k, field) {
					switch($(this)[0].tagName) {
						case "INPUT" :
							if($(this).attr("type") == "text") {
								$(this).val("");
								$(this).attr("data-value", "");
								$(this).attr("data-alt-value", "");
							}
							$(this).removeAttr('checked');
						break;
						case "SELECT" :
							$(this).find("option").each(function() {
								$(this).removeAttr("selected");
							});
							if (typeof $(this).selectpicker !== "undefined") { 
								$(this).selectpicker('refresh');
							}

							// Clear ChoicesJS
							if($(this)[0].ChoicesJS) {
								let select = $(this)[0].ChoicesJS.passedElement.element;
								select.value = '';
								$(this)[0].ChoicesJS.destroy();
								$(select)[0].ChoicesJS = new Choices(select);
							}
						break;
					}
				});
				
				//sliders reset
				if($("#GSearch<?php echo $module->id; ?>").find(".SliderField").length) {
					$("#GSearch<?php echo $module->id; ?>").find(".SliderField").each(function() {
						var slider_obj = $(this);
						var min = slider_obj.parent().find(".slider-handle").attr("aria-valuemin");
						var max = slider_obj.parent().find(".slider-handle").attr("aria-valuemax");
						slider_obj.parents('.slider-wrapper').find(".amount input").val(min + ' - ' + max);
					});
				}
				
				//acounter clean
				$("#GSearch<?php echo $module->id; ?> div.acounter .data").hide();
				
				//dynobox
				<?php if($params->get("dynobox")) { ?>
				initbox = $("#GSearch<?php echo $module->id; ?>").find("select:eq(0)")[0];
				init_selected_count = 1;
				dynobox<?php echo $module->id; ?>(initbox);
				<?php } ?>
				
				//autosubmit
				submit_form_<?php echo $module->id; ?>();
			}
		</script>	

		<input type="button" value="<?php echo JText::_('MOD_AGS_BUTTON_CLEAR'); ?>" class="btn btn-warning button reset <?php echo $moduleclass_sfx; ?>" onClick="clearSearch_<?php echo $module->id; ?>()" />