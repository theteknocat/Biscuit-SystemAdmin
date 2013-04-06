<?php
/**
 * Model the extensions table
 *
 * @package Modules
 * @subpackage SystemAdmin
 * @author Peter Epp
 * @version $Id: extension.php 13843 2011-07-27 19:45:49Z teknocat $
 */
class Extension extends AbstractModel {
	private $_is_core_extension = null;
	/**
	 * Whether or not the module comes with the framework
	 *
	 * @return void
	 * @author Peter Epp
	 */
	public function is_core_extension() {
		if ($this->_is_core_extension === null) {
			$my_folder = 'extension/'.AkInflector::underscore($this->name());
			$full_path = Crumbs::file_exists_in_load_path($my_folder, SITE_ROOT_RELATIVE);
			$this->_is_core_extension = preg_match('/\/framework\//',$full_path);
		}
		return $this->_is_core_extension;
	}
	/**
	 * Whether or not the extension is installed. This is true if it's in the database (has an ID), false if only it's files exist
	 *
	 * @return void
	 * @author Peter Epp
	 */
	public function installed() {
		return !$this->is_new();
	}
}
