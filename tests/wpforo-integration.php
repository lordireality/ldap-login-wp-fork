<?php
/**
 * Optional tests against an unmodified wpForo source tree.
 * Usage: php tests/wpforo-integration.php [path/to/wp-foro]
 * WordPress, the database and persistent cache are boundary doubles.
 */

require __DIR__ . '/bootstrap.php';
require dirname( __DIR__ ) . '/handlers/class-mo-ldap-local-avatar-handler.php';

use MO_LDAP\Handlers\Mo_Ldap_Local_Avatar_Handler;

$source = isset( $argv[1] ) ? $argv[1] : dirname( __DIR__ ) . '/wp-foro';
foreach ( array( 'classes/Members.php', 'classes/RamCache.php', 'includes/hooks.php', 'includes/functions.php' ) as $relative ) {
	if ( ! is_file( $source . '/' . $relative ) ) {
		fwrite( STDERR, 'Missing wpForo source file: ' . $source . '/' . $relative . PHP_EOL );
		exit( 1 );
	}
}
require $source . '/classes/Members.php';
require $source . '/classes/RamCache.php';

/** Load actual named functions without bootstrapping unrelated WordPress hooks. */
function integration_load_functions( $file, $names ) {
	$tokens = token_get_all( file_get_contents( $file ) );
	$loaded = array();
	for ( $i = 0; $i < count( $tokens ); ++$i ) {
		if ( ! is_array( $tokens[ $i ] ) || T_FUNCTION !== $tokens[ $i ][0] ) continue;
		$start = $i;
		$name = null;
		for ( $j = $i + 1; $j < count( $tokens ); ++$j ) {
			if ( is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) continue;
			$name = is_array( $tokens[ $j ] ) && T_STRING === $tokens[ $j ][0] ? $tokens[ $j ][1] : null;
			break;
		}
		if ( ! in_array( $name, $names, true ) ) continue;
		$body = '';
		$depth = 0;
		$opened = false;
		for ( $i = $start; $i < count( $tokens ); ++$i ) {
			$token = $tokens[ $i ];
			$body .= is_array( $token ) ? $token[1] : $token;
			if ( '{' === $token ) { ++$depth; $opened = true; }
			if ( '}' === $token && 0 === --$depth && $opened ) break;
		}
		// Execute the exact trusted local plugin function, rather than a copied implementation.
		eval( $body );
		$loaded[] = $name;
	}
	foreach ( $names as $name ) {
		if ( ! in_array( $name, $loaded, true ) ) throw new RuntimeException( 'wpForo function missing: ' . $name );
	}
}

function WPF() { return $GLOBALS['integration_wpf']; }
function wpforo_bigintval( $value ) { return absint( $value ); }
function wpforo_is_id( $value ) { return is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ); }
function wpfval( $value, $key ) { return isset( $value[ $key ] ) ? $value[ $key ] : null; }
function wpforo_setting( $group, $name ) { return ! empty( $GLOBALS['integration_settings'][ $name ] ); }
function wpforo_user_dname( $user ) { return isset( $user['display_name'] ) ? $user['display_name'] : 'User'; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return esc_attr( $value ); }
function get_avatar( $user, $size = 96 ) {
	$args = apply_filters( 'pre_get_avatar_data', array( 'url' => '', 'found_avatar' => false ), $user );
	if ( empty( $args['url'] ) ) $args['url'] = 'https://gravatar.test/default.png';
	$args = apply_filters( 'get_avatar_data', $args, $user );
	$html = '<img class="avatar" src="' . esc_url( $args['url'] ) . '" width="' . $size . '">';
	return apply_filters( 'get_avatar', $html, $user, $size, '', '' );
}

class IntegrationUser extends WP_User {
	public $data;
	public function __construct( $id, $email ) {
		parent::__construct( $id, $email );
		$this->data = (object) array( 'ID' => $id );
	}
}
class IntegrationCache {
	public $reads = 0;
	public $writes = 0;
	public $enabled = true;
	public function on( $kind ) { return $this->enabled; }
	public function get_item( $id, $kind ) { ++$this->reads; return 'https://native.test/stale-cache.png'; }
	public function create( $kind, $items, $group ) { ++$this->writes; }
}
class IntegrationDB {
	public $reads = 0;
	public function prepare( $query, $id ) { return $id; }
	public function get_var( $id ) { ++$this->reads; return 'https://native.test/profile.png'; }
}

integration_load_functions( $source . '/includes/functions.php', array( 'wpforo_ram_get' ) );
integration_load_functions( $source . '/includes/hooks.php', array( 'wpforo_avatar', 'wpforo_pre_get_avatar_data' ) );
add_filter( 'get_avatar', 'wpforo_avatar', 10, 5 );
add_filter( 'pre_get_avatar_data', 'wpforo_pre_get_avatar_data', 10, 2 );
$wpf = (object) array(
	'cache' => new IntegrationCache(),
	'db' => new IntegrationDB(),
	'ram_cache' => new wpforo\classes\RamCache(),
	'tables' => (object) array( 'profiles' => 'wp_wpforo_profiles' ),
);
$GLOBALS['integration_wpf'] = $wpf;
$GLOBALS['integration_settings'] = array( 'custom_avatars' => true, 'replace_avatar' => true );
$members = ( new ReflectionClass( 'wpforo\classes\Members' ) )->newInstanceWithoutConstructor();
$wpf->member = $members;
new Mo_Ldap_Local_Avatar_Handler();
$photo = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aVVcAAAAASUVORK5CYII=' );
assert_same( true, Mo_Ldap_Local_Avatar_Handler::sync_photo( 77, $photo ), 'Fixture LDAP photo must save.' );
$ldap_url = Mo_Ldap_Local_Avatar_Handler::get_photo_url( 77 );
$member = array( 'userid' => 77, 'avatar' => 'https://native.test/profile.png', 'display_name' => 'LDAP User' );
$local = array( 'userid' => 78, 'avatar' => 'https://native.test/profile.png', 'display_name' => 'Local User' );
$GLOBALS['test_state']['users'] = array( new IntegrationUser( 77, 'ldap@example.test' ), new IntegrationUser( 78, 'local@example.test' ) );

