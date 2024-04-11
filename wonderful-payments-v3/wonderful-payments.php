<?php

/**
 * Plugin Name: Wonderful Payments Gateway V3
 * Description: Account to account payments powered by Open Banking.
 * Author: Wonderful Payments Ltd
 * Author URI: https://www.wonderful.co.uk
 * Version: 0.7.3
 * Text Domain: wonderful-payments-gateway
 * Domain Path: /languages
 *
 * Copyright: (C) 2023, Wonderful Payments Limited <tek@wonderful.co.uk>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

define('ASPSP_PARAMETER', 'aspsp');

/**
 * Initialize the Wonderful Payments plugin.
 */
function wc_wonderful_plugin() {
    if(!class_exists('WC_Payment_Gateway')) {
        return;
    }
    require_once(plugin_dir_path(__FILE__)) . 'class-gateway.php';
}

/**
 * Add the Wonderful Payments gateway to the list of WooCommerce payment gateways.
 *
 * @param array $gateways The current list of gateways.
 * @return array The updated list of gateways.
 */
function add_wonderful_payments_gateway( $gateways ) {
    $gateways[] = 'WC_Wonderful_Payments_Gateway';
    return $gateways;
}

/**
 * Declare compatibility with the WooCommerce cart and checkout blocks.
 */
function declare_cart_checkout_blocks_compatibility() {
    if (class_exists('Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
}

/**
 * Register the custom block checkout class.
 */
function wonderful_register_order_approval_payment_method_type() {
    if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        return;
    }

    // Include the custom block checkout class
    require_once plugin_dir_path(__FILE__) . 'class-block.php';

    // Hook the registration function
    add_action('woocommerce_blocks_payment_method_type_registration',
        function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
            $payment_method_registry->register( new WC_Wonderful_Payments_Gateway_Blocks);
        }
    );
}

/**
 * Update the 'aspsp' session variable based on the 'aspsp' parameter in the POST request.
 */
function update_aspsp() {
    // Check if 'aspsp' parameter is set in the POST request
    if (isset($_POST[ASPSP_PARAMETER])) {
        $selected_aspsp = $_POST[ASPSP_PARAMETER];
        WC()->session->set(ASPSP_PARAMETER, $selected_aspsp);
    } else {
        // Handle error when 'aspsp' parameter is not set
        wp_send_json_error('aspsp parameter not found in request');
    }

    wp_die();
}

// Set up hooks
add_action('plugins_loaded', 'wc_wonderful_plugin', 11);
add_filter('woocommerce_payment_gateways', 'add_wonderful_payments_gateway');
add_action('before_woocommerce_init', 'declare_cart_checkout_blocks_compatibility');
add_action('woocommerce_blocks_loaded', 'wonderful_register_order_approval_payment_method_type');
add_action('wp_ajax_update_aspsp', 'update_aspsp');