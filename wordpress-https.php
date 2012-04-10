<?php
/**
 Plugin Name: WordPress HTTPS
 Plugin URI: http://mvied.com/projects/wordpress-https/
 Description: WordPress HTTPS is intended to be an all-in-one solution to using SSL on WordPress sites.
 Author: Mike Ems
 Version: 3.0
 Author URI: http://mvied.com/
 */

$include_paths = array(
	dirname(__FILE__),
	dirname(__FILE__) . '/lib',
	get_include_path()
);
set_include_path(implode(PATH_SEPARATOR, $include_paths));

require_once('WordPressHTTPS.php');

if ( function_exists('get_bloginfo') && ! defined('WP_UNINSTALL_PLUGIN') ) {
	$wordpress_https = new WordPressHTTPS;
	$wordpress_https->set(array(
		'slug'             => 'wordpress-https',
		'version'          => '3.0',
		'plugin_url'       => plugins_url('', __FILE__),
		'directory'        => dirname(__FILE__),
		'module_directory' => dirname(__FILE__) . '/lib/WordPressHTTPS/Module/',
	));

	//Load Modules
	$wordpress_https->loadModules();

	// If WPHTTPS_RESET global is defined, run reset method
	if ( defined('WPHTTPS_RESET') && constant('WPHTTPS_RESET') == true ) {
		$wordpress_https->reset();
	}

	// Initialize Plugin
	$wordpress_https->init();

	// Register activation hook. Must be called outside of a class.
	register_activation_hook(__FILE__, array($wordpress_https, 'install'));
}