<?php
/**
 * Twispay Payment Transaction Request Page
 *
 * Here is processed all payment transaction actions( refund )
 *
 * @package  Twispay/Admin
 * @category Admin
 * @author   Twispay
 */


/* Exit if the file is accessed directly. */
if ( !defined('ABSPATH') ) { exit; }

/* Require the "Twispay_TW_Logger" class. */
require_once( TWISPAY_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'Twispay_TW_Logger.php' );
/* Require the "Twispay_TW_Status_Updater" class. */
require_once( TWISPAY_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'Twispay_TW_Status_Updater.php' );


/**
 * Twispay Refund Transaction
 *
 * Process the Refund Transaction to database.
 *
 * @public
 * @return void
 */
function tw_twispay_p_refund_payment_transaction() {
    if ( isset( $_GET['payment_ad'] ) && sanitize_text_field( $_GET['payment_ad'] ) ) {
        $transaction_id = sanitize_text_field( $_GET['payment_ad'] );

        /* Get configuration from database. */
        global $wpdb;
        $apiKey = '';

        $configuration = $wpdb->get_row( "SELECT * FROM " . $wpdb->prefix . "twispay_tw_configuration" );

        if ( $configuration ) {
            if ( $configuration->live_mode == 1 ) {
                $apiKey = sanitize_text_field( $configuration->live_key );
                $url = 'https://api.twispay.com/transaction/' . $transaction_id;
            } else if ( $configuration->live_mode == 0 ) {
                $apiKey = sanitize_text_field( $configuration->staging_key );
                $url = 'https://api-stage.twispay.com/transaction/' . $transaction_id;
            }
        }

        $args = array('method' => 'DELETE', 'headers' => ['accept' => 'application/json', 'Authorization' => $apiKey]);
        $response = wp_remote_request( $url, $args );

        if ( $response['response']['message'] == 'OK' ) {
            /* Redirect to the Transaction list Page with success. */
            wp_safe_redirect( admin_url( 'admin.php?page=tw-transaction&notice=success_refund' ) );
        } else {
            /* Redirect to the Transaction list Page with error. */
            wp_safe_redirect( admin_url( 'admin.php?page=tw-transaction&notice=errorp_refund&emessage=' . rawurlencode($response['body']) ) );
        }
    } else {
        /* Redirect to the Transaction list Page with error. */
        wp_safe_redirect( admin_url( 'admin.php?page=tw-transaction&notice=error_refund' ) );
    }
}
add_action( 'tw_refund_payment_transaction', 'tw_twispay_p_refund_payment_transaction' );


/**
 * Twispay Recurring Order
 *
 * Process the Recurring Order to database.
 *
 * @public
 * @return void
 */
function tw_twispay_p_recurring_order( $request ) {
    if ( isset( $_GET['order_ad'] ) && sanitize_key( $_GET['order_ad'] ) ) {

        $order_ad = (int) sanitize_key( $_GET['order_ad'] );

        /* Get configuration from database. */
        global $wpdb;
        $apiKey = '';
        $configuration = $wpdb->get_row( "SELECT * FROM " . $wpdb->prefix . "twispay_tw_configuration" );

        if ( $configuration ) {
            if ( $configuration->live_mode == 1 ) {
                $apiKey = sanitize_text_field( $configuration->live_key );
                $url = 'https://api.twispay.com/order/' . $order_ad;
            } else if ( $configuration->live_mode == 0 ) {
                $apiKey = sanitize_text_field( $configuration->staging_key );
                $url = 'https://api-stage.twispay.com/order/' . $order_ad;
            }
        }

        $args = array('method' => 'DELETE', 'headers' => ['accept' => 'application/json', 'Authorization' => $apiKey]);
        $response = wp_remote_request( $url, $args );

        if ( $response['response']['message'] == 'OK' ) {
            /* Redirect to the Transaction list Page with success. */
            wp_safe_redirect( admin_url( 'admin.php?page=tw-transaction&notice=success_recurring' ) );
        } else {
            /* Redirect to the Transaction list Page with error. */
            wp_safe_redirect( admin_url( 'admin.php?page=tw-transaction&notice=errorp_refund&emessage=' . rawurlencode($response['body']) ) );
        }
    } else {
        /* Redirect to the Transaction list Page with error. */
        wp_safe_redirect( admin_url( 'admin.php?page=tw-transaction&notice=error_recurring' ) );
    }
}
add_action( 'tw_recurring_order', 'tw_twispay_p_recurring_order' );


