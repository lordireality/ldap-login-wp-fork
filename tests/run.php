<?php
/** Standalone regression tests. No WordPress, LDAP server or network required. */

require __DIR__ . '/bootstrap.php';
require dirname( __DIR__ ) . '/handlers/class-mo-ldap-local-avatar-handler.php';

use MO_LDAP\Handlers\Mo_Ldap_Local_Avatar_Handler;

$avatar = new Mo_Ldap_Local_Avatar_Handler();
$png    = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aVVcAAAAASUVORK5CYII=' );
$gif    = base64_decode( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' );
$jpeg   = file_get_contents( dirname( __DIR__ ) . '/includes/images/thumbnail.jpg' );
$key    = Mo_Ldap_Local_Avatar_Handler::META_KEY;
$GLOBALS['test_state']['users'] = array( new WP_User( 17, 'ldap@example.test' ), new WP_User( 18, 'local@example.test' ) );

test_case( 'stores binary photo and its local URL', function () use ( $avatar, $png, $key ) {
	assert_same( true, $avatar->sync_photo( 17, $png ), 'Valid PNG must sync.' );
	$meta = get_user_meta( 17, $key, true );
	assert_same( $png, file_get_contents( $meta['file'] ), 'Embedded binary bytes must survive storage.' );
	assert_same( $meta['url'], Mo_Ldap_Local_Avatar_Handler::get_photo_url( 17 ), 'Stored URL must be available.' );
} );

test_case( 'unchanged photo avoids another upload', function () use ( $avatar, $png ) {
	$uploads = $GLOBALS['test_state']['uploads'];
	$avatar->sync_photo( 17, $png );
	assert_same( $uploads, $GLOBALS['test_state']['uploads'], 'Same binary must reuse its file.' );
} );

test_case( 'new photo replaces file and URL', function () use ( $avatar, $gif, $key ) {
	$old = get_user_meta( 17, $key, true );
	assert_same( true, $avatar->sync_photo( 17, $gif ), 'Valid GIF must sync.' );
	$new = get_user_meta( 17, $key, true );
	assert_same( false, file_exists( $old['file'] ), 'Replaced plugin file must be deleted.' );
	assert_same( $gif, file_get_contents( $new['file'] ), 'New photo bytes must be stored.' );
	assert_same( false, $old['url'] === $new['url'], 'Changed photo needs a new cacheable URL.' );
} );

test_case( 'valid JPEG is accepted', function () use ( $avatar, $jpeg ) {
	assert_same( true, $avatar->sync_photo( 19, $jpeg ), 'AD JPEG must sync.' );
} );

test_case( 'missing and invalid data preserve current avatar', function () use ( $avatar, $key ) {
	$before = get_user_meta( 17, $key, true );
	foreach ( array( '', null, array(), '<svg xmlns="http://www.w3.org/2000/svg"/>', '<?php echo 1;', str_repeat( 'x', 5 * 1024 * 1024 + 1 ) ) as $invalid ) {
		assert_same( false, $avatar->sync_photo( 17, $invalid ), 'Non-image or oversized photo must be ignored.' );
		assert_same( $before, get_user_meta( 17, $key, true ), 'Failed photo must preserve metadata.' );
		assert_same( true, file_exists( $before['file'] ), 'Failed photo must preserve file.' );
	}
} );

test_case( 'invalid user ID cannot write a photo', function () use ( $avatar, $png ) {
	$uploads = $GLOBALS['test_state']['uploads'];
	assert_same( false, $avatar->sync_photo( 0, $png ), 'Zero user ID must be rejected.' );
	assert_same( $uploads, $GLOBALS['test_state']['uploads'], 'Invalid user must not upload.' );
} );

test_case( 'excessive image dimensions are rejected before writing', function () use ( $avatar, $png ) {
	$large = substr_replace( $png, pack( 'NN', 5000, 5000 ), 16, 8 );
	$uploads = $GLOBALS['test_state']['uploads'];
	assert_same( false, $avatar->sync_photo( 17, $large ), 'Oversized image dimensions must be rejected.' );
	assert_same( $uploads, $GLOBALS['test_state']['uploads'], 'Dimension rejection must precede writing.' );
} );

test_case( 'upload failure preserves previous photo', function () use ( $avatar, $png, $key ) {
	$before = get_user_meta( 17, $key, true );
	$GLOBALS['test_state']['upload_error'] = true;
	try {
		assert_same( false, $avatar->sync_photo( 17, $png ), 'Upload failure must be reported without throwing.' );
		assert_same( $before, get_user_meta( 17, $key, true ), 'Upload failure must preserve metadata.' );
		assert_same( true, file_exists( $before['file'] ), 'Upload failure must preserve file.' );
	} finally {
		$GLOBALS['test_state']['upload_error'] = false;
	}
} );

test_case( 'metadata failure rolls back only newly uploaded file', function () use ( $avatar, $png, $key ) {
	$before = get_user_meta( 17, $key, true );
	$files = glob( $GLOBALS['test_state']['directory'] . '/*' );
	$GLOBALS['test_state']['meta_error'] = true;
	try {
		assert_same( false, $avatar->sync_photo( 17, $png ), 'Metadata failure must be reported.' );
		assert_same( $before, get_user_meta( 17, $key, true ), 'Metadata failure must preserve old metadata.' );
		assert_same( true, file_exists( $before['file'] ), 'Metadata failure must preserve old file.' );
		assert_same( $files, glob( $GLOBALS['test_state']['directory'] . '/*' ), 'Failed save must clean up newly uploaded file.' );
	} finally {
		$GLOBALS['test_state']['meta_error'] = false;
	}
} );

test_case( 'missing file falls back and is recreated at next sync', function () use ( $avatar, $gif, $key ) {
	$before = get_user_meta( 17, $key, true );
	unlink( $before['file'] );
	assert_same( '', Mo_Ldap_Local_Avatar_Handler::get_photo_url( 17 ), 'Missing file must not produce a broken image URL.' );
	assert_same( true, $avatar->sync_photo( 17, $gif ), 'Missing file must be restored even when hash is unchanged.' );
	assert_same( true, is_file( get_user_meta( 17, $key, true )['file'] ), 'Recreated photo must exist.' );
} );

test_case( 'registered WordPress filter handles supported identities', function () use ( $key ) {
	$url = get_user_meta( 17, $key, true )['url'];
	$ids = array( 17, '17', 'ldap@example.test', new WP_User( 17 ), new WP_Post( 17 ), new WP_Comment( 17 ), new WP_Comment( 0, 'ldap@example.test' ) );
	foreach ( $ids as $identity ) {
		$actual = apply_filters( 'get_avatar_data', array( 'url' => 'https://gravatar.test/default', 'found_avatar' => false ), $identity );
		assert_same( $url, $actual['url'], 'WordPress identity must use LDAP photo.' );
		assert_same( true, $actual['found_avatar'], 'WordPress must know avatar exists.' );
	}
} );

test_case( 'WordPress default requests and unrelated users retain existing avatar', function () {
	$original = array( 'url' => 'https://gravatar.test/default', 'found_avatar' => false );
	foreach ( array( 18, 'missing@example.test', null, new stdClass(), new WP_Comment( 17, '', 'trackback' ) ) as $identity ) {
		assert_same( $original, apply_filters( 'get_avatar_data', $original, $identity ), 'Unrelated identity must remain untouched.' );
	}
	$forced = array_merge( $original, array( 'force_default' => true ) );
	assert_same( $forced, apply_filters( 'get_avatar_data', $forced, 17 ), 'Explicit force_default must be honored.' );
} );

test_case( 'registered wpForo filter supports IDs and member arrays', function () use ( $key ) {
	$url = get_user_meta( 17, $key, true )['url'];
	foreach ( array( 17, '17', array( 'userid' => 17 ), array( 'ID' => 17 ), array( 'user_email' => 'ldap@example.test' ) ) as $member ) {
		assert_same( $url, apply_filters( 'wpforo_avatar_url', 'https://forum.test/custom.jpg', $member ), 'wpForo member must use LDAP photo.' );
	}
	assert_same( 'https://forum.test/custom.jpg', apply_filters( 'wpforo_avatar_url', 'https://forum.test/custom.jpg', 18 ), 'Local forum avatar must remain unchanged.' );
} );

test_case( 'wpForo avatar cache is bypassed only for LDAP photos', function () {
	assert_same( false, apply_filters( 'wpforo_avatar_cache', true, 17 ), 'LDAP photo must bypass stale native avatar cache.' );
	assert_same( true, apply_filters( 'wpforo_avatar_cache', true, 18 ), 'Unmanaged user avatar cache must remain enabled.' );
} );

test_case( 'replacing a photo never deletes an unrelated upload', function () use ( $avatar, $png, $key ) {
	$foreign = $GLOBALS['test_state']['directory'] . '/custom-user-avatar.png';
	file_put_contents( $foreign, 'User-managed photo' );
	$GLOBALS['test_state']['meta'][21][$key] = array( 'hash' => 'old', 'file' => $foreign, 'url' => 'https://example.test/custom.png' );
	assert_same( true, $avatar->sync_photo( 21, $png ), 'New photo must sync.' );
	assert_same( true, is_file( $foreign ), 'An unrelated uploads file must never be deleted.' );
} );

test_case( 'replacing a photo never deletes files outside uploads', function () use ( $avatar, $png, $key ) {
	$foreign = dirname( $GLOBALS['test_state']['directory'] ) . '/mo-ldap-avatar-22-' . bin2hex( random_bytes( 8 ) ) . '.png';
	file_put_contents( $foreign, 'Outside uploads directory' );
	try {
		$GLOBALS['test_state']['meta'][22][$key] = array( 'hash' => 'old', 'file' => $foreign, 'url' => 'https://example.test/outside.png' );
		assert_same( true, $avatar->sync_photo( 22, $png ), 'New photo must sync.' );
		assert_same( true, is_file( $foreign ), 'A correctly named file outside uploads must never be deleted.' );
	} finally {
		if ( is_file( $foreign ) ) {
			unlink( $foreign );
		}
	}
} );

test_case( 'user removal deletes the managed photo file', function () use ( $avatar, $png, $key ) {
	$avatar->sync_photo( 23, $png );
	$file = get_user_meta( 23, $key, true )['file'];
	$avatar->delete_user_photo( 23 );
	assert_same( false, is_file( $file ), 'Deleting user must clean up the plugin-managed file.' );
} );

require __DIR__ . '/ldap-mapping.php';

echo PHP_EOL . $GLOBALS['test_passed'] . ' passed, ' . $GLOBALS['test_failed'] . ' failed.' . PHP_EOL;
exit( $GLOBALS['test_failed'] > 0 ? 1 : 0 );
