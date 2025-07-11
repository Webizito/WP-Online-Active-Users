<?php
/**
 * Plugin Name: Online Active Users
 * Plugin Title: Online Active Users Plugin
 * Plugin URI: https://wordpress.org/plugins/online-active-users/
 * Description: Monitor and display real-time online users and last seen status on your WordPress site with WP Online Active Users plugin.
 * Tags: wp-online-active-users, online-user, active-users, woocommerce-users, user-status
 * Version: 2.8
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
            add_action('init', array($this, 'webi_track_user_activity'));
            add_action('clear_auth_cookie', array($this, 'webizito_user_logout'));
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

            wp_add_inline_script('webi-plugin-script', "
                jQuery(document).ready(function($) {
                    $('.webizito-last-seen').each(function() {
                        var timestamp = $(this).data('timestamp');
                        if (timestamp) {
                            var date = new Date(timestamp * 1000);
                            $(this).text(date.toLocaleString());
                        }
                    });
                });
            ");
        }

        //Update user online status
        public function webizito_user_login($user_login, $user) {
            $logged_in_users = get_transient('users_status');
            
            if (!is_array($logged_in_users)) {
                $logged_in_users = array();
            }

            $logged_in_users[$user->ID] = array(
                'id' => $user->ID,
                'username' => $user->user_login,
                'last' => time(),
            );

            set_transient('users_status', $logged_in_users, 60);
            update_user_meta($user->ID, 'last_seen', time());
        }

        public function webizito_users_status_init() {
            $logged_in_users = get_transient('users_status');
            
            if (!is_array($logged_in_users)) {
                $logged_in_users = array();
            }

            $user = wp_get_current_user();
            if ($user->ID) {
                // Update user's last seen time
                $logged_in_users[$user->ID] = array(
                    'id' => $user->ID,
                    'username' => $user->user_login,
                    'last' => time(),
                );

                // Store without expiration, keep refreshing via heartbeat
                set_transient('users_status', $logged_in_users);
            }
        }

        // Remove user from the online list when they log out
        public function webizito_user_logout() {
            if (!is_user_logged_in()) return; // Prevent errors if already logged out

            $user_id = get_current_user_id();
            if (!$user_id) return;

            $logged_in_users = get_transient('users_status');

            if (isset($logged_in_users[$user_id])) {
                unset($logged_in_users[$user_id]);
                set_transient('users_status', $logged_in_users, 60);
            }

            update_user_meta($user_id, 'last_seen', time());
        }

        //Check if a user has been online in the last 5 minutes
        public function webizito_is_user_online($id){  
            $logged_in_users = get_transient('users_status');
            
            // User is online if found in transient and last activity is within 50 seconds
            if (isset($logged_in_users[$id]) && $logged_in_users[$id]['last'] > time() - 50) {
                return true;
            }

            // Fallback: Check user meta if transient fails
            $last_seen = get_user_meta($id, 'last_seen', true);
            return ($last_seen && $last_seen > time() - 50);
        }

        //Check when a user was last online.
        public function webizito_user_last_online($id){
            $logged_in_users = get_transient('users_status') ?: array();
            return isset($logged_in_users[$id]['last']) ? $logged_in_users[$id]['last'] : false;
        }

         // Always update last seen
        public function webi_track_user_activity() {
            if (is_user_logged_in()) {
                $user_id = get_current_user_id();
                update_user_meta($user_id, 'last_seen', time());
            }
        }

        //Add columns to user listings
        public function webizito_user_columns_head($defaults){
            $defaults['status'] = 'User Online Status';
            return $defaults;
        }

        //Display Status in Users Page 
        public function webizito_user_columns_content($value = '', $column_name, $id) {
            if ($column_name == 'status') {
                if ($this->webizito_is_user_online($id)) {
                    return '<span class="online-logged-in">●</span> <br /><small><em>Online Now</em></small>';
                } else {
                    $last_seen = get_user_meta($id, 'last_seen', true);

                    if (!$last_seen) {
                        $last_seen_text = "<small><em>Never Logged In</em></small>";
                        return '<span class="never-dot">●</span> <br />' . $last_seen_text;
                    } else {
                        $last_seen_text = "<small>Last Seen: <br /><em class='webizito-last-seen' data-timestamp='{$last_seen}'>" . date('M j, Y @ g:ia', $last_seen) . "</em></small>";
                        return '<span class="offline-dot">●</span> <br />' . $last_seen_text;
                    }

                    
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

if ( ! class_exists( 'Webi_Custom_Widget' ) ) {
    class Webi_Custom_Widget extends WP_Widget {
        
        // Constructor
        public function __construct() {
            parent::__construct(
                'webi_custom_widget',
                __( 'WP Online Active User', 'text_domain' ),
                array( 'description' => __( 'Display Online Active Users.', 'text_domain' ), )
            );
        }

        // Front-end display
        public function widget( $args, $instance ) {
            echo $args['before_widget'];

            // Title
            if ( ! empty( $instance['title'] ) ) {
                echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
            }

            // Widget content
            if ( class_exists( 'webi_active_user' ) ) {
                $webi_plugin = new webi_active_user();
                $active_users_count = $webi_plugin->webizito_online_users( 'count' );
                echo '<div class="webi-widget-content">';
                echo '<p>Online Active Users: <strong>' . $active_users_count . '</strong></p>';
                echo '</div>';
            } else {
                echo '<p>Webi Active User plugin not found.</p>';
            }

            echo $args['after_widget'];
        }

        // Back-end widget form
        public function form( $instance ) {
            $title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Active Users', 'text_domain' );
            ?>
            <p>
                <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_attr_e( 'Title:', 'text_domain' ); ?></label>
                <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
            </p>
            <?php
        }

        // Save widget settings
        public function update( $new_instance, $old_instance ) {
            $instance = array();
            $instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
            return $instance;
        }
    }
}

// Register the custom widget
add_action( 'widgets_init', function() {
    register_widget( 'Webi_Custom_Widget' );
} );
