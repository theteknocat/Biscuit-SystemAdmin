<?php
/**
 * A module for dealing with system administration functions
 *
 * @package Modules
 * @subpackage SystemAdmin
 * @author Peter Epp
 * @version 1.5 $Id: controller.php 14782 2013-01-21 17:15:26Z teknocat $
 */
class SystemAdmin extends AbstractModuleController {
	/**
	 * Models used by this module
	 *
	 * @var string
	 */
	protected $_models = array(
		'Permission' => 'Permission',
		'SystemSettings' => 'SystemSettings',
		'AccessLevel' => 'AccessLevel',
		'Module' => 'Module',
		'Extension' => 'Extension'
	);
	/**
	 * List of system settings that may not be modified by this module
	 *
	 * @var array
	 */
	private $_disallowed_system_settings = array('USE_PWD_HASH', 'SITE_THEME');
	/**
	 * Array of items for the administration menu
	 *
	 * @var array
	 */
	private $_admin_menu_items = array();
	/**
	 * Place to cache system settings
	 *
	 * @var string
	 */
	private $_system_settings_cache = array();
	/**
	 * Set log_viewer action as non-cacheable
	 *
	 * @var string
	 */
	protected $_uncacheable_actions = array('log_viewer');
	/**
	 * List of filenames of all the log files used by this module
	 *
	 * @var string
	 */
	private $_log_files = array('console_log','error_log','query_log','event_log','var_dump_log');
	/**
	 * Provide a menu of system administration functions. This basically does the exact same thing as the navigation admin menu - using the same event
	 * to trigger modules to provide their admin menu items.
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function action_index() {
		self::ensure_other_modules_are_installed();
		Event::fire('build_admin_menu',$this);
		ksort($this->_admin_menu_items);
		$this->register_css(array('filename' => 'system-admin.css', 'media' => 'screen'));
		$this->set_view_var('menu_items',$this->_admin_menu_items);
		$this->render();
	}
	/**
	 * Provide theme browse/set options
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function action_appearance() {
		$this->title('Appearance');
		$this->register_css(array('filename' => 'system-admin.css', 'media' => 'screen'));
		$fw_themes = FindFiles::ls('/framework/themes', array('include_directories' => true, 'include_files' => false, 'follow_symlinks' => false));
		$site_themes = FindFiles::ls('/themes', array('include_directories' => true, 'include_files' => false, 'follow_symlinks' => false));
		if (!empty($site_themes)) {
			// Get a list of all themes without duplicates:
			$all_themes = array_unique(array_merge($fw_themes, $site_themes));
		} else {
			$all_themes = $fw_themes;
		}
		$themes = array();
		if (defined('SITE_THEME') && SITE_THEME != '') {
			$active_theme = SITE_THEME;
		} else {
			$active_theme = 'default';
			// In same cases when using "default", as with the stock Biscuit theme setup, the "default" folder may be a symbolink link point to the real theme folder.
			// If that's the case, we want to ensure that it displays as the active theme and the real theme does not also get displayed under "Other Available Themes".
			// To that end, we determine and set the real directory name of the default theme so when looping through all the other themes it can do some logic to ensure the
			// desired result.
			$default_theme_path = Crumbs::file_exists_in_load_path('themes/'.$active_theme);
			$default_theme_path_bits = explode('/', $default_theme_path);
			$default_theme_dir = end($default_theme_path_bits);
		}
		foreach ($all_themes as $theme_dir) {
			$theme_path = Crumbs::file_exists_in_load_path('themes/'.$theme_dir,SITE_ROOT_RELATIVE);
			$theme_info_file = SITE_ROOT.$theme_path.'/theme.info';
			if (file_exists($theme_info_file)) {
				$theme_info = parse_ini_file($theme_info_file);
			} else {
				$theme_info = array(
					'name' => ucwords(AkInflector::humanize($theme_dir)),
					'author' => 'Unknown',
					'description' => 'No description.'
				);
			}
			if (!empty($theme_info['hidden'])) {
				// Skip a theme that is explicitly set to be hidden from the user interface. Use case would be a special theme meant to work with a specific module
				// that gets activated by the module that uses it and should not be active site-wide
				continue;
			}
			if (file_exists(SITE_ROOT.$theme_path.'/screenshot.png')) {
				$theme_info['screenshot_file'] = $theme_path.'/screenshot.png';
			} else if (file_exists(SITE_ROOT.$theme_path.'/screenshot.jpg')) {
				$theme_info['screenshot_file'] = $theme_path.'/screenshot.jpg';
			} else if (file_exists(SITE_ROOT.$theme_path.'/screenshot.gif')) {
				$theme_info['screenshot_file'] = $theme_path.'/screenshot.gif';
			} else {
				$theme_info['screenshot_file'] = '/framework/modules/system_admin/images/theme-no-preview.gif';
			}
			if ($theme_dir == $active_theme || ($active_theme == 'default' && !empty($default_theme_dir) && $default_theme_dir != 'default' && $default_theme_dir == $theme_dir)) {
				// The second part of this IF statement (after the OR operator) is what makes sure this theme is displayed as active in the case where 'default' is
				// a symlink pointing to it, rather than it showing up under "Other Available Themes"
				$active_theme_info = $theme_info;
			} else {
				$themes[$theme_dir] = $theme_info;
			}
		}
		$this->set_view_var('active_theme', $active_theme_info);
		$this->set_view_var('themes',$themes);
		$this->render();
	}
	/**
	 * Activate a theme
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function action_activate_theme() {
		$theme_name = $this->params['theme_name'];
		$theme_error = false;
		if (empty($theme_name)) {
			Session::flash('user_error', __('No valid theme name supplied'));
			$theme_error = true;
		} else {
			$theme_path = Crumbs::file_exists_in_load_path('themes/'.$this->params['theme_name']);
			if (empty($theme_path)) {
				Session::flash('user_error', sprintf(__('Invalid theme: %s'), $theme_name));
				$theme_error = true;
			}
		}
		if (!$theme_error) {
			$theme_setting = $this->SystemSettings->find_by('constant_name', 'SITE_THEME');
			if (!$theme_setting) {
				$theme_setting = $this->SystemSettings->create(array(
					'constant_name' => 'SITE_THEME',
					'friendly_name' => 'Site Theme',
					'description'   => 'Site-wide default theme',
					'required'      => 0,
					'group_name'    => 'Site'
				));
			}
			$theme_setting->set_value($theme_name);
			if ($theme_setting->save()) {
				// Invalidate system settings cache:
				$settings_cache_file = SITE_ROOT.'/var/cache/system_settings.cache.php';
				if (file_exists($settings_cache_file)) {
					@unlink($settings_cache_file);
				}
				Session::flash('user_success', __('Your theme has been activated.'));
			}
		}
		$redirect_url = $this->url('appearance');
		if ($theme_error) {
			$redirect_url = Crumbs::add_query_var_to_uri($redirect_url,'empty_caches','1');
		}
		Response::redirect($redirect_url);
	}
	/**
	 * Render a nice log viewer page
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function action_log_viewer() {
		$this->title('Biscuit Log Viewer');
		$this->register_js('footer','log-viewer.js');
		$this->register_css(array('filename' => 'log-viewer.css', 'media' => 'all'));
		foreach ($this->_log_files as $log_file_name) {
			$log_type = substr($log_file_name,0,-4);
			$this->set_view_var($log_file_name, Console::parse_log_file($log_type));
		}
		$this->render();
	}
	/**
	 * Delete log files
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function action_log_delete() {
		foreach ($this->_log_files as $log_file) {
			@unlink(SITE_ROOT.'/var/log/'.$log_file);
		}
		Response::redirect($this->url('log_viewer'));
	}
	/**
	 * Render the header for a log view section, including navigation to flip through all the requests
	 *
	 * @param string $debug_type Type of debug output, eg. "console"
	 * @param string $debug_data Array of debug data for output
	 * @return void
	 * @author Peter Epp
	 */
	public function render_log_view_header($debug_type,$header_title,$counter,$total_pages) {
		$view_vars = array(
			'debug_type'   => $debug_type,
			'header_title' => $header_title,
			'counter'      => $counter,
			'total_pages'  => $total_pages
		);
		return Crumbs::capture_include("system_admin/views/log_view_header.php",$view_vars);
	}
	/**
	 * Helper method to compose debug header title
	 *
	 * @param array $marker_info 
	 * @param string $item_name 
	 * @param string $total_count 
	 * @return string
	 * @author Peter Epp
	 */
	public function compose_log_header_title($marker_info,$item_name,$total_count) {
		if ($total_count > 1) {
			$item_name = AkInflector::pluralize($item_name);
		}
		if (!empty($marker_info['request_uri']) && !empty($marker_info['timestamp'])) {
			return 'Request for '.$marker_info['request_uri'].' on '.date('F jS, Y',$marker_info['timestamp']).' at '.date('H:i:s',$marker_info['timestamp']).'<br>'.$total_count.' '.$item_name;
		}
		return 'All entries not marked on any specific request URL or date<br>&nbsp;';
	}
	/**
	 * Provide extension management interface
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function action_manage_extensions() {
		$this->register_css(array('filename' => 'system-admin.css', 'media' => 'screen'));
		$this->title('Manage Extensions');
		$extensions = $this->_sync_and_get_extensions();
		$this->set_view_var('extensions', $extensions);
		$this->render();
	}
	/**
	 * Sync database info with existing extension files and return extension models for use in view
	 *
	 * @return void
	 * @author Peter Epp
	 */
	private function _sync_and_get_extensions() {
		$db_extensions_updated = false;
		$all_extension_names = array();
		$extensions_in_database = array();
		$extensions_not_in_database = array();
		// Find all extensions currently in the database
		$extensions = $this->Extension->find_all(array('name' => 'ASC'));
		// Find all the extension files current in the framework and the site and format into useful arrays
		$core_extensions = FindFiles::ls('/framework/extensions',array('include_directories' => true, 'include_files' => false, 'excludes' => array('customized')),false);
		$site_extensions = FindFiles::ls('/extensions',array('include_directories' => true, 'include_files' => false, 'excludes' => array('customized')),false);
		if (!empty($core_extensions)) {
			foreach ($core_extensions as $extension_file) {
				$extension_name = AkInflector::camelize($extension_file->getFilename());
				$all_extension_file_paths[$extension_name] = $extension_file->getPath().'/'.$extension_file->getFilename();
				$all_extension_names[] = $extension_name;
			}
		}
		if (!empty($site_extensions)) {
			foreach ($site_extensions as $extension_file) {
				$extension_name = AkInflector::camelize($extension_file->getFilename());
				$all_extension_file_paths[$extension_name] = $extension_file->getPath().'/'.$extension_file->getFilename();
				$all_extension_names[] = $extension_name;
			}
		}
		// Out of all the current modules in the site, make a list of the ones already in the database (installed):
		$extensions_in_database = array();
		if (!empty($extensions)) {
			foreach ($extensions as $extension) {
				$extensions_in_database[] = $extension->name();
			}
		}
		// Find out if there are any extensions not in the database (not installed), and if so create models of them to use in the view:
		$extensions_not_in_database = array_diff($all_extension_names, $extensions_in_database);
		if (!empty($extensions_not_in_database)) {
			foreach ($extensions_not_in_database as $extension_name) {
				$extension_info_file = $all_extension_file_paths[$extension_name].'/extension.info';
				if (file_exists($extension_info_file)) {
					$extension_info = parse_ini_file($extension_info_file);
					if (!empty($extension_info)) {
						if (isset($extension_info['is_global'])) {
							$extension_name = $extension_info['name'];
							if (empty($extension_info['description'])) {
								$extension_description = "No description";
							} else {
								$extension_description = $extension_info['description'];
							}
							$is_global = (int)$extension_info['is_global'];
							$extensions[] = $this->Extension->create(array(
								'name' => $extension_name,
								'description' => $extension_description,
								'is_global' => $is_global
							));
						} else {
							Session::flash('user_error','"'.$extension_name.'" is missing the is_global variable from it\'s info file. Please report this to the extension developer.');
						}
					}
				}
			}
		}
		// Find out if there are any modules in the database who's files are not present, and if there are any delete the refence to them in the database:
		$extensions_missing_files = array_diff($extensions_in_database, $all_extension_names);
		if (!empty($extensions_missing_files)) {
			$sql_extension_names = '("'.implode('","',$extensions_missing_files).'")';
			DB::query("DELETE FROM `extensions` WHERE `name` IN {$sql_extension_names}");
			$db_extensions_updated = true;
		}
		if ($db_extensions_updated) {
			$extensions = $this->Extension->find_all(array('name' => 'ASC'));
		}
		return $extensions;
	}
	/**
	 * Provide module management interface
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function action_manage_modules() {
		$this->register_css(array('filename' => 'system-admin.css', 'media' => 'screen'));
		$this->title('Manage Modules');
		$modules = $this->_sync_and_get_modules();
		$this->set_view_var('modules',$modules);
		$this->render();
	}
	/**
	 * Synchronize the modules table with the module files in the site and framework and then return an array of all the current modules
	 *
	 * @return void
	 * @author Peter Epp
	 */
	private function _sync_and_get_modules() {
		$db_modules_updated = false;
		$all_module_names = array();
		$modules_in_database = array();
		$modules_not_in_database = array();
		// Find all modules currently in the database
		$modules = $this->Module->find_all(array('sort_order' => 'ASC'));
		// Find all the module files current in the framework and the site and format into useful arrays
		$core_modules = FindFiles::ls('/framework/modules',array('include_directories' => true, 'include_files' => false, 'excludes' => array('customized', 'generic_views', 'shared_models')),false);
		$site_modules = FindFiles::ls('/modules',array('include_directories' => true, 'include_files' => false, 'excludes' => array('customized', 'generic_views', 'shared_models')),false);
		if (!empty($core_modules)) {
			foreach ($core_modules as $module_file) {
				$module_name = AkInflector::camelize($module_file->getFilename());
				$all_module_file_paths[$module_name] = $module_file->getPath().'/'.$module_file->getFilename();
				$all_module_names[] = $module_name;
			}
		}
		if (!empty($site_modules)) {
			foreach ($site_modules as $module_file) {
				$module_name = AkInflector::camelize($module_file->getFilename());
				$all_module_file_paths[$module_name] = $module_file->getPath().'/'.$module_file->getFilename();
				$all_module_names[] = $module_name;
			}
		}
		// Out of all the current modules in the site, make a list of the ones already in the database:
		$modules_in_database = array();
		if (!empty($modules)) {
			foreach ($modules as $module) {
				$modules_in_database[] = $module->name();
			}
		}
		// Find out if there are any modules not in the database, and if so add them:
		$modules_not_in_database = array_diff($all_module_names, $modules_in_database);
		if (!empty($modules_not_in_database)) {
			foreach ($modules_not_in_database as $module_name) {
				$module_info_file = $all_module_file_paths[$module_name].'/module.info';
				if (file_exists($module_info_file)) {
					$next_sort_order = (int)DB::fetch_one("SELECT `sort_order` FROM `modules` ORDER BY `sort_order` DESC LIMIT 1")+1;
					$module_info = parse_ini_file($module_info_file);
					if (empty($module_info['description'])) {
						$module_description = "No description";
					} else {
						$module_description = $module_info['description'];
					}
					$module = $this->Module->create(array(
						'name' => $module_info['name'],
						'description' => $module_description,
						'sort_order' => $next_sort_order,
						'installed' => 0
					));
					$module->save(true);
					$modules_in_database[] = $module->name();
				}
			}
			$db_modules_updated = true;
		}
		// Find out if there are any modules in the database who's files are not present, and if there are any delete the refence to them in the database:
		$modules_missing_files = array_diff($modules_in_database, $all_module_names);
		if (!empty($modules_missing_files)) {
			$sql_module_names = '("'.implode('","',$modules_missing_files).'")';
			DB::query("DELETE FROM `modules` WHERE `name` IN {$sql_module_names}");
			$db_modules_updated = true;
		}
		if ($db_modules_updated) {
			$modules = $this->Module->find_all(array('sort_order' => 'ASC'));
		}
		return $modules;
	}
	/**
	 * Find all system settings and render. Save settings on post request
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function action_configuration() {
		if (!empty($this->params['group_name'])) {
			$this->params['group_name'] = trim($this->params['group_name'],'/');
		}
		$system_settings = $this->_editable_system_settings();
		if (empty($system_settings)) {
			if (!empty($this->params['group_name'])) {
				Session::flash('user_error',sprintf(__('No configuration available for "%s"'),$this->params['group_name']));
			} else {
				Session::flash('user_error',__('No editable configuration settings available.'));
			}
			Response::redirect($this->url());
		}
		if (Request::is_post()) {
			if ($this->validate_configuration()) {
				// Process input
				$user_input = $this->params['system_setting'];
				$changed_settings = array();
				foreach ($system_settings as $index => $setting) {
					if ($setting->value_type() == 'file') {
						if ($this->params['remove_setting_'.$setting->id()]) {
							$this->_remove_file($setting->value());
						}
						$value = $this->_process_upload($setting);
					} else {
						if (is_array($user_input[$setting->id()])) {
							$value = implode(',',$user_input[$setting->id()]);
						} else {
							$value = $user_input[$setting->id()];
						}
					}
					if ($value != $system_settings[$index]->value()) {
						$system_settings[$index]->set_value($value);
						$changed_settings[] = $system_settings[$index];
					}
				}
				// Always delete the cache, regardless of whether any settings changed. This makes for an easy way to "fix" configuration settings that get
				// stuck in the cache
				$settings_cache_file = SITE_ROOT.'/var/cache/system_settings.cache.php';
				if (file_exists($settings_cache_file)) {
					@unlink($settings_cache_file);
				}
				// Save only the settings that were changed
				$changed_setting_names = array();
				foreach ($changed_settings as $setting) {
					if ($setting->friendly_name()) {
						$changed_setting_names[] = __($setting->friendly_name());
					} else {
						$changed_setting_names[] = $setting->constant_name();
					}
					$setting->save(true);
				}
				if (!empty($changed_settings)) {
					Session::flash('user_success','<p>'.__('The following settings were updated:').'</p><ul><li>'.implode('</li><li>',$changed_setting_names).'</li></ul>');
				} else {
					Session::flash('user_message',__('No settings were changed.'));
				}
				Response::redirect($this->url());
			} else {
				Session::flash('user_error',"<strong>".__("Please make the following corrections:")."</strong><br><br>".implode("<br>",$this->_validation_errors));
			}
		}
		if (!empty($this->params['group_name'])) {
			$page_title = sprintf(__("%s Configuration"),urldecode(__($this->params['group_name'])));
		} else {
			$page_title = __("Configuration");
		}
		$this->title($page_title);
		$this->register_css(array('filename' => 'system-admin.css', 'media' => 'screen'));
		// Re-organize settings by group for the view
		$settings_by_group = array();
		$requires_colorpicker = false;
		foreach ($system_settings as $setting) {
			$settings_by_group[$setting->group_name()][] = $setting;
			if ($setting->value_type() == 'color') {
				$requires_colorpicker = true;
			}
		}
		if ($requires_colorpicker) {
			$this->Biscuit->register_css(array('filename' => 'framework/js/vendor/colorpicker/css/colorpicker.css', 'media' => 'screen'));
			$this->Biscuit->register_js('footer','framework/js/vendor/colorpicker/js/colorpicker.js');
			$this->Biscuit->register_js('footer','framework/js/vendor/colorpicker/js/eye.js');
			$this->Biscuit->register_js('footer','framework/js/vendor/colorpicker/js/utils.js');
		}
		// Sort by key (alphabetically by group name), then put the site settings first:
		ksort($settings_by_group);
		if (empty($this->params['group_name'])) {
			$sorted_settings_by_group['Site'] = $settings_by_group['Site'];
			foreach ($settings_by_group as $group_name => $settings) {
				if ($group_name != 'Site') {
					$sorted_settings_by_group[$group_name] = $settings;
				}
			}
		} else {
			$sorted_settings_by_group = $settings_by_group;
		}
		$this->set_view_var('multiple_groups',empty($this->params['group_name']));
		$this->set_view_var('system_settings',$sorted_settings_by_group);
		$this->render();
	}
	/**
	 * Delete a file associated with a system setting
	 *
	 * @param string $filename 
	 * @return void
	 * @author Peter Epp
	 */
	protected function _remove_file($filename) {
		if (empty($filename)) {
			return false;
		}
		$full_file_path = SITE_ROOT.'/var/uploads/system_settings/'.$filename;
		if (!file_exists($full_file_path)) {
			return false;
		}
		if (!Crumbs::delete_file_or_folder($full_file_path)) {
			return false;
		}
		return true;
	}
	/**
	 * Process an uploaded file and return the filename, if upload was successful, or empty string if not
	 *
	 * @param string $setting_id 
	 * @return string
	 * @author Peter Epp
	 */
	protected function _process_upload($system_setting) {
		$upload_path = '/var/uploads/system_settings';
		$file_fieldname = 'setting_'.$system_setting->id().'_file';
		$all_uploads = Request::files();
		if (!empty($all_uploads) && !empty($all_uploads[$file_fieldname])) {
			if (DEBUG) {
				Console::log_var_dump('Uploaded files for '.get_class($this),$all_uploads[$file_fieldname]);
			}
			$old_filename = '';
			if ($system_setting->value() !== null) {
				$old_filename = $system_setting->value();
			}
			$uploaded_file = new FileUpload($all_uploads[$file_fieldname], $upload_path);
			if ($uploaded_file->is_okay()) {
				// uploaded, processed okay
				Console::log("                        Successfully uploaded file as: ".$uploaded_file->file_name());
				if (!empty($old_filename)) {
					Console::log("                        Deleting old file: ".$old_filename);
					Crumbs::delete_file_or_folder(SITE_ROOT.$upload_path."/".$old_filename);
				}
				return $uploaded_file->file_name();
			} else {
				return $system_setting->value();
			}
		}
		Console::log("                        No file upload data, chill");
		return $system_setting->value();
	}
	/**
	 * Validate system configuration input
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function validate_configuration() {
		$existing_settings = $this->_editable_system_settings();
		// Put existing settings into array by ID
		$settings_by_id = array();
		foreach ($existing_settings as $setting) {
			$settings_by_id[$setting->id()] = $setting;
		}
		$user_input = $this->params['system_setting'];
		foreach ($user_input as $id => $value) {
			if ($settings_by_id[$id]->required()) {
				if ($settings_by_id[$id]->value_type() == 'file') {
					if (Request::is_ajax()) {
						$invalid = empty($value);
					} else {
						// Otherwise check to see if it's actually an uploaded file:
						$uploaded_file = Request::files('setting_'.$id.'_file');
						$invalid = (empty($uploaded_file) || !is_uploaded_file($uploaded_file['tmp_name']));
					}
				} else {
					$invalid = (empty($value) && $value !== 0 && $value !== '0');
				}
				if ($invalid) {
					if ($settings_by_id[$id]->friendly_name()) {
						$setting_name = $settings_by_id[$id]->friendly_name();
					} else {
						$setting_name = $settings_by_id[$id]->constant_name();
					}
					$this->_validation_errors[] = 'Provide a value for &ldquo;'.$setting_name.'&rdquo;';
					$this->_invalid_fields[] = 'setting_'.$id;
				}
			}
		}
		return (empty($this->_validation_errors));
	}
	/**
	 * Return existing editable system settings, caching on first request if needed
	 *
	 * @return void
	 * @author Peter Epp
	 */
	private function _editable_system_settings() {
		if (empty($this->_system_settings_cache)) {
			if (!empty($this->params['group_name'])) {
				$system_settings = $this->SystemSettings->find_all_by('group_name',$this->params['group_name']);
			} else {
				$system_settings = $this->SystemSettings->find_all();
			}
			if (!empty($system_settings)) {
				foreach ($system_settings as $index => $setting) {
					if (in_array($setting->constant_name(),$this->_disallowed_system_settings)) {
						unset($system_settings[$index]);
					}
				}
				$this->_system_settings_cache = array_values($system_settings);
			} else {
				$this->_system_settings_cache = array();
			}
		}
		return $this->_system_settings_cache;
	}
	/**
	 * Render a system configuration form field based on the value type. Defaults to text field if no special type provided
	 *
	 * @param string $value_type 
	 * @return void
	 * @author Peter Epp
	 */
	public function render_config_field($value_type,$setting_id,$field_label,$current_value,$is_valid,$is_required = true) {
		if (substr($value_type,0,6) == 'select') {
			$type_name = 'select';
		} else if (substr($value_type,0,6) == 'radios') {
			$type_name = 'radios';
		} else if (substr($value_type,0,11) == 'multiselect') {
			$type_name = 'multiselect';
		} else if (substr($value_type,0,6) == 'slider') {
			$type_name = 'slider';
		} else {
			$type_name = $value_type;
		}
		Console::log("Render config field for ".$type_name);
		switch ($type_name) {
			case 'timezone':
			case 'year':
			case 'permission':
			case 'select':
				return Form::select($this->value_list($type_name,$value_type),'setting_'.$setting_id,'system_setting['.$setting_id.']',$field_label,$current_value,$is_required,$is_valid);
				break;
			case 'radios':
				return Form::radios($this->value_list($type_name,$value_type),'setting_'.$setting_id,'system_setting['.$setting_id.']',$field_label,$current_value,$is_required,$is_valid);
				break;
			case 'multiselect':
			case 'permissionmultiselect':
				$current_value = explode(',',$current_value);
				return Form::checkbox_multiple($this->value_list($type_name,$value_type),'setting_'.$setting_id,'system_setting['.$setting_id.']',$field_label,$current_value,$is_required,$is_valid);
				break;
			case 'slider':
				list($min,$max,$step,$units) = explode('|',substr($value_type,7,-1));
				return Form::slider('setting_'.$setting_id,'system_setting['.$setting_id.']',$field_label,$current_value,$is_required,$is_valid,$min,$max,$step,array('units' => $units));
				break;
			case 'color':
				return Form::colorpicker('setting_'.$setting_id,'system_setting['.$setting_id.']',$field_label,$current_value,$is_required,$is_valid);
				break;
			case 'file':
				$finfo = null;
				if (!empty($current_value)) {
					$finfo = $this->file_info('/var/uploads/system_settings/'.$current_value);
				}
				return Form::file('setting_'.$setting_id,'setting_'.$setting_id.'_file',$field_label,$finfo,$is_required,$is_valid);
				break;
			case 'textarea':
				return Form::textarea('setting_'.$setting_id,'system_setting['.$setting_id.']',$field_label,$current_value,20,$is_required,$is_valid,array('height' => '150px'));
				break;
			default:
				return Form::text('setting_'.$setting_id,'system_setting['.$setting_id.']',$field_label,$current_value,$is_required,$is_valid);
				break;
		}
	}
	/**
	 * Return an array of information about a file
	 *
	 * @param string $full_file_path 
	 * @return array|null
	 * @author Peter Epp
	 */
	protected function file_info($full_file_path) {
		if (file_exists(SITE_ROOT.$full_file_path)) {
			$filename = pathinfo($full_file_path, PATHINFO_BASENAME);
			$file_size = Crumbs::formatted_file_size($full_file_path);
			$file_date = date('M j Y', filemtime(SITE_ROOT.$full_file_path));
			$file_info = array(
				"size" => $file_size,
				"date" => $file_date,
				"download_url" => $full_file_path,
				"file_name" => $filename,
				"is_image" => false
			);
			$image_type = exif_imagetype(SITE_ROOT.$full_file_path);
			if ($image_type == IMAGETYPE_JPEG || $image_type == IMAGETYPE_PNG || $image_type == IMAGETYPE_GIF) {
				$file_info['is_image'] = true;
				// Add on some useful image data:
				$image_size = getimagesize(SITE_ROOT.$full_file_path);
				Console::log("Image info:\n".print_r($image_size,true));
				$file_info['image_width']  = $image_size[0];
				$file_info['image_height'] = $image_size[1];
				$file_info['mime']         = $image_size['mime'];
			}
			return $file_info;
		}
		return null;
	}
	/**
	 * Return a value list array in the format required for a drop-down select list
	 *
	 * @param string $type_name 
	 * @param string $value_type 
	 * @return array
	 * @author Peter Epp
	 */
	public function value_list($type_name, $value_type) {
		switch ($type_name) {
			case 'timezone':
				return $this->_timezone_options_array;
				break;
			case 'year':
				return $this->year_options_array();
				break;
			case 'permission':
			case 'permissionmultiselect':
				return $this->permission_options_array();
				break;
			case 'select':
			case 'multiselect':
			case 'radios':
				return $this->options_array_from_value_type($value_type);
				break;
		}
	}
	/**
	 * Parse the value type string into an array formatted for a drop-down select list
	 *
	 * @param string $value_type 
	 * @return array
	 * @author Peter Epp
	 */
	private function options_array_from_value_type($value_type) {
		if (!preg_match('/([^\{]+)\{([^\}]+)\}/',$value_type)) {
			throw new ModuleException('System Administration: the value type "'.$value_type.'" is not in the correct format!');
		} else {
			if (substr($value_type,0,6) == 'select' || substr($value_type,0,6) == 'radios') {
				$options_string = substr($value_type,7,-1);
			} else if (substr($value_type,0,11) == 'multiselect') {
				$options_string = substr($value_type,12,-1);
			}
			$possible_values = explode('|',$options_string);
			$options_array = array();
			foreach ($possible_values as $value) {
				$options_array[] = array('label' => Crumbs::entitize_utf8($value), 'value' => addslashes(Crumbs::entitize_utf8($value)));
			}
			return $options_array;
		}
	}
	/**
	 * Add a set of menu items to the admin menu item list
	 *
	 * @param string $menu_name 
	 * @param string $items 
	 * @return void
	 * @author Peter Epp
	 */
	public function add_admin_menu_items($menu_name,$items) {
		$this->_admin_menu_items[$menu_name] = $items;
	}
	/**
	 * Prevent delete action
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function action_delete() {
		Session::flash('user_error',__("System administration items may not be deleted."));
		Response::redirect($this->url());
	}
	/**
	 * Prevent edit action. Should never even get here due to the impossible permission level, but just in case
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function action_edit($mode = 'edit') {
		Session::flash('user_error',__("System administration items may not be directly edited."));
		Response::redirect($this->url());
	}
	/**
	 * Upon deletion of a module or extension, return to the manage modules page
	 *
	 * @param string $model 
	 * @return void
	 * @author Peter Epp
	 */
	protected function act_on_successful_delete($model) {
		$model_name = get_class($model);
		if ($model_name == 'Module' || $model_name == 'Extension') {
			$this->_return_url = $this->url('manage_modules');
		}
	}
	/**
	 * Add system admin items to the admin menu
	 *
	 * @param object $caller Object that's producing the admin menu - may be navigation extension or this module
	 * @return void
	 * @author Peter Epp
	 */
	protected function act_on_build_admin_menu($caller) {
		if ($this->action() == 'log_viewer') {
			$caller->prevent_admin_menu();
		} else if ($this->Biscuit->ModuleAuthenticator()->user_is_super()) {
			if (get_class($caller) != 'SystemAdmin') {
				$menu_items['Administration Home'] = array(
					'url' => $this->url(),
					'ui-icon' => 'ui-icon-home'
				);
			}
			$menu_items['Configuration'] = array(
				'url' => $this->url('configuration'),
				'ui-icon' => 'ui-icon-gear'
			);
			$menu_items['Appearance'] = array(
				'url' => $this->url('appearance'),
				'ui-icon' => 'ui-icon-image'
			);
			$menu_items['Manage Modules'] = array(
				'url' => $this->url('manage_modules'),
				'ui-icon' => 'ui-icon-script'
			);
			$menu_items['Manage Extensions'] = array(
				'url' => $this->url('manage_extensions'),
				'ui-icon' => 'ui-icon-script'
			);
			$menu_items['Log Viewer'] = array(
				'url' => $this->url('log_viewer'),
				'ui-icon' => 'ui-icon-document',
				'target' => '_blank'
			);
			$menu_items['Empty Caches'] = array(
				'url' => Crumbs::add_query_var_to_uri(Request::uri(),'empty_caches',1),
				'ui-icon' => 'ui-icon-trash'
			);
			// Will add the permissions admin feature later, just put this here as a placeholder for now
			// $menu_items['Permissions'] = $this->url('permissions');
			$caller->add_admin_menu_items('System',$menu_items);
		}
	}
	/**
	 * Install system admin module
	 *
	 * @return void
	 * @author Peter Epp
	 */
	public static function install_migration() {
		$my_page = DB::fetch_one("SELECT `id` FROM `page_index` WHERE `slug` = 'system-admin'");
		if (!$my_page) {
			// Add system-admin page:
			DB::insert("INSERT INTO `page_index` SET `parent` = 9999999, `slug` = 'system-admin', `title` = 'System Administration', `access_level` = 99");
			// Get module row ID:
			$module_id = DB::fetch_one("SELECT `id` FROM `modules` WHERE `name` = 'SystemAdmin'");
			// Remove SystemAdmin from module pages first to ensure clean install:
			DB::query("DELETE FROM `module_pages` WHERE `module_id` = {$module_id}");
			// Add SystemAdmin to system-admin page as primary and to all other pages as secondary:
			DB::insert("INSERT INTO `module_pages` (`module_id`, `page_name`, `is_primary`) VALUES ({$module_id}, 'system-admin', 1), ({$module_id}, '*', 0)");
			self::ensure_other_modules_are_installed();
			// Set impossible permission level for new, edit and delete actions to prevent access to those since they are not applicable for the models used by this module
			Permissions::add(__CLASS__,array('index' => 99, 'configuration' => 99, 'log_viewer' => 99, 'log_delete' => 99),true);
		}
	}
	/**
	 * Rewrite rules for the special system admin actions
	 *
	 * @return void
	 * @author Peter Epp
	 */
	public static function uri_mapping_rules() {
		return array(
			'/^(?P<page_slug>system-admin)\/(?P<action>configuration)(?P<group_name>\/.+)?$/',
			'/^(?P<page_slug>system-admin)\/(?P<action>permissions)$/',
			'/^(?P<page_slug>system-admin)\/(?P<action>log_viewer)$/',
			'/^(?P<page_slug>system-admin)\/(?P<action>log_delete)$/',
			'/^(?P<page_slug>system-admin)\/(?P<action>manage_modules)$/',
			'/^(?P<page_slug>system-admin)\/(?P<action>manage_extensions)$/',
			'/^(?P<page_slug>system-admin)\/(?P<action>appearance)$/',
			'/^(?P<page_slug>system-admin)\/(?P<action>activate_theme)\/(?P<theme_name>.+)$/'
		);
	}
	/**
	 * Make sure that all other installed modules are installed as secondary on this page if they aren't already, in case another module
	 * got installed after this one.
	 *
	 * @return void
	 * @author Peter Epp
	 */
	private static function ensure_other_modules_are_installed() {
		// Find all other installed modules, except the page content manager, and install them as secondary on the system-admin page:
		$all_other_module_ids = DB::fetch("SELECT `id` FROM `modules` WHERE `installed` = 1 AND `name` != 'SystemAdmin' AND `name` != 'PageContent'");
		$module_ids_installed_on_this_page = DB::fetch("SELECT `module_id` FROM `module_pages` WHERE `page_name` = 'system-admin'");
		$insert_values = '';
		if (!empty($all_other_module_ids)) {
			foreach ($all_other_module_ids as $other_module_id) {
				if (!in_array($other_module_id,$module_ids_installed_on_this_page)) {
					if (!empty($insert_values)) {
						$insert_values .= ", ";
					}
					$insert_values .= "({$other_module_id}, 'system-admin', 0)";
				}
			}
			if (!empty($insert_values)) {
				$query = "REPLACE INTO `module_pages` (`module_id`, `page_name`, `is_primary`) VALUES ".$insert_values;
				DB::query($query);
			}
		}
	}
	/**
	 * Uninstall system admin module
	 *
	 * @return void
	 * @author Peter Epp
	 */
	public static function uninstall_migration() {
		DB::query("DELETE FROM `page_index` WHERE `slug` = 'system-admin'");
		// Get module row ID:
		$module_id = DB::fetch_one("SELECT `id` FROM `modules` WHERE `name` = 'SystemAdmin'");
		DB::query("DELETE FROM `module_pages` WHERE `module_id` = {$module_id} OR `page_name` = 'system-admin'");
		Permissions::remove(__CLASS__);
	}
	/**
	 * Return an array of years for a year drop-down select starting 20 years ago and going to 20 years from now
	 *
	 * @return array
	 * @author Peter Epp
	 */
	public function year_options_array() {
		$start_year = (int)date('Y')-20;
		$end_year = (int)date('Y')+20;
		$years = array();
		for ($i=$start_year; $i <= $end_year; $i++) {
			$years[] = array('label' => $i, 'value' => $i);
		}
		return $years;
	}
	/**
	 * Return an array of access levels in the format needed for a select drop-down list
	 *
	 * @return array
	 * @author Peter Epp
	 */
	public function permission_options_array() {
		$access_levels = $this->AccessLevel->find_all(array('id' => 'ASC'));
		$levels_array = array();
		foreach ($access_levels as $access_level) {
			$levels_array[] = array('label' => $access_level->name(), 'value' => $access_level->id());
		}
		return $levels_array;
	}
	/**
	 * Array of all possible timezones in format for rendering drop-down select field
	 *
	 * @var array
	 */
	private $_timezone_options_array = array(
	array('label' => '(GMT-10:00) America/Adak (Hawaii-Aleutian Standard Time)', 'value' => 'America/Adak'),
	array('label' => '(GMT-10:00) America/Atka (Hawaii-Aleutian Standard Time)', 'value' => 'America/Atka'),
	array('label' => '(GMT-9:00) America/Anchorage (Alaska Standard Time)', 'value' => 'America/Anchorage'),
	array('label' => '(GMT-9:00) America/Juneau (Alaska Standard Time)', 'value' => 'America/Juneau'),
	array('label' => '(GMT-9:00) America/Nome (Alaska Standard Time)', 'value' => 'America/Nome'),
	array('label' => '(GMT-9:00) America/Yakutat (Alaska Standard Time)', 'value' => 'America/Yakutat'),
	array('label' => '(GMT-8:00) America/Dawson (Pacific Standard Time)', 'value' => 'America/Dawson'),
	array('label' => '(GMT-8:00) America/Ensenada (Pacific Standard Time)', 'value' => 'America/Ensenada'),
	array('label' => '(GMT-8:00) America/Los Angeles (Pacific Standard Time)', 'value' => 'America/Los Angeles'),
	array('label' => '(GMT-8:00) America/Tijuana (Pacific Standard Time)', 'value' => 'America/Tijuana'),
	array('label' => '(GMT-8:00) America/Vancouver (Pacific Standard Time)', 'value' => 'America/Vancouver'),
	array('label' => '(GMT-8:00) America/Whitehorse (Pacific Standard Time)', 'value' => 'America/Whitehorse'),
	array('label' => '(GMT-8:00) Canada/Pacific (Pacific Standard Time)', 'value' => 'Canada/Pacific'),
	array('label' => '(GMT-8:00) Canada/Yukon (Pacific Standard Time)', 'value' => 'Canada/Yukon'),
	array('label' => '(GMT-8:00) Mexico/BajaNorte (Pacific Standard Time)', 'value' => 'Mexico/BajaNorte'),
	array('label' => '(GMT-7:00) America/Boise (Mountain Standard Time)', 'value' => 'America/Boise'),
	array('label' => '(GMT-7:00) America/Cambridge Bay (Mountain Standard Time)', 'value' => 'America/Cambridge Bay'),
	array('label' => '(GMT-7:00) America/Chihuahua (Mountain Standard Time)', 'value' => 'America/Chihuahua'),
	array('label' => '(GMT-7:00) America/Dawson Creek (Mountain Standard Time)', 'value' => 'America/Dawson Creek'),
	array('label' => '(GMT-7:00) America/Denver (Mountain Standard Time)', 'value' => 'America/Denver'),
	array('label' => '(GMT-7:00) America/Edmonton (Mountain Standard Time)', 'value' => 'America/Edmonton'),
	array('label' => '(GMT-7:00) America/Hermosillo (Mountain Standard Time)', 'value' => 'America/Hermosillo'),
	array('label' => '(GMT-7:00) America/Inuvik (Mountain Standard Time)', 'value' => 'America/Inuvik'),
	array('label' => '(GMT-7:00) America/Mazatlan (Mountain Standard Time)', 'value' => 'America/Mazatlan'),
	array('label' => '(GMT-7:00) America/Phoenix (Mountain Standard Time)', 'value' => 'America/Phoenix'),
	array('label' => '(GMT-7:00) America/Shiprock (Mountain Standard Time)', 'value' => 'America/Shiprock'),
	array('label' => '(GMT-7:00) America/Yellowknife (Mountain Standard Time)', 'value' => 'America/Yellowknife'),
	array('label' => '(GMT-7:00) Canada/Mountain (Mountain Standard Time)', 'value' => 'Canada/Mountain'),
	array('label' => '(GMT-7:00) Mexico/BajaSur (Mountain Standard Time)', 'value' => 'Mexico/BajaSur'),
	array('label' => '(GMT-6:00) America/Belize (Central Standard Time)', 'value' => 'America/Belize'),
	array('label' => '(GMT-6:00) America/Cancun (Central Standard Time)', 'value' => 'America/Cancun'),
	array('label' => '(GMT-6:00) America/Chicago (Central Standard Time)', 'value' => 'America/Chicago'),
	array('label' => '(GMT-6:00) America/Costa Rica (Central Standard Time)', 'value' => 'America/Costa Rica'),
	array('label' => '(GMT-6:00) America/El Salvador (Central Standard Time)', 'value' => 'America/El Salvador'),
	array('label' => '(GMT-6:00) America/Guatemala (Central Standard Time)', 'value' => 'America/Guatemala'),
	array('label' => '(GMT-6:00) America/Knox IN (Central Standard Time)', 'value' => 'America/Knox IN'),
	array('label' => '(GMT-6:00) America/Managua (Central Standard Time)', 'value' => 'America/Managua'),
	array('label' => '(GMT-6:00) America/Menominee (Central Standard Time)', 'value' => 'America/Menominee'),
	array('label' => '(GMT-6:00) America/Merida (Central Standard Time)', 'value' => 'America/Merida'),
	array('label' => '(GMT-6:00) America/Mexico City (Central Standard Time)', 'value' => 'America/Mexico City'),
	array('label' => '(GMT-6:00) America/Monterrey (Central Standard Time)', 'value' => 'America/Monterrey'),
	array('label' => '(GMT-6:00) America/Rainy River (Central Standard Time)', 'value' => 'America/Rainy River'),
	array('label' => '(GMT-6:00) America/Rankin Inlet (Central Standard Time)', 'value' => 'America/Rankin Inlet'),
	array('label' => '(GMT-6:00) America/Regina (Central Standard Time)', 'value' => 'America/Regina'),
	array('label' => '(GMT-6:00) America/Swift Current (Central Standard Time)', 'value' => 'America/Swift Current'),
	array('label' => '(GMT-6:00) America/Tegucigalpa (Central Standard Time)', 'value' => 'America/Tegucigalpa'),
	array('label' => '(GMT-6:00) America/Winnipeg (Central Standard Time)', 'value' => 'America/Winnipeg'),
	array('label' => '(GMT-6:00) Canada/Central (Central Standard Time)', 'value' => 'Canada/Central'),
	array('label' => '(GMT-6:00) Canada/East-Saskatchewan (Central Standard Time)', 'value' => 'Canada/East-Saskatchewan'),
	array('label' => '(GMT-6:00) Canada/Saskatchewan (Central Standard Time)', 'value' => 'Canada/Saskatchewan'),
	array('label' => '(GMT-6:00) Chile/EasterIsland (Easter Is. Time)', 'value' => 'Chile/EasterIsland'),
	array('label' => '(GMT-6:00) Mexico/General (Central Standard Time)', 'value' => 'Mexico/General'),
	array('label' => '(GMT-5:00) America/Atikokan (Eastern Standard Time)', 'value' => 'America/Atikokan'),
	array('label' => '(GMT-5:00) America/Bogota (Colombia Time)', 'value' => 'America/Bogota'),
	array('label' => '(GMT-5:00) America/Cayman (Eastern Standard Time)', 'value' => 'America/Cayman'),
	array('label' => '(GMT-5:00) America/Coral Harbour (Eastern Standard Time)', 'value' => 'America/Coral Harbour'),
	array('label' => '(GMT-5:00) America/Detroit (Eastern Standard Time)', 'value' => 'America/Detroit'),
	array('label' => '(GMT-5:00) America/Fort Wayne (Eastern Standard Time)', 'value' => 'America/Fort Wayne'),
	array('label' => '(GMT-5:00) America/Grand Turk (Eastern Standard Time)', 'value' => 'America/Grand Turk'),
	array('label' => '(GMT-5:00) America/Guayaquil (Ecuador Time)', 'value' => 'America/Guayaquil'),
	array('label' => '(GMT-5:00) America/Havana (Cuba Standard Time)', 'value' => 'America/Havana'),
	array('label' => '(GMT-5:00) America/Indianapolis (Eastern Standard Time)', 'value' => 'America/Indianapolis'),
	array('label' => '(GMT-5:00) America/Iqaluit (Eastern Standard Time)', 'value' => 'America/Iqaluit'),
	array('label' => '(GMT-5:00) America/Jamaica (Eastern Standard Time)', 'value' => 'America/Jamaica'),
	array('label' => '(GMT-5:00) America/Lima (Peru Time)', 'value' => 'America/Lima'),
	array('label' => '(GMT-5:00) America/Louisville (Eastern Standard Time)', 'value' => 'America/Louisville'),
	array('label' => '(GMT-5:00) America/Montreal (Eastern Standard Time)', 'value' => 'America/Montreal'),
	array('label' => '(GMT-5:00) America/Nassau (Eastern Standard Time)', 'value' => 'America/Nassau'),
	array('label' => '(GMT-5:00) America/New York (Eastern Standard Time)', 'value' => 'America/New York'),
	array('label' => '(GMT-5:00) America/Nipigon (Eastern Standard Time)', 'value' => 'America/Nipigon'),
	array('label' => '(GMT-5:00) America/Panama (Eastern Standard Time)', 'value' => 'America/Panama'),
	array('label' => '(GMT-5:00) America/Pangnirtung (Eastern Standard Time)', 'value' => 'America/Pangnirtung'),
	array('label' => '(GMT-5:00) America/Port-au-Prince (Eastern Standard Time)', 'value' => 'America/Port-au-Prince'),
	array('label' => '(GMT-5:00) America/Resolute (Eastern Standard Time)', 'value' => 'America/Resolute'),
	array('label' => '(GMT-5:00) America/Thunder Bay (Eastern Standard Time)', 'value' => 'America/Thunder Bay'),
	array('label' => '(GMT-5:00) America/Toronto (Eastern Standard Time)', 'value' => 'America/Toronto'),
	array('label' => '(GMT-5:00) Canada/Eastern (Eastern Standard Time)', 'value' => 'Canada/Eastern'),
	array('label' => '(GMT-4:-30) America/Caracas (Venezuela Time)', 'value' => 'America/Caracas'),
	array('label' => '(GMT-4:00) America/Anguilla (Atlantic Standard Time)', 'value' => 'America/Anguilla'),
	array('label' => '(GMT-4:00) America/Antigua (Atlantic Standard Time)', 'value' => 'America/Antigua'),
	array('label' => '(GMT-4:00) America/Aruba (Atlantic Standard Time)', 'value' => 'America/Aruba'),
	array('label' => '(GMT-4:00) America/Asuncion (Paraguay Time)', 'value' => 'America/Asuncion'),
	array('label' => '(GMT-4:00) America/Barbados (Atlantic Standard Time)', 'value' => 'America/Barbados'),
	array('label' => '(GMT-4:00) America/Blanc-Sablon (Atlantic Standard Time)', 'value' => 'America/Blanc-Sablon'),
	array('label' => '(GMT-4:00) America/Boa Vista (Amazon Time)', 'value' => 'America/Boa Vista'),
	array('label' => '(GMT-4:00) America/Campo Grande (Amazon Time)', 'value' => 'America/Campo Grande'),
	array('label' => '(GMT-4:00) America/Cuiaba (Amazon Time)', 'value' => 'America/Cuiaba'),
	array('label' => '(GMT-4:00) America/Curacao (Atlantic Standard Time)', 'value' => 'America/Curacao'),
	array('label' => '(GMT-4:00) America/Dominica (Atlantic Standard Time)', 'value' => 'America/Dominica'),
	array('label' => '(GMT-4:00) America/Eirunepe (Amazon Time)', 'value' => 'America/Eirunepe'),
	array('label' => '(GMT-4:00) America/Glace Bay (Atlantic Standard Time)', 'value' => 'America/Glace Bay'),
	array('label' => '(GMT-4:00) America/Goose Bay (Atlantic Standard Time)', 'value' => 'America/Goose Bay'),
	array('label' => '(GMT-4:00) America/Grenada (Atlantic Standard Time)', 'value' => 'America/Grenada'),
	array('label' => '(GMT-4:00) America/Guadeloupe (Atlantic Standard Time)', 'value' => 'America/Guadeloupe'),
	array('label' => '(GMT-4:00) America/Guyana (Guyana Time)', 'value' => 'America/Guyana'),
	array('label' => '(GMT-4:00) America/Halifax (Atlantic Standard Time)', 'value' => 'America/Halifax'),
	array('label' => '(GMT-4:00) America/La Paz (Bolivia Time)', 'value' => 'America/La Paz'),
	array('label' => '(GMT-4:00) America/Manaus (Amazon Time)', 'value' => 'America/Manaus'),
	array('label' => '(GMT-4:00) America/Marigot (Atlantic Standard Time)', 'value' => 'America/Marigot'),
	array('label' => '(GMT-4:00) America/Martinique (Atlantic Standard Time)', 'value' => 'America/Martinique'),
	array('label' => '(GMT-4:00) America/Moncton (Atlantic Standard Time)', 'value' => 'America/Moncton'),
	array('label' => '(GMT-4:00) America/Montserrat (Atlantic Standard Time)', 'value' => 'America/Montserrat'),
	array('label' => '(GMT-4:00) America/Port of Spain (Atlantic Standard Time)', 'value' => 'America/Port of Spain'),
	array('label' => '(GMT-4:00) America/Porto Acre (Amazon Time)', 'value' => 'America/Porto Acre'),
	array('label' => '(GMT-4:00) America/Porto Velho (Amazon Time)', 'value' => 'America/Porto Velho'),
	array('label' => '(GMT-4:00) America/Puerto Rico (Atlantic Standard Time)', 'value' => 'America/Puerto Rico'),
	array('label' => '(GMT-4:00) America/Rio Branco (Amazon Time)', 'value' => 'America/Rio Branco'),
	array('label' => '(GMT-4:00) America/Santiago (Chile Time)', 'value' => 'America/Santiago'),
	array('label' => '(GMT-4:00) America/Santo Domingo (Atlantic Standard Time)', 'value' => 'America/Santo Domingo'),
	array('label' => '(GMT-4:00) America/St Barthelemy (Atlantic Standard Time)', 'value' => 'America/St Barthelemy'),
	array('label' => '(GMT-4:00) America/St Kitts (Atlantic Standard Time)', 'value' => 'America/St Kitts'),
	array('label' => '(GMT-4:00) America/St Lucia (Atlantic Standard Time)', 'value' => 'America/St Lucia'),
	array('label' => '(GMT-4:00) America/St Thomas (Atlantic Standard Time)', 'value' => 'America/St Thomas'),
	array('label' => '(GMT-4:00) America/St Vincent (Atlantic Standard Time)', 'value' => 'America/St Vincent'),
	array('label' => '(GMT-4:00) America/Thule (Atlantic Standard Time)', 'value' => 'America/Thule'),
	array('label' => '(GMT-4:00) America/Tortola (Atlantic Standard Time)', 'value' => 'America/Tortola'),
	array('label' => '(GMT-4:00) America/Virgin (Atlantic Standard Time)', 'value' => 'America/Virgin'),
	array('label' => '(GMT-4:00) Antarctica/Palmer (Chile Time)', 'value' => 'Antarctica/Palmer'),
	array('label' => '(GMT-4:00) Atlantic/Bermuda (Atlantic Standard Time)', 'value' => 'Atlantic/Bermuda'),
	array('label' => '(GMT-4:00) Atlantic/Stanley (Falkland Is. Time)', 'value' => 'Atlantic/Stanley'),
	array('label' => '(GMT-4:00) Brazil/Acre (Amazon Time)', 'value' => 'Brazil/Acre'),
	array('label' => '(GMT-4:00) Brazil/West (Amazon Time)', 'value' => 'Brazil/West'),
	array('label' => '(GMT-4:00) Canada/Atlantic (Atlantic Standard Time)', 'value' => 'Canada/Atlantic'),
	array('label' => '(GMT-4:00) Chile/Continental (Chile Time)', 'value' => 'Chile/Continental'),
	array('label' => '(GMT-3:-30) America/St Johns (Newfoundland Standard Time)', 'value' => 'America/St Johns'),
	array('label' => '(GMT-3:-30) Canada/Newfoundland (Newfoundland Standard Time)', 'value' => 'Canada/Newfoundland'),
	array('label' => '(GMT-3:00) America/Araguaina (Brasilia Time)', 'value' => 'America/Araguaina'),
	array('label' => '(GMT-3:00) America/Bahia (Brasilia Time)', 'value' => 'America/Bahia'),
	array('label' => '(GMT-3:00) America/Belem (Brasilia Time)', 'value' => 'America/Belem'),
	array('label' => '(GMT-3:00) America/Buenos Aires (Argentine Time)', 'value' => 'America/Buenos Aires'),
	array('label' => '(GMT-3:00) America/Catamarca (Argentine Time)', 'value' => 'America/Catamarca'),
	array('label' => '(GMT-3:00) America/Cayenne (French Guiana Time)', 'value' => 'America/Cayenne'),
	array('label' => '(GMT-3:00) America/Cordoba (Argentine Time)', 'value' => 'America/Cordoba'),
	array('label' => '(GMT-3:00) America/Fortaleza (Brasilia Time)', 'value' => 'America/Fortaleza'),
	array('label' => '(GMT-3:00) America/Godthab (Western Greenland Time)', 'value' => 'America/Godthab'),
	array('label' => '(GMT-3:00) America/Jujuy (Argentine Time)', 'value' => 'America/Jujuy'),
	array('label' => '(GMT-3:00) America/Maceio (Brasilia Time)', 'value' => 'America/Maceio'),
	array('label' => '(GMT-3:00) America/Mendoza (Argentine Time)', 'value' => 'America/Mendoza'),
	array('label' => '(GMT-3:00) America/Miquelon (Pierre & Miquelon Standard Time)', 'value' => 'America/Miquelon'),
	array('label' => '(GMT-3:00) America/Montevideo (Uruguay Time)', 'value' => 'America/Montevideo'),
	array('label' => '(GMT-3:00) America/Paramaribo (Suriname Time)', 'value' => 'America/Paramaribo'),
	array('label' => '(GMT-3:00) America/Recife (Brasilia Time)', 'value' => 'America/Recife'),
	array('label' => '(GMT-3:00) America/Rosario (Argentine Time)', 'value' => 'America/Rosario'),
	array('label' => '(GMT-3:00) America/Santarem (Brasilia Time)', 'value' => 'America/Santarem'),
	array('label' => '(GMT-3:00) America/Sao Paulo (Brasilia Time)', 'value' => 'America/Sao Paulo'),
	array('label' => '(GMT-3:00) Antarctica/Rothera (Rothera Time)', 'value' => 'Antarctica/Rothera'),
	array('label' => '(GMT-3:00) Brazil/East (Brasilia Time)', 'value' => 'Brazil/East'),
	array('label' => '(GMT-2:00) America/Noronha (Fernando de Noronha Time)', 'value' => 'America/Noronha'),
	array('label' => '(GMT-2:00) Atlantic/South Georgia (South Georgia Standard Time)', 'value' => 'Atlantic/South Georgia'),
	array('label' => '(GMT-2:00) Brazil/DeNoronha (Fernando de Noronha Time)', 'value' => 'Brazil/DeNoronha'),
	array('label' => '(GMT-1:00) America/Scoresbysund (Eastern Greenland Time)', 'value' => 'America/Scoresbysund'),
	array('label' => '(GMT-1:00) Atlantic/Azores (Azores Time)', 'value' => 'Atlantic/Azores'),
	array('label' => '(GMT-1:00) Atlantic/Cape Verde (Cape Verde Time)', 'value' => 'Atlantic/Cape Verde'),
	array('label' => '(GMT+0:00) Africa/Abidjan (Greenwich Mean Time)', 'value' => 'Africa/Abidjan'),
	array('label' => '(GMT+0:00) Africa/Accra (Ghana Mean Time)', 'value' => 'Africa/Accra'),
	array('label' => '(GMT+0:00) Africa/Bamako (Greenwich Mean Time)', 'value' => 'Africa/Bamako'),
	array('label' => '(GMT+0:00) Africa/Banjul (Greenwich Mean Time)', 'value' => 'Africa/Banjul'),
	array('label' => '(GMT+0:00) Africa/Bissau (Greenwich Mean Time)', 'value' => 'Africa/Bissau'),
	array('label' => '(GMT+0:00) Africa/Casablanca (Western European Time)', 'value' => 'Africa/Casablanca'),
	array('label' => '(GMT+0:00) Africa/Conakry (Greenwich Mean Time)', 'value' => 'Africa/Conakry'),
	array('label' => '(GMT+0:00) Africa/Dakar (Greenwich Mean Time)', 'value' => 'Africa/Dakar'),
	array('label' => '(GMT+0:00) Africa/El Aaiun (Western European Time)', 'value' => 'Africa/El Aaiun'),
	array('label' => '(GMT+0:00) Africa/Freetown (Greenwich Mean Time)', 'value' => 'Africa/Freetown'),
	array('label' => '(GMT+0:00) Africa/Lome (Greenwich Mean Time)', 'value' => 'Africa/Lome'),
	array('label' => '(GMT+0:00) Africa/Monrovia (Greenwich Mean Time)', 'value' => 'Africa/Monrovia'),
	array('label' => '(GMT+0:00) Africa/Nouakchott (Greenwich Mean Time)', 'value' => 'Africa/Nouakchott'),
	array('label' => '(GMT+0:00) Africa/Ouagadougou (Greenwich Mean Time)', 'value' => 'Africa/Ouagadougou'),
	array('label' => '(GMT+0:00) Africa/Sao Tome (Greenwich Mean Time)', 'value' => 'Africa/Sao Tome'),
	array('label' => '(GMT+0:00) Africa/Timbuktu (Greenwich Mean Time)', 'value' => 'Africa/Timbuktu'),
	array('label' => '(GMT+0:00) America/Danmarkshavn (Greenwich Mean Time)', 'value' => 'America/Danmarkshavn'),
	array('label' => '(GMT+0:00) Atlantic/Canary (Western European Time)', 'value' => 'Atlantic/Canary'),
	array('label' => '(GMT+0:00) Atlantic/Faeroe (Western European Time)', 'value' => 'Atlantic/Faeroe'),
	array('label' => '(GMT+0:00) Atlantic/Faroe (Western European Time)', 'value' => 'Atlantic/Faroe'),
	array('label' => '(GMT+0:00) Atlantic/Madeira (Western European Time)', 'value' => 'Atlantic/Madeira'),
	array('label' => '(GMT+0:00) Atlantic/Reykjavik (Greenwich Mean Time)', 'value' => 'Atlantic/Reykjavik'),
	array('label' => '(GMT+0:00) Atlantic/St Helena (Greenwich Mean Time)', 'value' => 'Atlantic/St Helena'),
	array('label' => '(GMT+0:00) Europe/Belfast (Greenwich Mean Time)', 'value' => 'Europe/Belfast'),
	array('label' => '(GMT+0:00) Europe/Dublin (Greenwich Mean Time)', 'value' => 'Europe/Dublin'),
	array('label' => '(GMT+0:00) Europe/Guernsey (Greenwich Mean Time)', 'value' => 'Europe/Guernsey'),
	array('label' => '(GMT+0:00) Europe/Isle of Man (Greenwich Mean Time)', 'value' => 'Europe/Isle of Man'),
	array('label' => '(GMT+0:00) Europe/Jersey (Greenwich Mean Time)', 'value' => 'Europe/Jersey'),
	array('label' => '(GMT+0:00) Europe/Lisbon (Western European Time)', 'value' => 'Europe/Lisbon'),
	array('label' => '(GMT+0:00) Europe/London (Greenwich Mean Time)', 'value' => 'Europe/London'),
	array('label' => '(GMT+1:00) Africa/Algiers (Central European Time)', 'value' => 'Africa/Algiers'),
	array('label' => '(GMT+1:00) Africa/Bangui (Western African Time)', 'value' => 'Africa/Bangui'),
	array('label' => '(GMT+1:00) Africa/Brazzaville (Western African Time)', 'value' => 'Africa/Brazzaville'),
	array('label' => '(GMT+1:00) Africa/Ceuta (Central European Time)', 'value' => 'Africa/Ceuta'),
	array('label' => '(GMT+1:00) Africa/Douala (Western African Time)', 'value' => 'Africa/Douala'),
	array('label' => '(GMT+1:00) Africa/Kinshasa (Western African Time)', 'value' => 'Africa/Kinshasa'),
	array('label' => '(GMT+1:00) Africa/Lagos (Western African Time)', 'value' => 'Africa/Lagos'),
	array('label' => '(GMT+1:00) Africa/Libreville (Western African Time)', 'value' => 'Africa/Libreville'),
	array('label' => '(GMT+1:00) Africa/Luanda (Western African Time)', 'value' => 'Africa/Luanda'),
	array('label' => '(GMT+1:00) Africa/Malabo (Western African Time)', 'value' => 'Africa/Malabo'),
	array('label' => '(GMT+1:00) Africa/Ndjamena (Western African Time)', 'value' => 'Africa/Ndjamena'),
	array('label' => '(GMT+1:00) Africa/Niamey (Western African Time)', 'value' => 'Africa/Niamey'),
	array('label' => '(GMT+1:00) Africa/Porto-Novo (Western African Time)', 'value' => 'Africa/Porto-Novo'),
	array('label' => '(GMT+1:00) Africa/Tunis (Central European Time)', 'value' => 'Africa/Tunis'),
	array('label' => '(GMT+1:00) Africa/Windhoek (Western African Time)', 'value' => 'Africa/Windhoek'),
	array('label' => '(GMT+1:00) Arctic/Longyearbyen (Central European Time)', 'value' => 'Arctic/Longyearbyen'),
	array('label' => '(GMT+1:00) Atlantic/Jan Mayen (Central European Time)', 'value' => 'Atlantic/Jan Mayen'),
	array('label' => '(GMT+1:00) Europe/Amsterdam (Central European Time)', 'value' => 'Europe/Amsterdam'),
	array('label' => '(GMT+1:00) Europe/Andorra (Central European Time)', 'value' => 'Europe/Andorra'),
	array('label' => '(GMT+1:00) Europe/Belgrade (Central European Time)', 'value' => 'Europe/Belgrade'),
	array('label' => '(GMT+1:00) Europe/Berlin (Central European Time)', 'value' => 'Europe/Berlin'),
	array('label' => '(GMT+1:00) Europe/Bratislava (Central European Time)', 'value' => 'Europe/Bratislava'),
	array('label' => '(GMT+1:00) Europe/Brussels (Central European Time)', 'value' => 'Europe/Brussels'),
	array('label' => '(GMT+1:00) Europe/Budapest (Central European Time)', 'value' => 'Europe/Budapest'),
	array('label' => '(GMT+1:00) Europe/Copenhagen (Central European Time)', 'value' => 'Europe/Copenhagen'),
	array('label' => '(GMT+1:00) Europe/Gibraltar (Central European Time)', 'value' => 'Europe/Gibraltar'),
	array('label' => '(GMT+1:00) Europe/Ljubljana (Central European Time)', 'value' => 'Europe/Ljubljana'),
	array('label' => '(GMT+1:00) Europe/Luxembourg (Central European Time)', 'value' => 'Europe/Luxembourg'),
	array('label' => '(GMT+1:00) Europe/Madrid (Central European Time)', 'value' => 'Europe/Madrid'),
	array('label' => '(GMT+1:00) Europe/Malta (Central European Time)', 'value' => 'Europe/Malta'),
	array('label' => '(GMT+1:00) Europe/Monaco (Central European Time)', 'value' => 'Europe/Monaco'),
	array('label' => '(GMT+1:00) Europe/Oslo (Central European Time)', 'value' => 'Europe/Oslo'),
	array('label' => '(GMT+1:00) Europe/Paris (Central European Time)', 'value' => 'Europe/Paris'),
	array('label' => '(GMT+1:00) Europe/Podgorica (Central European Time)', 'value' => 'Europe/Podgorica'),
	array('label' => '(GMT+1:00) Europe/Prague (Central European Time)', 'value' => 'Europe/Prague'),
	array('label' => '(GMT+1:00) Europe/Rome (Central European Time)', 'value' => 'Europe/Rome'),
	array('label' => '(GMT+1:00) Europe/San Marino (Central European Time)', 'value' => 'Europe/San Marino'),
	array('label' => '(GMT+1:00) Europe/Sarajevo (Central European Time)', 'value' => 'Europe/Sarajevo'),
	array('label' => '(GMT+1:00) Europe/Skopje (Central European Time)', 'value' => 'Europe/Skopje'),
	array('label' => '(GMT+1:00) Europe/Stockholm (Central European Time)', 'value' => 'Europe/Stockholm'),
	array('label' => '(GMT+1:00) Europe/Tirane (Central European Time)', 'value' => 'Europe/Tirane'),
	array('label' => '(GMT+1:00) Europe/Vaduz (Central European Time)', 'value' => 'Europe/Vaduz'),
	array('label' => '(GMT+1:00) Europe/Vatican (Central European Time)', 'value' => 'Europe/Vatican'),
	array('label' => '(GMT+1:00) Europe/Vienna (Central European Time)', 'value' => 'Europe/Vienna'),
	array('label' => '(GMT+1:00) Europe/Warsaw (Central European Time)', 'value' => 'Europe/Warsaw'),
	array('label' => '(GMT+1:00) Europe/Zagreb (Central European Time)', 'value' => 'Europe/Zagreb'),
	array('label' => '(GMT+1:00) Europe/Zurich (Central European Time)', 'value' => 'Europe/Zurich'),
	array('label' => '(GMT+2:00) Africa/Blantyre (Central African Time)', 'value' => 'Africa/Blantyre'),
	array('label' => '(GMT+2:00) Africa/Bujumbura (Central African Time)', 'value' => 'Africa/Bujumbura'),
	array('label' => '(GMT+2:00) Africa/Cairo (Eastern European Time)', 'value' => 'Africa/Cairo'),
	array('label' => '(GMT+2:00) Africa/Gaborone (Central African Time)', 'value' => 'Africa/Gaborone'),
	array('label' => '(GMT+2:00) Africa/Harare (Central African Time)', 'value' => 'Africa/Harare'),
	array('label' => '(GMT+2:00) Africa/Johannesburg (South Africa Standard Time)', 'value' => 'Africa/Johannesburg'),
	array('label' => '(GMT+2:00) Africa/Kigali (Central African Time)', 'value' => 'Africa/Kigali'),
	array('label' => '(GMT+2:00) Africa/Lubumbashi (Central African Time)', 'value' => 'Africa/Lubumbashi'),
	array('label' => '(GMT+2:00) Africa/Lusaka (Central African Time)', 'value' => 'Africa/Lusaka'),
	array('label' => '(GMT+2:00) Africa/Maputo (Central African Time)', 'value' => 'Africa/Maputo'),
	array('label' => '(GMT+2:00) Africa/Maseru (South Africa Standard Time)', 'value' => 'Africa/Maseru'),
	array('label' => '(GMT+2:00) Africa/Mbabane (South Africa Standard Time)', 'value' => 'Africa/Mbabane'),
	array('label' => '(GMT+2:00) Africa/Tripoli (Eastern European Time)', 'value' => 'Africa/Tripoli'),
	array('label' => '(GMT+2:00) Asia/Amman (Eastern European Time)', 'value' => 'Asia/Amman'),
	array('label' => '(GMT+2:00) Asia/Beirut (Eastern European Time)', 'value' => 'Asia/Beirut'),
	array('label' => '(GMT+2:00) Asia/Damascus (Eastern European Time)', 'value' => 'Asia/Damascus'),
	array('label' => '(GMT+2:00) Asia/Gaza (Eastern European Time)', 'value' => 'Asia/Gaza'),
	array('label' => '(GMT+2:00) Asia/Istanbul (Eastern European Time)', 'value' => 'Asia/Istanbul'),
	array('label' => '(GMT+2:00) Asia/Jerusalem (Israel Standard Time)', 'value' => 'Asia/Jerusalem'),
	array('label' => '(GMT+2:00) Asia/Nicosia (Eastern European Time)', 'value' => 'Asia/Nicosia'),
	array('label' => '(GMT+2:00) Asia/Tel Aviv (Israel Standard Time)', 'value' => 'Asia/Tel Aviv'),
	array('label' => '(GMT+2:00) Europe/Athens (Eastern European Time)', 'value' => 'Europe/Athens'),
	array('label' => '(GMT+2:00) Europe/Bucharest (Eastern European Time)', 'value' => 'Europe/Bucharest'),
	array('label' => '(GMT+2:00) Europe/Chisinau (Eastern European Time)', 'value' => 'Europe/Chisinau'),
	array('label' => '(GMT+2:00) Europe/Helsinki (Eastern European Time)', 'value' => 'Europe/Helsinki'),
	array('label' => '(GMT+2:00) Europe/Istanbul (Eastern European Time)', 'value' => 'Europe/Istanbul'),
	array('label' => '(GMT+2:00) Europe/Kaliningrad (Eastern European Time)', 'value' => 'Europe/Kaliningrad'),
	array('label' => '(GMT+2:00) Europe/Kiev (Eastern European Time)', 'value' => 'Europe/Kiev'),
	array('label' => '(GMT+2:00) Europe/Mariehamn (Eastern European Time)', 'value' => 'Europe/Mariehamn'),
	array('label' => '(GMT+2:00) Europe/Minsk (Eastern European Time)', 'value' => 'Europe/Minsk'),
	array('label' => '(GMT+2:00) Europe/Nicosia (Eastern European Time)', 'value' => 'Europe/Nicosia'),
	array('label' => '(GMT+2:00) Europe/Riga (Eastern European Time)', 'value' => 'Europe/Riga'),
	array('label' => '(GMT+2:00) Europe/Simferopol (Eastern European Time)', 'value' => 'Europe/Simferopol'),
	array('label' => '(GMT+2:00) Europe/Sofia (Eastern European Time)', 'value' => 'Europe/Sofia'),
	array('label' => '(GMT+2:00) Europe/Tallinn (Eastern European Time)', 'value' => 'Europe/Tallinn'),
	array('label' => '(GMT+2:00) Europe/Tiraspol (Eastern European Time)', 'value' => 'Europe/Tiraspol'),
	array('label' => '(GMT+2:00) Europe/Uzhgorod (Eastern European Time)', 'value' => 'Europe/Uzhgorod'),
	array('label' => '(GMT+2:00) Europe/Vilnius (Eastern European Time)', 'value' => 'Europe/Vilnius'),
	array('label' => '(GMT+2:00) Europe/Zaporozhye (Eastern European Time)', 'value' => 'Europe/Zaporozhye'),
	array('label' => '(GMT+3:00) Africa/Addis Ababa (Eastern African Time)', 'value' => 'Africa/Addis Ababa'),
	array('label' => '(GMT+3:00) Africa/Asmara (Eastern African Time)', 'value' => 'Africa/Asmara'),
	array('label' => '(GMT+3:00) Africa/Asmera (Eastern African Time)', 'value' => 'Africa/Asmera'),
	array('label' => '(GMT+3:00) Africa/Dar es Salaam (Eastern African Time)', 'value' => 'Africa/Dar es Salaam'),
	array('label' => '(GMT+3:00) Africa/Djibouti (Eastern African Time)', 'value' => 'Africa/Djibouti'),
	array('label' => '(GMT+3:00) Africa/Kampala (Eastern African Time)', 'value' => 'Africa/Kampala'),
	array('label' => '(GMT+3:00) Africa/Khartoum (Eastern African Time)', 'value' => 'Africa/Khartoum'),
	array('label' => '(GMT+3:00) Africa/Mogadishu (Eastern African Time)', 'value' => 'Africa/Mogadishu'),
	array('label' => '(GMT+3:00) Africa/Nairobi (Eastern African Time)', 'value' => 'Africa/Nairobi'),
	array('label' => '(GMT+3:00) Antarctica/Syowa (Syowa Time)', 'value' => 'Antarctica/Syowa'),
	array('label' => '(GMT+3:00) Asia/Aden (Arabia Standard Time)', 'value' => 'Asia/Aden'),
	array('label' => '(GMT+3:00) Asia/Baghdad (Arabia Standard Time)', 'value' => 'Asia/Baghdad'),
	array('label' => '(GMT+3:00) Asia/Bahrain (Arabia Standard Time)', 'value' => 'Asia/Bahrain'),
	array('label' => '(GMT+3:00) Asia/Kuwait (Arabia Standard Time)', 'value' => 'Asia/Kuwait'),
	array('label' => '(GMT+3:00) Asia/Qatar (Arabia Standard Time)', 'value' => 'Asia/Qatar'),
	array('label' => '(GMT+3:00) Europe/Moscow (Moscow Standard Time)', 'value' => 'Europe/Moscow'),
	array('label' => '(GMT+3:00) Europe/Volgograd (Volgograd Time)', 'value' => 'Europe/Volgograd'),
	array('label' => '(GMT+3:00) Indian/Antananarivo (Eastern African Time)', 'value' => 'Indian/Antananarivo'),
	array('label' => '(GMT+3:00) Indian/Comoro (Eastern African Time)', 'value' => 'Indian/Comoro'),
	array('label' => '(GMT+3:00) Indian/Mayotte (Eastern African Time)', 'value' => 'Indian/Mayotte'),
	array('label' => '(GMT+3:30) Asia/Tehran (Iran Standard Time)', 'value' => 'Asia/Tehran'),
	array('label' => '(GMT+4:00) Asia/Baku (Azerbaijan Time)', 'value' => 'Asia/Baku'),
	array('label' => '(GMT+4:00) Asia/Dubai (Gulf Standard Time)', 'value' => 'Asia/Dubai'),
	array('label' => '(GMT+4:00) Asia/Muscat (Gulf Standard Time)', 'value' => 'Asia/Muscat'),
	array('label' => '(GMT+4:00) Asia/Tbilisi (Georgia Time)', 'value' => 'Asia/Tbilisi'),
	array('label' => '(GMT+4:00) Asia/Yerevan (Armenia Time)', 'value' => 'Asia/Yerevan'),
	array('label' => '(GMT+4:00) Europe/Samara (Samara Time)', 'value' => 'Europe/Samara'),
	array('label' => '(GMT+4:00) Indian/Mahe (Seychelles Time)', 'value' => 'Indian/Mahe'),
	array('label' => '(GMT+4:00) Indian/Mauritius (Mauritius Time)', 'value' => 'Indian/Mauritius'),
	array('label' => '(GMT+4:00) Indian/Reunion (Reunion Time)', 'value' => 'Indian/Reunion'),
	array('label' => '(GMT+4:30) Asia/Kabul (Afghanistan Time)', 'value' => 'Asia/Kabul'),
	array('label' => '(GMT+5:00) Asia/Aqtau (Aqtau Time)', 'value' => 'Asia/Aqtau'),
	array('label' => '(GMT+5:00) Asia/Aqtobe (Aqtobe Time)', 'value' => 'Asia/Aqtobe'),
	array('label' => '(GMT+5:00) Asia/Ashgabat (Turkmenistan Time)', 'value' => 'Asia/Ashgabat'),
	array('label' => '(GMT+5:00) Asia/Ashkhabad (Turkmenistan Time)', 'value' => 'Asia/Ashkhabad'),
	array('label' => '(GMT+5:00) Asia/Dushanbe (Tajikistan Time)', 'value' => 'Asia/Dushanbe'),
	array('label' => '(GMT+5:00) Asia/Karachi (Pakistan Time)', 'value' => 'Asia/Karachi'),
	array('label' => '(GMT+5:00) Asia/Oral (Oral Time)', 'value' => 'Asia/Oral'),
	array('label' => '(GMT+5:00) Asia/Samarkand (Uzbekistan Time)', 'value' => 'Asia/Samarkand'),
	array('label' => '(GMT+5:00) Asia/Tashkent (Uzbekistan Time)', 'value' => 'Asia/Tashkent'),
	array('label' => '(GMT+5:00) Asia/Yekaterinburg (Yekaterinburg Time)', 'value' => 'Asia/Yekaterinburg'),
	array('label' => '(GMT+5:00) Indian/Kerguelen (French Southern & Antarctic Lands Time)', 'value' => 'Indian/Kerguelen'),
	array('label' => '(GMT+5:00) Indian/Maldives (Maldives Time)', 'value' => 'Indian/Maldives'),
	array('label' => '(GMT+5:30) Asia/Calcutta (India Standard Time)', 'value' => 'Asia/Calcutta'),
	array('label' => '(GMT+5:30) Asia/Colombo (India Standard Time)', 'value' => 'Asia/Colombo'),
	array('label' => '(GMT+5:30) Asia/Kolkata (India Standard Time)', 'value' => 'Asia/Kolkata'),
	array('label' => '(GMT+5:45) Asia/Katmandu (Nepal Time)', 'value' => 'Asia/Katmandu'),
	array('label' => '(GMT+6:00) Antarctica/Mawson (Mawson Time)', 'value' => 'Antarctica/Mawson'),
	array('label' => '(GMT+6:00) Antarctica/Vostok (Vostok Time)', 'value' => 'Antarctica/Vostok'),
	array('label' => '(GMT+6:00) Asia/Almaty (Alma-Ata Time)', 'value' => 'Asia/Almaty'),
	array('label' => '(GMT+6:00) Asia/Bishkek (Kirgizstan Time)', 'value' => 'Asia/Bishkek'),
	array('label' => '(GMT+6:00) Asia/Dacca (Bangladesh Time)', 'value' => 'Asia/Dacca'),
	array('label' => '(GMT+6:00) Asia/Dhaka (Bangladesh Time)', 'value' => 'Asia/Dhaka'),
	array('label' => '(GMT+6:00) Asia/Novosibirsk (Novosibirsk Time)', 'value' => 'Asia/Novosibirsk'),
	array('label' => '(GMT+6:00) Asia/Omsk (Omsk Time)', 'value' => 'Asia/Omsk'),
	array('label' => '(GMT+6:00) Asia/Qyzylorda (Qyzylorda Time)', 'value' => 'Asia/Qyzylorda'),
	array('label' => '(GMT+6:00) Asia/Thimbu (Bhutan Time)', 'value' => 'Asia/Thimbu'),
	array('label' => '(GMT+6:00) Asia/Thimphu (Bhutan Time)', 'value' => 'Asia/Thimphu'),
	array('label' => '(GMT+6:00) Indian/Chagos (Indian Ocean Territory Time)', 'value' => 'Indian/Chagos'),
	array('label' => '(GMT+6:30) Asia/Rangoon (Myanmar Time)', 'value' => 'Asia/Rangoon'),
	array('label' => '(GMT+6:30) Indian/Cocos (Cocos Islands Time)', 'value' => 'Indian/Cocos'),
	array('label' => '(GMT+7:00) Antarctica/Davis (Davis Time)', 'value' => 'Antarctica/Davis'),
	array('label' => '(GMT+7:00) Asia/Bangkok (Indochina Time)', 'value' => 'Asia/Bangkok'),
	array('label' => '(GMT+7:00) Asia/Ho Chi Minh (Indochina Time)', 'value' => 'Asia/Ho Chi Minh'),
	array('label' => '(GMT+7:00) Asia/Hovd (Hovd Time)', 'value' => 'Asia/Hovd'),
	array('label' => '(GMT+7:00) Asia/Jakarta (West Indonesia Time)', 'value' => 'Asia/Jakarta'),
	array('label' => '(GMT+7:00) Asia/Krasnoyarsk (Krasnoyarsk Time)', 'value' => 'Asia/Krasnoyarsk'),
	array('label' => '(GMT+7:00) Asia/Phnom Penh (Indochina Time)', 'value' => 'Asia/Phnom Penh'),
	array('label' => '(GMT+7:00) Asia/Pontianak (West Indonesia Time)', 'value' => 'Asia/Pontianak'),
	array('label' => '(GMT+7:00) Asia/Saigon (Indochina Time)', 'value' => 'Asia/Saigon'),
	array('label' => '(GMT+7:00) Asia/Vientiane (Indochina Time)', 'value' => 'Asia/Vientiane'),
	array('label' => '(GMT+7:00) Indian/Christmas (Christmas Island Time)', 'value' => 'Indian/Christmas'),
	array('label' => '(GMT+8:00) Antarctica/Casey (Western Standard Time (Australia))', 'value' => 'Antarctica/Casey'),
	array('label' => '(GMT+8:00) Asia/Brunei (Brunei Time)', 'value' => 'Asia/Brunei'),
	array('label' => '(GMT+8:00) Asia/Choibalsan (Choibalsan Time)', 'value' => 'Asia/Choibalsan'),
	array('label' => '(GMT+8:00) Asia/Chongqing (China Standard Time)', 'value' => 'Asia/Chongqing'),
	array('label' => '(GMT+8:00) Asia/Chungking (China Standard Time)', 'value' => 'Asia/Chungking'),
	array('label' => '(GMT+8:00) Asia/Harbin (China Standard Time)', 'value' => 'Asia/Harbin'),
	array('label' => '(GMT+8:00) Asia/Hong Kong (Hong Kong Time)', 'value' => 'Asia/Hong Kong'),
	array('label' => '(GMT+8:00) Asia/Irkutsk (Irkutsk Time)', 'value' => 'Asia/Irkutsk'),
	array('label' => '(GMT+8:00) Asia/Kashgar (China Standard Time)', 'value' => 'Asia/Kashgar'),
	array('label' => '(GMT+8:00) Asia/Kuala Lumpur (Malaysia Time)', 'value' => 'Asia/Kuala Lumpur'),
	array('label' => '(GMT+8:00) Asia/Kuching (Malaysia Time)', 'value' => 'Asia/Kuching'),
	array('label' => '(GMT+8:00) Asia/Macao (China Standard Time)', 'value' => 'Asia/Macao'),
	array('label' => '(GMT+8:00) Asia/Macau (China Standard Time)', 'value' => 'Asia/Macau'),
	array('label' => '(GMT+8:00) Asia/Makassar (Central Indonesia Time)', 'value' => 'Asia/Makassar'),
	array('label' => '(GMT+8:00) Asia/Manila (Philippines Time)', 'value' => 'Asia/Manila'),
	array('label' => '(GMT+8:00) Asia/Shanghai (China Standard Time)', 'value' => 'Asia/Shanghai'),
	array('label' => '(GMT+8:00) Asia/Singapore (Singapore Time)', 'value' => 'Asia/Singapore'),
	array('label' => '(GMT+8:00) Asia/Taipei (China Standard Time)', 'value' => 'Asia/Taipei'),
	array('label' => '(GMT+8:00) Asia/Ujung Pandang (Central Indonesia Time)', 'value' => 'Asia/Ujung Pandang'),
	array('label' => '(GMT+8:00) Asia/Ulaanbaatar (Ulaanbaatar Time)', 'value' => 'Asia/Ulaanbaatar'),
	array('label' => '(GMT+8:00) Asia/Ulan Bator (Ulaanbaatar Time)', 'value' => 'Asia/Ulan Bator'),
	array('label' => '(GMT+8:00) Asia/Urumqi (China Standard Time)', 'value' => 'Asia/Urumqi'),
	array('label' => '(GMT+8:00) Australia/Perth (Western Standard Time (Australia))', 'value' => 'Australia/Perth'),
	array('label' => '(GMT+8:00) Australia/West (Western Standard Time (Australia))', 'value' => 'Australia/West'),
	array('label' => '(GMT+8:45) Australia/Eucla (Central Western Standard Time (Australia))', 'value' => 'Australia/Eucla'),
	array('label' => '(GMT+9:00) Asia/Dili (Timor-Leste Time)', 'value' => 'Asia/Dili'),
	array('label' => '(GMT+9:00) Asia/Jayapura (East Indonesia Time)', 'value' => 'Asia/Jayapura'),
	array('label' => '(GMT+9:00) Asia/Pyongyang (Korea Standard Time)', 'value' => 'Asia/Pyongyang'),
	array('label' => '(GMT+9:00) Asia/Seoul (Korea Standard Time)', 'value' => 'Asia/Seoul'),
	array('label' => '(GMT+9:00) Asia/Tokyo (Japan Standard Time)', 'value' => 'Asia/Tokyo'),
	array('label' => '(GMT+9:00) Asia/Yakutsk (Yakutsk Time)', 'value' => 'Asia/Yakutsk'),
	array('label' => '(GMT+9:30) Australia/Adelaide (Central Standard Time (South Australia))', 'value' => 'Australia/Adelaide'),
	array('label' => '(GMT+9:30) Australia/Broken Hill (Central Standard Time (South Australia/New South Wales))', 'value' => 'Australia/Broken Hill'),
	array('label' => '(GMT+9:30) Australia/Darwin (Central Standard Time (Northern Territory))', 'value' => 'Australia/Darwin'),
	array('label' => '(GMT+9:30) Australia/North (Central Standard Time (Northern Territory))', 'value' => 'Australia/North'),
	array('label' => '(GMT+9:30) Australia/South (Central Standard Time (South Australia))', 'value' => 'Australia/South'),
	array('label' => '(GMT+9:30) Australia/Yancowinna (Central Standard Time (South Australia/New South Wales))', 'value' => 'Australia/Yancowinna'),
	array('label' => '(GMT+10:00) Antarctica/DumontDUrville (Dumont-d\'Urville Time)', 'value' => 'Antarctica/DumontDUrville'),
	array('label' => '(GMT+10:00) Asia/Sakhalin (Sakhalin Time)', 'value' => 'Asia/Sakhalin'),
	array('label' => '(GMT+10:00) Asia/Vladivostok (Vladivostok Time)', 'value' => 'Asia/Vladivostok'),
	array('label' => '(GMT+10:00) Australia/ACT (Eastern Standard Time (New South Wales))', 'value' => 'Australia/ACT'),
	array('label' => '(GMT+10:00) Australia/Brisbane (Eastern Standard Time (Queensland))', 'value' => 'Australia/Brisbane'),
	array('label' => '(GMT+10:00) Australia/Canberra (Eastern Standard Time (New South Wales))', 'value' => 'Australia/Canberra'),
	array('label' => '(GMT+10:00) Australia/Currie (Eastern Standard Time (New South Wales))', 'value' => 'Australia/Currie'),
	array('label' => '(GMT+10:00) Australia/Hobart (Eastern Standard Time (Tasmania))', 'value' => 'Australia/Hobart'),
	array('label' => '(GMT+10:00) Australia/Lindeman (Eastern Standard Time (Queensland))', 'value' => 'Australia/Lindeman'),
	array('label' => '(GMT+10:00) Australia/Melbourne (Eastern Standard Time (Victoria))', 'value' => 'Australia/Melbourne'),
	array('label' => '(GMT+10:00) Australia/NSW (Eastern Standard Time (New South Wales))', 'value' => 'Australia/NSW'),
	array('label' => '(GMT+10:00) Australia/Queensland (Eastern Standard Time (Queensland))', 'value' => 'Australia/Queensland'),
	array('label' => '(GMT+10:00) Australia/Sydney (Eastern Standard Time (New South Wales))', 'value' => 'Australia/Sydney'),
	array('label' => '(GMT+10:00) Australia/Tasmania (Eastern Standard Time (Tasmania))', 'value' => 'Australia/Tasmania'),
	array('label' => '(GMT+10:00) Australia/Victoria (Eastern Standard Time (Victoria))', 'value' => 'Australia/Victoria'),
	array('label' => '(GMT+10:30) Australia/LHI (Lord Howe Standard Time)', 'value' => 'Australia/LHI'),
	array('label' => '(GMT+10:30) Australia/Lord Howe (Lord Howe Standard Time)', 'value' => 'Australia/Lord Howe'),
	array('label' => '(GMT+11:00) Asia/Magadan (Magadan Time)', 'value' => 'Asia/Magadan'),
	array('label' => '(GMT+12:00) Antarctica/McMurdo (New Zealand Standard Time)', 'value' => 'Antarctica/McMurdo'),
	array('label' => '(GMT+12:00) Antarctica/South Pole (New Zealand Standard Time)', 'value' => 'Antarctica/South Pole'),
	array('label' => '(GMT+12:00) Asia/Anadyr (Anadyr Time)', 'value' => 'Asia/Anadyr'),
	array('label' => '(GMT+12:00) Asia/Kamchatka (Petropavlovsk-Kamchatski Time)', 'value' => 'Asia/Kamchatka')
	);
}
