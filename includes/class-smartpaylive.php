<?php
/**
 * SmartPayLive
 *
 * World-Class, Innovative, Direct Merchanting Bank to Bank Instant EFT Payment Solution in South Africa.
 *
 * @class WC_Gateway_SmartPayLive
 * @author SmartPayLive
 */
class SmartPayLive {
    const LIVE_BASE_URL = "https://api.smartpaylive.com/api/V1/";
    const SANDBOX_BASE_URL = "http://devapi.smartpaylive.com/api/V1/";
    const CLIENT_VERSION = "1.0";

    private $channel_name;
    private $channel_password;
    private $timestamp;
    private $request_reference;
    private $base_url;

    public function __construct($channel_name, $channel_password, $base_url = self::LIVE_BASE_URL) {
        $this->channel_name = $channel_name;
        $this->channel_password = $channel_password;
        $this->base_url = $base_url;
    }

    /**
	 * Get payment link by requesting payment.
	 *
	 * @since 1.0.0
	 */
    public function request_payment(
        $transaction_reference, 
        $customer_id, 
        $amount, 
        $redirect_url, 
        $customer_email_address,
        $cancel_redirect_url, 
        $is_recurring = false, 
        $first_recurring_payment_time = null, 
        $num_recurring_payments = 0
    ) {
        $url = $this->build_url("RequestPayment");
        $data = $this->build_request(array(
            "IntegratorTransactionReference" => $transaction_reference,
            "IntegratorCustomerId" => $customer_id,
            "Amount" => $amount,
            "IntegratorRedirectUrl" => $redirect_url,
            "CustomerEmailAddress" => $customer_email_address,
            "CancelRedirectUrl" => $cancel_redirect_url,
            "IsRecurring" => $is_recurring,
            "FirstRecurringPayment" => $first_recurring_payment_time,
            "NumRecurringPayments" => $num_recurring_payments
        ));
        return $this->make_request($url, $data);
    }

    /**
	 * Query payment status.
	 *
	 * @since 1.0.0
	 */
    public function query_status($payment_request_id) {
        $url = $this->build_url("QueryStatus");
        $data = $this->build_request(array(
            "PaymentRequestId" => $payment_request_id
        ));
        return $this->make_request($url, $data);
    }

    /**
	 * Cancel payment.
	 *
	 * @since 1.0.0
	 */
    public function cancel_payment($payment_request_id, $cancellation_reason = "") {
        $url = $this->build_url("CancelPayment");
        $data = $this->build_request(array(
            "PaymentRequestId" => $payment_request_id,
            "CancellationReason" => $cancellation_reason
        ));
        return $this->make_request($url, $data);
    }

    /**
	 * Build full api url.
	 *
	 * @since 1.0.0
	 */
    private function build_url($endpoint) {
        return $this->base_url . $endpoint;
    }

    /**
	 * Build request.
	 *
	 * @since 1.0.0
	 */
    private function build_request($request_data) {
        $this->timestamp = $this->get_current_timestamp();
        $this->request_reference = $this->get_request_reference();
        $credentials = $this->get_credentials();
        return array(
            "Credentials" => $credentials,
            "OriginatingIpAddress" => $this->get_client_ip(),
            "ClientVersion" => self::CLIENT_VERSION,
            "Request" => $request_data
        );
    }

    /**
	 * Generate api credentials.
	 *
	 * @since 1.0.0
	 */
    private function get_credentials() {
        return array(
            "ChannelName" => $this->channel_name,
            "ChannelHash" => $this->generate_channel_hash(),
            "Timestamp" => $this->timestamp,
            "RequestReference" => $this->request_reference,
        );
    }

    /**
	 * Get current timestamp.
	 *
	 * @since 1.0.0
	 */
    private function get_current_timestamp() {
        return date("Y-m-d H:i:s") . ".000";
    }

    /**
	 * Generate random request reference.
	 *
	 * @since 1.0.0
	 */
    private function get_request_reference() {
        return bin2hex(openssl_random_pseudo_bytes(16));
    }

    /**
	 * Generate channel hash.
	 *
	 * @since 1.0.0
	 */
    private function generate_channel_hash() {
        $data = $this->channel_name . $this->timestamp . $this->channel_password . $this->request_reference;
        $encrypted_data = $this->aes_encrypt($data, $this->channel_password);
        $channel_hash = strtoupper($this->sha256($encrypted_data));
        return $channel_hash;
    }

    /**
	 * Encrypt plain data with aes-192-cbc algorithm.
	 *
	 * @since 1.0.0
	 */
    private function aes_encrypt($plain_data, $key) {
        $method = "aes-192-cbc";
        $iv = str_repeat(chr(0x0), 16);
        $encrypted_data = openssl_encrypt($plain_data, $method, $key, OPENSSL_RAW_DATA, $iv);
        return $encrypted_data;
    }

    /**
	 * Generate sha256 hash from plain data.
	 *
	 * @since 1.0.0
	 */
    private function sha256($data) {
        return hash("sha256", $data);
    }

    /**
	 * Get client's ip address.
	 *
	 * @since 1.0.0
	 */
    private function get_client_ip() {
        $ip_address = '';
        if (getenv('HTTP_CLIENT_IP'))
            $ip_address = getenv('HTTP_CLIENT_IP');
        else if(getenv('HTTP_X_FORWARDED_FOR'))
            $ip_address = getenv('HTTP_X_FORWARDED_FOR');
        else if(getenv('HTTP_X_FORWARDED'))
            $ip_address = getenv('HTTP_X_FORWARDED');
        else if(getenv('HTTP_FORWARDED_FOR'))
            $ip_address = getenv('HTTP_FORWARDED_FOR');
        else if(getenv('HTTP_FORWARDED'))
            $ip_address = getenv('HTTP_FORWARDED');
        else if(getenv('REMOTE_ADDR'))
            $ip_address = getenv('REMOTE_ADDR');
        else
            $ip_address = 'UNKNOWN';
        return $ip_address;
    }

    /**
	 * Make api request.
	 *
	 * @since 1.0.0
	 */
    private function make_request($url, $data) {
        $args = array(
            'body' => wp_json_encode($data),
            'timeout' => '30',
            'redirection' => '10',
            'httpversion' => '1.1',
            'headers' => array(
                'Content-Type' => 'application/json'
            )
        );

        $response = wp_remote_post($url, $args);

        if (!is_wp_error($response)) {
            $response_body = wp_remote_retrieve_body($response);
            return json_decode($response_body);
        }

        return null;
    }
}