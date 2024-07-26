<?php
/*
Plugin Name: woocommerce-email-verification-after-checkout
Description: Handles email verification after checkout and login confirmation with enhanced security measures.
Version: 1.0
Author: RB Soikot
*/

// Hook into WooCommerce thank you page to send verification email after checkout
add_action('woocommerce_thankyou', 'custom_send_verification_email_after_checkout', 10, 1);
function custom_send_verification_email_after_checkout($order_id) {
    $order = wc_get_order($order_id);
    if (!$order || !is_object($order)) {
        error_log('Custom Email Verification Plugin: Error retrieving order details for order ID ' . $order_id);
        return;
    }
    
    $user_id = $order->get_user_id();
    if (!$user_id || !is_numeric($user_id)) {
        error_log('Custom Email Verification Plugin: No valid user found for order ID ' . $order_id);
        return;
    }

    // Generate verification token securely
    $verification_token = wp_generate_password(32, false);

    // Save verification token and purchase time in user meta
    update_user_meta($user_id, 'verification_token', $verification_token);
    update_user_meta($user_id, 'purchase_time', current_time('timestamp'));

    // Send verification email
    custom_send_verification_email($user_id, $verification_token);
}

// Hook into user registration to set purchase time
add_action('user_register', 'custom_set_purchase_time_on_registration');
function custom_set_purchase_time_on_registration($user_id) {
    update_user_meta($user_id, 'purchase_time', current_time('timestamp'));
}

// Hook into login to check if user is verified
add_action('wp_login', 'custom_check_user_verification_status', 10, 2);
function custom_check_user_verification_status($user_login, $user) {
    $user_id = $user->ID;
    if (!$user_id || !is_numeric($user_id)) {
        error_log('Custom Email Verification Plugin: No valid user ID found during login check');
        return;
    }

    $is_verified = get_user_meta($user_id, 'is_verified', true);

    // Get purchase time from user meta
    $purchase_time = get_user_meta($user_id, 'purchase_time', true);

    // Check if purchase time is retrieved successfully
    if ($purchase_time === false) {
        // Handle the error, e.g., log it or set a default value
        error_log("Custom Email Verification Plugin: Failed to retrieve purchase time for user ID $user_id.");
        $purchase_time = 0; // Set a default value if needed
    }

    // Ensure purchase time is an integer (timestamp)
    $purchase_time = intval($purchase_time);

    // Get the current time as a timestamp
    $current_time = current_time('timestamp');

    // Check if current time retrieval is successful
    if ($current_time === false) {
        // Handle the error, e.g., log it or set a default value
        error_log("Custom Email Verification Plugin: Failed to retrieve current time.");
        $current_time = time(); // Use PHP's time() function as a fallback
    }

    // Verify user if not already verified and purchase time is recent
    if (!$is_verified && absint($current_time - $purchase_time) > 300) { // 5 minutes in seconds
        // Generate a new verification token securely
        $verification_token = wp_generate_password(32, false);
        update_user_meta($user_id, 'verification_token', $verification_token);

        // Send verification email
        custom_send_verification_email($user_id, $verification_token);

        // Redirect to verification notice page
        wp_logout();
        wp_redirect(home_url('/verify-email?user_id=' . $user_id));
        exit;
    }
}

// Function to send verification email securely
function custom_send_verification_email($user_id, $verification_token) {
    $user = get_userdata($user_id);
    if (!$user) {
        error_log('Custom Email Verification Plugin: No valid user found for user ID ' . $user_id);
        return;
    }

    $subject = 'Please Verify Your Email Address';
    $message = 'Dear customer, please click on the following link to verify your email address: ' . add_query_arg(array(
        'action' => 'verify_email',
        'user_id' => $user_id,
        'token' => $verification_token
    ), home_url('/')) . "\n\nThis link will expire in 5 minutes.";

    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
    );

    if (!wp_mail($user->user_email, $subject, $message, $headers)) {
        error_log('Custom Email Verification Plugin: Failed to send verification email to user ID ' . $user_id);
    }
}

// Handle successful email verification securely
add_action('template_redirect', 'custom_handle_successful_email_verification');
function custom_handle_successful_email_verification() {
    if (isset($_GET['action']) && $_GET['action'] === 'verify_email' && isset($_GET['user_id']) && isset($_GET['token'])) {
        $user_id = absint($_GET['user_id']);
        $token = sanitize_text_field($_GET['token']);

        $saved_token = get_user_meta($user_id, 'verification_token', true);

        if ($saved_token === $token) {
            // Mark user as verified
            update_user_meta($user_id, 'is_verified', true);
            update_user_meta($user_id, 'verification_time', current_time('mysql'));
            delete_user_meta($user_id, 'verification_token');

            // Send success email
            custom_send_verification_success_email($user_id);

            // Redirect to my account page
            wp_redirect(home_url('/my-account'));
            exit;
        } else {
            // Invalid verification link, handle error securely
            wp_redirect(home_url('/verify-email?error=invalid_link'));
            exit;
        }
    }
}

