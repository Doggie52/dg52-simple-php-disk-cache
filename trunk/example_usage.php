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

	include( 'class.DiskCache.php' );

	// Instantiate cache class
	$cache = DiskCache::getInstance();
	DiskCache::$expirationTime = 5;

	$cache->foo = "foo!";
	$cache->bar = "bar!";
	$cache->{'test test'} = "lol!";
	$cache->array = array( 'one' => 'yes', 'two' => 'no' );

	var_dump( $cache->foo );
	var_dump( $cache->bar );
	var_dump( $cache->{'test test'} );
	var_dump( $cache->array );

	echo $cache->prune() . " entries were pruned, ";
	echo $cache->cacheHits . " entries were hit.";
?>