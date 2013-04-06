<form name="system-config" id="system-config" method="post" accept-charset="UTF-8">
	<?php echo RequestTokens::render_token_field();
	$last_group_name = '';
	?>
	<?php
	$group_count = 0;
	foreach($system_settings as $group_name => $group_settings) {
		if ($group_name != $last_group_name && $multiple_groups) {
			if ($group_count > 0) {
				?></fieldset><?php
			}
			?><fieldset><legend><?php echo __($group_name) ?></legend><?php
		}
		?><div class="system-settings-row <?php echo $Navigation->tiger_stripe('system-settings') ?>"><?php
		foreach ($group_settings as $index => $setting) {
			?><div class="system-settings-column"><p>
				<?php
				if ($setting->friendly_name()) {
					$field_label = $setting->friendly_name();
				} else {
					$field_label = $setting->constant_name();
				}
				print $SystemAdmin->render_config_field($setting->value_type(),$setting->id(),$field_label,$setting->value(),$SystemAdmin->field_is_valid('setting_'.$setting->id()),$setting->required());
				if ($setting->description()) {
					?><span class="instructions"><?php echo __($setting->description()); ?></span><?php
				}
				?></p></div><?php
			if (($index+1)%2 == 0 || ($index+1) == count($group_settings)) {
				?><div class="clearance"></div><?php
				if (($index+1) < count($group_settings)) {
					?></div><div class="system-settings-row <?php echo $Navigation->tiger_stripe('system-settings') ?>"><?php
				}
			}
		}
		?></div><?php
		$group_count++;
	}
	if ($multiple_groups) {
	?></fieldset>
	<?php
	}
	?>
	<div class="controls"><a href="<?php echo $SystemAdmin->url() ?>"><?php echo __("Cancel") ?></a><input type="submit" class="SubmitButton" value="<?php echo __("Save") ?>"></div>
</form>
<script type="text/javascript" charset="utf-8">
	$(document).ready(function() {
		$('#system-config').submit(function(){
			new Biscuit.Ajax.FormValidator('system-config');
			return false;
		});
	});
</script>
