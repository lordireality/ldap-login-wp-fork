<?php
/**
 * Display Attribute Mapping Page.
 *
 * @package miniOrange_LDAP_AD_Integration
 * @subpackage views
 */

?>
<div class="mo_ldap_local_attribute_mapping_outer">	
		<div class="mo_ldap_role_local_outer">
			<form name="f" method="post" id="attribute_config_form">
				<?php wp_nonce_field( 'mo_ldap_save_attribute_config' ); ?>	
				<input type="hidden" name="option" value="mo_ldap_save_attribute_config"/>	
				<div>
					<div class="mo_ldap_heading_container mo_ldap_local_heading_right">
						<div class="mo_ldap_local_footer_btns_container mo_ldap_local_btns_upr_space_remove">
							<a 
								<?php
								echo 'href="' . esc_url(
									add_query_arg(
										array(
											'subtab' => 'role-mapping',
											'tab'    => 'default',
										),
										$filtered_current_page_url
									)
								)
								. '"';
								?>
								class="mo_ldap_local_unset_link_affect">
								<button type="button" class="mo_ldap_back_btn">Back</button>
							</a>
							<a
								<?php
								echo 'href="' . esc_url(
									add_query_arg(
										array(
											'subtab' => 'login-settings',
											'tab'    => 'default',
										),
										$filtered_current_page_url
									)
								)
								. '"';
								?>
								class="mo_ldap_local_unset_link_affect">
								<button type="button" class="mo_ldap_next_btn">Next</button>
							</a>
						</div>
					</div>

					<div class="mo_ldap_local_attribute_mapping_premium_feature_all_feature">
						<div class="mo_ldap_premium_feature_each_feature">
							<div class="mo_ldap_premium_freature_input_common">
								<label for="ldap_intranet_attribute_mail_name" class="mo_ldap_input_label_text">Email Attribute <span style="color:red;">*</span></label>
							</div>
							<div class="mo_ldap_premium_freature_input_common mo_ldap_premium_feature_input">
								<input type="text" id="ldap_intranet_attribute_mail_name" name="mo_ldap_email_attribute" placeholder="Enter Email Attribute" class="mo_ldap_local_input_field1 mo_ldap_local_attribute_mapping_input" required value="<?php echo esc_attr( get_option( 'mo_ldap_local_email_attribute' ) ); ?>">
							</div>
						</div>

						<div class="mo_ldap_premium_feature_each_feature">
							<div class="mo_ldap_premium_freature_input_common">
								<label for="mo_ldap_email_domain" class="mo_ldap_input_label_text">Email Domain</label>
							</div>
							<div class="mo_ldap_premium_freature_input_common mo_ldap_premium_feature_input">
								<input type="text" pattern="[a-z0-9.-]+\.[a-z]{2,}$" title="Please Enter Valid Domain Name. Ex. miniorange.com" id="mo_ldap_email_domain" name="mo_ldap_email_domain" placeholder="example.com" class="mo_ldap_local_input_field1 mo_ldap_local_attribute_mapping_input" value="<?php echo esc_attr( get_option( 'mo_ldap_local_email_domain' ) ); ?>" >
							</div>
						</div>
						<div class="mo_ldap_local_attribute_mapping_email_para">
							<p class="mo_ldap_local_input_paragraph">Set user email to <span style="font-weight: bold;">username@email_domain</span> in WordPress, if the "mail" attribute is not set in  LDAP directory.</p>
						</div>
						<!--Фамилия-->
						<div class="mo_ldap_premium_feature_each_feature">
							<div class="mo_ldap_premium_freature_input_common">
								<label for="ldap_intranet_attribute_lastname" class="mo_ldap_input_label_text">Фамилия</label>
							</div>
							<div class="mo_ldap_premium_freature_input_common mo_ldap_premium_feature_input">
								<input type="text" id="ldap_intranet_attribute_lastname" name="mo_ldap_lastname_attribute" placeholder="Введите наименование атрибута Фамилии" class="mo_ldap_local_input_field1 mo_ldap_local_attribute_mapping_input" value="<?php echo esc_attr( get_option( 'mo_ldap_local_lastname_attribute' ) ); ?>">
							</div>
						</div>
						<!--Имя-->
						<div class="mo_ldap_premium_feature_each_feature">
							<div class="mo_ldap_premium_freature_input_common">
								<label for="ldap_intranet_attribute_firstname" class="mo_ldap_input_label_text">Имя</label>
							</div>
							<div class="mo_ldap_premium_freature_input_common mo_ldap_premium_feature_input">
								<input type="text" id="ldap_intranet_attribute_firstname" name="mo_ldap_firstname_attribute" placeholder="Введите наименование атрибута Имя" class="mo_ldap_local_input_field1 mo_ldap_local_attribute_mapping_input" value="<?php echo esc_attr( get_option( 'mo_ldap_local_firstname_attribute' ) ); ?>">
							</div>
						</div>
						<!--Отчество-->
						<div class="mo_ldap_premium_feature_each_feature">
							<div class="mo_ldap_premium_freature_input_common">
								<label for="ldap_intranet_attribute_middlename" class="mo_ldap_input_label_text">Отчество</label>
							</div>
							<div class="mo_ldap_premium_freature_input_common mo_ldap_premium_feature_input">
								<input type="text" id="ldap_intranet_attribute_middlename" name="mo_ldap_middlename_attribute" placeholder="Введите наименование атрибута Отчество" class="mo_ldap_local_input_field1 mo_ldap_local_attribute_mapping_input" value="<?php echo esc_attr( get_option( 'mo_ldap_local_middlename_attribute' ) ); ?>">
							</div>
						</div>
						<!--Должность-->
						<div class="mo_ldap_premium_feature_each_feature">
							<div class="mo_ldap_premium_freature_input_common">
								<label for="ldap_intranet_attribute_position" class="mo_ldap_input_label_text">Должность</label>
							</div>
							<div class="mo_ldap_premium_freature_input_common mo_ldap_premium_feature_input">
								<input type="text" id="ldap_intranet_attribute_position" name="mo_ldap_position_attribute" placeholder="Введите наименование атрибута Должность" class="mo_ldap_local_input_field1 mo_ldap_local_attribute_mapping_input" value="<?php echo esc_attr( get_option( 'mo_ldap_local_position_attribute' ) ); ?>">
							</div>
						</div>



					</div>
				</div>

				<div class="mo_ldap_local_attr_mapping_left_align"> 
					<input type="submit" class="mo_ldap_save_user_mapping " value="Save Configuration" />
				</div>
			</form>
		</div>

		<div class="mo_ldap_local_outer">
			<h3>Test Attribute Configuration</h3>
			<form method="post" id="attribiteconfigtest">
				<input type="hidden" name="option" value="mo_ldap_test_attribute_configuration" />
				<br>
				<div class="mo_ldap_display_flex_elements mo_ldap_local_test_attr_username_box">
					<label for="username" class="mo_ldap_input_label_text mo_ldap_local_config_label">Username <span style="color:red;">*</span></label>
					<input type="text" id="mo_ldap_username" name="mo_ldap_username" placeholder="Enter Username" class="mo_ldap_enter_username"required>
				</div>
				<p class="mo_ldap_local_input_paragraph mo_ldap_local_test_mapping_para">Enter LDAP username to test attribute configuration</p>
				<br>
				<?php
				$search_base_string = get_option( 'mo_ldap_local_search_base' ) ? $utils::decrypt( get_option( 'mo_ldap_local_search_base' ) ) : '';
				$search_bases       = explode( ';', $search_base_string );
				?>
				<div class="mo_ldap_local_attr_mapping_left_align"> 
				<input type="submit" class="mo_ldap_save_user_mapping" value="Test Configuration" 
					<?php
					if ( empty( $search_bases[0] ) ) {
						echo 'disabled';
					}
					?>
				>
				</div>
			</form>
		</div>
		<script>
			function testConfiguration(){

				var nonce = "<?php echo esc_attr( wp_create_nonce( 'testattrconfig_nonce' ) ); ?>";

				var username = jQuery("#mo_ldap_username").val();
				var myWindow = window.open('<?php echo esc_url( site_url() ); ?>' + '/?option=testattrconfig&user='+username + '&_wpnonce='+nonce, "Test Attribute Configuration", "width=700, height=600");
			}
		</script>
</div>
