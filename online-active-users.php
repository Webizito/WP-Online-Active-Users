<?php
/**
 * Plugin Name: Online Active Users
 * Plugin URI: https://webizito.com/
 * Description: WordPress Online Active Users plugin enables you to display how many users are currently online active and display user last seen on your Users page in the WordPress admin.
 * Version: 1.0
 * Author: Webizito
 * License:  GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Author URI: https://github.com/Webizito
 * Text Domain: wp-online-active-users
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'webi_active_user' ) ) {
    class webi_active_user {
        public function __construct(){
           
            add_action('init', array($this, 'webizito_users_status_init'));
            add_action('admin_init', array($this, 'webizito_users_status_init'));
            add_action('wp_dashboard_setup', array($this, 'webizito_active_users_metabox'));
            add_filter('manage_users_columns', array($this, 'webizito_user_columns_head'));
            add_action('manage_users_custom_column', array($this, 'webizito_user_columns_content'), 15, 3);
            add_filter('views_users', array($this, 'webizito_modify_user_view' ));
            add_action('admin_bar_menu',  array($this, 'webizito_admin_bar_link'),999);
            add_action('wp_head', array($this, 'webi_active_users_css'));
            add_action('admin_head', array($this, 'webi_active_users_css'));
            $this->webizito_active_user_shortcode();
        }    

        //Update user online status
        public function webizito_users_status_init(){
            $logged_in_users = get_transient('users_status');
            $user = wp_get_current_user();
            
            if ( !isset($logged_in_users[$user->ID]['last']) || $logged_in_users[$user->ID]['last'] <= time()-50 ){
                $logged_in_users[$user->ID] = array(
                    'id' => $user->ID,
                    'username' => $user->user_login,
                    'last' => time(),
                );
                set_transient('users_status', $logged_in_users, 50);
            }
        }

        //Check if a user has been online in the last 5 minutes
        public function webizito_is_user_online($id){  
            $logged_in_users = get_transient('users_status');
            return isset($logged_in_users[$id]['last']) && $logged_in_users[$id]['last'] > time()-50;
        }

        //Check when a user was last online.
        public function webizito_user_last_online($id){
            $logged_in_users = get_transient('users_status');
            if ( isset($logged_in_users[$id]['last']) ){
                return $logged_in_users[$id]['last'];
            } else {
                return false;
            }
        }

        //Add columns to user listings
        public function webizito_user_columns_head($defaults){
            $defaults['status'] = 'User Online Status';
            return $defaults;
        }

        
        public function webizito_user_columns_content($value='', $column_name, $id){
            if ( $column_name == 'status' ){
                if ( $this->webizito_is_user_online($id) ){
                    return '<span class="online-logged-in">●</span>';
                } else if($this->webizito_user_last_online($id)){
                    return ( $this->webizito_user_last_online($id) ) ? ' <span class="offline-dot">●</span><br /><small>Last Seen: <br /><em>' . date('M j, Y @ g:ia', $this->webizito_user_last_online($id)) . '</em></small>' : '';
                }else{
                    return '<span class="offline-dot">●</span>';
                }
            }
        }


        //Active Users Metabox
        public function webizito_active_users_metabox(){
            global $wp_meta_boxes;
            wp_add_dashboard_widget('webizito_active_users', 'Active Users', array($this, 'webizito_active_user_dashboard'));
        }

        public function webizito_active_user_dashboard( $post, $callback_args ){
                $user_count = count_users();
                $users_plural = ( $user_count['total_users'] == 1 )? 'User' : 'Users';
                echo '<div><a href="users.php">' . $user_count['total_users'] . ' ' . $users_plural . '</a> <small>(' . $this->webizito_online_users('count') . ' currently active)</small></div>';
        }

        public function webizito_active_user_shortcode(){
            add_shortcode('webi_active_user', array($this, 'webizito_active_user'));
        }  

         public function webizito_active_user(){
            ob_start();
            if(is_user_logged_in()){
                $user_count = count_users();
                $users_plural = ( $user_count['total_users'] == 1 ) ? 'User' : 'Users';
                echo '<div class="webi-active-users"> Currently Active Users: <small>(' . $this->webizito_online_users('count') . ')</small></div>';
            }  
            return ob_get_clean();
        }    

        public function webizito_admin_bar_link() {
            global $wp_admin_bar;
            if ( !is_super_admin() || !is_admin_bar_showing() )
                return;
            $wp_admin_bar->add_menu( array(
                'id' => 'webi_user_link', 
                'title' => '<span class="ab-icon online-logged-in">●</span><span class="ab-label">' . __( 'Active Users (' . $this->webizito_online_users('count') . ')') .'</span>',
                'href' => esc_url( admin_url( 'users.php' ) )
            ) );
        }
       
        //Get a count of online users, or an array of online user IDs
        public function webizito_online_users($return='count'){
            $logged_in_users = get_transient('users_status');
            
            //If no users are online
            if ( empty($logged_in_users) ){
                return ( $return == 'count' )? 0 : false;
            }
            
            $user_online_count = 0;
            $online_users = array();
            foreach ( $logged_in_users as $user ){
                if ( !empty($user['username']) && isset($user['last']) && $user['last'] > time()-50 ){ 
                    $online_users[] = $user;
                    $user_online_count++;
                }
            }

            return ( $return == 'count' )? $user_online_count : $online_users; 

        }

        public function webizito_modify_user_view( $views ) {

            $logged_in_users = get_transient('users_status');
            $user = wp_get_current_user();

            $logged_in_users[$user->ID] = array(
                    'id' => $user->ID,
                    'username' => $user->user_login,
                    'last' => time(),
                );

            $view = '<a href=' . admin_url('users.php') . '>User Online <span class="count">('.$this->webizito_online_users('count').')</span></a>';

            $views['status'] = $view;
            return $views;
        }

        // We need some CSS
        public function webi_active_users_css() {
            echo "
            <style type='text/css'>
            .status.column-status, 
            .manage-column.column-status{
                text-align: center;
            }
            .online-logged-in {
                color: #4CBB17;
                font-size: 32px;
            }
            .offline-dot{
                color:#FF4433;
                font-size: 32px;
            }
            #wpadminbar #wp-admin-bar-webi_user_link .ab-icon {
                font-size: 30px !important;
                top: -9px;
            }
            .webi-active-users{
                width: 30%;
                max-width: 100%;
                background-color: #4CBB17;
                text-align: center;
                padding: 15px;
                height: auto;
                color: #fff;
                font-size: 17px;
                font-weight: 600;
            }
            .webi-active-users small {
                font-size: 17px;
                font-weight: 700;
            }
            .widget-area .webi-active-users{
                 width: 100%;
            }
           </style>";
        }
    }
}
$myPlugin = new webi_active_user();