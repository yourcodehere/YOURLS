<?php

/**
 * MySQL Wrapper
 *
 * @since 2.0
 * @copyright 2009-2014 YOURLS - MIT
 */

namespace YOURLS;

/**
 * Connection with database
 *
 * Load everything (driver and subclasses) to talk with MySQL.
 */
class MySQL {

    /**
     * Pick the right DB class and return an instance
     *
     * @since 1.7
     * @param string $extension Optional: user defined choice
     * @return class $ydb DB class instance
     */
    function set_DB_driver( ) {

        // Auto-pick the driver. Priority: user defined, then PDO, then mysqli, then mysql
        if ( defined( 'DB_DRIVER' ) ) {
            $driver = strtolower( DB_DRIVER ); // accept 'MySQL', 'mySQL', etc
        } elseif ( extension_loaded( 'pdo_mysql' ) ) {
            $driver = 'pdo';
        } elseif ( extension_loaded( 'mysqli' ) ) {
            $driver = 'mysqli';
        } elseif ( extension_loaded( 'mysql' ) ) {
            $driver = 'mysql';
        } else {
            $driver = '';
        }

        // Set the new driver
        if ( in_array( $driver, array( 'mysql', 'mysqli', 'pdo' ) ) ) {
            $class = require_db_files( $driver );
        }

        global $ydb;

        if ( !class_exists( $class, false ) ) {
            $ydb = new stdClass();
            die(
                _( 'YOURLS requires the mysql, mysqli or pdo_mysql PHP extension. No extension found. Check your server config, or contact your host.' ),
                _( 'Fatal error' ),
                503
            );
        }

        do_action( 'set_DB_driver', $driver );

        $ydb = new $class( DB_USER, DB_PASS, DB_NAME, DB_HOST );
        $ydb->DB_driver = $driver;

        debug_log( "Database driver: $driver" );
    }

    /**
     * Load required DB class files
     *
     * This goes in its own function to allow easier unit tests
     *
     * @since 1.7.1
     * @param string $driver DB driver
     * @return string name of the DB class to instantiate
     */
    function require_db_files( $driver ) {
        require_once( INC . '/ezSQL/ez_sql_core.php' );
        require_once( INC . '/ezSQL/ez_sql_core_yourls.php' );
        require_once( INC . '/ezSQL/ez_sql_' . $driver . '.php' );
        require_once( INC . '/ezSQL/ez_sql_' . $driver . '_yourls.php' );

        return 'ezSQL_' . $driver . '_yourls';
    }

    /**
     * Connect to DB
     *
     * @since 1.0
     */
    function db_connect() {
        global $ydb;

        if (   !defined( 'DB_USER' )
            or !defined( 'DB_PASS' )
            or !defined( 'DB_NAME' )
            or !defined( 'DB_HOST' )
        ) die ( _( 'Incorrect DB config, or could not connect to DB' ), _( 'Fatal error' ), 503 );

        // Are we standalone or in the WordPress environment?
        if ( class_exists( 'wpdb', false ) ) {
            /* TODO: should we deprecate this? Follow WP dev in that area */
            $ydb =  new wpdb( DB_USER, DB_PASS, DB_NAME, DB_HOST );
        } else {
            set_DB_driver();
        }

        return $ydb;
    }

    /**
     * Return true if DB server is responding
     *
     * This function is supposed to be called right after get_all_options() has fired. It is not designed (yet) to
     * check for a responding server after several successful operation to check if the server has gone MIA
     *
     * @since 1.7.1
     */
    function is_db_alive() {
        global $ydb;

        $alive = false;
        switch( $ydb->DB_driver ) {
            case 'pdo' :
                $alive = isset( $ydb->dbh );
                break;

            case 'mysql' :
                $alive = ( isset( $ydb->dbh ) && false !== $ydb->dbh );
                break;

            case 'mysqli' :
                $alive = ( null == mysqli_connect_error() );
                break;

            // Custom DB driver & class : delegate check
            default:
                $alive = apply_filter( 'is_db_alive_custom', false );
        }

        return $alive;
    }

    /**
     * Die with a DB error message
     *
     * @TODO in version 1.8 : use a new localized string, specific to the problem (ie: "DB is dead")
     *
     * @since 1.7.1
     */
    function db_dead() {
        // Use any /user/db_error.php file
        if( file_exists( USERDIR . '/db_error.php' ) ) {
            include_once( USERDIR . '/db_error.php' );
            die();
        }

        die( _( 'Incorrect DB config, or could not connect to DB' ), _( 'Fatal error' ), 503 );
    }

}
