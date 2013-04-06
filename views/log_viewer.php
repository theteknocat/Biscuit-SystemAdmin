<?php
// Set PHP to english now because otherwise it will render the padding values for console messages per the locale which may not be right, eg. it'll use a comma instead of a period for French float values
setlocale(LC_ALL,'en_CA');
putenv('LANG=en_CA');
?>
<div id="debug-content">
	<div id="debug-nav">
		<ul>
			<li><a href="#debug-console"    class="debug-button selected" id="button-debug-console">Console Messages</a></li>
			<li><a href="#debug-error"      class="debug-button" id="button-debug-error">Errors</a></li>
			<li><a href="#debug-db-queries" class="debug-button" id="button-debug-db-queries">Database Queries</a></li>
			<li><a href="#debug-events"     class="debug-button" id="button-debug-events">Events</a></li>
			<li><a href="#debug-var-dump"   class="debug-button" id="button-debug-var-dump">Variables</a></li>
			<li id="delete-button"><a href="<?php echo $SystemAdmin->url('log_delete'); ?>" data-item-title="all the log files" class="delete-button">Delete All Logs</a></li>
		</ul>
	</div>
	<div id="debug-console" class="debug-content">
		<?php
		if (empty($console_log)) {
			?><h1>Console Log Empty</h1><?php
		} else {
			$counter = 0;
			foreach ($console_log as $request_marker => $messages) {
				$marker_info = Console::parse_log_marker($request_marker);
				$header_title = $SystemAdmin->compose_log_header_title($marker_info,"Message",count($messages));
				?>
		<div class="debug-header" id="debug-console-header-<?php echo $counter ?>">
			<?php echo $SystemAdmin->render_log_view_header('console',$header_title,$counter,count($console_log)); ?>
			<table width="100%">
				<tr>
					<th>Message</th>
				</tr>
			</table>
		</div>
		<div class="debug-content-container" id="debug-console-content-container-<?php echo $counter ?>">
			<?php
				if (empty($messages)) {
					?><h1>No Messages</h1><?php
				} else {
					foreach ($messages as $message) {
						$padding_left = 0;
						preg_match('/^(\s+).*/', $message, $matches);
						if (!empty($matches[1])) {
							$padding_left = 0.6*strlen($matches[1]);
						}
						?>
			<p style="padding-left: <?php echo $padding_left; ?>em" class="<?php echo $Navigation->tiger_stripe('console-log-list-'.$counter) ?>"><?php echo trim($message); ?></p>
						<?php
					}
				}
			?>
		</div>
				<?php
				$counter++;
			}
		}
		?>
	</div>
	<div id="debug-error" class="debug-content">
		<?php
		if (empty($error_log)) {
			?><h1>Error Log Empty</h1><?php
		} else {
			$counter = 0;
			foreach ($error_log as $request_marker => $errors) {
				$marker_info = Console::parse_log_marker($request_marker);
				$header_title = $SystemAdmin->compose_log_header_title($marker_info,"Error",count($errors));
				?>
		<div class="debug-header" id="debug-error-header-<?php echo $counter ?>">
			<?php echo $SystemAdmin->render_log_view_header('error',$header_title,$counter,count($error_log)) ?>
			<table width="100%">
				<tr>
					<th width="180">Type</th>
					<th>Message</th>
					<th width="350">File</th>
					<th width="50">Line</th>
				</tr>
			</table>
		</div>
		<div class="debug-content-container" id="debug-error-content-container-<?php echo $counter ?>">
			<?php
				if (empty($errors)) {
					?><h1>No Errors</h1><?php
				} else {
			?>
			<table width="100%">
				<?php
				foreach ($errors as $index => $error_details) {
					?>
				<tr class="<?php echo $Navigation->tiger_stripe('error-log-list-'.$counter) ?>">
					<td width="180"><?php echo $error_details[0] ?></td>
					<td><?php echo $error_details[1] ?></td>
					<td width="350"><?php echo $error_details[2] ?></td>
					<td width="50"><?php echo $error_details[3] ?></td>
				</tr>
					<?php
				}
				?>
			</table>
			<?php
				}
			?>
		</div>
				<?php
				$counter++;
			}
		}
		?>
	</div>
	<div id="debug-db-queries" class="debug-content">
		<?php
		if (empty($query_log)) {
			?><h1>Query Log Empty</h1><?php
		} else {
			$counter = 0;
			foreach ($query_log as $request_marker => $queries) {
				$marker_info = Console::parse_log_marker($request_marker);
				$header_title = $SystemAdmin->compose_log_header_title($marker_info,"Query",count($queries));
				?>
		<div class="debug-header" id="debug-query-header-<?php echo $counter ?>">
			<?php echo $SystemAdmin->render_log_view_header('query',$header_title,$counter,count($query_log)); ?>
			<table width="100%">
				<tr>
					<th width="80">Method</th>
					<th width="350">Called By</th>
					<th>Query</th>
					<th>Parameters</th>
				</tr>
			</table>
		</div>
		<div class="debug-content-container" id="debug-query-content-container-<?php echo $counter ?>">
			<?php
				if (empty($queries)) {
					?><h1>No Queries</h1><?php
				} else {
			?>
			<table width="100%">
				<?php
				foreach ($queries as $index => $query_details) {
					?>
				<tr class="<?php echo $Navigation->tiger_stripe('query-log-list-'.$counter) ?>">
					<td width="80"><?php echo $query_details[0] ?></td>
					<td width="350"><?php echo $query_details[1] ?></td>
					<td><?php echo $query_details[2] ?></td>
					<td width="400"><pre style="width: 392px; overflow: auto;"><?php htmlentities(var_export(unserialize(stripslashes($query_details[3])))); ?></pre></td>
				</tr>
					<?php
				}
				?>
			</table>
			<?php
				}
			?>
		</div>
				<?php
				$counter++;
			}
		}
		?>
	</div>
	<div id="debug-events" class="debug-content">
		<?php
		if (empty($event_log)) {
			?><h1>Event Log Empty</h1><?php
		} else {
			$counter = 0;
			foreach ($event_log as $request_marker => $events) {
				$marker_info = Console::parse_log_marker($request_marker);
				$header_title = $SystemAdmin->compose_log_header_title($marker_info,"Event Response",count($events));
				?>
		<div class="debug-header" id="debug-event-header-<?php echo $counter ?>">
			<?php echo $SystemAdmin->render_log_view_header('event',$header_title,$counter,count($event_log)); ?>
			<table width="100%">
				<tr>
					<th width="240">Event Name</th>
					<th width="160">Observer</th>
					<th>Fired By</th>
				</tr>
			</table>
		</div>
		<div class="debug-content-container" id="debug-event-content-container-<?php echo $counter ?>">
			<?php
				if (empty($events)) {
					?><h1>No Events</h1><?php
				} else {
			?>
			<table width="100%">
				<?php
				foreach ($events as $index => $event_details) {
					?>
				<tr class="<?php echo $Navigation->tiger_stripe('event-log-list-'.$counter) ?>">
					<td width="240"><?php echo $event_details[0] ?></td>
					<td width="160"><?php echo $event_details[1] ?></td>
					<td><?php echo $event_details[2] ?></td>
				</tr>
					<?php
				}
				?>
			</table>
			<?php
				}
			?>
		</div>
				<?php
				$counter++;
			}
		}
		?>
	</div>
	<div id="debug-var-dump" class="debug-content">
		<?php
		if (empty($var_dump_log)) {
			?><h1>Variable Log Empty</h1><?php
		} else {
			$counter = 0;
			foreach ($var_dump_log as $request_marker => $var_dumps) {
				$marker_info = Console::parse_log_marker($request_marker);
				$header_title = $SystemAdmin->compose_log_header_title($marker_info,"Variable",count($var_dumps));
				?>
		<div class="debug-header" id="debug-var-dump-header-<?php echo $counter ?>">
			<?php echo $SystemAdmin->render_log_view_header('var-dump',$header_title,$counter,count($var_dump_log)); ?>
			<table width="100%">
				<tr>
					<th width="200">Dumped By</th>
					<th>Variable Name</th>
					<th width="700">Contents</th>
				</tr>
			</table>
		</div>
		<div class="debug-content-container" id="debug-var-dump-content-container-<?php echo $counter ?>">
			<?php
				if (empty($var_dumps)) {
					?><h1>No Variables</h1><?php
				} else {
			?>
			<table width="100%">
				<?php
				foreach ($var_dumps as $index => $var_details) {
					?>
				<tr class="<?php echo $Navigation->tiger_stripe('var-log-list-'.$counter) ?>">
					<td width="200"><?php echo $var_details[0] ?></td>
					<td><?php echo $var_details[1] ?></td>
					<td width="700"><pre style="width: 692px; overflow: auto;"><?php htmlentities(var_export($var_details[2])); ?></pre></td>
				</tr>
					<?php
				}
				?>
			</table>
			<?php
				}
			?>
		</div>
				<?php
				$counter++;
			}
		}
		?>
	</div>
</div>
