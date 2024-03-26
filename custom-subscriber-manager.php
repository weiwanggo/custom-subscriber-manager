<?php
/**
 * Plugin Name: Custom Subscriber Manager
 * Description: This plugin creates a form for users to subscribe and saves the data in a custom database table.
 * Version: 1.0
 * Author: Vivian Wang
 */
function create_custom_subscribers_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_subscribers';
    
    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        // Table doesn't exist, create it
        $charset_collate = $wpdb->get_charset_collate();
    
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            username varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_updated_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
    
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
register_activation_hook(__FILE__, 'create_custom_subscribers_table');


function custom_subscriber_form_shortcode() {
    ob_start(); ?>

    <form id="custom-subscriber-form" method="post">
        <label for="username">Username:</label>
        <input type="text" id="username" name="username" required>
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>
        <button type="button" id="subscribe-button">Subscribe</button>
    </form>

    <div id="message-container" style="display: none;"></div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('subscribe-button').addEventListener('click', function() {
            var formData = new FormData(document.getElementById('custom-subscriber-form'));
            var xhr = new XMLHttpRequest();
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        var response = JSON.parse(xhr.responseText);
                        var messageContainer = document.getElementById('message-container');
                        messageContainer.innerHTML = response.message;
                        messageContainer.style.color = response.success ? 'green' : 'red';
                        messageContainer.style.display = 'block';
                    } else {
                        // Handle server error
                        console.error('Server error');
                    }
                }
            };
             xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>?action=process_custom_subscriber_form');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.send(formData);
        });
    });
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('custom_subscriber_form', 'custom_subscriber_form_shortcode');

add_action('wp_ajax_process_custom_subscriber_form', 'process_custom_subscriber_form');
add_action('wp_ajax_nopriv_process_custom_subscriber_form', 'process_custom_subscriber_form');

function process_custom_subscriber_form() {
    if (isset($_POST['username']) && isset($_POST['email'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'custom_subscribers';
        $email = sanitize_email($_POST['email']);
        
        // Check if email exists and status is active
        $subscriber = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE email = %s AND status = 'active'", $email));
        
        if ($subscriber) {
            // User already subscribed
            echo json_encode(array('success' => false, 'message' => 'You are already subscribed.'));
        } else {
            // Send confirmation email
            $subject = 'Confirm Your Subscription';
            $message = 'Please confirm your subscription by clicking the button below:</br></br>';
            $message .= '<a href="' . admin_url('admin-ajax.php?action=confirm_subscription&email=' . urlencode($email)) . '" style="background-color: #4CAF50; border: none; color: white; padding: 15px 32px; text-align: center; text-decoration: none; display: inline-block; font-size: 16px; margin: 4px 2px; cursor: pointer;">Confirm Subscription</a>';

            // Set headers to ensure HTML content is rendered properly
            
            
            $from_name = 'zhangzhehan';
            $from = 'support@zhangzhehan.net';
            $body = 'Test';
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $headers[] = 'From: '.$from_name.' <'.$from.'>';
            $headers[] = 'Reply-To: '.$from_name.' <'.$from.'>';

            wp_mail($email, $subject, $message);
            //$success = wp_mail($email, $subject, $message, $headers);
            

            // Display confirmation message
            echo json_encode(array('success' => true, 'message' => 'We have received your request. Please check your email and confirm your subscription.'));
        }
    } else {
        // Error case
        echo json_encode(array('success' => false, 'message' => 'Error processing your subscribing request.'));
    }
    wp_die();
}

add_action('wp_ajax_confirm_subscription', 'confirm_subscription');
add_action('wp_ajax_nopriv_confirm_subscription', 'confirm_subscription');

function confirm_subscription() {
    if (isset($_GET['email'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'custom_subscribers';
        $email = sanitize_email($_GET['email']);

        // Add user to database
        $wpdb->insert($table_name, array(
            'email' => $email,
            'status' => 'active'
        ));

        // Display confirmation message
        echo 'Subscription confirmed successfully. Thank you!';
    } else {
        // Error case
        echo 'Error confirming subscription.';
    }
    wp_die();
}





function create_unsubscribe_page() {
    $unsubscribe_page = array(
        'post_title' => 'Unsubscribe',
        'post_content' => '[unsubscribe_form]',
        'post_status' => 'publish',
        'post_type' => 'page'
    );

    // Insert the post into the database
    wp_insert_post($unsubscribe_page);
}
register_activation_hook(__FILE__, 'create_unsubscribe_page');

function unsubscribe_form_shortcode() {
    ob_start(); ?>

    <form id="unsubscribe-form" method="post">
        <p>Are you sure you want to unsubscribe?</p>
        <button type="submit">Confirm</button>
    </form>

    <?php
    return ob_get_clean();
}
add_shortcode('unsubscribe_form', 'unsubscribe_form_shortcode');


function process_unsubscribe_form() {
    if (isset($_POST['unsubscribe'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'custom_subscribers';
        $email = sanitize_email($_POST['unsubscribe']);
        
        $wpdb->update($table_name, array('status' => 'deleted'), array('email' => $email));

        // Display confirmation message
        echo '<p>You have been unsubscribed successfully.</p>';
    }
}
add_action('init', 'process_unsubscribe_form');

function send_unsubscribe_email($email) {
    $unsubscribe_link = site_url('/unsubscribe/');
    $subject = 'Unsubscribe from our newsletter';
    $message = 'Click the link below to unsubscribe: ' . $unsubscribe_link;
    wp_mail($email, $subject, $message);
}
