<?php
/**
 * This file is executed at the time of plugin uninstallation
 *
 * @package miniOrange_LDAP_AD_Integration
 * @subpackage Main
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

if ( empty( get_option( 'en_save_config' ) ) || strcasecmp( get_option( 'en_save_config' ), '0' ) === 0 ) {
	delete_option( 'mo_ldap_local_register_user' );
	delete_option( 'mo_ldap_local_enable_admin_wp_login' );
	delete_option( 'mo_ldap_local_anonymous_bind' );
	delete_option( 'mo_ldap_local_anonymous_bind' );
	delete_option( 'mo_ldap_local_admin_email' );
	delete_option( 'mo_ldap_local_host_name' );
	delete_option( 'mo_ldap_local_password' );
	delete_option( 'mo_ldap_local_new_registration' );
	delete_option( 'mo_ldap_local_admin_phone' );
	delete_option( 'mo_ldap_local_verify_customer' );
	delete_option( 'mo_ldap_local_admin_customer_key' );
	delete_option( 'mo_ldap_local_admin_api_key' );
	delete_option( 'mo_ldap_local_customer_token' );
	delete_option( 'mo_ldap_local_message' );
	delete_option( 'mo_ldap_local_registration_status' );
	delete_option( 'mo_ldap_local_enable_login' );
	delete_option( 'mo_ldap_local_enable_log_requests' );
	delete_option( 'mo_ldap_local_server_url' );
	delete_option( 'mo_ldap_local_server_dn' );
	delete_option( 'mo_ldap_local_server_password' );
	delete_option( 'mo_ldap_local_search_base' );
	delete_option( 'mo_ldap_local_search_filter' );
	delete_option( 'mo_ldap_local_username_attribute' );
	delete_option( 'Filter_search' );
	delete_option( 'mo_ldap_local_current_plugin_version' );
	delete_option( 'mo_ldap_local_email_attribute' );
	//фамилия
	delete_option( 'mo_ldap_local_lastname_attribute' );
	//имя
	delete_option( 'mo_ldap_local_firstname_attribute' );
	//отчество
	delete_option( 'mo_ldap_local_middlename_attribute' );
	//должность
	delete_option( 'mo_ldap_local_position_attribute' );
	delete_option( 'mo_ldap_local_directory_server' );
	delete_option( 'mo_ldap_local_ldap_protocol' );
	delete_option( 'mo_ldap_local_ldap_server_address' );
	delete_option( 'mo_ldap_local_ldap_port_number' );
	delete_option( 'mo_ldap_local_email_domain' );
	delete_option( 'mo_ldap_local_admin_company' );
	delete_option( 'mo_ldap_local_ldaps_port_number' );
	delete_option( 'custom_ldap_username_attribute' );


	$role_mapping_count = get_option( 'mo_ldap_local_role_mapping_count' );
	for ( $i = 1;$i <= $role_mapping_count;$i++ ) {
		delete_option( 'mo_ldap_local_mapping_key_' . $i );
		delete_option( 'mo_ldap_local_mapping_value_' . $i );
	}

	delete_option( 'mo_ldap_local_role_mapping_count' );
	delete_option( 'mo_ldap_local_keep_existing_user_roles' );
	delete_option( 'mo_ldap_local_mapping_value_default' );
	delete_option( 'mo_ldap_local_enable_role_mapping' );
	delete_option( 'mo_ldap_local_server_url_status' );
	delete_option( 'mo_ldap_local_service_account_status' );
	delete_option( 'mo_ldap_local_user_mapping_status' );
	delete_option( 'mo_ldap_local_save_config_status' );
	delete_option( 'mo_ldap_local_username_status' );
	delete_option( 'mo_ldap_local_password_status' );
	delete_option( 'mo_ldap_local_admin_fname' );
	delete_option( 'mo_ldap_local_admin_lname' );
	delete_option( 'mo_ldap_local_company' );
	delete_option( 'import_flag' );
	delete_option( 'mo_ldap_export' );
	delete_option( 'mo_ldap_local_mapping_memberof_attribute' );
	delete_option( 'mo_ldap_local_skip_redirectto_parameter' );
	delete_option( 'mo_ldap_local_empty_pointers' );
	delete_option( 'mo_ldap_local_search_bases_list' );
	delete_option( 'mo_ldap_directory_server_value' );
	delete_option( 'mo_ldap_directory_server_custom_value' );
	delete_option( 'mo_ldap_local_multisite_message' );

	global $wpdb;
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}user_report" );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange,  - Fetching data from a custom table.
	wp_cache_delete( 'mo_ldap_user_report_cache' );
	wp_cache_delete( 'mo_ldap_user_report_count_cache' );
	wp_cache_delete( 'wp_user_reports_pagination_cache' );

	delete_option( 'user_logs_table_exists' );
	delete_option( 'mo_ldap_local_user_table_updated' );
	delete_option( 'mo_ldap_local_user_report_log' );
	delete_option( 'en_save_config' );
	delete_option( 'mo_ldap_local_host_name' );
}
