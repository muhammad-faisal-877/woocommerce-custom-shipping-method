<?php
/**
 * Plugin Name: Custom Shipping Method
 * Description: Adds a custom shipping method for specific products and hides other shipping methods when these products are in the cart.
 * Version: 1.2
 * Author: Faisal
 */

if (!defined('ABSPATH')) {
    exit;
}

function register_custom_shipping_method() {
    if (!class_exists('WC_Custom_Shipping_Method')) {
        class WC_Custom_Shipping_Method extends WC_Shipping_Method {
            public function __construct() {
                $this->id = 'custom_shipping_method';
                $this->method_title = __('Custom Shipping Method', 'custom-shipping-method');
                $this->method_description = __('Custom Shipping Method for specific products', 'custom-shipping-method');
                $this->enabled = 'yes';
                $this->title = __('Custom Shipping Method', 'custom-shipping-method');

                $this->init();
            }

            function init() {
                $this->init_form_fields();
                $this->init_settings();

                $this->enabled = $this->get_option('enabled');
                $this->title = $this->get_option('title');
                $this->specific_products = $this->get_option('specific_products', array());
                $this->price = $this->get_option('price', 10);

                add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
            }

            function init_form_fields() {
                $this->form_fields = array(
                    'enabled' => array(
                        'title' => __('Enable/Disable', 'custom-shipping-method'),
                        'type' => 'checkbox',
                        'label' => __('Enable this shipping method', 'custom-shipping-method'),
                        'default' => 'yes',
                    ),
                    'title' => array(
                        'title' => __('Title', 'custom-shipping-method'),
                        'type' => 'text',
                        'description' => __('Title to be displayed during checkout', 'custom-shipping-method'),
                        'default' => __('Custom Shipping Method', 'custom-shipping-method'),
                    ),
                    'specific_products' => array(
                        'title' => __('Specific Products', 'custom-shipping-method'),
                        'type' => 'multiselect',
                        'description' => __('Select products for which this shipping method will be available', 'custom-shipping-method'),
                        'options' => $this->get_products(),
                        'desc_tip' => true,
                    ),
                    'price' => array(
                        'title'       => __('Shipping Cost', 'custom-shipping-method'),
                        'type'        => 'number',
                        'description' => __('Set the shipping cost for this method', 'custom-shipping-method'),
                        'default'     => '10',
                        'desc_tip'    => true,
                    ),
                );
            }

            public function get_products() {
                $cached_product_options = get_transient('custom_shipping_method_product_options');
                if ($cached_product_options !== false) {
                    return $cached_product_options;
                }

                $args = array(
                    'status' => 'publish',
                    'limit' => -1,
                    'fields' => 'id=>name',
                );
                $products = wc_get_products($args);
                $product_options = array();

                foreach ($products as $product) {
                    $product_options[$product->get_id()] = $product->get_name();
                }

                set_transient('custom_shipping_method_product_options', $product_options, 12 * HOUR_IN_SECONDS);

                return $product_options;
            }

            public function calculate_shipping($package = array()) {
                $rate = array(
                    'id' => $this->id,
                    'label' => $this->title,
                    'cost' => floatval($this->price),
                    'calc_tax' => 'per_order'
                );
                $this->add_rate($rate);
            }
        }
    }
}

add_action('woocommerce_shipping_init', 'register_custom_shipping_method');

function add_custom_shipping_method($methods) {
    $methods['custom_shipping_method'] = 'WC_Custom_Shipping_Method';
    return $methods;
}

add_filter('woocommerce_shipping_methods', 'add_custom_shipping_method');

function custom_shipping_for_specific_products($rates, $package) {

    $custom_shipping_method = 'custom_shipping_method';

    $options = get_option('woocommerce_custom_shipping_method_settings');
    $specific_product_ids = isset($options['specific_products']) ? $options['specific_products'] : array();

    $custom_method_found = false;

    foreach ($package['contents'] as $item_id => $values) {
        if (in_array($values['product_id'], $specific_product_ids)) {
            $custom_method_found = true;
            break;
        }
    }

    if ($custom_method_found) {
        $filtered_rates = array();

        foreach ($rates as $rate_key => $rate) {
            if ($rate->method_id === $custom_shipping_method) {
                $filtered_rates[$rate_key] = $rate;
            }
        }

        return $filtered_rates;
    } else {
        foreach ($rates as $rate_key => $rate) {
            if ($rate->method_id === $custom_shipping_method) {
                unset($rates[$rate_key]);
            }
        }
    }

    return $rates;
}

add_filter('woocommerce_package_rates', 'custom_shipping_for_specific_products', 10, 2);

function custom_shipping_enqueue_select2() {
    wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0', true);
    wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0');
    wp_enqueue_script(
        'custom-select2-init',
        plugin_dir_url(__FILE__) . 'assets/js/select2-init.js',
        array('jquery', 'select2'),
        '1.0',
        true
    );
}

add_action('admin_enqueue_scripts', 'custom_shipping_enqueue_select2');
