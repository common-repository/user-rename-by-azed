<?php
/*
Plugin Name: User Rename by Azed
Author: Azed - Cyril Chaniaud
Plugin URI: http://www.azed-dev.com/wordpress/azeduserrename/
Description: This plugin let you easily and completely rename a user account (login + nicename + display name).
Version: 0.4.2
Author URI: http://www.azed-dev.com/
License: GPL3
*/


if( !class_exists('AzedUserRename') ){

	class AzedUserRename{

		const textDomain = 'azeduserrenametextdomain';
		private $capability = array();
		private $userAccess = '';
	  /**
		* Construct the plugin object
		*/

		public function __construct()
		{
			add_action('plugins_loaded', array(&$this,'plugin_init'));
			add_action('admin_menu', array(&$this, 'azedUserRename_menu'));
			$currentUser = wp_get_current_user();
			$currentUserInfo = get_userdata( $currentUser->ID );
			$currentUserRole = implode(', ', $currentUserInfo->roles);
			$this -> userAccess = get_option( SELF :: textDomain .'_'.$currentUserRole );
			$this -> capability = array('all' => __('User can edit settings and all users datas') ,'only' => __('User can only edit his datas'), 'none' => __('User can\'t access this plugin'));
		} // END public function __construct

		public static function init(){
			if( is_user_logged_in () ){
				load_plugin_textdomain( SELF :: textDomain, false, dirname(plugin_basename(__FILE__)).'/languages/' );
				$azedUserRenamePlugin = new AzedUserRename();
			}
		}

		public static function install(){
			foreach (get_editable_roles() as $role_name => $role_info){
				add_option( SELF :: textDomain.'_'.$role_name, 'none', '', 'no' );
			}
			update_option(SELF :: textDomain.'_administrator', 'all');
			register_uninstall_hook(__FILE__, array('AzedUserRename', 'uninstall'));
		}
		public static function uninstall(){
			foreach (get_editable_roles() as $role_name => $role_info){
				delete_option( SELF :: textDomain.'_'.$role_name );
			}
		}

		public function azedUserRename_menu()
		{
			if( $this -> userAccess != 'none' ){
				$pageID = add_users_page ( __('Change user slug', SELF :: textDomain), __('Change user slug', SELF :: textDomain), 'read', 'AzedUserRename', array(&$this,'find_and_replace_user_slug') );

	   		add_action( 'admin_print_styles-' . $pageID, array(&$this,'admin_custom_css') );
			}
		} // END azedUserRename_menu function for adding submenu item

		public function admin_custom_css(){
			wp_enqueue_style( 'AzedUserRename_CSS', plugins_url( 'css/styles.css', __FILE__ ));
		}

		public function find_and_replace_user_slug()
		{
			global $wpdb;
			$currentUser = wp_get_current_user();
			if( $this -> userAccess != 'none' ){
			$success = false;
			echo '<div class="azedWrapper">';
			echo '<h1>'.__('User Rename by Azed', SELF :: textDomain).'</h1>';
			echo '</div>';
			echo '<div class="azedRow azedFlex">';
			if( $this -> userAccess == 'all' ){
				echo '<div class="fleft-2">';
				echo '<div class="azedWrapper">';
				echo '<h2>'.__( 'Edit settings :' , SELF :: textDomain ).'</h2>';

				if( isset( $_POST['tryChangeSettings'] ) && $_POST['tryChangeSettings'] == 'letsgo' ){
					//update_option(SELF :: textDomain.'_administrator', 'all');

					if( is_array( $_POST['azedUserRenameSetting'] ) ){

						foreach (get_editable_roles() as $role_name => $role_info){
							if( $role_name != 'administrator'){
								update_option(SELF :: textDomain.'_'.$role_name , $_POST['azedUserRenameSetting'][$role_name] );
							}

						}
						echo '<p class="throwMessage success">'.__('Changes are done and already effectives.', SELF :: textDomain ).'</p>';

					}

				}

				echo '<form action="" method="post">';
				echo '<input type="hidden" name="tryChangeSettings" value="letsgo"/>';
				foreach (get_editable_roles() as $role_name => $role_info){
					if( $role_name != 'administrator'){
						$rolecap = get_option( SELF :: textDomain.'_'.$role_name );
						echo '
						<p><label for="azedUserRenameSetting['.$role_name.']">'.$role_name.'</label></p>
						<p><select name="azedUserRenameSetting['.$role_name.']" id="azedUserRenameSetting['.$role_name.']" class="formElt">';
						foreach( $this -> capability as $cap => $descr  ){
							echo '<option value="'.$cap.'"'.( $cap == $rolecap ? ' selected="selected"':'').'>'.__( $descr , SELF :: textDomain ).'</option>';
						}
						echo '</select></p>';
					}

				}
				submit_button( __( 'Update Settings' , SELF :: textDomain ) );
				echo '</form>';
				echo '</div>';
				echo '</div>';
			}
			echo '<div class="fleft-2">';
			echo '<div class="azedWrapper">';
			echo '<h2>'.__( 'Change User datas :' , SELF :: textDomain ).'</h2>';
			if( isset( $_POST['tryChangeUserInfos'] ) && !empty( $_POST['tryChangeUserInfos'] ) && $_POST['tryChangeUserInfos'] == 'letsgo' ){

				$newLogin = sanitize_user( $_POST['newUserLoginValue'] , true );
				$newNicename = ( !empty( $_POST['newUserNicenameValue'] ) ? sanitize_title( $_POST['newUserNicenameValue'] ) : sanitize_title( $newLogin ) );
				$newDisplayName = ( !empty( $_POST['newUserDisplayNameValue'] ) ? $_POST['newUserDisplayNameValue'] : $newLogin );

				if( $this -> userAccess == 'all' ){
					$userIdToFocus = $_POST['userListSelector'];
				}
				else{
					$userIdToFocus = $currentUser -> ID;
				}
				if( !empty( $newLogin ) ){
					$user_count = intval( $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->users WHERE `user_login` = '$newLogin' AND `ID` != '$userIdToFocus' " ) );
					if( $user_count > 0 ){
						echo '<p class="throwMessage error">'.__( 'This LOGIN is already used by someone else', SELF :: textDomain ).'</p>';
					}
					else{
						$user_count = intval( $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->users WHERE `user_nicename` = '$newNicename' AND `ID` != '$userIdToFocus' " ) );
						if( $user_count > 0 ){
							echo '<p class="throwMessage error">'.__( 'This NICENAME is already used by someone else', SELF :: textDomain ).'</p>';
						}
						else{
							$user_count = intval( $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->users WHERE `display_name` = '$newDisplayName' AND `ID` != '$userIdToFocus' " ) );
							if( $user_count > 0 ){
								echo '<p class="throwMessage error">'.__( 'This DISPLAY NAME is already used by someone else', SELF :: textDomain ).'</p>';
							}
							else{
								$myrows = $wpdb->get_results( "UPDATE $wpdb->users SET `user_login` = '$newLogin' ,
									`user_nicename`= '$newNicename' ,
									`display_name` = '$newDisplayName' WHERE `ID` = '$userIdToFocus' " );
									echo '<p class="throwMessage success">'.__('Changes are done and already effectives.', SELF :: textDomain ).'</p>';
									$success = true;
									$currentUser = wp_get_current_user();
							}
						}
					}
				}
				else{
					echo '<p class="throwMessage error">'.__( 'You have to specify a LOGIN', SELF :: textDomain ).'</p>';
				}

			}
			$blogusers = get_users( 'blog_id=1'.$GLOBALS['blog_id'].'&orderby=nicename' );

			echo '<form action="" method="post">';
			echo '<input type="hidden" name="tryChangeUserInfos" value="letsgo"/>';
			if( $this -> userAccess == 'all' ){
				echo '<p><label for="userListSelector">'.__('Choose from the list', SELF :: textDomain ).'</label></p>
				<p><select name="userListSelector" id="userListSelector" class="formElt">';
				//echo '<option value=""></option>';

				// Array of WP_User objects.
				foreach ( $blogusers as $user ){
					echo '<option'.( intval( $_POST['userListSelector'] ) == intval( $user->ID ) ? ' selected="selected"' : ( intval( $currentUser -> ID ) == intval( $user->ID ) && !isset( $_POST['userListSelector'] ) && empty( $_POST['userListSelector'] ) ? ' selected="selected"' : '' ) ).
					' value="'.esc_html( $user->ID ).'"'.
					' data-nicename="'.esc_html( $user->user_nicename ).'"'.
					' data-login="'.esc_html( $user->user_login ).'"'.
					' data-display="'.esc_html( $user->display_name ).'">'.
					esc_html( $user->user_login ).' | '.esc_html( $user->display_name ).' | '.esc_html( $user->user_nicename ).'</option>';
				}
				echo '</select></p>';
				?>
					<script type="text/javascript">
						jQuery('#userListSelector').change( function(){
							var userListOptionSelected = jQuery(this).children('option').filter(':selected');
							jQuery('#newUserLoginValue').val( jQuery( userListOptionSelected ).attr('data-login') );
							jQuery('#newUserNicenameValue').val( jQuery( userListOptionSelected ).attr('data-nicename') );
							jQuery('#newUserDisplayNameValue').val( jQuery( userListOptionSelected ).attr('data-display') );
						} );
					</script>
				<?php
			}
			else{
			}
			echo '
			<div id="updateUserOptions">
				<p><label for="newUserLoginValue">'.__( 'Change user Login (if you change your login, you will be disconnected)' , SELF :: textDomain ).'</label></p>
				<p><input type="text" name="newUserLoginValue" id="newUserLoginValue" value="'.
				( isset( $newLogin ) && !empty( $newLogin ) ? esc_html( stripslashes( $newLogin ) ) : esc_html( $currentUser -> user_login ) ).
				'" class="formElt" /></p>
				<p><label for="newUserNicenameValue">'.__( 'Change user Nicename (empty to auto generate)' , SELF :: textDomain ).'</label></p>
				<p><input type="text" name="newUserNicenameValue" id="newUserNicenameValue" value="'.
				( isset( $newNicename ) && !empty( $newNicename ) ? esc_html( stripslashes( $newNicename ) ) : esc_html( $currentUser -> user_nicename ) ).
				'" class="formElt" /></p>
				<p><label for="newUserDisplayNameValue">'.__( 'Change user Display Name (empty to auto generate)' , SELF :: textDomain ).'</label></p>
				<p><input type="text" name="newUserDisplayNameValue" id="newUserDisplayNameValue" value="'.
				( isset( $newDisplayName ) && !empty( $newDisplayName ) ? esc_html( stripslashes( $newDisplayName ) ) : esc_html( $currentUser -> display_name ) ).
				'" class="formElt" /></p>
			</div>';
			submit_button( __( 'Update User' , SELF :: textDomain ) );
			echo '</form>';
			echo '</div>';
			echo '</div>';
			echo '</div>';
			}
			else{

				echo '<div class="azedWrapper">';
				echo '<h1>'.__('User Rename by Azed', SELF :: textDomain).'</h1>';
				echo '<h2>'.__('You don\'t have access to this plugin', SELF :: textDomain).'</h2>';
				echo '</div>';

			}
		} // END find_and_replace_user_slug for display and exec the form

		function plugin_init() {
		} // END init function for loading textDomain
	}
}
add_action('init',array('AzedUserRename', 'init'));


register_activation_hook(__FILE__, array('AzedUserRename', 'install'));
?>
