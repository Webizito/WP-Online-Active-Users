<?php 
/**
* 
* Show User List Data.
*/

defined( 'ABSPATH' ) || exit;

global $wpoau_users;

$all_roles = [];
$all_countries = [];

foreach ($users as $u) {
    $u_obj = get_userdata($u['id']);
    if (!empty($u_obj->roles)) {
        foreach ($u_obj->roles as $r) {
            $all_roles[$r] = ucfirst($r);
        }
    }
    if (!empty($u['country'])) {
        $all_countries[$u['country']] = $u['country'];
    }
}

$filter_role = isset($_GET['filter_role']) ? sanitize_text_field($_GET['filter_role']) : '';
$filter_country = isset($_GET['filter_country']) ? sanitize_text_field($_GET['filter_country']) : '';

echo '<div class="webi-table-container">';
echo '<div class="table-header-wrap">';
echo '<form method="get">
    <input type="hidden" name="page" value="wpoau-active-users" />
    <select name="filter_role" style="margin-right: 10px;">
        <option value="">All Roles</option>';
        foreach ($all_roles as $role => $label) {
            $selected = ($filter_role === $role) ? 'selected' : '';
            echo "<option value='{$role}' {$selected}>{$label}</option>";
        }
echo '</select>';

echo '<select name="filter_country" style="margin-right: 10px;">
        <option value="">All Countries</option>';
        foreach ($all_countries as $c) {
            $selected = ($filter_country === $c) ? 'selected' : '';
            echo "<option value='{$c}' {$selected}>{$c}</option>";
        }
echo '</select>';

echo '<button class="button">Filter</button>';
echo '</form>';
echo '<div class="top-total-user">Total Online Users: ' . count($users) . '</div>';
echo '</div>';
echo '<table class="webi-online-table">';
 
echo '<thead><tr>
    <th>Sr. No.</th>
    <th>Avatar</th>
    <th>Username</th>
    <th>Full Name</th>
    <th>User Role</th>
    <th>IP Address</th>
    <th>Country</th>
    <th>Timezone</th>
    <th>Status</th>
</tr></thead>';
echo '<tbody>';

if ($filter_role || $filter_country) {
    $users = array_filter($users, function ($u) use ($filter_role, $filter_country) {
        $user_obj = get_userdata($u['id']);

        $match_role = !$filter_role || in_array($filter_role, $user_obj->roles);
        $match_country = !$filter_country || (isset($u['country']) && $u['country'] === $filter_country);

        return $match_role && $match_country;
    });
}

$sr = 1;
foreach ($users as $user) {
    $user_obj = get_userdata($user['id']);
    $avatar = get_avatar($user['id'], 32, '', '', array('class' => 'webi-avatar'));
    $first_name = get_user_meta($user_obj->ID, 'first_name', true);
    $last_name = get_user_meta($user_obj->ID, 'last_name', true);
    $full_name = trim($first_name . ' ' . $last_name);
    if (!$full_name) {
        $full_name = $user_obj->display_name;
    }
    $is_online = $wpoau_users->wpoau_is_user_online($user['id']);
    $last_seen = date('M j, Y @ g:ia', $user['last']);
    $ip = isset($user['ip']) ? $user['ip'] : 'Unknown';
    $country = isset($user['country']) ? $user['country'] : 'Unknown';
    $timezone = isset($user['timezone']) ? $user['timezone'] : 'Unknown';
    $roles = isset($user_obj->roles) ? implode(', ', $user_obj->roles) : '—';
    $country_code = strtolower($wpoau_users->wpoau_get_user_country_code($ip));

    echo '<tr>';
    echo '<td>' . $sr++ . '</td>';
    echo '<td>' . $avatar . '</td>';
    echo '<td>' . esc_html($user_obj->user_login) . '</td>';
    echo '<td>' . esc_html($full_name);
    echo '<td>' . esc_html(ucfirst($roles)) . '</td>';
    echo '<td>' . esc_html($ip) . '</td>';
    echo '<td><img src="https://flagcdn.com/16x12/' . esc_attr($country_code) . '.webp" class="webi-country-flag" alt="' . esc_attr($country_code) . '" />' . esc_html($country) . '</td>';
    echo '<td>' . esc_html($timezone) . '</td>';
    echo '<td class="status-cols-wrap">';
    if ($is_online) {
        echo ' <span class="online-logged-in">●</span> <br />';
    }
    echo $is_online
        ? '<strong style="color:green;">Online Now</strong>'
        : esc_html($last_seen);
    echo '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div>';
echo '</div>';
