<?php 

//Active Users Metabox
class Wpoau_Active_Users {

	protected $main_class;

	public function __construct( $main_class ) {
        $this->main_class = $main_class;
    }

    public function wpoau_users_status_init() {
        $logged_in_users = get_transient('users_status');
        
        if (!is_array($logged_in_users)) {
            $logged_in_users = array();
        }

        $user = wp_get_current_user();
        if ($user->ID) {
            $ip = $this->wpoau_get_user_ip();
            $country = $this->wpoau_get_user_country($ip);
            $timezone = $this->wpoau_get_user_timezone($ip);

            $logged_in_users[$user->ID] = array(
                'id' => $user->ID,
                'username' => $user->user_login,
                'last' => time(),
                'ip' => $ip,
                'country' => $country,
                'timezone' => $timezone,
            );

            // Store without expiration, keep refreshing via heartbeat
            set_transient('users_status', $logged_in_users);
            update_user_meta($user->ID, 'last_seen', time());
        }
    }

    //Check if a user has been online in the last 5 minutes
    public function wpoau_is_user_online($id){  
        $logged_in_users = get_transient('users_status');
        
        // User is online if found in transient and last activity is within 50 seconds
        if (isset($logged_in_users[$id]) && $logged_in_users[$id]['last'] > time() - 50) {
            return true;
        }

        // Fallback: Check user meta if transient fails
        $last_seen = get_user_meta($id, 'last_seen', true);
        return ($last_seen && $last_seen > time() - 50);
    }

    //Display Status in Users Page 
    public function wpoau_user_columns_content($value = '', $column_name, $id) {
        if ($column_name == 'status') {
            if ($this->wpoau_is_user_online($id)) {
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

    // Always update last seen
    public function webi_track_user_activity() {
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            update_user_meta($user_id, 'last_seen', time());
        }
    }

    //Add columns to user listings
    public function wpoau_user_columns_head($defaults){
        $defaults['status'] = 'User Online Status';
        return $defaults;
    }

    //Active User Shortcode
    public function wpoau_active_user_shortcode(){
        add_shortcode('webi_active_user', array($this, 'wpoau_active_user'));
    }  

     public function wpoau_active_user(){
        ob_start();
        if(is_user_logged_in()){
            $user_count = count_users();
            $users_plural = ( $user_count['total_users'] == 1 ) ? 'User' : 'Users';
            echo '<div class="webi-active-users"> Currently Active Users: <small>(' . $this->main_class->wpoau_online_users('count') . ')</small></div>';
        }  
        return ob_get_clean();
    }    

    //Display Active User in Admin Bar 
    public function wpoau_admin_bar_link() {
        global $wp_admin_bar;
        if ( !is_super_admin() || !is_admin_bar_showing() )
            return;
        $wp_admin_bar->add_menu( array(
            'id' => 'webi_user_link', 
            'title' => '<span class="ab-icon online-logged-in">●</span><span class="ab-label">' . __( 'Active Users (' . $this->main_class->wpoau_online_users('count') . ')') .'</span>',
            'href' => esc_url( admin_url( 'users.php?page=wpoau-active-users' ) )
        ) );
    }

    public function wpoau_active_users_metabox() {
        global $wp_meta_boxes;
        wp_add_dashboard_widget('webizito_active_users', 'Active Users', array($this, 'wpoau_active_user_dashboard'));
    }

    public function wpoau_active_user_dashboard($post, $callback_args) {
        $user_count = count_users();
        $users_plural = ($user_count['total_users'] == 1) ? 'User' : 'Users';
        
        $active_users = $this->main_class->wpoau_online_users('count');

        echo '<div><a href="users.php?page=wpoau-active-users">' . $user_count['total_users'] . ' ' . $users_plural . '</a> <small>(' . $active_users . ' currently active)</small>
              <br />
              <strong><a href="https://wordpress.org/support/plugin/online-active-users/reviews/?rate=5#new-post" target="_blank">Rate our plugin &nbsp;<span style="color:#ffb900;font-size: 18px;position:relative;top:0.1em;">★★★★★</span></a></strong></div>';
    }

    // Helper method: track transient key in options for cleanup later
    public function wpoau_track_transient_key($key) {
        $stored_keys = get_option('wpoau_transient_keys', []);
        if (!in_array($key, $stored_keys)) {
            $stored_keys[] = $key;
            update_option('wpoau_transient_keys', $stored_keys);
        }
    }

    // Get IP address
    public function wpoau_get_user_ip() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip_list = explode(',', $_SERVER[$key]);
                $ip = trim($ip_list[0]);

                // If IP is ::1 (localhost IPv6), convert to 127.0.0.1 or fetch public IP for dev
                if ($ip === '::1' || $ip === '127.0.0.1') {
                    $response = wp_remote_get('https://api.ipify.org?format=json');
                    if (!is_wp_error($response)) {
                        $data = json_decode(wp_remote_retrieve_body($response), true);
                        if (isset($data['ip'])) {
                            return $data['ip'];
                        }
                    }
                }
                return $ip;
            }
        }

        return 'Unknown';
    }

    // Get country from IP (free API)
    public function wpoau_get_user_country($ip) {
        if ($ip === 'Unknown' || empty($ip)) return 'Unknown';

        $transient_key = 'wpoau_country_name_' . md5($ip);
        $cached = get_transient($transient_key);
        if ($cached !== false) {
            return $cached;
        }

        $response = wp_remote_get("https://ipwho.is/{$ip}");

        if (is_wp_error($response)) return 'Unknown';

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($data) || !$data['success']) {
            return 'Unknown';
        }

        $country = $data['country'] ?? 'Unknown';
        set_transient($transient_key, $country, 24 * HOUR_IN_SECONDS);
        $this->wpoau_track_transient_key($transient_key);

        return $country;
    }


    public function wpoau_get_user_timezone($ip) {
        if ($ip === 'Unknown') return 'Unknown';

        $transient_key = 'wpoau_timezone_' . md5($ip);
        $cached = get_transient($transient_key);
        if ($cached !== false) {
            return $cached;
        }

        $response = wp_remote_get("http://ip-api.com/json/{$ip}?fields=timezone");

        if (is_wp_error($response)) return 'Unknown';

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $timezone = isset($data['timezone']) ? $data['timezone'] : 'Unknown';

        // Cache for 24 hours
        set_transient($transient_key, $timezone, 24 * HOUR_IN_SECONDS);
        $this->wpoau_track_transient_key($transient_key);

        return $timezone;
    }

    public function wpoau_get_user_country_code($ip) {
        if ($ip === 'Unknown') return 'xx';

        $transient_key = 'wpoau_country_' . md5($ip); // prevent long key issues

        // Try getting from transient
        $cached_code = get_transient($transient_key);
        if ($cached_code !== false) {
            return $cached_code;
        }

        // Fetch from API
        $response = wp_remote_get("http://ip-api.com/json/{$ip}?fields=countryCode");

        if (is_wp_error($response)) {
            return 'xx';
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        $code = isset($data['countryCode']) ? strtolower($data['countryCode']) : 'xx';

        // Cache it for 24 hours
        set_transient($transient_key, $code, 24 * HOUR_IN_SECONDS);
        $this->wpoau_track_transient_key($transient_key);
        
        return $code;
    }


}
