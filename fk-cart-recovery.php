<?php
/**
 * Plugin Name: FK Cart Recovery Pro
 * Plugin URI:  https://linktr.ee/fahadkhalid211
 * Description: Complete WooCommerce Cart Abandonment Recovery with Email, WhatsApp, SMS, Telegram & Analytics.
 * Version:     1.0.0
 * Author:      Fahad Khalid
 * Author URI:  https://linktr.ee/fahadkhalid211
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fk-cart-recovery
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 5.0
 * WC tested up to:      9.9
 */

defined( 'ABSPATH' ) || exit;

define( 'CAR_PRO_VERSION',    '1.0.0' );
define( 'CAR_PRO_PATH',       plugin_dir_path( __FILE__ ) );
define( 'CAR_PRO_URL',        plugin_dir_url( __FILE__ ) );
define( 'CAR_PRO_FILE',       __FILE__ );
define( 'CAR_PRO_BASENAME',   plugin_basename( __FILE__ ) );
define( 'CAR_PRO_DB_VERSION', '1.0.0' );

final class CAR_Pro {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->includes();
        $this->hooks();
    }

    private function includes() {
        require_once CAR_PRO_PATH . 'includes/functions.php';
        require_once CAR_PRO_PATH . 'includes/class-car-db.php';
        require_once CAR_PRO_PATH . 'includes/class-car-install.php';
        require_once CAR_PRO_PATH . 'includes/class-car-tracker.php';
        require_once CAR_PRO_PATH . 'includes/class-car-recovery-link.php';
        require_once CAR_PRO_PATH . 'includes/class-car-coupon.php';
        require_once CAR_PRO_PATH . 'includes/class-car-email-handler.php';
        require_once CAR_PRO_PATH . 'includes/class-car-whatsapp.php';
        require_once CAR_PRO_PATH . 'includes/class-car-sms.php';
        require_once CAR_PRO_PATH . 'includes/class-car-telegram.php';
        require_once CAR_PRO_PATH . 'includes/class-car-scheduler.php';
        require_once CAR_PRO_PATH . 'includes/class-car-rule-engine.php';
        require_once CAR_PRO_PATH . 'includes/class-car-analytics.php';
        require_once CAR_PRO_PATH . 'includes/class-car-gdpr.php';
        require_once CAR_PRO_PATH . 'includes/class-car-notifications.php';
        require_once CAR_PRO_PATH . 'includes/class-car-shortcodes.php';

        if ( is_admin() ) {
            require_once CAR_PRO_PATH . 'admin/class-car-admin.php';
        }
    }

    private function hooks() {
        // Register cron interval ASAP so WP can find it when evaluating schedule.
        add_filter( 'cron_schedules', [ $this, 'add_cron_intervals' ] );

        add_action( 'admin_init',    [ 'CAR_Install', 'maybe_install' ] );
        register_activation_hook( CAR_PRO_FILE,   [ 'CAR_Install', 'activate' ] );
        register_deactivation_hook( CAR_PRO_FILE, [ 'CAR_Install', 'deactivate' ] );

        // Reschedule cron on every load (safe no-op if already scheduled).
        add_action( 'init', [ $this, 'ensure_cron' ] );

        add_action( 'plugins_loaded', [ $this, 'check_woocommerce' ] );

        // WooCommerce HPOS Compatibility Declaration
        add_action( 'before_woocommerce_init', [ $this, 'declare_hpos_compatibility' ] );

        // Intercept the WordPress.org API lookup for "View Details" popup
        // so it shows our plugin info instead of any same-slug WP.org listing.
        add_filter( 'plugins_api', [ $this, 'plugin_info' ], 20, 3 );
    }

    /**
     * Declare compatibility with WooCommerce High-Performance Order Storage (HPOS).
     */
    public function declare_hpos_compatibility() {
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', CAR_PRO_FILE, true );
        }
    }

    /**
     * Serve our own plugin info for the "View Details" modal on plugins.php.
     * Prevents WordPress from fetching data from WordPress.org for this slug.
     */
    public function plugin_info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action ) {
            return $result;
        }

        if ( ! isset( $args->slug ) || 'fk-cart-recovery' !== $args->slug ) {
            return $result;
        }

        $info                = new stdClass();
        $info->name          = 'FK Cart Recovery Pro';
        $info->slug          = 'fk-cart-recovery';
        $info->version       = CAR_PRO_VERSION;
        $info->author        = '<a href="https://linktr.ee/fahadkhalid211">Fahad Khalid</a>';
        $info->author_profile= 'https://linktr.ee/fahadkhalid211';
        $info->homepage      = 'https://linktr.ee/fahadkhalid211';
        $info->requires      = '5.8';
        $info->tested        = '6.9';
        $info->requires_php  = '7.4';
        $info->last_updated  = gmdate( 'Y-m-d' );
        $info->short_description = 'Complete WooCommerce Cart Abandonment Recovery with Email, WhatsApp, SMS, Telegram & Analytics.';
        $info->sections      = [
            'description' => '<p>FK Cart Recovery Pro helps WooCommerce store owners recover lost sales through automated multi-channel recovery campaigns.</p>'
                . '<ul>'
                . '<li>Automated email recovery sequences with open/click tracking</li>'
                . '<li>WhatsApp recovery (UltraMsg, WhatsApp Business API, Twilio)</li>'
                . '<li>SMS recovery (Twilio, Vonage)</li>'
                . '<li>Telegram bot recovery</li>'
                . '<li>1-click cart recovery links</li>'
                . '<li>Unique coupon code generation</li>'
                . '<li>Analytics dashboard with charts</li>'
                . '<li>GDPR-ready consent checkbox</li>'
                . '</ul>',
            'installation' => '<ol><li>Upload the plugin folder to <code>/wp-content/plugins/</code></li><li>Activate via WordPress Plugins menu</li><li>Go to <strong>Cart Recovery</strong> in the admin sidebar</li></ol>',
        ];

        return $info;
    }

    /**
     * Register custom cron intervals early (on cron_schedules filter).
     */
    public function add_cron_intervals( $schedules ) {
        if ( ! isset( $schedules['every_5_minutes'] ) ) {
            $schedules['every_5_minutes'] = [
                'interval' => 300,
                'display'  => __( 'Every 5 Minutes', 'fk-cart-recovery' ),
            ];
        }
        return $schedules;
    }

    /**
     * Make sure the WP-Cron event is scheduled. Runs on 'init' every request
     * so it self-heals if the schedule was cleared.
     */
    public function ensure_cron() {
        if ( ! wp_next_scheduled( 'car_pro_process_scheduler' ) ) {
            wp_schedule_event( time(), 'every_5_minutes', 'car_pro_process_scheduler' );
        }
    }

    public function check_woocommerce() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', static function() {
                echo '<div class="error"><p>' . esc_html__( 'Cart Abandonment Recovery Pro requires WooCommerce to be installed and active.', 'fk-cart-recovery' ) . '</p></div>';
            } );
            return;
        }

        $this->init();
    }

    private function init() {
        global $car_tracker_instance;
        $car_tracker_instance = new CAR_Tracker();

        new CAR_Recovery_Link();
        new CAR_Scheduler();
        new CAR_GDPR();
        new CAR_Notifications();
        new CAR_Shortcodes();

        if ( is_admin() ) {
            new CAR_Admin();
        }
    }
}

function car_pro() {
    return CAR_Pro::instance();
}

add_action( 'plugins_loaded', 'car_pro', 5 );