<?php
/**
 * Plugin Name: EffectConnect Marketplaces
 * Description: This plugin will allow you to connect your WooCommerce 4.0+ webshop with EffectConnect Marketplaces.
 * Version: 99999999.0.32
 * Author: EffectConnect
 * Author URI: https://www.effectconnect.com/
 */

use EffectConnect\Marketplaces\Controller\ECMenu;
use EffectConnect\Marketplaces\Cron\CronSchedules;
use EffectConnect\Marketplaces\DB\ECTables;
use EffectConnect\Marketplaces\Logic\OfferExport\ProductWatcher;
use EffectConnect\Marketplaces\Logic\ShipmentExport\ShipmentWatcher;
use EffectConnect\Marketplaces\Model\ECPayment;
use EffectConnect\Marketplaces\Model\ECShipping;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!defined('EFFECTCONNECT_MARKETPLACES_VERSION')) {
    define('EFFECTCONNECT_MARKETPLACES_VERSION', '3.0.32');
}

class PluginActivationClass
{
    /**
     * @var CronSchedules
     */
    private $cronSchedules;

    public function __construct()
    {
        require_once __DIR__ . '/vendor/autoload.php';

        register_activation_hook(__FILE__, [$this, 'ec_plugin_activate']);
        register_deactivation_hook(__FILE__, [$this, 'ec_plugin_deactivate']);

        add_action('woocommerce_shipping_init', [$this, 'registerECShippingMethod']);
        add_action('woocommerce_after_register_post_type', [$this, 'registerECPaymentMethods']);
        add_action('woocommerce_after_register_post_type', [$this, 'addWatchers']);
        add_action('plugins_loaded', [$this, 'checkVersion']);

        $this->addPluginMenus();
        $this->addCronSchedules();
        $this->loadTextDomain();
        $this->registerAcfFields();
        $this->registerCliTasks();
    }

    private function addPluginMenus()
    {
        new ECMenu();
    }

    public function addWatchers()
    {
        new ProductWatcher();
        new ShipmentWatcher();
    }

    private function addCronSchedules()
    {
        $this->cronSchedules = new CronSchedules();
    }

    /**
     * Load translations files and set the translations domain key to 'effectconnect_marketplaces'.
     * https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#loading-text-domain
     *
     * @return void
     */
    protected function loadTextDomain()
    {
        load_plugin_textdomain( 'effectconnect_marketplaces', false, dirname(plugin_basename( __FILE__ )) . '/languages' );
    }

    /**
     * Activate the plugin.
     */
    public function ec_plugin_activate()
    {
        $this->updateDatabase();
    }

    public function registerECShippingMethod() {
        add_action( 'woocommerce_shipping_methods', [$this, 'addShippingMethod']);
    }

    public function registerECPaymentMethods() {
        add_action( 'woocommerce_payment_gateways', [$this, 'addPaymentMethod']);
    }

    public function addShippingMethod($methods) {
        if ((function_exists('is_admin') && is_admin()) || (function_exists('wp_doing_cron') && wp_doing_cron()) || (defined( 'WP_CLI' ) && WP_CLI)) {
            $methods[] = new ECShipping();
        }
        return $methods;
    }

    public function addPaymentMethod($methods) {
        if ((function_exists('is_admin') && is_admin()) || (function_exists('wp_doing_cron') && wp_doing_cron()) || (defined( 'WP_CLI' ) && WP_CLI)) {
            $methods[] = new ECPayment();
        }
        return $methods;
    }

    /**
     * Support for plugin https://www.advancedcustomfields.com/
     * Create custom fields for saving additional data to an order such as channel and channel order number.
     * The contents of those additional fields are described in JSON files in folder /acf-json.
     *
     * @return void
     */
    public function registerAcfFields() {
        add_filter('acf/settings/load_json', function($paths) {
            $paths[] = dirname(__FILE__) . '/acf-json';
            return $paths;
        });
    }

    /**
     * Make cron tasks available through WP_CLI for testing (https://make.wordpress.org/cli/).
     * Command line example: wp ec_catalog_export
     *
     * @return void
     */
    public function registerCliTasks() {
        if (class_exists( 'WP_CLI')) {
            WP_CLI::add_command('ec_clean_logs', [$this->cronSchedules, 'runCleanLogsCommand']);
            WP_CLI::add_command('ec_catalog_export', [$this->cronSchedules, 'runCatalogExportCommand']);
            WP_CLI::add_command('ec_queued_shipment_export', [$this->cronSchedules, 'runQueuedShipmentExportCommand']);
            WP_CLI::add_command('ec_full_offer_export', [$this->cronSchedules, 'runFullOfferExportCommand']);
            WP_CLI::add_command('ec_order_import', [$this->cronSchedules, 'runOrderImportCommand']);
        }
    }

    /**
     * Create plugin database tables (includes database updates in case of changes).
     *
     * @return void
     */
    public function updateDatabase()
    {
        $tables = ECTables::getInstance();
        $tables->ecCreateConnectionsTable();
        $tables->ecCreateProductOptionsTable();
        $tables->ecCreateOfferUpdateQueueTable();
        $tables->ecCreateShipmentExportQueueTable();
    }

    /**
     * In case plugin was updated, also update the database.
     *
     * @return void
     */
    public function checkVersion() {
        if (EFFECTCONNECT_MARKETPLACES_VERSION !== get_option('ec_version')) {
            $this->updateDatabase();
            update_option('ec_version', EFFECTCONNECT_MARKETPLACES_VERSION);
        }
    }

    /**
     * Deactivation hook.
     */
    public function ec_plugin_deactivate()
    {
        CronSchedules::unscheduleAll();
    }
}
$plugin = new PluginActivationClass();

register_uninstall_hook(__FILE__, 'ec_plugin_uninstall');

/**
 * Uninstall hook.
 */
function ec_plugin_uninstall()
{
    $tables = ECTables::getInstance();
    $tables->ecDeleteProductOptionsTable();
    $tables->ecDeleteConnectionsTable();
    $tables->ecDeleteOfferQueueTable();
    $tables->ecDeleteShipmentQueueTable();
}