/**
 * Twispay Recurring Order
 *
 * Synchronize the subscription statuses.
 *
 * @public
 * @return void
 */
function tw_twispay_p_synchronize_subscriptions( $request ) {
    /* Get configuration from database. */
    global $wpdb;
    $apiKey = '';
    $configuration = $wpdb->get_row( "SELECT * FROM " . $wpdb->prefix . "twispay_tw_configuration" );

    if ( $configuration ) {
        if ( $configuration->live_mode == 1 ) {
            $apiKey = sanitize_text_field( $configuration->live_key );
            $baseUrl = 'https://api.twispay.com/order?externalOrderId=__EXTERNAL_ORDER_ID__&orderType=recurring&page=1&perPage=1&reverseSorting=0';
        } else if ( $configuration->live_mode == 0 ) {
            $apiKey = sanitize_text_field( $configuration->staging_key );
            $baseUrl = 'https://api-stage.twispay.com/order?externalOrderId=__EXTERNAL_ORDER_ID__&orderType=recurring&page=1&perPage=1&reverseSorting=0';
        }
    }

    /* Load languages. */
    $lang = explode( '-', get_bloginfo( 'language' ) )[0];
    if ( file_exists( TWISPAY_PLUGIN_DIR . 'lang/' . $lang . '/lang.php' ) ) {
        require( TWISPAY_PLUGIN_DIR . 'lang/' . $lang . '/lang.php' );
    } else {
        require( TWISPAY_PLUGIN_DIR . 'lang/en/lang.php' );
    }

    /* Extract all the subscriptions. */
    $subscriptions = wcs_get_subscriptions(['subscriptions_per_page' => -1]);
    $skip = FALSE;

    foreach ($subscriptions as $key => $subscription) {
        /* Reset skip flag. */
        $skip = FALSE;

        /* Construct the URL. */
        $url = str_replace('__EXTERNAL_ORDER_ID__', esc_html( $subscription->get_parent_id() ), $baseUrl);

        /* Execute the request. This means to perform a "GET"/"PUT" request at the specified URL. */
        $args = array('method' => 'GET', 'headers' => ['accept' => 'application/json', 'Authorization' => $apiKey]);
        $response = wp_remote_request( $url, $args );

        /* Check if the CURL call failed. */
        if( FALSE === $response ) {
            Twispay_TW_Logger::twispay_tw_log( $tw_lang['subscriptions_log_error_call_failed'] . WP_Error::get_error_message() );
            $skip = TRUE;
        }

        if((FALSE == $skip) && (200 != wp_remote_retrieve_response_code( $response ))){
            Twispay_TW_Logger::twispay_tw_log( $tw_lang['subscriptions_log_error_http_code'] . wp_remote_retrieve_response_code( $response ) );
            $skip = TRUE;
        }

        if(FALSE == $skip){
            $response = json_decode($response['body']);

            if ( 'Success' == $response->message ) {

                /* Check if any order was found on the server. */
                if($response->pagination->currentItemCount){
                    /* Synchronize the statuses. */
                    Twispay_TW_Status_Updater::updateSubscriptionStatus($subscription->get_parent_id(), $response->data[0]->orderStatus, $tw_lang);
                } else {
                    /* Cancel the local subscription as no order was found on the server. */
                    Twispay_TW_Status_Updater::updateSubscriptionStatus($subscription->get_parent_id(), Twispay_TW_Status_Updater::$RESULT_STATUSES['CANCEL_OK'], $tw_lang);
                }

                /* Redirect to the Transaction list Page with success. */
                wp_safe_redirect( admin_url( 'admin.php?page=tw-transaction&notice=success_recurring' ) );
            } else {
                Twispay_TW_Logger::twispay_tw_log( $tw_lang['subscriptions_log_error_get_status'] . $subscription->get_parent_id() );
            }
        }
    }
    /* Redirect to the Transaction list Page with message. */
    wp_safe_redirect( admin_url( 'admin.php?page=tw-transaction&notice=sync_finished' ) );
}
add_action( 'tw_synchronize_subscriptions', 'tw_twispay_p_synchronize_subscriptions' );


