<?php

require __DIR__ . '/ldap-doubles.php';
require dirname( __DIR__ ) . '/helpers/class-mo-ldap-local-auth-response-helper.php';
require dirname( __DIR__ ) . '/handlers/class-mo-ldap-local-configuration-handler.php';
require dirname( __DIR__ ) . '/handlers/class-mo-ldap-local-login-handler.php';

use MO_LDAP\Handlers\Mo_Ldap_Local_Configuration_Handler;
use MO_LDAP\Handlers\Mo_Ldap_Local_Login_Handler;

foreach ( array( 'LDAP_ESCAPE_FILTER', 'LDAP_OPT_NETWORK_TIMEOUT', 'LDAP_OPT_PROTOCOL_VERSION', 'LDAP_OPT_REFERRALS' ) as $constant ) {
	if ( ! defined( $constant ) ) {
		define( $constant, 1 );
	}
}

$GLOBALS['test_state']['options'] = array(
	'mo_ldap_local_search_filter'       => '(uid=?)',
	'mo_ldap_local_search_base'         => 'cn=users,dc=example,dc=test',
	'mo_ldap_local_server_dn'           => 'uid=service,cn=users,dc=example,dc=test',
	'mo_ldap_local_server_password'     => 'service-password',
	'mo_ldap_local_server_url'          => 'ldap://example.test',
	'mo_ldap_local_email_attribute'     => 'mail',
	'mo_ldap_local_lastname_attribute'  => 'sn',
	'mo_ldap_local_firstname_attribute' => 'givenname',
	'mo_ldap_local_middlename_attribute'=> 'middlename',
	'mo_ldap_local_position_attribute'  => 'title',
	'mo_ldap_local_username_attribute'  => 'uid',
	'custom_ldap_username_attribute'    => '',
	'Filter_search'                    => 'uid',
	'mo_ldap_local_register_user'       => 1,
);
$configuration = new Mo_Ldap_Local_Configuration_Handler();
$login         = new Mo_Ldap_Local_Login_Handler();
$directory_profile = array(
	'mail' => array( 'ldap@example.test' ),
	'sn' => array( 'Иванов' ),
	'givenname' => array( 'Иван' ),
	'middlename' => array( 'Иванович' ),
	'title' => array( 'Инженер' ),
);

test_case( 'LDAP auto mode requests photo attributes and preserves binary bytes', function () use ( $configuration, $directory_profile, $png ) {
	$GLOBALS['test_state']['ldap_entry'] = $directory_profile + array( 'thumbnailphoto' => array( $png ) );
	$response = $configuration->mo_ldap_local_authenticate( 'ldap', 'correct-password' );
	assert_same( 'LDAP_USER_BIND_SUCCESS', $response->status_message, 'Directory login must succeed.' );
	assert_same( $png, $response->profile_attributes_list['photo'], 'LDAP photo bytes must remain intact.' );
	assert_same( true, in_array( 'thumbnailphoto', $GLOBALS['test_state']['ldap_attributes'], true ), 'AD thumbnailPhoto must be requested.' );
	assert_same( true, in_array( 'jpegphoto', $GLOBALS['test_state']['ldap_attributes'], true ), 'ALD jpegPhoto must be requested.' );
	assert_same( 'Иван', $response->profile_attributes_list['firstname'], 'Existing profile mapping must still work.' );
} );

test_case( 'LDAP jpegPhoto fallback and binary suffix work', function () use ( $configuration, $directory_profile, $jpeg ) {
	$GLOBALS['test_state']['ldap_entry'] = $directory_profile + array( 'jpegphoto;binary' => array( $jpeg ) );
	$response = $configuration->mo_ldap_local_authenticate( 'ldap', 'correct-password' );
	assert_same( $jpeg, $response->profile_attributes_list['photo'], 'ALD binary photo must be found without AD photo.' );
} );

