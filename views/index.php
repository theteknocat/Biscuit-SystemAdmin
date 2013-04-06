<?php
foreach ($menu_items as $menu_label => $items) {
	?>
	<fieldset>
	<legend><?php echo __($menu_label) ?></legend>
		<ul>
		<?php
		foreach ($items as $label => $url) {
			?><li><a href="<?php echo $url ?>"><?php echo __($label) ?></a></li><?php
		}
		?>
		</ul>
	</fieldset><?php
}