// Function to send success email upon verification securely
function custom_send_verification_success_email($user_id) {
    $user = get_userdata($user_id);
    if (!$user) {
        error_log('Custom Email Verification Plugin: No valid user found for user ID ' . $user_id);
        return;
    }

    $subject = 'Email Verified Successfully';
    $message = 'Dear customer, your email address has been successfully verified.';

    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
    );

    if (!wp_mail($user->user_email, $subject, $message, $headers)) {
        error_log('Custom Email Verification Plugin: Failed to send verification success email to user ID ' . $user_id);
    }
}

// Add admin menu securely
add_action('admin_menu', 'custom_email_verification_admin_menu');
function custom_email_verification_admin_menu() {
    add_menu_page(
        'Verified Users',
        'Verified Users',
        'manage_options',
        'custom-email-verified-users',
        'custom_email_verified_users_page'
    );
}

// Display verified users page in admin securely with pagination
function custom_email_verified_users_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $users_per_page = 20;
    $paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
    $offset = ($paged - 1) * $users_per_page;

    $args = array(
        'meta_key'   => 'is_verified',
        'meta_value' => true,
        'number'     => $users_per_page,
        'offset'     => $offset,
    );

    $verified_users = get_users($args);
    $total_users_count = count_users()['total_users'];
    $total_pages = ceil($total_users_count / $users_per_page);

    ?>
    <div class="wrap">
        <h1>Verified Users</h1>

        <?php if ($verified_users) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('User Name', 'text-domain'); ?></th>
                        <th><?php esc_html_e('Email', 'text-domain'); ?></th>
                        <th><?php esc_html_e('Verified Date', 'text-domain'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($verified_users as $user) : ?>
                        <tr>
                            <td><a href="<?php echo admin_url('user-edit.php?user_id=' . $user->ID); ?>"><?php echo esc_html($user->first_name . ' ' . $user->last_name); ?></a></td>
                            <td><?php echo esc_html($user->user_email); ?></td>
                            <td><?php echo esc_html(get_user_meta($user->ID, 'verification_time', true)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1) : ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php echo sprintf(_n('%s user', '%s users', $total_users_count, 'text-domain'), number_format_i18n($total_users_count)); ?></span>
                        <?php if ($paged > 1) : ?>
                            <a class="prev-page" href="<?php echo admin_url('admin.php?page=custom-email-verified-users&paged=' . ($paged - 1)); ?>">
                                <span class="screen-reader-text">Previous page</span>
                                <span aria-hidden="true">&lsaquo;</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($paged < $total_pages) : ?>
                            <a class="next-page" href="<?php echo admin_url('admin.php?page=custom-email-verified-users&paged=' . ($paged + 1)); ?>">
                                <span class="screen-reader-text">Next page</span>
                                <span aria-hidden="true">&rsaquo;</span>
                            </a>
                        <?php endif; ?>
                    </div>
                    <br class="clear">
                </div>
            <?php endif; ?>

        <?php else : ?>
            <p>No verified users found.</p>
        <?php endif; ?>
    </div>
    <?php
}


// Shortcode to display the verification message and resend button
add_shortcode('email_verification_message', 'custom_email_verification_message');
function custom_email_verification_message($atts) {
    if (isset($_GET['user_id'])) {
        $user_id = absint($_GET['user_id']);
        $error = isset($_GET['error']) ? sanitize_text_field($_GET['error']) : '';

        if ($error === 'invalid_link') {
            $message = '<p>Your verification link is invalid or has expired. Please request a new verification email.</p>';
        } else {
            $message = '<p>Please verify your email address by clicking the link in the email we sent you.</p>';
        }

        $resend_button = '<form method="post" action="">
                            <input type="hidden" name="resend_verification" value="true">
                            <input type="hidden" name="user_id" value="' . esc_attr($user_id) . '">
                            <button type="submit">Resend Verification Email</button>
                          </form>';

        return $message . $resend_button;
    }

    return '<p>Invalid user ID.</p>';
}

// Handle resend verification email
add_action('template_redirect', 'custom_resend_verification_email');
function custom_resend_verification_email() {
    if (isset($_POST['resend_verification']) && isset($_POST['user_id'])) {
        $user_id = absint($_POST['user_id']);

        if ($user_id) {
            $verification_token = wp_generate_password(32, false);
            update_user_meta($user_id, 'verification_token', $verification_token);

            custom_send_verification_email($user_id, $verification_token);

            wp_redirect(home_url('/verify-email?user_id=' . $user_id . '&resent=true'));
            exit;
        }
    }
}

// Activate plugin securely
register_activation_hook(__FILE__, 'custom_activate_plugin');
function custom_activate_plugin() {
    flush_rewrite_rules();
}

// Deactivate plugin securely
register_deactivation_hook(__FILE__, 'custom_deactivate_plugin');
function custom_deactivate_plugin() {
    flush_rewrite_rules();
}
