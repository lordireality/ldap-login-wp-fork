<?php
/** LDAP boundary doubles: real configuration/login code runs without a directory. */

namespace MO_LDAP\Utils {
	class Mo_Ldap_Local_Utils {
		public static function is_extension_installed( $name ) {
			return true;
		}
		public static function decrypt( $value ) {
			return $value;
		}
		public function mo_ldap_report_update( $user, $status, $message ) {}
	}
}

namespace MO_LDAP\Handlers {
	function ldap_connect( $server ) {
		return (object) array( 'error' => false );
	}
	function ldap_set_option( $connection, $option, $value ) {
		return true;
	}
	function ldap_start_tls( $connection ) {
		return true;
	}
	function ldap_escape( $value, $ignore, $flags ) {
		return strtr( $value, array( '\\' => '\\5c', '*' => '\\2a', '(' => '\\28', ')' => '\\29', "\0" => '\\00' ) );
	}
	function ldap_bind( $connection, $dn, $password ) {
		$connection->error = 'bad-password' === $password;
		return ! $connection->error;
	}
	function ldap_errno( $connection ) {
		return $connection->error ? 49 : 0;
	}
	function ldap_error( $connection ) {
		return $connection->error ? 'Invalid credentials' : 'Success';
	}
	function ldap_search( $connection, $base, $filter, $attributes ) {
		$GLOBALS['test_state']['ldap_attributes'] = $attributes;
		$GLOBALS['test_state']['ldap_filter'] = $filter;
		return (object) array();
	}
	function ldap_first_entry( $connection, $result ) {
		return empty( $GLOBALS['test_state']['ldap_entry'] ) ? false : (object) array();
	}
	function ldap_get_entries( $connection, $result ) {
		return array( $GLOBALS['test_state']['ldap_entry'] );
	}
	function ldap_get_dn( $connection, $entry ) {
		return 'uid=ldap,cn=users,dc=example,dc=test';
	}
}
