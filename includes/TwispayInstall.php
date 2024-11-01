<?php

namespace Twispay\Includes;

/**
 * Twispay Install
 *
 * Installing Twispay user pages, tables, and options.
 *
 * @package  Twispay/Includes
 * @author   Twispay
 */

 use Twispay\Assets\TwispayLogger;

final class TwispayInstall
{
    public static function twispay_tw_check_updates() {
        if (get_option('twispay_tw_installed') == '2') {
            return; //already updated to twispay 2.0
        }
        global $wpdb;
        $configurationTableName = $wpdb->prefix . "twispay_tw_configuration";
        
        // Saving current configuration for updating scenario
        if($wpdb->get_var("SHOW TABLES LIKE '$configurationTableName'") == $configurationTableName) {
            $configurations = $wpdb->get_results("SELECT * FROM `$configurationTableName` LIMIT 1");
            
            if (count($configurations)) {
                $configuration = (array) $configurations[0]; //remove unnecessary id field
                unset($configuration['id_tw_configuration']);
                $configuration['use_iframe'] = 0; //add iframe option field
             
            } else {
                $configuration = self::getDefaultConfiguration();
            }
            update_option('twispay_tw_configuration', $configuration);
            //remove old table
            $wpdb->get_results("DROP TABLE $configurationTableName");
        }

        //set twispay database version to twispay 2.0
        update_option('twispay_tw_installed', '2');
        
        TwispayLogger::log('Info', "Configuration updated to Twispay2.0");
    }

    public static function twispay_tw_install() {
        global $wpdb;

        //add configuration to the wp_option instead of using a table for just one entry.
        update_option('twispay_tw_configuration', self::getDefaultConfiguration());

        $wpdb->get_results(
            "CREATE TABLE IF NOT EXISTS `" . $wpdb->prefix . "twispay_tw_transactions` (
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
            ) DEFAULT CHARSET=latin1 AUTO_INCREMENT=1" 
        );
        //set twispay database version to twispay 2.0
        update_option( 'twispay_tw_installed', '2' );
    }

    static function getDefaultConfiguration()
    {
        return [
            'live_mode' => 0,
            'use_iframe' => 0,
            'staging_id' => '',
            'staging_key' => '',
            'live_id' => '',
            'live_key' => '',
            'thankyou_page' => '0',
            'suppress_email' => '0',
            'contact_email' => '0',
        ];
    }

    public static function twispay_tw_install_all() {
        add_action( 'admin_init', [TwispayInstall::class, 'twispay_tw_install'] );
        register_activation_hook( TwispayInstall::class, 'twispay_tw_install' );
    }
}
