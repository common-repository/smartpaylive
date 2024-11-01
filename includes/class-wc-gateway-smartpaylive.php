<?php
/**
 * SmartPayLive Payment Gateway
 *
 * Provides a SmartPayLive Payment Gateway.
 *
 * @class WC_Gateway_SmartPayLive
 * @author SmartPayLive
 */
class WC_Gateway_SmartPayLive extends WC_Payment_Gateway {
    
    /**
	 * Constructor
	 */
    public function __construct() {
        $this->id = 'smartpaylive';
        $this->icon = WP_PLUGIN_URL . '/' . plugin_basename(dirname(dirname(__FILE__))) . '/assets/images/icon.png';
        $this->has_fields = false;
        $this->method_title = 'SmartPayLive';
        $this->method_description = 'SmartPayLive is a World-Class, Innovative, Direct Merchanting Bank to Bank Instant EFT Payment Solution in South Africa.';
        $this->available_currencies = array('ZAR');
        $this->notification_url = add_query_arg(array(
            'wc-api' => 'WC_Gateway_SmartPayLive',
            'callback' => 'check_notification_response'
        ), home_url('/'));

        $this->init_form_fields();
        $this->init_settings();
        
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->sandbox_enabled = $this->get_option('sandbox_enabled');
        $this->channel_name = $this->get_option('channel_name');
        $this->channel_password = $this->get_option('channel_password');
        $this->base_url = SmartPayLive::LIVE_BASE_URL;
        $this->enabled = $this->is_valid_for_use() ? 'yes' : 'no';

        if ($this->sandbox_enabled == 'yes') {
            $this->base_url = SmartPayLive::SANDBOX_BASE_URL;
        }

        add_action('woocommerce_api_wc_gateway_' . $this->id, array($this, 'wc_callback'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
    }

    /**
	 * Initialize Gateway Settings Form Fields.
	 *
	 * @since 1.0.0
	 */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
				'title' => 'Enable/Disable',
				'label' => 'Enable SmartPayLive',
				'type' => 'checkbox',
				'description' => 'This controls whether or not this gateway is enabled within WooCommerce.',
				'default' => 'yes',
				'desc_tip' => true,
			),
			'title' => array(
				'title' => 'Title',
				'type' => 'text',
				'description' => 'This controls the title which the user sees during checkout.',
				'default' => 'SmartPayLive',
				'desc_tip' => true,
			),
			'description' => array(
				'title' => 'Description',
				'type' => 'text',
				'description' => 'This controls the description which the user sees during checkout.',
				'default' => '',
				'desc_tip' => true,
            ),
            'sandbox_enabled' => array(
				'title' => 'SmartPayLive Sandbox',
                'label' => 'Enable SmartPayLive Sandbox',
				'type' => 'checkbox',
				'description' => 'SmartPayLive sandbox can be used to test payments.',
				'default' => 'no',
                'desc_tip' => true,
			),
            'channel_name' => array(
				'title' => 'Channel Name',
				'type' => 'text',
				'description' => 'The channel name as provided by SmartPayLive system.',
				'default' => '',
				'desc_tip' => true,
			),
            'channel_password' => array(
				'title' => 'Channel Password',
				'type' => 'text',
				'description' => 'The channel password as provided by SmartPayLive system.',
				'default' => '',
				'desc_tip' => true,
			),
            'notification_url' => array(
				'title' => 'Notification URL',
				'type' => 'text',
				'description' => 'Notification URL required for SmartPayLive account setup.',
				'default' => $this->notification_url,
				'desc_tip' => true,
                'custom_attributes' => array('readonly' => 'readonly')
			),
        );
    }

    /**
	 * Process the payment and return the result.
	 *
	 * @since 1.0.0
	 */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        return array(
            'result' => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        );
    }

    /**
	 * Reciept page.
	 *
	 * Display text and a button to direct the user to SmartPayLive.
	 *
	 * @since 1.0.0
	 */
    public function receipt_page($order_id) {
        $payment_url = '';
		$order = wc_get_order($order_id);
        $transaction_id = $order->get_transaction_id();
        if (!empty($transaction_id)) {
            $spl = new SmartPayLive($this->channel_name, $this->channel_password, $this->base_url);
            $status = $spl->query_status($transaction_id);
            if (!is_null($status) && $status->Succeeded == true) {
                $payment_url = $status->Response->PaymentUrl;
            }
        } else {
            $spl = new SmartPayLive($this->channel_name, $this->channel_password, $this->base_url);
            $response = $spl->request_payment(
                $order_id,
                $order->get_user_id(),
                $order->get_total(),
                add_query_arg(array(
                    'wc-api' => 'WC_Gateway_SmartPayLive',
                    'callback' => 'payment_callback',
                    'order_id' => $order_id
                ), home_url('/')),
                $order->get_billing_email(),
                $order->get_cancel_order_url(),
            );
    
            if (!is_null($response) && $response->Succeeded == true) {
                $order->set_transaction_id($response->Response->PaymentRequestId);
                $order->save();
                $payment_url = $response->Response->PaymentUrl;
            }
        }

        echo esc_html($this->render_payment_button($payment_url));
	}

    /**
	 * Render text and payment button.
	 *
	 * @since 1.0.0
	 */
    private function render_payment_button($payment_url) {
        if (!empty($payment_url)) { ?>
        <p>Thank you for your order, please click the button below to pay with SmartPayLive.</p>
        <a href="<?php echo esc_url($payment_url); ?>" style="max-width:200px;display:block;border:1px solid #ddd;border-radius:50px;padding:8px 0px 10px 18px;"><img src="<?php echo esc_url($this->icon); ?>" style="width:100%;"></a>
        <script type="text/javascript">
            jQuery(function() {
                window.location = '<?php echo esc_url($payment_url); ?>';
            });
        </script>
    <?php } else { ?>
        <p>There was an issue generating a payment link, please try again later.</p>
    <?php }}

    /**
	 * Respond to SmartPayLive payment notifications.
	 *
	 * @since 1.0.0
	 */
    public function check_notification_response() {
        global $woocommerce;
        $raw_input = file_get_contents('php://input');

        $webhook_payload = json_decode($raw_input);
        if (!empty($webhook_payload)) {
            $order = wc_get_order($webhook_payload->IntegratorTransactionReference);
            $requestIdCheck = $this->validate_request_id($webhook_payload->PaymentRequestId, $order->get_transaction_id());
            $paymentAmountCheck = $this->validate_payment_amount($webhook_payload->AmountRequested, $order->get_total());
            $validIpCheck = $this->validate_ip();
            $validPaymentStatusCheck = $this->validate_payment_status($webhook_payload->PaymentRequestId, $webhook_payload->PaymentStatus);
    
            if ($requestIdCheck && $paymentAmountCheck && $validIpCheck && $validPaymentStatusCheck) {
                $woocommerce->cart->empty_cart();
                $order->payment_complete();
            }
        }
    }

    /**
	 * Validate SmartPayLive payment request id.
	 *
	 * @since 1.0.0
	 */
    private function validate_request_id($payment_request_id, $order_payment_request_id) {
        return $payment_request_id === $order_payment_request_id;
    }

    /**
	 * Validate SmartPayLive payment amount.
	 *
	 * @since 1.0.0
	 */
    private function validate_payment_amount($amount_requested, $order_total) {
        return (float)$amount_requested == (float)$order_total;
    }

    /**
	 * Validate SmartPayLive ip.
	 *
	 * @since 1.0.0
	 */
    private function validate_ip() {
        $valid_smart_pay_ips = [
          '41.185.80.146',
          '41.185.18.98',
          '41.185.18.99'
        ];
      
        $referrer_ip = $_SERVER['REMOTE_ADDR'];
      
        return in_array($referrer_ip, $valid_smart_pay_ips, true);
    }

    /**
	 * Validate SmartPayLive payment status.
	 *
	 * @since 1.0.0
	 */
    public function validate_payment_status($payment_request_id, $payment_status) {
        $spl = new SmartPayLive($this->channel_name, $this->channel_password, $this->base_url);
        $status = $spl->query_status($payment_request_id);
        if (!is_null($status) && $status->Succeeded == true) {
          if ($status->Response->Status == $payment_status && $payment_status == 3) {
            return true;
          }
        }
        return false;
    }

    /**
	 * Handle woocommerce api callbacks.
	 *
	 * @since 1.0.0
	 */
    public function wc_callback() {
        switch ($_GET['callback']) {
            case 'check_notification_response':
                $this->check_notification_response();
                break;
            case 'payment_callback':
                $order_id = (int)$_GET['order_id'];
                $this->payment_callback($order_id);
                break;
        }
    }

    /**
	 * Handle after payment callback.
	 *
	 * @since 1.0.0
	 */
    private function payment_callback($order_id) {
        $order = wc_get_order($order_id);
        if (is_object($order)) {
            $payment_request_id = $order->get_transaction_id();
            if (!empty($payment_request_id)) {
                $spl = new SmartPayLive($this->channel_name, $this->channel_password, $this->base_url);
                $status = $spl->query_status($payment_request_id);
                if (!is_null($status) && $status->Succeeded == true) {
                    if ($status->Response->Status == 3) {
                        wp_redirect($this->get_return_url($order));
                        exit;
                    } else {
                        wc_add_notice('Payment cancelled', 'error');
                        wp_redirect($order->get_cancel_order_url());
                        exit;
                    }
                }
            }
        }
        echo esc_html("<h1>Something went wrong!</h1>");
        exit;
    }

    /**
	 * Show error if unavailable currency selected.
	 *
	 * @since 1.0.0
	 */
    public function admin_options() {
		if (in_array( get_woocommerce_currency(), $this->available_currencies)) {
			parent::admin_options();
		} else { ?>
			<h3>SmartPayLive</h3>
			<div class="inline error"><p><strong>Gateway Disabled</strong> Choose South African Rands as your store currency in <a href="<?php echo esc_url(admin_url('admin.php?page=wc-settings&tab=general')); ?>">General Settings</a> to enable the SmartPayLive Gateway.</p></div>
        <?php }
	}

    /**
	 * Check if payment gateway is valid for use.
	 *
	 * @since 1.0.0
	 */
    private function is_valid_for_use() {
		return in_array(get_woocommerce_currency(), $this->available_currencies) && $this->get_option('enabled') == 'yes';
	}
}