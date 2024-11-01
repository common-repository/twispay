<?php
namespace Twispay\Includes\Gateway;

use Twispay\Assets\Labels;
use Twispay\Assets\Translator;
class TwispayGateway extends WC_Payment_Gateway {
    /**
     * Twispay Gateway Constructor
     *
     * @public
     * @return void
     */
    public function __construct() {
        $translator = Translator::instance();

        $this->id = Labels::TWISPAY_PAYMENT_ID;
        $this->icon =  TWISPAY_PLUGIN_URL . 'logo.png';
        $this->has_fields = true;
        $this->method_title = esc_html( $translator->get('ws_title') );
        $this->method_description = esc_html( $translator->get('ws_description') );
        // if( class_exists('WC_Subscriptions') ){
        //     $this->supports = [ 'products'
        //                         , 'refunds'
        //                         , 'subscriptions'
        //                         , 'subscription_cancellation'
        //                         , 'subscription_suspension'
        //                         , 'subscription_reactivation'
        //                         , 'subscription_amount_changes'
        //                         , 'subscription_date_changes'
        //                         , 'subscription_payment_method_change'
        //                         , 'subscription_payment_method_change_customer'
        //                         , 'subscription_payment_method_change_admin'
        //                         , 'multiple_subscriptions'
        //                         , 'gateway_scheduled_payments'];
        // } else {
            $this->supports = [ 'products', 'refunds' ];
        // }

        if(class_exists('WC_Subscriptions')) {
            $this->supports = ['products', 'refunds'];
        }

        $this->init_form_fields();
        $this->init_settings();

        $this->title = empty( $this->get_option( 'title' ) ) ? 'Twispay' : $this->get_option( 'title' );
        $this->description = empty( $this->get_option( 'description' ) ) ? $translator->get('default_description') : $this->get_option( 'description' );
        $this->enable_for_methods = $this->get_option( 'enable_for_methods', array() );
        $this->enable_for_virtual = $this->get_option( 'enable_for_virtual', 'yes' ) === 'yes' ? true : false;

        $shipping_methods = array();

        foreach ( WC()->shipping()->load_shipping_methods() as $method ) {
            $shipping_methods[ $method->id ] = $method->get_method_title();
        }

        $this->form_fields = array(
            'enabled' => array(
                'title'    => esc_html__( 
                    
                    g['ws_enable_disable_title'], 'woocommerce' ),
                'type'     => 'checkbox',
                'label'    => esc_html__( $translator->get('ws_enable_disable_label'), 'woocommerce' ),
                'default'  => 'yes'
            ),
            'title' => array(
                'title'        => esc_html__( $translator->get('ws_title_title'), 'woocommerce' ),
                'type'         => 'text',
                'description'  => esc_html__( $translator->get('ws_title_desc'), 'woocommerce' ),
                'default'      => esc_html__( 'Twispay', 'woocommerce' ),
                'desc_tip'     => true
            ),
            'description' => array(
                'title'        => esc_html__( $translator->get('ws_description_title'), 'woocommerce' ),
                'type'         => 'textarea',
                'description'  => esc_html__( $translator->get('ws_description_desc'), 'woocommerce' ),
                'default'      => esc_html__( $translator->get('ws_description_default'), 'woocommerce' ),
                'desc_tip'     => true
            ),
            'live_mode' => array(
                'title'         => esc_html__( 'Live mode' ),
                'label'         => esc_html__( 'Select "Yes" if you want to use the payment gateway in Production Mode or "No" if you want to use it in Staging Mode.'),
                'type'          => 'checkbox',
                'default'       => false
            ),
            'staging_id' => array(
                'title'         => esc_html__('Staging Site ID	'),
                'label'         => esc_html__('Enter the Site ID for Staging Mode. You can get one from here.'),
                'type'          => 'text',
                'default'       => '7794'
            ),
            'staging_private_key' => array(
                'title'         => esc_html__('Staging Private Key'),
                'label'         => esc_html__('Enter the Private Key for Staging Mode. You can get one from here.'),
                'type'          => 'text',
                'default'       => '55fe831882e10fbcacc682787818a152'
            ),
            'server_to_server_url_notif' => array(
                'title'         => 'Server-to-server notification URL',
                'label'         => 'Put this URL in your Twispay account.',
                'type'          => 'text',
                'default'       => 'https://wordpress.twispay/?twispay-ipn',
            ),
            'Redirect to custom Thank you page' => array(
                'title'         => 'Redirect to custom Thank you page',
                'label'         => 'Default',
                'type'          => 'multiselect',
                'default'       => 'Default',
                'choices' => array(
                    'Default' => 'Default'
                )
            ),
            'Suppress default WooCommerce payment receipt emails' => array(
                'title'         => 'Suppress default WooCommerce payment receipt emails',
                'label'         => 'Option to suppress the communication sent by the ecommerce system, in order to configure it from Twispay’s Merchant interface.                        ',
                'type'          => 'checkbox',
                'default'       => true,
            ),
            'Contact email(Optional)' => array(
                'title' => 'Contact email(Optional)',
                'label' => 'This email will be used for payment errors',
                'type' => 'text'
            ),
            'enable_for_methods' => array(
                'title'              => esc_html__( $translator->get('ws_enable_methods_title'), 'woocommerce' ),
                'type'               => 'multiselect',
                'class'              => 'wc-enhanced-select',
                'css'                => 'width: 400px;',
                'default'            => '',
                'description'        => esc_html__( $translator->get('ws_enable_methods_desc'), 'woocommerce' ),
                'options'            => $shipping_methods,
                'desc_tip'           => true,
                'custom_attributes'  => array(
                    'data-placeholder'  => esc_html__( $translator->get('ws_enable_methods_placeholder'), 'woocommerce' ),
                ),
            ),
            'enable_for_virtual' => array(
                'title'    => esc_html__( $translator->get('ws_vorder_title'), 'woocommerce' ),
                'label'    => esc_html__( $translator->get('ws_vorder_desc'), 'woocommerce' ),
                'type'     => 'checkbox',
                'default'  => 'yes',
            )
        );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    /**
    * Check if the Twispay Gateway is available for use
    *
    * @return bool
    */
    public function is_available() {
        $order          = null;
        $needs_shipping = false;

        // Test if shipping is needed first
        if ( WC()->cart && WC()->cart->needs_shipping() ) {
            $needs_shipping = true;
        }
        elseif ( is_page( wc_get_page_id( 'checkout' ) ) && 0 < get_query_var( 'order-pay' ) ) {
            $order_id = absint( get_query_var( 'order-pay' ) );
            $order    = wc_get_order( $order_id );

            // Test if order needs shipping.
            if ( 0 < sizeof( $order->get_items() ) ) {
                foreach ( $order->get_items() as $item ) {
                    $_product = $item->get_product();
                    if ( $_product && $_product->needs_shipping() ) {
                        $needs_shipping = true;
                        break;
                    }
                }
            }
        }

        $needs_shipping = apply_filters( 'woocommerce_cart_needs_shipping', $needs_shipping );

        // Virtual order, with virtual disabled
        if ( ! $this->enable_for_virtual && ! $needs_shipping ) {
            return false;
        }

        // Check methods
        if ( ! empty( $this->enable_for_methods ) && $needs_shipping ) {
            // Only apply if all packages are being shipped via chosen methods, or order is virtual
            $chosen_shipping_methods_session = WC()->session->get( 'chosen_shipping_methods' );

            if ( isset( $chosen_shipping_methods_session ) ) {
                $chosen_shipping_methods = array_unique( $chosen_shipping_methods_session );
            }
            else {
                $chosen_shipping_methods = array();
            }

            $check_method = false;

            if ( is_object( $order ) ) {
                if ( $order->shipping_method ) {
                    $check_method = $order->shipping_method;
                }
            }
            elseif ( empty( $chosen_shipping_methods ) || sizeof( $chosen_shipping_methods ) > 1 ) {
                $check_method = false;
            }
            elseif ( sizeof( $chosen_shipping_methods ) == 1 ) {
                $check_method = $chosen_shipping_methods[0];
            }

            if ( ! $check_method ) {
                return false;
            }

            if ( strstr( $check_method, ':' ) ) {
                $check_method = current( explode( ':', $check_method ) );
            }

            $found = false;

            foreach ( $this->enable_for_methods as $method_id ) {
                if ( $check_method === $method_id ) {
                    $found = true;
                    break;
                }
            }

            if ( ! $found ) {
                return false;
            }
        }

        return parent::is_available();
    }

    /**
     * Twispay Process Payment function
     *
     * @public
     * @return array with Result and Redirect
     */
    function process_payment( $order_id ) {

        /*
            * For several pages get order working this conditions $actual_link is not equal home page
            * and get page name, for example default - /checkout/
            *
            * For single page all in one (cart and checkout page) $actual_link is equal home page
            * if in admin setting page Woocommerce -> Settings -> Advanced the field "Checkout page"
            * - must be empty then condition str_replace(home_url(), '', $actual_link) === '' returning true
            *
            */
        $actual_link = wc_get_checkout_url();

        if ( str_replace( home_url(), '', $actual_link ) === '' ) {
            $actual_link = wc_get_cart_url();
        }

        /* Check if the order contains a subscription. */
        if ( class_exists( 'WC_Subscriptions' ) && ( TRUE == wcs_order_contains_subscription( $order_id ) ) ) {
            /*
                * Redirect to the virtual page for products with subscription.
                * The content of the file was moved to the main twispay.php file, and hooks for the virtual page
                * were also created.
                *
                * The virtual page differs from the usual one by adding get parameters to the page url, in
                * this case - ?order_id=xx&subscription=true will be added to the page address url
                *
                * The woocommerce_after_checkout_form hook will intercept the passed parameters and redirect
                * to the twispay payment gateway page
                */
            $args = array( 'order_id' =>  $order_id . '_sub' );

            return array(
                'result' => 'success',
                'redirect' => esc_url_raw(
                add_query_arg(
                    $args,
                    $actual_link
                )
                )
            );
        } else {
            /*
                * Redirect to the virtual page for products with default payment method.
                * The content of the file was moved to the main twispay.php file, and hooks for the virtual page
                * were also created.
                *
                * The virtual page differs from the usual one by adding get parameters to the page url, in
                * this case - ?order_id=xx will be added to the page address url
                *
                * The woocommerce_after_checkout_form hook will intercept the passed parameters and redirect
                * to the twispay payment gateway page
                */
            $args = array( 'order_id' =>  $order_id );

            return array(
                'result' => 'success',
                'redirect' => esc_url_raw(
                add_query_arg(
                    $args,
                    $actual_link
                )
                )
            );
        }
    }

    /**
     * Twispay Process Payment function
     *
     * @param  int        $order_id Order ID.
     * @param  float|null $amount Refund amount.
     * @param  string     $reason Refund reason.
     *
     * @return boolean|WP_Error True or false based on success, or a WP_Error object.
     */
    function process_refund($order_id, $amount = NULL, $reason = '') {
        global $wpdb;
        $apiKey = '';
        $transaction_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT transactionId FROM " . $wpdb->prefix . "twispay_tw_transactions WHERE id_cart = %d",
                $order_id
            )
        );
        if (!$transaction_id) {
            return new WP_Error( 'error', "Invalid transaction id");
        }

