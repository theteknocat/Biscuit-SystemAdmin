<fieldset class="active-theme">
	<legend><?php echo __('Active Theme'); ?></legend>
	<div class="theme-item">
		<div class="theme-image">
			<?php
			if (!empty($active_theme['screenshot_file'])) {
				?><img src="<?php echo $active_theme['screenshot_file']; ?>" width="280" alt="<?php echo __('Preview'); ?>"><?php
			}
			?>
		</div>
		<div class="theme-details">
			<h2><?php echo $active_theme['name'];
			if (!empty($active_theme['version'])) {
				echo ' <span class="small" style="margin: 0;">v'.$active_theme['version'].'</span>';
			}
			?></h2>
			<?php
			if (!empty($active_theme['author'])) {
				if (!empty($active_theme['author_link'])) {
					$author_name = '<a href="'.$active_theme['author_link'].'" target="_blank">'.$active_theme['author'].'</a>';
				} else {
					$author_name = $active_theme['author'];
				}
				?><p><strong><?php echo __('Author'); ?>:</strong> <?php echo $author_name; ?></p><?php
			}
			if (!empty($active_theme['description'])) {
				?><p><?php echo $active_theme['description']; ?></p><?php
			}
			?>
		</div>
	</div>
</fieldset>
<?php
if (!empty($themes)) {
?>
<fieldset class="other-themes">
	<legend><?php echo __('Other Available Themes') ?></legend>
	<?php
	$index = 1;
	foreach ($themes as $theme_dir => $theme_info) {
		?>
	<div class="theme-item">
		<div class="theme-item-content">
			<div class="theme-image">
				<?php
				if (!empty($theme_info['screenshot_file'])) {
					?><img src="<?php echo $theme_info['screenshot_file']; ?>" width="130" alt="<?php echo __('Preview'); ?>"><?php
				}
				?>
			</div>
			<div class="theme-details">
				<h3><?php echo $theme_info['name'];
				if (!empty($theme_info['version'])) {
					echo ' <span class="small" style="margin: 0;">v'.$theme_info['version'].'</span>';
				}
				?></h3>
				<?php
				if (!empty($theme_info['author'])) {
					if (!empty($theme_info['author_link'])) {
						$author_name = '<a href="'.$theme_info['author_link'].'" target="_blank">'.$theme_info['author'].'</a>';
					} else {
						$author_name = $theme_info['author'];
					}
					?><p><strong><?php echo __('Author'); ?>:</strong> <?php echo $author_name; ?></p><?php
				}
				?>
			</div>
			<div class="clearance"></div>
			<?php
			if (!empty($theme_info['description'])) {
				?><p><?php echo $theme_info['description']; ?></p><?php
			}
			?>
			<div class="controls"><a href="<?php echo $SystemAdmin->url('activate_theme'); ?>/<?php echo $theme_dir; ?>" class="activate-button"><?php echo __('Activate'); ?></a><a href="<?php echo STANDARD_URL; ?>?theme_name=<?php echo $theme_dir; ?>&amp;no_cache=1" class="preview-button" target="_blank"><?php echo __('Preview'); ?></a></div>
			<div class="clearance"></div>
		</div>
	</div>
		<?php
		if ($index%2 == 0 && $index != count($themes)) {
			?>
	<div class="clearance"></div>
			<?php
		}
		$index++;
	}
	?>
	<div class="clearance"></div>
</fieldset>
<script type="text/javascript">
	$(document).ready(function() {
		$('.activate-button').button({
			icons: {
				primary: 'ui-icon-power'
			}
		});
		$('.preview-button').button({
			icons: {
				primary: 'ui-icon-extlink'
			}
		});
	});
</script>
<?php
}
?>