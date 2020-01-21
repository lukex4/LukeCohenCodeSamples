<?php
/**
 * The base configuration for WordPress Multisite (OLT-HA)
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', '$MYSQL_DB');

/** MySQL database username */
define('DB_USER', '$MYSQL_USER');

/** MySQL database password */
define('DB_PASSWORD', '$MYSQL_PASS');

/** MySQL hostname */
define('DB_HOST', '$MYSQL_HOST');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

define('WP_SITEURL', 'http://$WORDPRESS_URL');
define('WP_HOME', 'http://$WORDPRESS_URL');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         '$SALT1');
define('SECURE_AUTH_KEY',  '$SALT2');
define('LOGGED_IN_KEY',    '$SALT3');
define('NONCE_KEY',        '$SALT4');
define('AUTH_SALT',        '$SALT5');
define('SECURE_AUTH_SALT', '$SALT6');
define('LOGGED_IN_SALT',   '$SALT7');
define('NONCE_SALT',       '$SALT8');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
\$table_prefix  = 'soltwp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', false);


/* AWS credentials */
define('DBI_AWS_ACCESS_KEY_ID', '$AWS_ACCESS_KEY_ID');
define('DBI_AWS_SECRET_ACCESS_KEY', '$AWS_SECRET_ACCESS_KEY');


/* Multisite setup */
define('WP_ALLOW_MULTISITE', true);

define('MULTISITE', true);
define('SUBDOMAIN_INSTALL', true);
define('DOMAIN_CURRENT_SITE', 'wp.16to25live.co.uk');
define('PATH_CURRENT_SITE', '/');
define('SITE_ID_CURRENT_SITE', 1);
define('BLOG_ID_CURRENT_SITE', 1);


/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
        define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');