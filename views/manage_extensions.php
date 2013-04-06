<fieldset>
	<legend>Current Extensions</legend>
	<table id="extension-list-headings" width="100%" cellpadding="0" cellspacing="0" border="0">
		<tr>
			<th style="width: 15%">Name</th>
			<th style="width: 55%">Description</th>
			<th style="width: 12%">Type</th>
			<th align="right" style="text-align: right; width: 18%">Action</th>
		</tr>
	</table>
	<div id="extension-list">
	<?php
	foreach ($extensions as $index => $extension) {
		$friendly_name = ucwords(AkInflector::humanize(AkInflector::underscore($extension->name())));
		$extra_classes = '';
		if (!$extension->installed()) {
			$extra_classes .= ' inactive';
		}
		?>
		<div class="<?php echo $Navigation->tiger_stripe('extensions-list'); ?> extension-list-item<?php echo $extra_classes; ?>">
			<div class="cell" style="width: 15%"><div class="cell-content"><?php echo $friendly_name; ?></div></div>
			<div class="cell" style="width: 55%"><div class="cell-content"><?php echo Crumbs::auto_paragraph($extension->description()); ?></div></div>
			<div class="cell" style="width: 12%"><div class="cell-content"><?php
			if ($extension->is_global()) {
				echo 'Global';
			} else {
				echo 'On Demand';
			}
			?></div></div>
			<div class="cell" style="text-align: right; width: 18%"><div class="cell-content"><?php
			if ($extension->name() != 'Navigation' && $extension->name() != 'BluePrintCss' && $extension->name() != 'HtmlPurify') {
				?><div class="controls"><?php
				if (!$extension->installed()) {
					?><a href="<?php echo $SystemAdmin->url('manage_extensions'); ?>?install_extension=<?php echo $extension->name(); ?>" data-extension-name="<?php echo $friendly_name; ?>" class="install-button">Activate</a><?php
				} else {
					?><a href="<?php echo $SystemAdmin->url('manage_extensions'); ?>?uninstall_extension=<?php echo $extension->name(); ?>" data-extension-name="<?php echo $friendly_name; ?>" class="uninstall-button">Deactivate</a><?php
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
	<h4><?php echo __('Global vs. On Demand') ?></h4>
	<p><?php echo __('Global extensions are available to use anywhere, any time. On-demand extensions must be explicitly initialized before they can be accessed. This is generally done be specifying them as a dependency by another module or extension. Extensions are often defined as on-demand only when they are only needed for special cases and would otherwise impose unnecessary overhead.'); ?></p>
</fieldset>
<fieldset>
	<legend>Getting More Extensions</legend>
	<p><?php echo __('Extensions are available in the subversion repository at:'); ?></p>
	<p>https://teknocat.svn.beanstalkapp.com/open_source/biscuit_framework/extensions</p>
	<p><?php echo sprintf(__('Within this folder, look for the folder for the Biscuit version you are using (you are currently running version %s).'), Biscuit::version()); ?> <?php echo __('If the extension you want is not available in that folder, keep going back to previous versions until you see one. The oldest versions are in the top level of the extensions folder and are most likely incompatible with the current version of Biscuit.'); ?></p>
	<h3><?php echo __('For Site Checked Out with Subversion'); ?></h3>
	<p><?php echo __('Add the desired extension repository location as an svn external to your site\'s "extensions" folder using the same folder name as the extension folder name in the repository. Then, come back to this page (refresh it if needed) and click the "Activate" button next to the new extension.'); ?></p>
	<h3><?php echo __('For Unversioned Site'); ?></h3>
	<p><?php echo __('Use subversion to export the extension folder into your site\'s "extensions" folder using the same folder name as the repository. Then, come back to this page (refresh it if needed) and click the "Activate" button next to the new extension.'); ?></p>
</fieldset>
<script type="text/javascript">
	$(document).ready(function() {
		$('.install-button, .uninstall-button').button({
			icons: {
				primary: 'ui-icon-power'
			}
		});
		$('.extension-list-item').mouseover(function() {
			$(this).addClass('hovered');
		});
		$('.extension-list-item').mouseout(function() {
			$(this).removeClass('hovered');
		});
		$('.install-button, .uninstall-button').click(function() {
			var target_url = $(this).attr('href');
			var extension_name = $(this).data('extension-name');
			var action_name = $(this).text();
			var message_text = '<h4>Are you sure you want to '+action_name.toLowerCase()+' the '+extension_name+' extension?</h4>';
			if (action_name.toLowerCase() == 'deactivate') {
				message_text += '<p style="color: red"><strong>WARNING:</strong> deactivation could cause errors or problems if the extensions is being used by any other modules or extensions, or in your theme.</p>';
			}
			Biscuit.Crumbs.Confirm(message_text, function() {
				top.location.href = target_url;
			}, action_name+' '+extension_name);
			return false;
		});
	});
</script>
