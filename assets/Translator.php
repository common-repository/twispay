<?php
namespace Twispay\Assets;

class Translator
{
    private static $_instance;
    private static array $tw_labels;
    public static function instance() {
        if (!isset(self::$_instance) || !(self::$_instance instanceof Translator) ) {
            self::$_instance = new self();
            
            $language = explode( '-', get_bloginfo( 'language' ) )[0];
            if ( file_exists( TWISPAY_PLUGIN_DIR . 'language/' . $language . '/language.php' ) ) {
                require( TWISPAY_PLUGIN_DIR . 'language/' . $language . '/language.php' );
            } else {
                require( TWISPAY_PLUGIN_DIR . 'lang/en/lang.php' );
            }
            $self::$tw_labels = $tw_lang;
        }
        
        return self::$_instance;
    }
    function get(string $key) {
        return array_key_exists($key, self::$tw_labels) ? self::$tw_labels[$key] : "";
    }
}