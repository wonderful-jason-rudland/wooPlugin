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
    private const ENDPOINT = 'https://api.wonderful-one.test';
    private const WONDERFUL_ONE = 'https://wonderful-one.test';
    private const CURRENCY = 'GBP';
    private const ID = 'wonderful_payments_gateway';
    private const SSL_VERIFY = false;
    private const ONLINE = 'online';
    private const ISSUES = 'issues';
    private const OFFLINE = 'offline';

    private string $merchant_key;
    private $banks;

    /**
     * WC_Wonderful_Payments_Gateway constructor.
     *
     * Initializes the payment gateway.
     */
    public function __construct()
    {
        wc_get_logger()->debug('class gateway constructor fired');

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
        $this->merchant_key = $this->get_option('merchant_key');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_' . $this->id, array($this, 'webhook'));
        add_action('woocommerce_api_' . $this->id . '_pending', array($this, 'payment_pending'));
    }

    /**
     * Initialize form fields.
     */
    public function init_form_fields()
    {
        // Get permalink structure
        $permalink_structure = get_option('permalink_structure');

        // Check if plain permalinks are set
        $is_plain_permalink = empty($permalink_structure);

        // Warning message
        $warning_message = '';
        if ($is_plain_permalink) {
            $warning_message = 'Warning: Your permalink structure is set to "Plain". This may cause issues with this plugin.';
        }

        $this->form_fields = array(

            'warning' => array(
                'title' => $warning_message ? __('Warning', 'wc-gateway-wonderful') : '',
                'type' => 'title',
                'description' => $warning_message,
            ),

            'enabled' => array(
                'title' => __('Enable/Disable', 'wc-gateway-wonderful'),
                'type' => 'checkbox',
                'label' => __('Enable Wonderful Payments', 'wc-gateway-wonderful'),
                'default' => 'yes'
            ),

            'title' => array(
                'title' => __('Title', 'wc-gateway-wonderful'),
                'type' => 'text',
                'description' => __('This controls the title for the payment method the customer sees during checkout.', 'wc-gateway-wonderful'),
                'default' => __('Instant bank payment', 'wc-gateway-wonderful'),
                'desc_tip' => true,
            ),

            'description' => array(
                'title' => __('Description', 'wc-gateway-wonderful'),
                'type' => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'wc-gateway-wonderful'),
                'default' => __('No data entry. Pay effortlessly and securely through your mobile banking app. Powered by Wonderful Payments.', 'wc-gateway-wonderful'),
                'desc_tip' => true,
            ),

            'merchant_key' => array(
                'title' => __('Wonderful Payments Merchant Token', 'wc-gateway-wonderful'),
                'type' => 'text',
                'description' => __('Your merchant token will be provided to you when you register with Wonderful Payments', 'wc-gateway-wonderful'),
                'default' => '',
                'desc_tip' => true,
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

        // Get permalink structure
        $permalink_structure = get_option('permalink_structure');

        // Check if plain permalinks are set
        $is_plain_permalink = empty($permalink_structure);

        // Create a merchant payment reference
        $merchant_payment_reference = 'WOO-' . strtoupper(
                substr(md5(uniqid(rand(), true)), 0, 6)
            ) . '-' . $order->get_order_number();

        // get the selected bank for the block checkout.
        if (WC()->session->GET('aspsp') !== null) {
            $_POST[ 'aspsp_name' ] = WC()->session->get('aspsp');
            WC()->session->set('aspsp', null);
        }

        if (empty($_POST['aspsp_name'])) {
            wc_add_notice('Select your bank from the list provided', 'error');
            return false;
        }

        // Initiate a new payment and redirect to WP to pay
        $payload = [
            'amount' => round($order->get_total() * 100), // in pence
            'clientBrowserAgent' => $order->get_customer_ip_address(),
            'clientIpAddress' => $order->get_customer_user_agent(),
            'consented_at' => date('c', time()),
            'currency' => self::CURRENCY,
            'customer_email_address' => $order->get_billing_email(),
            'is_plain_permalink' => $is_plain_permalink,
            'merchant_payment_reference' => $merchant_payment_reference,
            'order_id' => $order_id,
            'plugin_id' => $this->id,
            'selected_aspsp' => sanitize_text_field($_POST['aspsp_name']),
            'source' => sprintf('woocommerce_%s', self::PLUGIN_VERSION),
            'woo_url' => get_site_url(),
        ];

        $iv = openssl_random_pseudo_bytes(16); // openssl_cipher_iv_length('aes-256-cbc')
        $encrypted = openssl_encrypt(json_encode($payload), 'aes-256-cbc', $this->merchant_key, 0, $iv);
        $encrypted_payload = base64_encode($encrypted . '::' . $iv);

        // Get a reference from Wonderful Payments
        $response = wp_remote_get(self::ENDPOINT . '/v2/ref', [
            'body' => $payload,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->merchant_key,
            ],
            'sslverify' => self::SSL_VERIFY,
        ]);

        $body = json_decode(wp_remote_retrieve_body($response));
        $url  = self::WONDERFUL_ONE . '/woo-redirect?payload=' . $encrypted_payload . '&ref=' . $body->ref;

        // redirect to WP
        return array(
            'result' => 'success',
            'redirect' => $url,
        );
    }

    /**
     * Handle the webhook.
     */
    public function webhook()
    {
        wc_get_logger()->debug('webhook fired');

        $wonderfulPaymentId = (isset($_GET['wonderfulPaymentId'])) ? sanitize_text_field($_GET['wonderfulPaymentId']) : null;

        if (null === $wonderfulPaymentId) {
            echo 'error';
            exit;
        }

        // Get the payment status from Wonderful Payments, work out which order it is for, update and redirect.
        $response = wp_remote_get(
            self::ENDPOINT . '/v2/woo/' . $wonderfulPaymentId,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->merchant_key,
                ],
                'sslverify' => self::SSL_VERIFY,
            ]
        );

        $body = json_decode(wp_remote_retrieve_body($response));

        // Extract the Order ID, lookup the WooCommerce order and then process according to the Wonderful Payments order state
        $order = wc_get_order(explode('-', $body->paymentReference)[2]);
        $order->add_order_note(sprintf('Payment Update. Wonderful Payments ID: %s, Status: %s', $body->wonderfulPaymentsId, $body->status));

        // Check payment state and handle accordingly
        switch ($body->status) {
            case 'completed':
                // Mark order payment complete
                $order->add_order_note(sprintf('Payment Success. Order reference: %s, Customer Bank: %s', $body->paymentReference, $body->selectedAspsp));
                $order->payment_complete($body->paymentReference);
                wp_safe_redirect($this->get_return_url($order));
                exit;

            case 'accepted':
            case 'pending':
                // Mark order "on hold" for manual review
                $order->update_status('on-hold', 'Payment has been processed but has not been confirmed. Please manually check payment status before order processing.');
                wp_safe_redirect($this->get_return_url($order));
                exit;

            case 'rejected':
                // Payment has failed, move order to "failed" and redirect back to checkout
                $order->update_status('failed', 'Payment was rejected at the bank');
                wc_add_notice('Your payment was rejected by your bank, you have not be charged. Please try again.', 'error');
                wp_safe_redirect($order->get_checkout_payment_url());
                exit;

            case 'cancelled':
                // Payment was explicitly cancelled, move order to "failed" and redirect back to checkout
                $order->update_status('failed', 'Payment was cancelled by the customer');
                wc_add_notice('Your payment was cancelled.', 'notice');
                wp_safe_redirect($order->get_checkout_payment_url());
                exit;

            case 'errored':
                // Payment errored, move order to "failed" and redirect back to checkout
                $order->update_status('failed', 'Payment error during checkout');
                wc_add_notice('Your payment errored during checkout, please try again.', 'error');
                wp_safe_redirect($order->get_checkout_payment_url());
                exit;

            case 'expired':
                // Payment has expired, move order to "failed" and redirect back to checkout
                $order->update_status('failed', 'Payment expired');
                wc_add_notice('Your payment was not completed in time, you have not been charged. Please try again.', 'error');
                wp_safe_redirect($order->get_checkout_payment_url());
                exit;

        }

        // If we get this far, an unknown error has occurred
        $order->update_status('failed', sprintf('Payment error: unknown payment state %s', $body->status));
        wc_add_notice('An unexpected error occurred while processing your payment.');
        wp_safe_redirect($order->get_checkout_payment_url());
        exit;
    }

    // Webhook to update the payment status.
    public function payment_pending()
    {
        wc_get_logger()->debug('payment_pending fired');

        $wonderfulPaymentId = (isset($_GET['wonderfulPaymentId'])) ? sanitize_text_field($_GET['wonderfulPaymentId']) : null;
        $order_id = (isset($_GET['order_id'])) ? sanitize_text_field($_GET['order_id']) : null;

        if (null === $wonderfulPaymentId || null === $order_id) {
            echo 'error';
            exit;
        }

        $order = wc_get_order($order_id);

        // Mark as pending (we're awaiting the payment)
        $updated = $order->update_status(
            'pending'
        );
        if ($updated) {
            $order->add_order_note('Payment Created - Wonderful Payments ID:' .  $wonderfulPaymentId);
        } else {
            // The status update failed
            wc_get_logger()->error('Order status update failed');
        }
        exit;
    }

    public function payment_fields() {
        wc_get_logger()->debug('payment_fields fired');

        // Get the supported banks from Wonderful Payments
        if ($this->banks === null) {
            $response = wp_remote_get(
                self::ENDPOINT . '/v2/supported-banks',
                [
                    'headers'   => [
                        'Authorization' => 'Bearer ' . $this->merchant_key,
                    ],
                    'sslverify' => self::SSL_VERIFY,
                ]
            );

            if (is_a($response, WP_Error::class)) {
                wc_get_logger()->error('Unable to connect to Wonderful Payments, please try again or select another payment method.');
                wc_add_notice('Unable to connect to Wonderful Payments, please try again or select another payment method.', 'error');
                return;
            }

            $this->banks = json_decode(wp_remote_retrieve_body($response));

            // Error checking and bail if creation failed!
            if (200 != $response['response']['code']) {
                wc_get_logger()->error('Unable to initiate Wonderful Payments checkout, please try again or select another payment method. Code: ' . $response['response']['code']);
                wc_add_notice('Unable to initiate Wonderful Payments checkout, please try again or select another payment method. Code: ' . $response['response']['code'], 'error');
                return;
            }
        }

            echo '
        <div style="margin-left: auto; margin-right: auto; display: grid; grid-template-columns: repeat(1, minmax(0, 1fr)); align-items: start; gap: 1rem; margin-top: 1rem;">
            <div style="background-color: white; height: 28rem; overflow-y: auto;">';

            foreach ($this->banks->data as $bank) {
                if ($bank->status === self::ONLINE) {
                    echo '<div class="bank-button" data-bank-id="' . $bank->bank_name . '" style="width: 100%; border: 1px solid #E2E8F0; transition: box-shadow 0.15s ease-in-out, border-color 0.15s ease-in-out;"
                     onmouseover="this.style.boxShadow = \'0 4px 6px rgba(0, 0, 0, 0.1)\'; this.style.borderColor = \'#4299e1\';"
                     onmouseout="this.style.boxShadow = \'none\'; this.style.borderColor = \'#E2E8F0\';"
                     onclick="this.style.backgroundColor = \'#1F2A64\';">
                    <span data-bank-id="' . $bank->bank_id . '" style="display: flex; align-items: center;">
                        <span data-bank-id="' . $bank->bank_id . '">
                            <img data-bank-id="' . $bank->bank_id . '" src="' . $bank->bank_logo  . '" alt="" style="height: 2.5rem; width: 2.5rem; min-width: 2.5rem; margin: 1rem;">
                        </span>
                        <span data-bank-id="' . $bank->bank_id . '" style="text-align: left; color: #718096; overflow: hidden; padding-right: 1rem; font-family: paralucent, sans-serif; line-height: 0.1px">
                            <span data-bank-id="' . $bank->bank_id . '" style="font-size: 1.35rem; line-height: 2rem;">' . $bank->bank_name . '</span>';
                    if ($bank->status === self::ISSUES) {
                        echo '<i class="fas fa-exclamation-triangle" style="color: #FFC107;" data-toggle="tooltip" data-placement="top" title="This bank may be experiencing issues"></i>';
                    }
                    if ($bank->status === self::OFFLINE) {
                        echo '<i class="fas fa-exclamation-square" style="color: #A0AEC0;" data-toggle="tooltip" data-placement="top" title="This bank is currently offline"></i>';
                    }
                    echo '</span>
                    </span>
                </div>';

                echo '<input type="hidden" name="aspsp_name" value="natwest">';
                }
            }

            echo '</div></div>';

            echo '<p style="font-size: 0.7em; text-align: center;">Instant payments are processed by <a href="https://wonderful.co.uk" target="_blank">Wonderful Payments</a>
            and are subject to their <a href="https://wonderful.co.uk/legal" target="_blank">Consumer Terms and Privacy Policy</a>.</p></div>';
    }

    public function banks()
    {
        wc_get_logger()->debug('banks (for the blocks checkout data) fired');
        // Get the supported banks from Wonderful Payments
        if ($this->banks === null) {
            $response = wp_remote_get(
                self::ENDPOINT . '/v2/supported-banks',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->merchant_key,
                    ],
                    'sslverify' => self::SSL_VERIFY,
                ]
            );

            if (is_a($response, WP_Error::class)) {
                wc_get_logger()->error(
                    'Unable to connect to Wonderful Payments, please try again or select another payment method.'
                );
                wc_add_notice(
                    'Unable to connect to Wonderful Payments, please try again or select another payment method.',
                    'error'
                );
                return;
            }

            $this->banks = json_decode(wp_remote_retrieve_body($response));

            // Error checking and bail if creation failed!
            if (200 != $response['response']['code']) {
                wc_get_logger()->error(
                    'Unable to initiate Wonderful Payments checkout, please try again or select another payment method. Code: ' . $response['response']['code']
                );
                wc_add_notice(
                    'Unable to initiate Wonderful Payments checkout, please try again or select another payment method. Code: ' . $response['response']['code'],
                    'error'
                );
                return;
            }

            return $this->banks;
        }
    }
}
