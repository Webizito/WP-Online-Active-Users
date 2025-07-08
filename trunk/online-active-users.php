<?php
/**
 * Plugin Name: Online Active Users
 * Plugin Title: Online Active Users Plugin
 * Plugin URI: https://wordpress.org/plugins/online-active-users/
 * Description: Monitor and display real-time online users and last seen status on your WordPress site with WP Online Active Users plugin.
 * Tags: wp-online-active-users, online-user, active-users, woocommerce-users, user-status
 * Version: 2.9
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

if ( ! defined( 'WPOAU_PLUGIN_DIR' ) ) {
    define( 'WPOAU_PLUGIN_DIR', dirname( __FILE__ ) );
}

if ( ! defined( 'WPOAU_PLUGIN_FILE' ) ) {
    define( 'WPOAU_PLUGIN_FILE', __FILE__ );
}

 require_once WPOAU_PLUGIN_DIR . '/inc/webi-functions.php';

if ( ! class_exists( 'webi_active_user' ) ) {
    class webi_active_user {

        public $wpoau;

        public function __construct(){

            $this->wpoau = new Wpoau_Active_Users($this);
            $GLOBALS['wpoau_users'] = $this->wpoau;

            register_activation_hook( __FILE__, array($this->wpoau, 'wpoau_users_status_init' ));
            add_action('init', array($this->wpoau, 'wpoau_users_status_init'));
            add_action('init', array($this->wpoau, 'webi_track_user_activity'));
            add_action('clear_auth_cookie', array($this, 'wpoau_user_logout'));
            add_action('wp_loaded', array($this,'wpoau_enqueue_script'));
            add_action('admin_enqueue_scripts', array($this,'webi_enqueue_custom_scripts'));
            add_action('admin_init', array($this->wpoau, 'wpoau_users_status_init'));
            add_action('wp_dashboard_setup', array($this->wpoau, 'wpoau_active_users_metabox'));
            add_filter('manage_users_columns', array($this->wpoau, 'wpoau_user_columns_head'));
            add_action('manage_users_custom_column', array($this->wpoau, 'wpoau_user_columns_content'), 10, 10);
            add_filter('views_users', array($this, 'wpoau_modify_user_view' ));
            add_action('admin_bar_menu',  array($this->wpoau, 'wpoau_admin_bar_link'),999);
            add_filter('plugin_row_meta', array($this, 'wpoau_support_and_faq_links'), 10, 4 );
            add_action('admin_menu', array($this, 'wpoau_add_admin_submenu'));
            add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array($this, 'wpoau_plugin_by_link'), 10, 2 );
            add_action('admin_notices', array($this,'wpoau_display_notice'));
            register_deactivation_hook( __FILE__, array($this,'wpoau_display_notice' ));
            register_deactivation_hook( __FILE__, array($this,'wpoau_delete_transient' ));
            
            $this->wpoau->wpoau_active_user_shortcode();
        }    

        public function wpoau_enqueue_script() {
           wp_enqueue_style( 'style-css', plugin_dir_url( __FILE__ ) . '/assets/css/style.css' );
        }

        public function webi_enqueue_custom_scripts() {
            wp_enqueue_script('webi-plugin-script', plugin_dir_url( __FILE__ ) . '/assets/js/custom.js', array('jquery'), rand(1,9999), true);

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
       
        // Remove user from the online list when they log out
        public function wpoau_user_logout() {
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

        //Get a count of online users, or an array of online user IDs
        public function wpoau_online_users($return='count'){
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

        //Display Sub Menu 
        public function wpoau_add_admin_submenu() {
            add_users_page(
                'Online Active Users',                     
                'Online Active Users',                    
                'list_users',                      
                'wpoau-active-users',           
                array($this, 'wpoau_active_users_page')
            );
        }

        public function wpoau_active_users_page() {
            echo '<div class="wrap"><h1>Online Users Lists</h1>';

            $users = $this->wpoau_online_users('array');

            if (empty($users)) {
                echo '<p>No users are online right now.</p></div>';
                return;
            }
            
            require_once WPOAU_PLUGIN_DIR . '/inc/user-list-table.php';
        }

        public function wpoau_modify_user_view( $views ) {

            $logged_in_users = get_transient('users_status');
            $user = wp_get_current_user();

            $logged_in_users[$user->ID] = array(
                    'id' => $user->ID,
                    'username' => $user->user_login,
                    'last' => time(),
                );

            $view = '<a href=' . admin_url('users.php?page=wpoau-active-users') . '>User Online <span class="count">('.$this->wpoau_online_users('count').')</span></a>';

            $views['status'] = $view;
            return $views;
        }

        public function wpoau_support_and_faq_links( $links_array, $plugin_file_name, $plugin_data, $status ) {
            if ( strpos( $plugin_file_name, basename(__FILE__) ) ) {

                // You can still use `array_unshift()` to add links at the beginning.
                $links_array[] = '<a href="https://wordpress.org/support/plugin/online-active-users/" target="_blank">Support</a>';
                $links_array[] = '<a href="https://webizito.com/wp-online-active-users/" target="_blank">Docs</a>';
                $links_array[] = '<strong><a href="https://wordpress.org/support/plugin/online-active-users/reviews/?rate=5#new-post" target="_blank">Rate our plugin  <span style="color:#ffb900;font-size: 18px;position:relative;top:0.1em;">★★★★★</span></a></strong>';
            }
            return $links_array;
        }

        public function wpoau_plugin_by_link( $links ){
            $url = 'https://webizito.com/';
            $links[] = '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=APRNBJUZHRP7G" target="_blank">' . __( '<span style="font-weight: bold;">Donate</span>', 'wp-online-active-users' ) . '</a>';
            $_link = '<a href="'.$url.'" target="_blank">' . __( 'By <span>Webizito</span>', 'wp-online-active-users' ) . '</a>';
            $links[] = $_link;
            return $links;
        }

        public function wpoau_display_notice() {
            echo '<div class="notice notice-success is-dismissible wp-online-active-users-notice" id="wp-online-active-users-notice">';
            echo '<p>Enjoying our Wp online active users plugin? Please consider leaving us a review <a href="https://wordpress.org/support/plugin/online-active-users/reviews/?rate=5#new-post" target="_blank">here</a>. Or Support with a small donation <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=APRNBJUZHRP7G" target="_blank">here</a>. We would greatly appreciate it!</p>';
            echo '</div>';
        }

        public function wpoau_delete_transient() {
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
                $active_users_count = $webi_plugin->wpoau_online_users( 'count' );
                echo '<div class="webi-widget-content">';
                echo '<p>Online Active Users: <strong>' . $active_users_count . '</strong></p>';
                echo '</div>';
            } else {
                echo '<p>WP Active User plugin not found.</p>';
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
