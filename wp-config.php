<?php
define( 'WP_CACHE', true ); // Added by WP Rocket

/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'japanski_live' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'WZnM96NQaZWhfE?OU:_<,$@(HK EP@N&!S7z6`l/ernkeg(a}vKb jf_[nG$>=6_' );
define( 'SECURE_AUTH_KEY',  'nw)p5S5@72fD>H(e*0|3jevvo5w1u?9.-(uq+{:BHr<Emo~,jNdn^cq_))ecBJ!c' );
define( 'LOGGED_IN_KEY',    'YHYymkptR_TGSXZ: )q`rXa;]tj0lj&6Tvq,V:.;DJ]_`f=XP)$O*R6zN}spDHtE' );
define( 'NONCE_KEY',        'j|>dYG =fSXH-AOLu@/Qom4WJ&=/$5Gl_ iw]}HK5^?d%_$=/qzCJt%(fbC6?!8]' );
define( 'AUTH_SALT',        ':-GN $,-hkY{Em.?Yxa*CzE@n!0rW]9vw.|.UXxeEx~50-48H.43X,wk1N L:;A_' );
define( 'SECURE_AUTH_SALT', ',~v=ssk5tn|,M(tHeyk~2_O{~TtgRS}$h<^z 0+18DtH62#;$p:&)VXp -~[SG0Z' );
define( 'LOGGED_IN_SALT',   '}^=,V)#r@9a%e[}).!BXA+W`Y-xr<(Wem=&Zy)}G1cB!L$NDDPYpC{cd;O6%EJCC' );
define( 'NONCE_SALT',       '198L7FH NGIQA`s-_$PNX?cdqe.#,IsdLpKCdi,t; R$d%^NjBes:>rh-Tpm2pxH' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', true );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
