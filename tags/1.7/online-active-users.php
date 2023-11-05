<?php
/**
 * Plugin Name: Online Active Users
 * Plugin Title: Online Active Users Plugin
 * Plugin URI: https://wordpress.org/plugins/online-active-users/
 * Description: WordPress Online Active Users plugin enables you to display how many users are currently online active and display user last seen on your Users page in the WordPress admin.
 * Tags: wp-online-active-users, users, active-users, online-user, available-users, wordpress users, user-last-seen, currently-active-user, user-online, online-user-status, online users, active users, wordpress online user
 * Version: 1.7
 * Author: Webizito
 * Author URI: http://webizito.com/
 * Contributors: valani9099
 * License:  GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: wp-online-active-users
 * Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=APRNBJUZHRP7G
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'webi_active_user' ) ) {
    class webi_active_user {
        public function __construct(){
           
            register_activation_hook( __FILE__, array($this, 'webizito_users_status_init' ));
            add_action('init', array($this, 'webizito_users_status_init'));
            add_action('wp_loaded', array($this,'webizito_enqueue_script'));
            add_action('admin_enqueue_scripts', array($this,'webi_enqueue_custom_scripts'));
            add_action('admin_init', array($this, 'webizito_users_status_init'));
            add_action('wp_dashboard_setup', array($this, 'webizito_active_users_metabox'));
            add_filter('manage_users_columns', array($this, 'webizito_user_columns_head'));
            add_action('manage_users_custom_column', array($this, 'webizito_user_columns_content'), 10, 10);
            add_filter('views_users', array($this, 'webizito_modify_user_view' ));
            add_action('admin_bar_menu',  array($this, 'webizito_admin_bar_link'),999);
            add_filter('plugin_row_meta', array($this, 'webizito_support_and_faq_links'), 10, 4 );
            add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array($this, 'webizito_plugin_by_link'), 10, 2 );
            add_action('admin_notices', array($this,'webizito_display_notice'));
            register_deactivation_hook( __FILE__, array($this,'webizito_display_notice' ));
            register_deactivation_hook( __FILE__, array($this,'webizito_delete_transient' ));
            
            $this->webizito_active_user_shortcode();
        }    

        public function webizito_enqueue_script() {
           wp_enqueue_style( 'style-css', plugin_dir_url( __FILE__ ) . 'assets/css/style.css' );
        }

        public function webi_enqueue_custom_scripts() {
            wp_enqueue_script('webi-plugin-script', plugin_dir_url(__FILE__) . 'assets/js/custom.js', array('jquery'), rand(1,9999), true);
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

        //Display Status in Users Page 
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
            echo '<div><a href="users.php">' . $user_count['total_users'] . ' ' . $users_plural . '</a> <small>(' . $this->webizito_online_users('count') . ' currently active)</small>
                        <br />
                        <strong><a href="https://wordpress.org/support/plugin/online-active-users/reviews/?rate=5#new-post" target="_blank">Rate our plugin &nbsp;<span style="color:#ffb900;font-size: 18px;position:relative;top:0.1em;">★★★★★</span></a></strong></div>';
        }

        //Active User Shortcode
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

        //Display Active User in Admin Bar 
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

        public function webizito_support_and_faq_links( $links_array, $plugin_file_name, $plugin_data, $status ) {
            if ( strpos( $plugin_file_name, basename(__FILE__) ) ) {

                // You can still use `array_unshift()` to add links at the beginning.
                $links_array[] = '<a href="https://wordpress.org/support/plugin/online-active-users/" target="_blank">Support</a>';
                $links_array[] = '<a href="https://webizito.com/wp-online-active-users/" target="_blank">Docs</a>';
                $links_array[] = '<strong><a href="https://wordpress.org/support/plugin/online-active-users/reviews/?rate=5#new-post" target="_blank">Rate our plugin  <span style="color:#ffb900;font-size: 18px;position:relative;top:0.1em;">★★★★★</span></a></strong>';
            }
            return $links_array;
        }

        public function webizito_plugin_by_link( $links ){
            $url = 'https://webizito.com/';
            $links[] = '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=APRNBJUZHRP7G" target="_blank">' . __( '<span style="font-weight: bold;">Donate</span>', 'wp-online-active-users' ) . '</a>';
            $_link = '<a href="'.$url.'" target="_blank">' . __( 'By <span>Webizito</span>', 'wp-online-active-users' ) . '</a>';
            $links[] = $_link;
            return $links;
        }

        public function webizito_display_notice() {
            echo '<div class="notice notice-success is-dismissible wp-online-active-users-notice" id="wp-online-active-users-notice">';
            echo '<p>Enjoying our Wp online active users plugin? Please consider leaving us a review <a href="https://wordpress.org/support/plugin/online-active-users/reviews/?rate=5#new-post" target="_blank">here</a>. Or Support with a small donation <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=APRNBJUZHRP7G" target="_blank">here</a>. We would greatly appreciate it!</p>';
            echo '</div>';
        }

        public function webizito_delete_transient() {
            delete_transient( 'users_status' );
        }

    }
}
$myPlugin = new webi_active_user();