test_case( 'LDAP configured photo attribute overrides automatic candidates', function () use ( $configuration, $directory_profile, $png, $jpeg ) {
	$GLOBALS['test_state']['options']['mo_ldap_local_photo_attribute'] = ' CustomPhoto;binary ';
	try {
		$GLOBALS['test_state']['ldap_entry'] = $directory_profile + array( 'customphoto' => array( $png ), 'thumbnailphoto' => array( $jpeg ) );
		$response = $configuration->mo_ldap_local_authenticate( 'ldap', 'correct-password' );
		assert_same( $png, $response->profile_attributes_list['photo'], 'Configured photo must take precedence.' );
		assert_same( true, in_array( 'customphoto;binary', $GLOBALS['test_state']['ldap_attributes'], true ), 'Configured attribute must be requested normalized.' );
		assert_same( false, in_array( 'thumbnailphoto', $GLOBALS['test_state']['ldap_attributes'], true ), 'Explicit mapping replaces auto mode.' );
	} finally {
		unset( $GLOBALS['test_state']['options']['mo_ldap_local_photo_attribute'] );
	}
} );

test_case( 'LDAP bind failure never exposes profile photo', function () use ( $configuration, $directory_profile, $png ) {
	$GLOBALS['test_state']['ldap_entry'] = $directory_profile + array( 'thumbnailphoto' => array( $png ) );
	$response = $configuration->mo_ldap_local_authenticate( 'ldap', 'bad-password' );
	assert_same( 'LDAP_USER_BIND_ERROR', $response->status_message, 'Incorrect password must fail authentication.' );
	assert_same( false, isset( $response->profile_attributes_list['photo'] ), 'Failed authentication must not expose photo.' );
} );

test_case( 'malformed imported photo settings fall back to automatic mapping', function () use ( $configuration, $directory_profile, $png ) {
	try {
		foreach ( array( array( 'jpegphoto' ), 'jpegphoto)(bad=*', "jpeg\0photo" ) as $invalid ) {
			$GLOBALS['test_state']['options']['mo_ldap_local_photo_attribute'] = $invalid;
			$GLOBALS['test_state']['ldap_entry'] = $directory_profile + array( 'thumbnailphoto' => array( $png ) );
			$response = $configuration->mo_ldap_local_authenticate( 'ldap', 'correct-password' );
			assert_same( 'LDAP_USER_BIND_SUCCESS', $response->status_message, 'Malformed optional mapping must not interrupt authentication.' );
			assert_same( $png, $response->profile_attributes_list['photo'], 'Malformed optional mapping must recover with automatic photo lookup.' );
			assert_same( true, in_array( 'thumbnailphoto', $GLOBALS['test_state']['ldap_attributes'], true ), 'Recovered lookup must request AD photo.' );
			assert_same( true, in_array( 'jpegphoto', $GLOBALS['test_state']['ldap_attributes'], true ), 'Recovered lookup must request ALD photo.' );
		}
	} finally {
		unset( $GLOBALS['test_state']['options']['mo_ldap_local_photo_attribute'] );
	}
} );

test_case( 'successful LDAP login updates an existing user avatar', function () use ( $login, $directory_profile, $png, $key ) {
	$GLOBALS['test_state']['ldap_entry'] = $directory_profile + array( 'thumbnailphoto' => array( $png ) );
	$user = $login->mo_ldap_local_ldap_login( null, 'ldap', 'correct-password' );
	assert_same( 17, $user->ID, 'Existing WordPress user must log in.' );
	assert_same( $png, file_get_contents( get_user_meta( 17, $key, true )['file'] ), 'Authenticated photo must update existing avatar.' );
} );

