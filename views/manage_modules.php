<fieldset>
	<legend>Current Modules</legend>
	<table id="module-list-headings" width="100%" cellpadding="0" cellspacing="0" border="0">
		<tr>
			<th style="width: 5%;">No.</th>
			<th style="width: 15%">Name</th>
			<th style="width: 62%">Description</th>
			<th align="right" style="text-align: right; width: 18%">Action</th>
		</tr>
	</table>
	<div id="module-list">
	<?php
	foreach ($modules as $index => $module) {
		$friendly_name = ucwords(AkInflector::humanize(AkInflector::underscore($module->name())));
		$extra_classes = '';
		if (!$module->installed()) {
			$extra_classes .= ' inactive';
		}
		?>
		<div id="list-module_<?php echo $module->id(); ?>" class="<?php echo $Navigation->tiger_stripe('modules-list'); ?> module-list-item<?php echo $extra_classes; ?>">
			<div class="cell sort-num" style="width: 5%"><div class="cell-content"><?php echo ($index+1); ?></div></div>
			<div class="cell" style="width: 15%"><div class="cell-content"><?php echo $friendly_name; ?></div></div>
			<div class="cell" style="width: 62%"><div class="cell-content"><?php echo Crumbs::auto_paragraph($module->description()); ?></div></div>
			<div class="cell" style="text-align: right; width: 18%"><div class="cell-content"><?php
			if ($module->name() != 'Authenticator' && $module->name() != 'SystemAdmin') {
				?><div class="controls"><?php
				if (!$module->installed()) {
					?><a href="<?php echo $SystemAdmin->url('manage_modules'); ?>?install_module=<?php echo $module->name(); ?>" data-module-name="<?php echo $friendly_name; ?>" class="install-button">Activate</a><?php
				} else {
					?><a href="<?php echo $SystemAdmin->url('manage_modules'); ?>?uninstall_module=<?php echo $module->name(); ?>" data-module-name="<?php echo $friendly_name; ?>" class="uninstall-button">Deactivate</a><?php
				}
				?></div><?php
			} else {
				?><span style="color: red; font-style: italic; float: right;">Required</span><?php
			}
			?></div></div>
			<div class="clearance"></div>
		</div>
		<?php
	}
	?>
	</div>
	<h4><?php echo __('About Module Priority'); ?></h4>
	<p><?php echo __("The module order generally only affects modules that render content and determines the order in which the content is rendered within a page. For example, if the Page Content module is sorted before the News And Events module, and both are installed on the news page, the static content defined by the Page Content module would be rendered above the list of news items produced by the News and Events module."); ?></p>
	<p><?php echo __("If there is some other reason why any given module must run in a particular order it should indicate that in it's description. Note that the Authenticator module will always run first regardless of it's priority in the list above."); ?></p>
</fieldset>
<fieldset>
	<legend>Getting More Modules</legend>
	<p><?php echo __('Modules are available in the subversion repository at:'); ?></p>
	<p>https://teknocat.svn.beanstalkapp.com/open_source/biscuit_framework/modules</p>
	<p><?php echo sprintf(__('Within this folder, look for the folder for the Biscuit version you are using (you are currently running version %s).'), Biscuit::version()); ?> <?php echo __('If the module you want is not available in that folder, keep going back to previous versions until you see one. The oldest versions are in the top level of the modules folder and are most likely incompatible with the current version of Biscuit.'); ?></p>
	<h3><?php echo __('For Site Checked Out with Subversion'); ?></h3>
	<p><?php echo __('Add the desired module repository location as an svn external to your site\'s "modules" folder using the same folder name as the module folder name in the repository. Then, come back to this page (refresh it if needed) and click the "Activate" button next to the new module.'); ?></p>
	<h3><?php echo __('For Unversioned Site'); ?></h3>
	<p><?php echo __('Use subversion to export the module folder into your site\'s "modules" folder using the same folder name as the repository. Then, come back to this page (refresh it if needed) and click the "Activate" button next to the new module.'); ?></p>
</fieldset>
<script type="text/javascript">
	<?php
	$token_info = RequestTokens::get();
	?>
	var sortable_request_token = '<?php echo $token_info['token']; ?>';
	var sortable_token_form_id = '<?php echo $token_info['form_id']; ?>';
	$(document).ready(function() {
		$('.install-button, .uninstall-button').button({
			icons: {
				primary: 'ui-icon-power'
			}
		});
		$('.module-list-item').mouseover(function() {
			$(this).addClass('hovered');
		});
		$('.module-list-item').mouseout(function() {
			$(this).removeClass('hovered');
		});
		$('.install-button, .uninstall-button').click(function() {
			var target_url = $(this).attr('href');
			var module_name = $(this).data('module-name');
			var action_name = $(this).text();
			var message_text = '<h4>Are you sure you want to '+action_name.toLowerCase()+' the '+module_name+' module?</h4>';
			if (action_name.toLowerCase() == 'deactivate') {
				message_text += '<p style="color: red"><strong>WARNING:</strong> This action will irreversibly remove database tables and/or content used by this module, if any.</p>';
			}
			Biscuit.Crumbs.Confirm(message_text, function() {
				top.location.href = target_url;
			}, action_name+' '+module_name);
			return false;
		});
		var sortable_options = {
			action: 'resort_module',
			array_name: 'module_sort',
			axis: 'y',
			onUpdate: function() {
				Biscuit.Crumbs.ShowCoverThrobber('module-list', '<?php echo __('Saving...') ?>');
				var curr_num = 1;
				$('#module-list .module-list-item').each(function() {
					$(this).removeClass('stripe-even');
					$(this).removeClass('stripe-odd');
					if (curr_num%2 == 0) {
						$(this).addClass('stripe-even');
					} else {
						$(this).addClass('stripe-odd');
					}
					$(this).children('.sort-num').children('.cell-content').text(curr_num);
					curr_num++;
				});
			},
			onFinish: function() {
				Biscuit.Crumbs.HideCoverThrobber('module-list');
			}
		}
		Biscuit.Crumbs.Sortable.create('#module-list','<?php echo $SystemAdmin->url(); ?>',sortable_options);
		$('#module-list').sortable().disableSelection();
	});
</script>
