<?php

namespace Twispay\Assets;

use Labels;

class TwispayLogger {
    public static function log($type = Labels::LOG_INFO, $message) 
    {
        $log_file = WP_PLUGIN_DIR . '/twispay/twispay-log.txt';
            /* Build the log message. */
            $message = (!$message) ? (PHP_EOL . PHP_EOL) : ("[$type][" . date( 'Y-m-d H:i:s' ) . "] " . esc_html( $message ) );

            /* Try to append log to file and silence and PHP errors may occur. */
            @file_put_contents( $log_file, esc_html( $message ) . PHP_EOL, FILE_APPEND );

    }
}