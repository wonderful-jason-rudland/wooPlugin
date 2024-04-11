<?php

// Bail If Accessed Directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WC_Wonderful_Payments_Gateway
 *
 * Payment Gateway Class for Wonderful Payments
 */
class WC_Wonderful_Payments_Gateway extends WC_Payment_Gateway
{
    private const PLUGIN_VERSION = '0.6.0';
    private const ID = 'wonderful_payments_gateway';

    /**
     * WC_Wonderful_Payments_Gateway constructor.
     *
     * Initializes the payment gateway.
     */
    public function __construct()
    {
        $this->id = self::ID;
        $this->method_title = __('Wonderful Payments', 'wc-gateway-wonderful');
        $this->method_description = __('Account to account bank payments, powered by Open Banking', 'wc-gateway-wonderful');
        $this->icon = '';
        $this->has_fields = true;
        $this->supports = array('products');

        $this->init_form_fields();
        $this->init_settings();
        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    /**
     * Initialize form fields.
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'wc-gateway-wonderful'),
                'type' => 'checkbox',
                'label' => __('Enable Wonderful Payments', 'wc-gateway-wonderful'),
                'default' => 'yes'
            ),
        );
    }

    /**
     * Process the payment.
     *
     * @param int $order_id The ID of the order.
     * @return array|void The result of the payment processing.
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        $order->payment_complete();

        // Skip redirecting to Wonderful Payments, and go straight to the success page
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url( $order ),
        );
    }

    /*
     * Display the banks for the shortcode checkout
     */
    public function payment_fields() {
                    echo '<div class="bank-button" data-bank-id="natwest" style="width: 100%; border: 1px solid #E2E8F0; transition: box-shadow 0.15s ease-in-out, border-color 0.15s ease-in-out;"
                     onmouseover="this.style.boxShadow = \'0 4px 6px rgba(0, 0, 0, 0.1)\'; this.style.borderColor = \'#4299e1\';"
                     onmouseout="this.style.boxShadow = \'none\'; this.style.borderColor = \'#E2E8F0\';"
                     onclick="this.style.backgroundColor = \'#1F2A64\';">
                    <span data-bank-id="natwest" style="display: flex; align-items: center;">
                        <span data-bank-id="natwest">
                            <img data-bank-id="natwest" src="https://payments.test/img/bank_logos/natwest.png" alt="" style="height: 2.5rem; width: 2.5rem; min-width: 2.5rem; margin: 1rem;">
                        </span>
                        <span data-bank-id="natwest" style="text-align: left; color: #718096; overflow: hidden; padding-right: 1rem; font-family: paralucent, sans-serif; line-height: 0.1px">
                            <span data-bank-id="natwest" style="font-size: 1.35rem; line-height: 2rem;">NatWest</span>
                    </span>
                    </span>
                </div>
        <input type="hidden" name="aspsp_name" value="natwest"></div></div>';
    }

    /*
     * Return the selectable banks data for the block checkout to render
     */
    public function banks()
    {
        $data = new stdClass();
        $data->data = array(
            (object) array(
                "bank_id" => "natwest",
                "bank_name" => "NatWest",
                "bank_logo" => "https://payments.test/img/bank_logos/natwest.png",
                "status" => "online"
            )
        );
        return $data;
    }
}
