<form name="system-config" id="system-config" method="post" accept-charset="UTF-8" enctype="multipart/form-data">
	<?php echo RequestTokens::render_token_field();
	if ($multiple_groups) {
		?>
	<div id="config-tabs">
		<ul><?php
	foreach($system_settings as $group_name => $group_settings) {
		$setting_id = str_replace('_', '-', AkInflector::underscore($group_name));
		?><li><a href="#setting-<?php echo $setting_id; ?>"><?php echo $group_name; ?></a></li><?php
	}
	?>

		</ul>
	<?php
	}
	$last_group_name = '';
	$group_count = 0;
	foreach($system_settings as $group_name => $group_settings) {
		if ($group_name != $last_group_name && $multiple_groups) {
			$setting_id = str_replace('_', '-', AkInflector::underscore($group_name));
			if ($group_count > 0) {
				?></div><?php
			}
			?><div id="setting-<?php echo $setting_id; ?>"><?php
		}
		?><div class="system-settings-row <?php echo $Navigation->tiger_stripe('system-settings') ?>"><?php
		foreach ($group_settings as $index => $setting) {
			?><div class="system-settings-column">
				<?php
				if (substr($setting->value_type(),0,6) == 'slider') {
					?><div style="padding: 5px"><?php
				} else {
					?><p><?php
				}
				if ($setting->friendly_name()) {
					$field_label = $setting->friendly_name();
				} else {
					$field_label = $setting->constant_name();
				}
				print $SystemAdmin->render_config_field($setting->value_type(),$setting->id(),$field_label,$setting->value(),$SystemAdmin->field_is_valid('setting_'.$setting->id()),$setting->required());
				if ($setting->description()) {
					?><span class="instructions"><?php echo __($setting->description()); ?></span><?php
				}
				if (substr($setting->value_type(),0,6) == 'slider') {
					?></div><?php
				} else {
					?></p><?php
				}
				?></div><?php
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
	?></div>
	</div>
	<?php
	}
	?>
	<div class="controls"><a href="<?php echo $SystemAdmin->url() ?>"><?php echo __("Cancel") ?></a><input type="submit" class="SubmitButton" value="<?php echo __("Save Configuration") ?>"></div>
</form>
<script type="text/javascript">
	$(document).ready(function() {
		$('#config-tabs').tabs();
		$('#system-config').submit(function(){
			new Biscuit.Ajax.FormValidator('system-config');
			return false;
		});
	});
</script>