test_case( 'successful LDAP registration stores the new user photo', function () use ( $login, $directory_profile, $png, $key ) {
	$profile = $directory_profile;
	$profile['mail'] = array( 'newldap@example.test' );
	$GLOBALS['test_state']['ldap_entry'] = $profile + array( 'jpegphoto' => array( $png ) );
	$user = $login->mo_ldap_local_ldap_login( null, 'newldap', 'correct-password' );
	assert_same( false, is_wp_error( $user ), 'New LDAP user must register.' );
	assert_same( 'newldap', $user->user_login, 'New account must match the LDAP login.' );
	assert_same( $png, file_get_contents( get_user_meta( $user->ID, $key, true )['file'] ), 'New account must receive LDAP photo.' );
} );

test_case( 'missing LDAP photo preserves existing avatar and allows login', function () use ( $login, $directory_profile, $key ) {
	$before = get_user_meta( 17, $key, true );
	$GLOBALS['test_state']['ldap_entry'] = $directory_profile;
	$user = $login->mo_ldap_local_ldap_login( null, 'ldap', 'correct-password' );
	assert_same( 17, $user->ID, 'Missing optional photo must allow login.' );
	assert_same( $before, get_user_meta( 17, $key, true ), 'Missing photo must preserve avatar metadata.' );
} );

test_case( 'invalid LDAP photo does not block login', function () use ( $login, $directory_profile, $key ) {
	$before = get_user_meta( 17, $key, true );
	$GLOBALS['test_state']['ldap_entry'] = $directory_profile + array( 'thumbnailphoto' => array( 'not an image' ) );
	$user = $login->mo_ldap_local_ldap_login( null, 'ldap', 'correct-password' );
	assert_same( 17, $user->ID, 'Invalid optional photo must allow login.' );
	assert_same( $before, get_user_meta( 17, $key, true ), 'Invalid photo must preserve avatar metadata.' );
} );

test_case( 'failed LDAP login cannot modify an avatar', function () use ( $login, $directory_profile, $gif, $key ) {
	$before = get_user_meta( 17, $key, true );
	$uploads = $GLOBALS['test_state']['uploads'];
	$GLOBALS['test_state']['ldap_entry'] = $directory_profile + array( 'thumbnailphoto' => array( $gif ) );
	$user = $login->mo_ldap_local_ldap_login( null, 'ldap', 'bad-password' );
	assert_same( true, is_wp_error( $user ), 'Invalid credentials must reject login.' );
	assert_same( $before, get_user_meta( 17, $key, true ), 'Failed login must preserve avatar.' );
	assert_same( $uploads, $GLOBALS['test_state']['uploads'], 'Failed login must never write a photo.' );
} );

test_case( 'WordPress user save failure cannot replace existing photo', function () use ( $login, $directory_profile, $gif, $key ) {
	$before = get_user_meta( 17, $key, true );
	$GLOBALS['test_state']['ldap_entry'] = $directory_profile + array( 'thumbnailphoto' => array( $gif ) );
	$GLOBALS['test_state']['user_error'] = true;
	try {
		$login->mo_ldap_local_ldap_login( null, 'ldap', 'correct-password' );
		assert_same( $before, get_user_meta( 17, $key, true ), 'Photo must sync only after successful user save.' );
	} finally {
		$GLOBALS['test_state']['user_error'] = false;
	}
} );

test_case( 'WordPress registration failure does not upload a photo', function () use ( $login, $directory_profile, $gif ) {
	$profile = $directory_profile;
	$profile['mail'] = array( 'failednew@example.test' );
	$GLOBALS['test_state']['ldap_entry'] = $profile + array( 'thumbnailphoto' => array( $gif ) );
	$GLOBALS['test_state']['user_error'] = true;
	$uploads = $GLOBALS['test_state']['uploads'];
	try {
		$result = $login->mo_ldap_local_ldap_login( null, 'failednew', 'correct-password' );
		assert_same( true, is_wp_error( $result ), 'Failed user creation must report error.' );
		assert_same( $uploads, $GLOBALS['test_state']['uploads'], 'Failed user creation must not upload a photo.' );
	} finally {
		$GLOBALS['test_state']['user_error'] = false;
	}
} );
