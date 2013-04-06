<!DOCTYPE html>
<html lang="<?php echo $lang ?>">
	<head>
		<?php print $header_tags; ?>

		<?php print $header_includes; ?>

	</head>
	<body id="<?php echo $body_id ?>" class="locale-<?php echo $locale ?>">
		<?php print $page_content; ?>
	</body>
	<?php
	print $footer;
	?>
</html>
