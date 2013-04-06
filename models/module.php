<?php
/**
 * Model the modules table
 *
 * @package Modules
 * @subpackage SystemAdmin
 * @author Peter Epp
 * @version $Id: module.php 13843 2011-07-27 19:45:49Z teknocat $
 */
class Module extends AbstractModel {
	private $_is_core_module = null;
	/**
	 * Whether or not the module comes with the framework
	 *
	 * @return void
	 * @author Peter Epp
	 */
	public function is_core_module() {
		if ($this->_is_core_module === null) {
			$my_folder = 'modules/'.AkInflector::underscore($this->name());
			$full_path = Crumbs::file_exists_in_load_path($my_folder, SITE_ROOT_RELATIVE);
			$this->_is_core_module = preg_match('/\/framework\//',$full_path);
		}
		return $this->_is_core_module;
	}
	/**
	 * Full path to the module folder
	 *
	 * @return void
	 * @author Peter Epp
	 */
	public function full_path() {
		return Crumbs::file_exists_in_load_path('modules/'.AkInflector::underscore($this->name()), SITE_ROOT_RELATIVE);
	}
}