function tw_twispay_p_synchronize_transactions( $request ) {
    $orders = [];
    global $wpdb;
    $apiKey = '';
    $configuration = $wpdb->get_row( "SELECT * FROM " . $wpdb->prefix . "twispay_tw_configuration" );

    if ( $configuration ) {
        if ( $configuration->live_mode == 1 ) {
            $apiKey = sanitize_text_field( $configuration->live_key );
            $baseUrl = 'https://api.twispay.com/order?createdAtFrom=2023-10-01T00%3A00%3A00%2B00%3A00&reverseSorting=0';
        } else if ( $configuration->live_mode == 0 ) {
            $apiKey = sanitize_text_field( $configuration->staging_key );
            $baseUrl = 'https://api-stage.twispay.com/order?createdAtFrom=2023-10-01T00%3A00%3A00%2B00%3A00&reverseSorting=0';
        }
    }


    /* Load languages. */
    $lang = explode( '-', get_bloginfo( 'language' ) )[0];
    if ( file_exists( TWISPAY_PLUGIN_DIR . 'lang/' . $lang . '/lang.php' ) ) {
        require( TWISPAY_PLUGIN_DIR . 'lang/' . $lang . '/lang.php' );
    } else {
        require( TWISPAY_PLUGIN_DIR . 'lang/en/lang.php' );
    }

    /* Execute the request. This means to perform a "GET"/"PUT" request at the specified URL. */
    $args = array('method' => 'GET', 'headers' => ['accept' => 'application/json', 'Authorization' => $apiKey]);
    $response = wp_remote_request( $baseUrl, $args );

    if( FALSE === $response ) {
        Twispay_TW_Logger::twispay_tw_log( $tw_lang['subscriptions_log_error_call_failed'] . WP_Error::get_error_message() );
        $skip = TRUE;
    }
    $response = json_decode($response['body']);

    if ( 'Success' == $response->message ) {
        if ($response->pagination->currentItemCount) {
            foreach ($response->data as $order) {
                $orders[$order->id]['externalOrderId'] = $order->externalOrderId;
            }
        }
    }

    if ( $configuration ) {
        if ( $configuration->live_mode == 1 ) {
            $apiKey = sanitize_text_field( $configuration->live_key );
            $baseUrl = 'https://api.twispay.com/transaction?createdAtFrom=2023-10-01T00%3A00%3A00%2B00%3A00&reverseSorting=0';
        } else if ( $configuration->live_mode == 0 ) {
            $apiKey = sanitize_text_field( $configuration->staging_key );
            $baseUrl = 'https://api-stage.twispay.com/transaction?createdAtFrom=2023-10-01T00%3A00%3A00%2B00%3A00&reverseSorting=0';
        }
    }

    /* Execute the request. This means to perform a "GET"/"PUT" request at the specified URL. */
    $args = array('method' => 'GET', 'headers' => ['accept' => 'application/json', 'Authorization' => $apiKey]);
    $response = wp_remote_request( $baseUrl, $args );

    
    if( FALSE === $response ) {
        Twispay_TW_Logger::twispay_tw_log( $tw_lang['subscriptions_log_error_call_failed'] . WP_Error::get_error_message() );
        $skip = TRUE;
    }

    $response = json_decode($response['body']);

    if ( 'Success' == $response->message ) {
        if ($response->pagination->currentItemCount) {
            foreach ($response->data as $transaction) {
                if (isset($orders[$transaction->orderId])) {
                    if (isset($orders[$transaction->orderId]['transactionId']) && $transaction->id < $orders[$transaction->orderId]['transactionId']) {
                        continue;
                    }
                    $orders[$transaction->orderId]['transactionId'] = $transaction->id;
                    $orders[$transaction->orderId]['cardId'] = $transaction->cardId;
                    $orders[$transaction->orderId]['status'] = $transaction->transactionStatus;
                }
            }
        }
    }

    foreach ($orders as $orderId => $details) {
        if (!isset($details['status'])) {
            Twispay_TW_Logger::twispay_tw_log("Error: Status not found for orderId=$orderId");
            continue;
        }
        if (!isset($details['externalOrderId'])) {
            Twispay_TW_Logger::twispay_tw_log("Error: externalOrderId not found for orderId=$orderId");
            continue;
        }
        if (!isset($details['transactionId'])) {
            Twispay_TW_Logger::twispay_tw_log("Error: transactionId not found for orderId=$orderId");
            continue;
        }
        if (!isset($details['cardId'])) {
            Twispay_TW_Logger::twispay_tw_log("Error: cardId not found for orderId=$orderId");
            continue;
        }
        // echo "Successfully added woocommerce order no. " . $details['externalOrderId'] . " with status: " . $details['status'] . " and transactionId: " . $details['transactionId'];die;
        global $wpdb;
        $query = $wpdb->prepare( 
            "SELECT COUNT(*) FROM {$wpdb->prefix}twispay_tw_transactions WHERE TransactionId = %s", 
            $details['transactionId']
            );
        
            // Execute the query
            $item_count = (int) $wpdb->get_var( $query );

            // Check if the transaction already exists 
            if ($item_count == 0) {
                $wcOrder =  wc_get_order($details['externalOrderId']);
                if (false == $wcOrder) {
                    Twispay_TW_Logger::twispay_tw_log("Invalid externalOrderId: " . $details['orderId'] . ". Transaction has been skipped.");
                    continue;
                }
                $wcCustomerId = $wcOrder->get_customer_id();
                if (false == $wcCustomerId) {
                    Twispay_TW_Logger::twispay_tw_log("Invalid wcCustomerId. Transaction has been skipped.");
                    continue;
                }
                try {
                    $wpdb->get_results( 
                        $wpdb->prepare( "
                            INSERT INTO `" . $wpdb->prefix . "twispay_tw_transactions` (
                                `status`,
                                `id_cart`, 
                                `identifier`, 
                                `orderId`, 
                                `transactionId`,
                                `customerId`,
                                `cardId`,
                                `checkout_url`
                            ) VALUES (
                                '%s', 
                                '%s', 
                                '%s', 
                                '%d', 
                                '%d', 
                                '%d', 
                                '%d', 
                                '%s'
                            );", 
                            $details['status'], 
                            $details['externalOrderId'], 
                            $wcCustomerId, 
                            $details['externalOrderId'], 
                            $details['transactionId'],
                            $wcCustomerId, 
                            $details['cardId'], 
                            "" ) 
                    );
                } catch (Throwable | Exception $e) {
                    Twispay_TW_Logger::twispay_tw_log("Failed to add woocommerce order no. " . $details['externalOrderId'] . " with status " . $details['status'] . " and transactionId: " . $details['transactionId'] . " due to error " . $e->getMessage());
                }
                

            Twispay_TW_Logger::twispay_tw_log("Successfully added woocommerce order no. " . $details['externalOrderId'] . " with status " . $details['status'] . " and transactionId: " . $details['transactionId']);
            }
    }

}

add_action( 'tw_synchronize_transactions', 'tw_twispay_p_synchronize_transactions');
