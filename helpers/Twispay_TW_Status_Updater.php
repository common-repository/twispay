<?php
/**
 * Twispay Helpers
 *
 * Updates the statused of orders and subscriptions based
 *  on the status read from the server response.
 *
 * @package  Twispay/Front
 * @category Front
 * @author   Twispay
 */

/* Exit if the file is accessed directly. */
if ( !defined('ABSPATH') ) { exit; }

/* Security class check */
if ( ! class_exists( 'Twispay_TW_Status_Updater' ) ) :
    /**
     * Twispay Helper Class
     *
     * Class that implements methods to update the statuses
     * of orders and subscriptions based on the status received
     * from the server.
     */
    class Twispay_TW_Status_Updater {
        /* Array containing the possible result statuses. */
        public static $RESULT_STATUSES = [
                'UNCERTAIN' => 'uncertain' /* No response from provider */
            , 'IN_PROGRESS' => 'in-progress' /* Authorized */
            , 'COMPLETE_OK' => 'complete-ok' /* Captured */
            , 'COMPLETE_FAIL' => 'complete-failed' /* Not authorized */
            , 'CANCEL_OK' => 'cancel-ok' /* Capture reversal */
            , 'REFUND_OK' => 'refund-ok' /* Settlement reversal */
            , 'VOID_OK' => 'void-ok' /* Authorization reversal */
            , 'CHARGE_BACK' => 'charge-back' /* Charge-back received */
            , 'THREE_D_PENDING' => '3d-pending' /* Waiting for 3d authentication */
            , 'EXPIRING' => 'expiring' /* The recurring order has expired */
        ];

        /**
         * Update the status of an Woocommerce order according to the received server status.
         *
         * @param orderId: The id of the order for which to update the status.
         * @param serverStatus: The status received from server.
         * @param checkout_url: The url to which to redirect the client in case of error.
         * @param tw_lang: The array of available messages.
         * @param configuration: The configuration of the plugin
         *
         * @return void
         */
        public static function updateStatus_backUrl($orderId, $serverStatus, $checkout_url, $tw_lang, $configuration, $decrypted) {
            /* Extract the order. */
            $order = wc_get_order($orderId);
            if (Twispay_TW_Status_Updater::$RESULT_STATUSES[$serverStatus] && $order->get_status() == Twispay_TW_Status_Updater::$RESULT_STATUSES[$serverStatus]) {
                return;
            }
            switch ($serverStatus) {
                case Twispay_TW_Status_Updater::$RESULT_STATUSES['COMPLETE_FAIL']:
                    /* Mark order as failed. */
                    $order->update_status('failed', esc_html__( $tw_lang['wa_order_failed_notice'], 'woocommerce' ));

                    if(class_exists('WC_Subscriptions') && wcs_order_contains_subscription($order)){
                        WC_Subscriptions_Manager::maybe_process_failed_renewal_for_repair( $orderId );
                    }

                    Twispay_TW_Logger::twispay_tw_log($tw_lang['log_ok_status_failed'] . $orderId);
                    ?>
                    <div class="error notice" style="margin-top: 20px;">
                        <h3><?php echo esc_html($tw_lang['general_error_title']); ?></h3>

                        <p>
                            <?php echo esc_html($tw_lang['general_error_desc_f']); ?>

                            <a href="<?php echo esc_url($checkout_url); ?>">
                                <?php echo esc_html($tw_lang['general_error_desc_try_again']); ?>
                            </a>

                            <?php if ('0' == $configuration->contact_email) { ?>
                                <?php
                                printf(
                                    '%s %s %s',
                                    esc_html($tw_lang['general_error_desc_or']),
                                    esc_html($tw_lang['general_error_desc_contact']),
                                    esc_html($tw_lang['general_error_desc_s'])
                                );
                                ?>
                            <?php } else { ?>
                                <?php echo esc_html($tw_lang['general_error_desc_or']); ?>

                                <a href="mailto:<?php echo sanitize_email($configuration->contact_email); ?>">
                                    <?php echo esc_html($tw_lang['general_error_desc_contact']); ?>
                                </a>

                                <?php echo esc_html($tw_lang['general_error_desc_s']); ?>
                            <?php } ?>
                        </p>
                    </div>
                    <?php
                break;

                case Twispay_TW_Status_Updater::$RESULT_STATUSES['IN_PROGRESS']:
                case Twispay_TW_Status_Updater::$RESULT_STATUSES['THREE_D_PENDING']:
                    /* Mark order as on-hold. */
                    $order->update_status('on-hold', esc_html__( $tw_lang['wa_order_hold_notice'], 'woocommerce' ));

                    Twispay_TW_Logger::twispay_tw_log($tw_lang['log_ok_status_hold'] . $orderId);
                    ?>
                    <div class="error notice" style="margin-top: 20px;">
                        <h3><?php echo esc_html($tw_lang['general_error_title']); ?></h3>

                        <span><?php echo esc_html($tw_lang['general_error_hold_notice']); ?></span>

                        <?php if ('0' == $configuration->contact_email) { ?>
                            <p>
                                <?php printf(
                                    '%s %s %s',
                                    esc_html($tw_lang['general_error_desc_f']),
                                    esc_html($tw_lang['general_error_desc_contact']),
                                    esc_html($tw_lang['general_error_desc_s'])
                                );
                                ?>
                            </p>
                        <?php } else { ?>
                            <p><?php echo esc_html($tw_lang['general_error_desc_f']); ?>
                                <a href="mailto:<?php echo sanitize_email($configuration->contact_email); ?>">
                                    <?php echo esc_html($tw_lang['general_error_desc_contact']); ?>
                                </a>

                                <?php echo esc_html($tw_lang['general_error_desc_s']); ?>
                            </p>
                        <?php } ?>
                    </div>
                    <?php
                break;

                case Twispay_TW_Status_Updater::$RESULT_STATUSES['COMPLETE_OK']:
                    /* Mark order as completed. */
                    $order->payment_complete($decrypted['transactionId']);
                    if(class_exists('WC_Subscriptions') && wcs_order_contains_subscription($order)){

                      $subscription = wcs_get_subscriptions_for_order($order);
                      $subscription = reset($subscription);

                      /* First payment on order, process payment & activate subscription. */
                      if ( 0 == $subscription->get_payment_count() ) {

                          $order->payment_complete();
                          
                          if(class_exists('WC_Subscriptions') ){
                              WC_Subscriptions_Manager::activate_subscriptions_for_order( $order );
                          }
                      } else {
                          if(class_exists('WC_Subscriptions') ){
                              WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
                          }
                      }
                            
                    }
                    try {
                        global $wpdb;
                    $query = $wpdb->prepare( 
                        "SELECT COUNT(*) FROM {$wpdb->prefix}twispay_tw_transactions WHERE TransactionId = %s", 
                        $decrypted['transactionId']
                        );
                    
                        // Execute the query
                        $item_count = (int) $wpdb->get_var( $query );
            
                        // Check if the transaction already exists 
                        if ($item_count == 0) {
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
                                $serverStatus, 
                                $order->get_id(), 
                                $order->get_customer_id(), 
                                $decrypted['orderId'], 
                                $decrypted['transactionId'],
                                $decrypted['customerId'], 
                                $decrypted['cardId'], 
                                $checkout_url ) 
                        );
                        Twispay_TW_Logger::twispay_tw_log("Correspondence between twispay transaction and woocommerce order saved in database: orderID: $orderId, transactionID: {$decrypted['transactionId']}, customerID: {$decrypted['customerId']}, cardID: {$decrypted['cardId']}" );
                    } else {
                        $wpdb->query(
                            $wpdb->prepare(
                                "UPDATE `" . $wpdb->prefix . "twispay_tw_transactions` 
                                 SET `checkout_url` = %s 
                                 WHERE `transactionId` = %d",
                                $checkout_url,
                                $decrypted['transactionId']
                            )
                        );
                        Twispay_TW_Logger::twispay_tw_log("CheckoutURL updated:" . $checkout_url);
                    }
                    } catch(Throwable | Exception $e) {
                        Twispay_TW_Logger::twispay_tw_log("Exception while trying to register transaction: " . $e->getMessage() . " <> " . $e->getTraceAsString());
                    }

                    Twispay_TW_Logger::twispay_tw_log($tw_lang['log_ok_status_complete'] . $orderId);

                    /* Redirect to Twispay "Thank you Page" if it is set, if not, redirect to default "Thank you Page" */
                    if ( $configuration->thankyou_page ) {
                        wp_safe_redirect( esc_url( $configuration->thankyou_page ) );
                    } else {
                        new Twispay_TW_Default_Thankyou( $order );
                    }
                break;

                default:
                    Twispay_TW_Logger::twispay_tw_log($tw_lang['log_error_wrong_status'] . $serverStatus);
                    ?>
                    <div class="error notice" style="margin-top: 20px;">
                        <h3><?php echo esc_html($tw_lang['general_error_title']); ?></h3>

                        <p>
                            <?php echo esc_html($tw_lang['general_error_desc_f']); ?>

                            <a href="<?php echo esc_url($checkout_url); ?>">
                                <?php echo esc_html($tw_lang['general_error_desc_try_again']); ?>
                            </a>

                            <?php if ('0' == $configuration->contact_email) { ?>
                                <?php
                                printf(
                                    '%s %s %s',
                                    esc_html($tw_lang['general_error_desc_or']),
                                    esc_html($tw_lang['general_error_desc_contact']),
                                    esc_html($tw_lang['general_error_desc_s'])
                                );
                                ?>
                            <?php } else { ?>
                                <?php echo esc_html($tw_lang['general_error_desc_or']); ?>

                                <a href="mailto:<?php echo sanitize_email($configuration->contact_email); ?>">
                                    <?php echo esc_html($tw_lang['general_error_desc_contact']); ?>
                                </a>

                                <?php echo esc_html($tw_lang['general_error_desc_s']); ?>
                            <?php } ?>
                        </p>
                    </div>
                    <?php
                break;
            }
        }

        /**
         * Update the status of an Woocommerce subscription according to the received server status.
         *
         * @param orderId: The ID of the order to be updated.
         * @param serverStatus: The status received from server.
         * @param tw_lang: The array of available messages.
         *
         * @return void
         */
        public static function updateStatus_IPN($orderId, $serverStatus, $tw_lang, $decrypted) {
            /* Extract the order. */
            $order = wc_get_order($orderId);

            switch ($serverStatus) {
                case Twispay_TW_Status_Updater::$RESULT_STATUSES['COMPLETE_FAIL']:
                    /* Mark order as failed. */
                    $order->update_status('failed', esc_html__( $tw_lang['wa_order_failed_notice'], 'woocommerce' ));

                    if(class_exists('WC_Subscriptions') && wcs_order_contains_subscription($order)){
                        WC_Subscriptions_Manager::maybe_process_failed_renewal_for_repair( $orderId );
                    }

                    Twispay_TW_Logger::twispay_tw_log($tw_lang['log_ok_status_failed'] . $orderId);
                break;

                case Twispay_TW_Status_Updater::$RESULT_STATUSES['CANCEL_OK']:
                case Twispay_TW_Status_Updater::$RESULT_STATUSES['REFUND_OK']:
                case Twispay_TW_Status_Updater::$RESULT_STATUSES['VOID_OK']:
                case Twispay_TW_Status_Updater::$RESULT_STATUSES['CHARGE_BACK']:
                    /* Mark order as refunded. */
                    $order->update_status('refunded', esc_html__( $tw_lang['wa_order_refunded_notice'], 'woocommerce' ));

                    if(class_exists('WC_Subscriptions') && wcs_order_contains_subscription($order)){
                        WC_Subscriptions_Manager::cancel_subscriptions_for_order($order);
                    }

                    Twispay_TW_Logger::twispay_tw_log($tw_lang['log_ok_status_refund'] . $orderId);
                break;

                case Twispay_TW_Status_Updater::$RESULT_STATUSES['IN_PROGRESS']:
                case Twispay_TW_Status_Updater::$RESULT_STATUSES['THREE_D_PENDING']:
                    /* Mark order as on-hold. */
                    $order->update_status('on-hold', esc_html__( $tw_lang['wa_order_hold_notice'], 'woocommerce' ));

                    Twispay_TW_Logger::twispay_tw_log($tw_lang['log_ok_status_hold'] . $orderId);
                break;

                case Twispay_TW_Status_Updater::$RESULT_STATUSES['COMPLETE_OK']:
                    /* Mark order as completed. */
                    $order->payment_complete($decrypted['transactionId']);

                    if(class_exists('WC_Subscriptions') && wcs_order_contains_subscription($order)){
                      $subscription = wcs_get_subscriptions_for_order($order);
                      $subscription = reset($subscription);

                      /* First payment on order, process payment & activate subscription. */
                      if ( 0 == $subscription->get_payment_count() ) {
                          $order->payment_complete('transactionId');

                          if(class_exists('WC_Subscriptions') ){
                              WC_Subscriptions_Manager::activate_subscriptions_for_order( $order );
                          }
                      } else {
                        if(class_exists('WC_Subscriptions') ){
                            WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
                        }
                      }
                     

                    } else {
                        try {
                            global $wpdb;
                      $query = $wpdb->prepare( 
                        "SELECT COUNT(*) FROM {$wpdb->prefix}twispay_tw_transactions WHERE TransactionId = %s", 
                        $decrypted['transactionId']
                        );
                    
                        // Execute the query
                        $item_count = (int) $wpdb->get_var( $query );
            
                        // Check if the transaction already exists 
                        if ($item_count == 0) {
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
                                $serverStatus, 
                                $order->get_id(), 
                                $order->get_customer_id(), 
                                $decrypted['orderId'], 
                                $decrypted['transactionId'],
                                $decrypted['customerId'], 
                                $decrypted['cardId'], 
                                "" ) 
                            );
                            Twispay_TW_Logger::twispay_tw_log("Correspondence between twispay transaction and woocommerce order saved in database: orderID: $orderId, transactionID: {$decrypted['transactionId']}, customerID: {$decrypted['customerId']}, cardID: {$decrypted['cardId']}" );
                        }
                        } catch(Throwable | Exception $e) {
                            Twispay_TW_Logger::twispay_tw_log("Exception while trying to register transaction: " . $e->getMessage() . " <> " . $e->getTraceAsString());
                        }
                    }

                    Twispay_TW_Logger::twispay_tw_log($tw_lang['log_ok_status_complete'] . $orderId);
                break;

                default:
                  Twispay_TW_Logger::twispay_tw_log($tw_lang['log_error_wrong_status'] . $serverStatus);
                break;
            }
        }


        /**
        * Update the status of an Woocommerce subscription according to the received server status.
        *
        * @param orderId: The ID of the order that is the parent of the subscription.
        * @param serverStatus: The status received from server.
        * @param tw_lang: The array of available messages.
        *
        * @return void
        */
        public static function updateSubscriptionStatus($orderId, $serverStatus, $tw_lang){
            /* Check that the subscriptions plugin is installed. */
            if(!class_exists('WC_Subscriptions') ){
              return;
            }

            /* Extract the order. */
            $order = wc_get_order($orderId);
            /* Extract the subscription. */
            $subscription = wcs_get_subscriptions_for_order($order);
            $subscription = reset($subscription);

            switch ($serverStatus) {
                case Twispay_TW_Status_Updater::$RESULT_STATUSES['COMPLETE_FAIL']: /* The subscription has payment failure. */
                case Twispay_TW_Status_Updater::$RESULT_STATUSES['THREE_D_PENDING']: /* The subscription has a 3D pending payment. */
                    if($subscription->can_be_updated_to('on-hold')){
                        /* Mark subscription as 'ON-HOLD'. */
                        $subscription->update_status('on-hold');
                        Twispay_TW_Logger::twispay_tw_updateTransactionStatus($orderId, $serverStatus);
                    }
                break;

                case Twispay_TW_Status_Updater::$RESULT_STATUSES['COMPLETE_OK']: /* The subscription has been completed. */
                case Twispay_TW_Status_Updater::$RESULT_STATUSES['CANCEL_OK']: /* The subscription has been canceled. */
                case Twispay_TW_Status_Updater::$RESULT_STATUSES['REFUND_OK']: /* The subscription has been refunded. */
                case Twispay_TW_Status_Updater::$RESULT_STATUSES['VOID_OK']: /*  */
                case Twispay_TW_Status_Updater::$RESULT_STATUSES['CHARGE_BACK']: /* The subscription has been forced back. */
                    if($subscription->can_be_updated_to('canceled')){
                        /* Mark subscription as 'CANCELED'. */
                        $subscription->update_status('canceled');
                        Twispay_TW_Logger::twispay_tw_updateTransactionStatus($orderId, $serverStatus);
                    }
                break;

                case Twispay_TW_Status_Updater::$RESULT_STATUSES['EXPIRING']: /* The subscription will expire soon. */
                case Twispay_TW_Status_Updater::$RESULT_STATUSES['IN_PROGRESS']: /* The subscription is in progress. */
                    if($subscription->can_be_updated_to('active')){
                        /* Mark subscription as 'ACTIVE'. */
                        $subscription->update_status('active');
                        Twispay_TW_Logger::twispay_tw_updateTransactionStatus($orderId, $serverStatus);
                    }
                break;

                default:
                  Twispay_TW_Logger::twispay_tw_log($tw_lang['log_error_wrong_status'] . $serverStatus);
                break;
            }
        }
    }
endif; /* End if class_exists. */
