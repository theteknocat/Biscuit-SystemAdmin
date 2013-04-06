<div class="debug-flip-buttons">
	<span class="debug-page-count"><?php echo $total_pages-$counter ?>&nbsp;of&nbsp;<?php echo $total_pages ?></span>
	<?php
	if ($counter < $total_pages-1) {
		?>
	<a class="debug-flip-button debug-prev-button" href="#<?php echo $debug_type ?>-content-container-<?php echo ($counter+1) ?>" id="debug-flip-button-<?php echo $debug_type ?>-<?php echo ($counter+1) ?>">Previous</a>
		<?php
	} else {
		?>
	<div class="debug-prev-button-placeholder">Previous</div>
		<?php
	}
	?>
	<?php
	if ($counter > 0) {
		?>
	<a class="debug-flip-button debug-next-button" href="#<?php echo $debug_type ?>-content-container-<?php echo ($counter-1) ?>" id="debug-flip-button-<?php echo $debug_type ?>-<?php echo ($counter-1) ?>">Next</a>
		<?php
	} else {
		?>
	<div class="debug-next-button-placeholder">Next</div>
		<?php
	}
	?>
</div>
<div class="debug-header-title">
	<?php echo $header_title ?>
</div>
