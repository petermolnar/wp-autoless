<?php
/*
Plugin Name: wp-autoless
Plugin URI: https://github.com/petermolnar/wp-autoless
Description:
Version: 0.1
Author: Peter Molnar <hello@petermolnar.net>
Author URI: http://petermolnar.net/
License: GPLv3
*/

/*  Copyright 2016 Peter Molnar ( hello@petermolnar.net )

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 3, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

namespace WP_AUTOLESS;

require __DIR__ . '/vendor/autoload.php';

\add_action ( 'init', 'WP_AUTOLESS\compile' );

function compile () {
	$tdir = \get_template_directory();
	$lessfiles = find_less_files ( $tdir );

	foreach ( $lessfiles as $lessfile ) {
		$lessmtime = filemtime( $lessfile );
		$cssfile = preg_replace( '/\.less$/i', '.css', $lessfile );
		$cssmtime = file_exists ( $cssfile ) ? filemtime( $cssfile ) : '0';

		if ( $cssmtime < $lessmtime ) {
			try {
				$less = new \lessc;
				//$less->setFormatter("classic");
				$less->setFormatter("compressed");
				$less->compileFile( $lessfile, $cssfile );
			}
			catch (Exception $e) {
				debug('Something went wrong with LESS: ' . $e->getMessage(), 4);
			}

			touch ( $cssfile, $lessmtime );
		}
	}
}

function find_less_files ( $dir ) {
	$r = array();
	$list = scandir( $dir );

	foreach ($list as $key => $name ) {
		$path = realpath( $dir . DIRECTORY_SEPARATOR . $name );
		if ( strstr ( $path, 'lessphp') )
			continue;

		if ( is_dir( $path ) && ! in_array ( $name, array( '.', '..' ) ) ) {
			$sub = find_less_files( $path );
			$r = array_merge( $r, $sub );
		}
		elseif ( preg_match ( '/\.less$/i', $name ) ) {
			array_push( $r, $path );
		}
	}

	$r = array_unique( $r );
	return $r;
}


/**
 *
 * debug messages; will only work if WP_DEBUG is on
 * or if the level is LOG_ERR, but that will kill the process
 *
 * @param string $message
 * @param int $level
 *
 * @output log to syslog | wp_die on high level
 * @return false on not taking action, true on log sent
 */
function debug( $message, $level = LOG_NOTICE ) {
	if ( empty( $message ) )
		return false;

	if ( @is_array( $message ) || @is_object ( $message ) )
		$message = json_encode($message);

	$levels = array (
		LOG_EMERG => 0, // system is unusable
		LOG_ALERT => 1, // Alert 	action must be taken immediately
		LOG_CRIT => 2, // Critical 	critical conditions
		LOG_ERR => 3, // Error 	error conditions
		LOG_WARNING => 4, // Warning 	warning conditions
		LOG_NOTICE => 5, // Notice 	normal but significant condition
		LOG_INFO => 6, // Informational 	informational messages
		LOG_DEBUG => 7, // Debug 	debug-level messages
	);

	// number for number based comparison
	// should work with the defines only, this is just a make-it-sure step
	$level_ = $levels [ $level ];

	// in case WordPress debug log has a minimum level
	if ( defined ( '\WP_DEBUG_LEVEL' ) ) {
		$wp_level = $levels [ \WP_DEBUG_LEVEL ];
		if ( $level_ > $wp_level ) {
			return false;
		}
	}

	// ERR, CRIT, ALERT and EMERG
	if ( 3 >= $level_ ) {
		\wp_die( '<h1>Error:</h1>' . '<p>' . $message . '</p>' );
		exit;
	}

	$trace = debug_backtrace();
	$caller = $trace[1];
	$parent = $caller['function'];

	if (isset($caller['class']))
		$parent = $caller['class'] . '::' . $parent;

	if (isset($caller['namespace']))
		$parent = $caller['namespace'] . '::' . $parent;

	return error_log( "{$parent}: {$message}" );
}

