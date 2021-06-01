<?php
/**
 * Basic autoloader
 *
 * @since    1.0.0
 */
spl_autoload_register('AutoLoader');

/**
 * load class files based on namespace as folder and class as filename
 *
 * @since    1.0.0
 * @param $className
 */
function AutoLoader($className) {

	$base = str_replace('OHERO\\Alerts\\','', $className);

	$name = str_replace('\\',DS, $base);

	$file = PLUGIN_DIR . $name . '.php';

	if ( is_readable( $file ) ) {
		require_once $file;
	}

}