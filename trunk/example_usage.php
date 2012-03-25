<?php

	/**
	 * dG52 PHP Simple Disk Caching class
	 *
	 * @author: Douglas Stridsberg
	 * @email: doggie52@gmail.com
	 * @url: www.douglasstridsberg.com
	 *
	 * Example usage.
	 */

	// Display errors if any occur
	ini_set('display_errors', 1); 
	error_reporting(E_ALL);

	include( 'class_cache.php' );

	// Instantiate cache class
	$cache = DiskCache::getInstance();
	
	$cache->foo = "foo!";
	$cache->bar = "bar!";

	var_dump( $cache->foo );
	var_dump( $cache->bar );

	unset( $cache->foo );
	unset( $cache->bar );

	var_dump( $cache->foo );
	var_dump( $cache->bar );
?>