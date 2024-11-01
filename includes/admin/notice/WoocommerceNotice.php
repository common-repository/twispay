<?php

namespace Twispay\Includes\Admin\Notice;

use Twispay\Assets\Translator;

require WP_PLUGIN_DIR . '/twispay/vendor/autoload.php';
final class WoocommerceNotice
{
    static function notice() {
        $translator = Translator::instance();

            echo '<div class="error"><p><strong>'
        . esc_html( $translator->get('no_woocommerce_f') )
        . ' <a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a> '
        . esc_html( $translator->get('no_woocommerce_s') ) . '</strong></p></div>';
          
    }
}