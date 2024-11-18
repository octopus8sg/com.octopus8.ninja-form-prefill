<?php
/*
Plugin Name: Ninja Form Pre-fill
Description: Pre-fill Ninja Form fields with data via a custom plugin.
Version: 1.0
Author: MJ
*/

add_action('init', 'start_session_for_singpass_plugin', 1);
function start_session_for_singpass_plugin()
{
    if (!session_id()) {
        session_start();
    }
}

function set_userinfo_data($data)
{
    global $singpass_userinfo;
    if (!empty($data)) {
        error_log("data" . json_encode($data));
        $decoded_data = json_decode($data, true);
        unset($_SESSION['singpass_userinfo'], $_SESSION['singpass_userinfo_expiration']);
        $_SESSION['singpass_userinfo'] = [
            'uinfin' => $decoded_data['uinfin']['value'],
            'date_of_birth' => $decoded_data['dob']['value'] ?? '',
            'email' => $decoded_data['email']['value'] ?? '',
            'mobile_no' => $decoded_data['mobileno']['prefix']['value'] . $decoded_data['mobileno']['areacode']['value'] . $decoded_data['mobileno']['nbr']['value'],
            'full_name' => $decoded_data['name']['value'] ?? '',
            // 'nationality' => $decoded_data['nationality']['code'],
            // 'race' => $decoded_data['race']['code'],
            'address' => isset($decoded_data['regadd']['country']['code']) && $decoded_data['regadd']['country']['code'] === 'SG'
                ? (
                        // Building the Singapore address format
                    (isset($decoded_data['regadd']['block']['value']) ? $decoded_data['regadd']['block']['value'] . ' ' : '') .
                    (isset($decoded_data['regadd']['street']['value']) ? $decoded_data['regadd']['street']['value'] . ' ' : '') .
                        // Validate floor number and convert to two-digit format (01, 02, etc.)
                    (isset($decoded_data['regadd']['floor']['value'], $decoded_data['regadd']['unit']['value']) &&
                        preg_match('/^[0-9]$/', $decoded_data['regadd']['floor']['value'])
                        ? '#' . str_pad($decoded_data['regadd']['floor']['value'], 2, '0', STR_PAD_LEFT) . '-' . $decoded_data['regadd']['unit']['value'] . ' '
                        : ''
                    ) .
                    (isset($decoded_data['regadd']['building']['value']) ? $decoded_data['regadd']['building']['value'] . ' ' : '') .
                    'Singapore ' .
                    (isset($decoded_data['regadd']['postal']['value']) ? $decoded_data['regadd']['postal']['value'] : '')
                )
                // Building the non-Singapore address format
                : (
                    (isset($decoded_data['regadd']['line1']['value']) ? $decoded_data['regadd']['line1']['value'] . ' ' : '') .
                    (isset($decoded_data['regadd']['line2']['value']) ? $decoded_data['regadd']['line2']['value'] : '')
                ),
            // 'sex' => $decoded_data['sex']['code'],
        ];
        $_SESSION['singpass_userinfo_expiration'] = time() + 60;
    }
}

add_action('wp_ajax_singpass_button_pressed', 'singpass_button_pressed');
add_action('wp_ajax_nopriv_singpass_button_pressed', 'singpass_button_pressed');

function singpass_button_pressed()
{
    // Include any required files, e.g., for CiviCRM initialization
    include_once '/var/www/html/wp-content/plugins/civicrm/civicrm.settings.php';
    civicrm_initialize();

    error_log("The Singpass button is pressed");

    // $get_prefill_data = get_prefill_data();
    // Simulate calling ninja_form_prefill_fields for each field

    $singpass_userinfo = $_SESSION['singpass_userinfo'] ?? [];
    $singpass_userinfo_expiration = $_SESSION['singpass_userinfo_expiration'] ?? null;
    unset($_SESSION['singpass_userinfo']);
    unset($_SESSION['singpass_userinfo_expiration']);
    error_log('userinfo data: ' . json_encode($singpass_userinfo, JSON_PRETTY_PRINT) . "\nexpiration: " . $singpass_userinfo_expiration);

    if (!empty($singpass_userinfo) && !empty($singpass_userinfo)) {
        $isExpired = time() > $singpass_userinfo_expiration;
        error_log("E: " . $isExpired);
        if (!$isExpired) {
            $prefill_data = [
                'uinfin'=>$singpass_userinfo['uinfin'],
                'date_of_birth'=>$singpass_userinfo['date_of_birth'],
                'email' => $singpass_userinfo['email'],
                'mobile_no' => $singpass_userinfo['mobile_no'],
                'full_name' => $singpass_userinfo['full_name'],
                // 'nationality' => $singpass_userinfo['nationality'],
                // 'race' => $singpass_userinfo['race'],
                'address' => $singpass_userinfo['address'],
                // 'sex' => $singpass_userinfo['sex'],
            ];

            // Log the prefill data for debugging
            error_log('Prefill Data for AJAX: ' . print_r($prefill_data, true));


            // Send prefill data back as the AJAX response
            $response = array(
                'message' => 'Button pressed and prefill data retrieved successfully!',
                'data' => $prefill_data
            );

            wp_send_json_success($response);
        } else {
            wp_send_json_error('Session expired');
        }
    } else {
        wp_send_json_error('Singpass session empty');
    }

    wp_die(); // End the AJAX request
}