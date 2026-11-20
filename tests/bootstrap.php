<?php
/**
 * Minimal WordPress boundary doubles for the standalone regression suite.
 * Run with: php tests/run.php
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['test_state'] = array(
	'options'       => array(),
	'meta'          => array(),
	'users'         => array(),
	'hooks'         => array(),
	'uploads'       => 0,
	'upload_error'  => false,
	'meta_error'    => false,
	'user_error'    => false,
	'next_user_id'  => 30,
	'deleted_files' => array(),
	'directory'     => sys_get_temp_dir() . '/mo-ldap-avatar-test-' . bin2hex( random_bytes( 8 ) ),
);
mkdir( $GLOBALS['test_state']['directory'], 0700, true );

class WP_User {
	public $ID;
	public $user_email;
	public $user_login;
	public $first_name;
	public $last_name;
	public $description;
	public $display_name;
	public function __construct( $id = 0, $email = '', $login = '' ) {
		$this->ID         = $id;
		$this->user_email = $email;
		$this->user_login = $login ? $login : strstr( $email, '@', true );
	}
}

class WP_Post {
	public $post_author;
	public function __construct( $author ) {
		$this->post_author = $author;
	}
}

class WP_Comment {
	public $user_id;
	public $comment_author_email;
	public $comment_type;
	public function __construct( $user_id = 0, $email = '', $type = 'comment' ) {
		$this->user_id              = $user_id;
		$this->comment_author_email = $email;
		$this->comment_type         = $type;
	}
}

class WP_Error {
	public $code;
	public $message;
	public function __construct( $code = '', $message = '' ) {
		$this->code = $code;
		$this->message = $message;
	}
	public function add( $code, $message ) {
		$this->code = $code;
		$this->message = $message;
	}
	public function get_error_message() {
		return $this->message;
	}
}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['test_state']['hooks'][ $hook ][ $priority ][] = array( $callback, $accepted_args );
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	add_filter( $hook, $callback, $priority, $accepted_args );
}

function remove_filter( $hook, $callback, $priority = 10 ) {}

function apply_filters( $hook, $value, ...$arguments ) {
	$callbacks = isset( $GLOBALS['test_state']['hooks'][ $hook ] ) ? $GLOBALS['test_state']['hooks'][ $hook ] : array();
	ksort( $callbacks );
	foreach ( $callbacks as $priority_callbacks ) {
		foreach ( $priority_callbacks as $callback ) {
			$value = call_user_func_array( $callback[0], array_slice( array_merge( array( $value ), $arguments ), 0, $callback[1] ) );
		}
	}
	return $value;
}

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['test_state']['options'] ) ? $GLOBALS['test_state']['options'][ $name ] : $default;
}

function get_user_meta( $user_id, $key, $single = false ) {
	return isset( $GLOBALS['test_state']['meta'][ $user_id ][ $key ] ) ? $GLOBALS['test_state']['meta'][ $user_id ][ $key ] : '';
}

function update_user_meta( $user_id, $key, $value, $previous = '' ) {
	if ( $GLOBALS['test_state']['meta_error'] ) {
		return false;
	}
	$value = wp_unslash( $value );
	if ( get_user_meta( $user_id, $key, true ) === $value ) {
		return false;
	}
	$GLOBALS['test_state']['meta'][ $user_id ][ $key ] = $value;
	return true;
}

function delete_user_meta( $user_id, $key ) {
	unset( $GLOBALS['test_state']['meta'][ $user_id ][ $key ] );
	return true;
}

function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['test_state']['users'] as $user ) {
		if ( ( 'id' === strtolower( $field ) && $user->ID === (int) $value ) || ( 'email' === $field && $user->user_email === $value ) || ( 'login' === $field && $user->user_login === $value ) ) {
			return $user;
		}
	}
	return false;
}

function get_userdata( $id ) {
	return get_user_by( 'id', $id );
}

function wp_slash( $value ) {
	return is_array( $value ) ? array_map( 'wp_slash', $value ) : ( is_string( $value ) ? addslashes( $value ) : $value );
}

function wp_unslash( $value ) {
	return is_array( $value ) ? array_map( 'wp_unslash', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value );
}

function wp_normalize_path( $path ) {
	return str_replace( '\\', '/', $path );
}

function trailingslashit( $path ) {
	return rtrim( $path, '/\\' ) . '/';
}

function wp_upload_dir() {
	$path = $GLOBALS['test_state']['directory'];
	return array( 'path' => $path, 'basedir' => $path, 'url' => 'https://example.test/uploads', 'baseurl' => 'https://example.test/uploads', 'error' => false );
}

function wp_upload_bits( $name, $deprecated, $bytes, $time = null ) {
	++$GLOBALS['test_state']['uploads'];
	if ( $GLOBALS['test_state']['upload_error'] ) {
		return array( 'error' => 'Simulated full uploads disk.' );
	}
	$path = $GLOBALS['test_state']['directory'] . '/' . basename( $name );
	if ( file_exists( $path ) ) {
		$path = preg_replace( '/(\.[^.]+)$/', '-' . $GLOBALS['test_state']['uploads'] . '$1', $path );
	}
	file_put_contents( $path, $bytes );
	return array( 'file' => $path, 'url' => 'https://example.test/uploads/' . basename( $path ), 'error' => false );
}

function wp_delete_file( $path ) {
	$GLOBALS['test_state']['deleted_files'][] = $path;
	if ( is_file( $path ) ) {
		unlink( $path );
	}
}

function esc_url_raw( $url ) {
	return $url;
}

function absint( $value ) {
	return abs( (int) $value );
}

function is_email( $value ) {
	return filter_var( $value, FILTER_VALIDATE_EMAIL );
}

function get_comment_type( $comment ) {
	return $comment->comment_type ? $comment->comment_type : 'comment';
}

function username_exists( $login ) {
	$user = get_user_by( 'login', $login );
	return $user ? $user->ID : false;
}

function email_exists( $email ) {
	$user = get_user_by( 'email', $email );
	return $user ? $user->ID : false;
}

function wp_generate_password( $length = 12, $special_chars = true ) {
	return str_repeat( 'X', $length );
}

function wp_update_user( $data ) {
	if ( $GLOBALS['test_state']['user_error'] ) {
		return new WP_Error( 'test_db_error', 'User database unavailable.' );
	}
	$user = get_userdata( $data['ID'] );
	foreach ( $data as $property => $value ) {
		$user->$property = $value;
	}
	return $user->ID;
}

function wp_insert_user( $data ) {
	if ( $GLOBALS['test_state']['user_error'] ) {
		return new WP_Error( 'test_db_error', 'User database unavailable.' );
	}
	$user = new WP_User( $GLOBALS['test_state']['next_user_id']++, $data['user_email'], $data['user_login'] );
	$GLOBALS['test_state']['users'][] = $user;
	return $user->ID;
}

function __( $message, $domain = '' ) {
	return $message;
}

function assert_same( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		throw new RuntimeException( $message . '\nExpected: ' . var_export( $expected, true ) . '\nActual: ' . var_export( $actual, true ) );
	}
}

function test_case( $name, $callback ) {
	try {
		$callback();
		++$GLOBALS['test_passed'];
		echo 'PASS ' . $name . PHP_EOL;
	} catch ( Throwable $error ) {
		++$GLOBALS['test_failed'];
		fwrite( STDERR, 'FAIL ' . $name . ': ' . $error->getMessage() . PHP_EOL );
	}
}

$GLOBALS['test_passed'] = 0;
$GLOBALS['test_failed'] = 0;

register_shutdown_function(
	function () {
		// Delete only this runner's randomly allocated directory and its direct files.
		$directory = $GLOBALS['test_state']['directory'];
		foreach ( glob( $directory . '/*' ) as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}
		rmdir( $directory );
	}
);