test_case( 'actual scalar lookup bypasses stale persistent cache for LDAP user', function () use ( $members, $wpf, $ldap_url ) {
	$before = $wpf->cache->reads;
	assert_same( $ldap_url, $members->get_avatar_url( 77 ), 'LDAP URL must win.' );
	assert_same( $before, $wpf->cache->reads, 'Stale persistent cache must be bypassed.' );
} );
test_case( 'actual scalar lookup preserves persistent cache for local user', function () use ( $members, $wpf ) {
	$before = $wpf->db->reads;
	assert_same( 'https://native.test/stale-cache.png', $members->get_avatar_url( 78 ), 'Native cache must remain active.' );
	assert_same( $before, $wpf->db->reads, 'Native cache must still avoid DB lookup.' );
} );
test_case( 'actual member-array lookup handles both LDAP and local avatars', function () use ( $members, $member, $local, $ldap_url ) {
	assert_same( $ldap_url, $members->get_avatar_url( $member ), 'LDAP member array must resolve.' );
	assert_same( $local['avatar'], $members->get_avatar_url( $local ), 'Local member array must stay native.' );
} );
test_case( 'actual custom-avatar HTML overrides native image and preserves dimensions', function () use ( $members, $member, $ldap_url ) {
	$GLOBALS['integration_settings']['custom_avatars'] = true;
	$html = $members->get_avatar_html( $member['avatar'], $member, 48 );
	assert_same( true, false !== strpos( $html, 'src="' . $ldap_url . '"' ), 'HTML must contain LDAP photo.' );
	assert_same( true, false !== strpos( $html, 'width="48"' ), 'Requested dimensions must survive.' );
} );
test_case( 'actual empty-native-avatar pipeline displays LDAP photo', function () use ( $members, $member, $ldap_url ) {
	$member['avatar'] = '';
	assert_same( true, false !== strpos( $members->get_avatar( $member, 32 ), $ldap_url ), 'No native avatar must still yield LDAP photo.' );
} );
test_case( 'actual WordPress avatar hooks preserve LDAP photo with wpForo replacement enabled', function () use ( $ldap_url ) {
	$GLOBALS['integration_settings']['replace_avatar'] = true;
	foreach ( array( 77, 'ldap@example.test', $GLOBALS['test_state']['users'][0] ) as $identity ) {
		assert_same( true, false !== strpos( get_avatar( $identity, 64 ), $ldap_url ), 'wpForo hooks must preserve LDAP photo.' );
	}
} );
test_case( 'actual WordPress fallback works when custom avatars and replacement are disabled', function () use ( $members, $member, $ldap_url ) {
	$GLOBALS['integration_settings'] = array( 'custom_avatars' => false, 'replace_avatar' => false );
	assert_same( true, false !== strpos( $members->get_avatar_html( $member['avatar'], $member, 64 ), $ldap_url ), 'Core avatar fallback must still use LDAP photo.' );
} );
test_case( 'local WordPress fallback remains unchanged', function () use ( $members, $local ) {
	assert_same( true, false !== strpos( $members->get_avatar_html( $local['avatar'], $local, 96 ), 'https://gravatar.test/default.png' ), 'Local user fallback must stay native.' );
} );
test_case( 'persistent cache disabled still renders LDAP photo', function () use ( $members, $wpf, $ldap_url ) {
	$wpf->ram_cache->reset();
	$wpf->cache->enabled = false;
	assert_same( $ldap_url, $members->get_avatar_url( 77 ), 'Disabled cache must not change LDAP URL.' );
	$wpf->cache->enabled = true;
} );
test_case( 'changed LDAP photo is rendered after next-request cache reset', function () use ( $members, $wpf, $photo ) {
	$old_url = $members->get_avatar_url( 77 );
	$new_photo = file_get_contents( dirname( __DIR__ ) . '/includes/images/thumbnail.jpg' );
	assert_same( true, Mo_Ldap_Local_Avatar_Handler::sync_photo( 77, $new_photo ), 'Changed JPEG must save.' );
	$new_url = Mo_Ldap_Local_Avatar_Handler::get_photo_url( 77 );
	assert_same( false, $old_url === $new_url, 'Changed content must receive a new URL.' );
	$wpf->ram_cache->reset(); // A normal login redirects to a new request.
	assert_same( $new_url, $members->get_avatar_url( 77 ), 'New request must show updated photo.' );
	assert_same( true, false !== strpos( get_avatar( 77 ), $new_url ), 'Both wpForo WordPress hooks must see updated photo.' );
} );

echo PHP_EOL . $GLOBALS['test_passed'] . ' passed, ' . $GLOBALS['test_failed'] . ' failed.' . PHP_EOL;
exit( $GLOBALS['test_failed'] ? 1 : 0 );
