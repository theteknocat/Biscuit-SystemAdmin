<?php
$index = 0;
foreach ($menu_items as $menu_label => $items) {
	$fieldset_class = '';
	if (count($menu_items) > 1) {
		if ($index%2 == 0) {
			?><div class="config-menu-row"><?php
		}
		if ($index%2 == 0) {
			$fieldset_class = 'first';
		}
		?><div class="config-menu-column"><?php
	}
	?>
	<fieldset class="<?php echo $fieldset_class; ?>">
	<legend><?php echo __($menu_label) ?></legend>
		<ul>
		<?php
		foreach ($items as $label => $item_data) {
			$classname = '';
			if (is_string($item_data)) {
				$url = $item_data;
				$item_data = (array)$item_data;
			} else {
				$url = $item_data['url'];
				if (!empty($item_data['classname'])) {
					$classname = $item_data['classname'];
				}
				if (!empty($item_data['ui-icon'])) {
					$classname .= ' ui-button-text-icon-primary';
				}
			}
			$target = '';
			if (!empty($item_data['target'])) {
				$target = ' target="'.$item_data['target'].'"';
			}
			?><li><a class="ui-button <?php echo $classname; ?>" href="<?php echo $url ?>"<?php echo $target; ?>><?php
			if (!empty($item_data['ui-icon'])) {
				?><span class="ui-button-icon-primary ui-icon <?php echo $item_data['ui-icon']; ?>"></span>
				<span class="ui-button-text"><?php
			}
			echo __($label);
			if (!empty($item_data['ui-icon'])) {
				?></span><?php
			}
			?></a></li><?php
		}
		?>
		</ul>
	</fieldset><?php
	$index++;
	if (count($menu_items) > 1) {
		?></div><?php
		if ($index%2 == 0 || $index == count($menu_items)) {
			?></div><?php
		}
	}
}
if (count($menu_items) > 1) {
	?><div class="clearance"></div><?php
}