        /* Get configuration from database. */
        $configuration = $wpdb->get_row( "SELECT * FROM " . $wpdb->prefix . "twispay_tw_configuration" );
        if (!$configuration) {
            return new WP_Error( 'error', "Missing configuration");
        }
        
        if ( 1 == $configuration->live_mode ) {
            $apiKey = $configuration->live_key;
            $url = 'https://api.twispay.com/transaction/' . sanitize_key( $transaction_id );
        } else {
            $apiKey = $configuration->staging_key;
            $url = 'https://api-stage.twispay.com/transaction/' . sanitize_key( $transaction_id );
        }

        $args = array('method' => 'DELETE', 'headers' => ['accept' => 'application/json', 'Authorization' => $apiKey]);
        if (!is_null($amount)) {
            $amount = round($amount,2);
            if ($amount > 0) {
                $args['body']['amount'] = $amount;
            } else {
                return new WP_Error( 'error', "Invalid amount");
            }
        }
        
        if ($reason) {
            $args['body']['reason'] = 'customer-demand';
            $args['body']['message'] = $reason;
        }
        
        $response = wp_remote_request( $url, $args );
        $code = $response['response']['code'] ?? 0;
        $msg = $response['response']['message'] ?? "Unknown reason";
        
        if ( 'OK' != $msg ) {
            return new WP_Error( 'error', "TWISPAY API error: $code - $msg" );
        }
        
        Twispay_TW_Logger::twispay_tw_updateTransactionStatus($order_id, Twispay_TW_Status_Updater::$RESULT_STATUSES['REFUND_OK']);
        return true;
    }
}