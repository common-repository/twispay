<?php
/**
 * Twispay Install
 *
 * Installing Twispay user pages, tables, and options.
 *
 * @package  Twispay/Install
 * @category Core
 * @author   Twispay
 */

function delete_duplicate_pages_by_title($page_title) {
    global $wpdb;
    // Query to find all pages with the given title
    $page_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT ID FROM $wpdb->posts 
         WHERE post_title = %s 
         AND post_type = 'page'
         AND post_status IN ('publish', 'draft', 'pending', 'trash')", // You can adjust statuses if needed
        $page_title
    ));
    // If more than one page exists, delete all of them
    if (count($page_ids) > 1) {
        array_shift($page_ids);
        foreach ($page_ids as $page_id) {
            wp_delete_post($page_id, true); // true to force deletion (bypass trash)
        }
    }
}

function twispay_wp_check_install() {
    if( ! get_option( 'twispay_tw_installed' ) ) {
        twispay_tw_install();
    }

    $page_title = 'Twispay confirmation';  // Title to check
    $page_count = count_pages_by_title($page_title);

    if ($page_count > 1) {
        delete_duplicate_pages_by_title($page_title);
    }

    if (get_option( 'twispay_tw_installed') == '2') {
        global $wpdb;
        $wpdb->query( "DROP TABLE IF EXISTS " . $wpdb->prefix . "twispay_tw_configuration" );

        $wpdb->get_results( "CREATE TABLE IF NOT EXISTS `" . $wpdb->prefix . "twispay_tw_configuration` (
		`id_tw_configuration` int(10) NOT NULL AUTO_INCREMENT,
		`live_mode` int(10) NOT NULL,
		`staging_id` varchar(255) NOT NULL,
		`staging_key` varchar(255) NOT NULL,
		`live_id` varchar(255) NOT NULL,
		`live_key` varchar(255) NOT NULL,
		`thankyou_page` VARCHAR(255) NOT NULL DEFAULT '0',
		`suppress_email` int(10) NOT NULL DEFAULT '0',
		`contact_email` VARCHAR(50) NOT NULL DEFAULT '0',
		PRIMARY KEY (`id_tw_configuration`)
	) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1" );

        $configurations = get_option('twispay_tw_configuration');
        $live_mode = $configurations['live_mode'];
        $staging_id = $configurations['staging_id'];
        $staging_key = $configurations['staging_key'];
        $live_id = $configurations['live_id'];
        $live_key = $configurations['live_key'];
        $thankyou_page = $configurations['thankyou_page'];
        $suppress_email = $configurations['suppress_email'];
        $contact_email = $configurations['contact_email'];

        $wpdb->get_results( "INSERT INTO `" . $wpdb->prefix . "twispay_tw_configuration` (`id_tw_configuration`) VALUES (1);" );

        if (!empty($live_mode)) {
            $wpdb->get_results( "UPDATE `" . $wpdb->prefix .
                "twispay_tw_configuration` SET `live_mode` = $live_mode where `id_tw_configuration` = 1;" );
        }

        if (!empty($staging_id)) {
            $wpdb->get_results( "UPDATE `" . $wpdb->prefix .
                "twispay_tw_configuration` SET `staging_id` = \"$staging_id\" where `id_tw_configuration` = 1;" );
        }

        if (!empty($staging_key)) {
            $wpdb->get_results( "UPDATE `" . $wpdb->prefix .
                "twispay_tw_configuration` SET `staging_key` = \"$staging_key\" where `id_tw_configuration` = 1;" );
        }

        if (!empty($live_id)) {
            $wpdb->get_results( "UPDATE `" . $wpdb->prefix .
                "twispay_tw_configuration` SET `live_id` = \"$live_id\" where `id_tw_configuration` = 1;" );
        }

        if (!empty($live_key)) {
            $wpdb->get_results( "UPDATE `" . $wpdb->prefix .
                "twispay_tw_configuration` SET `live_key` = \"$live_key\" where `id_tw_configuration` = 1;" );
        }

        if (!empty($thankyou_page)) {
            $wpdb->get_results( "UPDATE `" . $wpdb->prefix .
                "twispay_tw_configuration` SET `thankyou_page` = \"$thankyou_page\" where `id_tw_configuration` = 1;" );
        }

        if (!empty($suppress_email)) {
            $wpdb->get_results( "UPDATE `" . $wpdb->prefix .
                "twispay_tw_configuration` SET `suppress_email` = \"$suppress_email\" where `id_tw_configuration` = 1;" );
        }

        if (!empty($contact_email)) {
            $wpdb->get_results( "UPDATE `" . $wpdb->prefix .
                "twispay_tw_configuration` SET `contact_email` = \"$contact_email\" where `id_tw_configuration` = 1;" );
        }

        update_option( 'twispay_tw_installed', '1' );

    }
}
add_action( 'admin_init', 'twispay_wp_check_install' );

function count_pages_by_title($page_title) {
    global $wpdb;

    // Query the database to count the number of pages with the given title
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $wpdb->posts 
         WHERE post_title = %s 
         AND post_type = 'page'
         AND post_status IN ('publish', 'draft', 'pending', 'trash')", // You can adjust statuses if needed
        $page_title
    ));

    return $count;
}

function twispay_tw_install() {
    update_option( 'twispay_tw_installed', '1' );

    // Create new pages from Twispay Confirmation with shortcodes included
    $page_title = 'Twispay confirmation';  // Title to check

    // Call the function to get the count of pages with this title
    $page_count = count_pages_by_title($page_title);

    if ($page_count == 0) {
        wp_insert_post(
            array(
                'post_title'     => esc_html__( 'Twispay confirmation', 'tw-confirmation' ),
                'post_content'   => '[tw_payment_confirmation]',
                'post_status'    => 'publish',
                'post_author'    => get_current_user_id(),
                'post_type'      => 'page',
                'comment_status' => 'closed'
            )
        );
    }

    // Create All tables
    global $wpdb;

    $wpdb->get_results( "CREATE TABLE IF NOT EXISTS `" . $wpdb->prefix . "twispay_tw_configuration` (
		`id_tw_configuration` int(10) NOT NULL AUTO_INCREMENT,
		`live_mode` int(10) NOT NULL,
		`staging_id` varchar(255) NOT NULL,
		`staging_key` varchar(255) NOT NULL,
		`live_id` varchar(255) NOT NULL,
		`live_key` varchar(255) NOT NULL,
		`thankyou_page` VARCHAR(255) NOT NULL DEFAULT '0',
		`suppress_email` int(10) NOT NULL DEFAULT '0',
		`contact_email` VARCHAR(50) NOT NULL DEFAULT '0',
		PRIMARY KEY (`id_tw_configuration`)
	) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1" );

    $wpdb->get_results( "CREATE TABLE IF NOT EXISTS `" . $wpdb->prefix . "twispay_tw_transactions` (
		`id_tw_transactions` int(10) NOT NULL AUTO_INCREMENT,
		`status` varchar(50) NOT NULL,
		`checkout_url` varchar(255) NOT NULL,
		`id_cart` int(10) NOT NULL,
		`identifier` varchar(50) NOT NULL,
		`orderId` int(10) NOT NULL,
		`transactionId` int(10) NOT NULL,
		`customerId` int(10) NOT NULL,
		`cardId` int(10) NOT NULL,
		PRIMARY KEY (`id_tw_transactions`)
	) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1" );

    $wpdb->get_results( "INSERT INTO `" . $wpdb->prefix . "twispay_tw_configuration` (`live_mode`) VALUES (0);" );
}
register_activation_hook( TWISPAY_PLUGIN_DIR, 'twispay_tw_install' );

