<?php

include_once( ABSPATH . WPINC. '/class-http.php' );
/**
 * Soukapay Payment Gateway Classs
 */
class soukapay extends WC_Payment_Gateway {
	function __construct() {
		$this->id = "Soukapay";

		$this->method_title = __( "Soukapay", 'Soukapay' );

		$this->method_description = __( "Soukapay Payment Gateway Plug-in for WooCommerce", 'Soukapay' );

		$this->title = __( "Soukapay", 'Soukapay' );

		$this->icon = 'https://soukapay.com/img/s_green_logo.png'; 

		$this->has_fields = true;

		$this->init_form_fields();

		$this->init_settings();

		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}

		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
				$this,
				'process_admin_options'
			) );
		}
	}

	# Build the administration fields for this specific Gateway
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'        => array(
				'title'   => __( 'Enable / Disable', 'Soukapay' ),
				'label'   => __( 'Enable this payment gateway', 'Soukapay' ),
				'type'    => 'checkbox',
				'default' => 'no',
			),
			'title'          => array(
				'title'    => __( 'Title', 'Soukapay' ),
				'type'     => 'text',
				'desc_tip' => __( 'Payment title the customer will see during the checkout process.', 'Soukapay' ),
				'default'  => __( 'Soukapay', 'Soukapay' ),
			),
			'description'    => array(
				'title'    => __( 'Description', 'Soukapay' ),
				'type'     => 'textarea',
				'desc_tip' => __( 'Payment description the customer will see during the checkout process.', 'Soukapay' ),
				'default'  => __( 'Pay securely using your online banking through Soukapay.', 'Soukapay' ),
				'css'      => 'max-width:350px;'
			),
			'universal_enabled_testing' => array(
				'title'   => __( 'Enable Sandbox Mode', 'Soukapay' ),
				'label'   => __( 'You must have a sandbox account at https://stg.soukapay.com. Make sure you\'ve setting up your channel before integration. Note: All sandbox transactions will not get paid from Soukapay.', 'Soukapay' ),
				'type'    => 'checkbox',
				'default' => 'no',
			),
			'universal_form' => array(
				'title'    => __( 'Channel Email', 'Soukapay' ),
				'type'     => 'text',
				'desc_tip' => __( 'This is your SOUKAPAY\'s username or email that registered at Soukapay', 'Soukapay' ),
			),
			'secretkey'      => array(
				'title'    => __( 'Channel API Key', 'Soukapay' ),
				'type'     => 'text',
				'desc_tip' => __( 'This is the API key that you can obtain from your channel in Soukapay', 'Soukapay' ),
			)
		);
	}

	# Submit payment
	public function process_payment( $order_id ) {
		
		# Get this order's information so that we know who to charge and how much
		$customer_order = wc_get_order( $order_id );

		# Prepare the data to send to Soukapay
		$detail = "Order_" . $order_id;

		// set url variable
		$url = '';
		$getKeyUrl = '';
		$soukapay_args = '';
		$post_args = '';

		$old_wc = version_compare( WC_VERSION, '3.0', '<' );

		if ( $old_wc ) {
			$order_id = $customer_order->id;
			$amount   = $customer_order->order_total;
			$name     = $customer_order->billing_first_name . ' ' . $customer_order->billing_last_name;
			$email    = $customer_order->billing_email;
			$phone    = $customer_order->billing_phone;
		} else {
			$order_id = $customer_order->get_id();
			$amount   = $customer_order->get_total();
			$name     = $customer_order->get_billing_first_name() . ' ' . $customer_order->get_billing_last_name();
			$email    = $customer_order->get_billing_email();
			$phone    = $customer_order->get_billing_phone();
		}

		if($this->universal_enabled_testing == "yes") {
			$getKeyUrl = 'https://stg.soukapay.com/api/get-key';
			$url = 'https://stg.soukapay.com/api/wp';
		} else {
			$getKeyUrl = 'https://soukapay.com/api/get-key';
			$url = 'https://soukapay.com/api/wp';
		}

		$result = wp_remote_post( $getKeyUrl, array( 'method' => 'POST', 'body' => ['channelemail'=>$this->universal_form, 'apikey'=>$this->secretkey]) );
		$request_result = json_decode($result['body']);   		             
		
		if ($result === false || $request_result->status == 'error') {
			add_filter( 'the_content', 'soukapay_key_error_msg' );
		}

		$post_args = array('detail'=> $detail,
			'amount'=> $amount,
			'order_id'=> $order_id,
			'buyername'=> $name,
			'buyeremail'=> $email,
			'channelemail'=> $this->universal_form,
			'channelkey'=>$this->secretkey,
			'buyerphone'=> $phone,
			'checkout_page'=> wc_get_checkout_url() );
		
		$soukapay_args = urlencode($detail).'|'.urlencode($amount).'|'.urlencode($order_id).'|'.urlencode($name).'|'.urlencode($email).'|'.urlencode($phone).'|'.urlencode($this->universal_form).'|'.urlencode($this->secretkey).'|'.urlencode(wc_get_checkout_url());
		
		$hash = openssl_encrypt($soukapay_args, 'AES-256-CBC', $this->secretkey, 0, $request_result->pkey);
		
		$request_payment_result = wp_remote_post( $url, array( 'method' => 'POST', 'body' => ['email'=>$this->universal_form, 'apikey'=>$this->secretkey, 'hash'=>base64_encode($hash)] ) );
		
// 		print_r($request_payment_result); exit;
		
		$payment_result = json_decode($request_payment_result['body']);

		if(empty($request_payment_result) || $payment_result->status == 'error') {
			add_filter('the_content', 'soukapay_payment_error_msg');
		} else {
			return array(
				'result'   => 'success',
				'redirect' => $payment_result->url
			);
		}
	}

	public function check_soukapay_response() {

		$msg = filter_input(INPUT_GET, "msg", FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$order_id = filter_input(INPUT_GET, "order_id", FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$status_id =  filter_input(INPUT_GET, "status_id", FILTER_SANITIZE_NUMBER_INT);
		$transaction_id = filter_input(INPUT_GET, "transaction_id", FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$hash = filter_input(INPUT_GET, "hash", FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$porder_id = filter_input(INPUT_POST, "order_id", FILTER_SANITIZE_FULL_SPECIAL_CHARS);


		if ( isset($status_id) && isset($order_id) && $msg==true && isset($transaction_id) && isset($hash) ) {

			global $woocommerce;

			$is_callback = isset( $porder_id ) ? true : false;

			$order = wc_get_order( $order_id );

			$old_wc = version_compare( WC_VERSION, '3.0', '<' );

			$order_id = $old_wc ? $order->id : $order->get_id();

			if ( $order && $order_id != 0 ) {

				# Check if the data sent is valid based on the hash value
				$hash_value = md5( $this->secretkey . $status_id . $order_id . $transaction_id . $msg );
	
				if ( $hash_value == $hash ) {
					if ( $status_id == 1 || $status_id == '1' ) {
						if ( strtolower( $order->get_status() ) == 'pending' || strtolower( $order->get_status() ) == 'processing' ) {
							# only update if order is pending
							if ( strtolower( $order->get_status() ) == 'pending' ) {
								$order->payment_complete($transaction_id);

								$order->add_order_note( 'Payment successfully made through Soukapay. Transaction reference is ' . $transaction_id );
							}

							if ( $is_callback ) {
								echo 'OK';
							} else {
								# redirect to order receive page
								wp_redirect( $order->get_checkout_order_received_url() );

							}

							exit();
						}
					} else {
						if ( strtolower( $order->get_status() ) == 'pending' ) {
							if ( ! $is_callback ) {
								$order->add_order_note( 'Payment was unsuccessful' );
								add_filter( 'the_content', 'soukapay_payment_declined_msg' );
							}
						}
					}
				} else {

					add_filter( 'the_content', 'soukapay_hash_error_msg' );
				}
			}

			if ( $is_callback ) {
				echo 'OK';

				exit();
			}
		}
	}
	
	# this function used to call from backoffice system Soukapay
	public function callback_from_soukapay() {
	
		$msg = filter_input(INPUT_GET, "msg", FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$order_id = filter_input(INPUT_GET, "order_id", FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$status_id =  filter_input(INPUT_GET, "status_id", FILTER_SANITIZE_NUMBER_INT);
		$transaction_id = filter_input(INPUT_GET, "transaction_id", FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$hash = filter_input(INPUT_GET, "hash", FILTER_SANITIZE_FULL_SPECIAL_CHARS);

		if ( isset($status_id) && isset($order_id) && $msg==true && isset($transaction_id) && isset($hash) ) {

			global $woocommerce;

			$order = wc_get_order( $order_id );

			$old_wc = version_compare( WC_VERSION, '3.0', '<' );

			$order_id = $old_wc ? $order->id : $order->get_id();

			if ( $order && $order_id != 0 ) {

				# Check if the data sent is valid based on the hash value
				$hash_value = md5( $this->secretkey . $status_id . $order_id . $transaction_id . $msg );
	
				if ( $hash_value == $hash ) {
					if ( $status_id == 1 || $status_id == '1' ) {
						if ( strtolower( $order->get_status() ) == 'pending' || strtolower( $order->get_status() ) == 'processing' ) {
							# only update if order is pending
							if ( strtolower( $order->get_status() ) == 'pending' ) {
								$order->payment_complete($transaction_id);

								$order->add_order_note( 'Payment successfully made through Soukapay. Transaction reference is ' . $transaction_id );
							}

							echo 'OK';
							exit();
						}
					} else {
						if ( strtolower( $order->get_status() ) == 'pending' && $status_id == 3 ) {
							
							$order->add_order_note( 'Payment was unsuccessful' );
							add_filter( 'the_content', 'soukapay_payment_declined_msg' );
							
						}
					}
				} else {

					add_filter( 'the_content', 'soukapay_hash_error_msg' );
				}
			}
		}
	}

	# Validate fields, do nothing for the moment
	public function validate_fields() {
		return true;
	}

	# Check if we are forcing SSL on checkout pages, Custom function not required by the Gateway for now
	public function do_ssl_check() {
		if ( $this->enabled == "yes" ) {
			if ( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
				echo "<div class=\"error\"><p>" . sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . "</p></div>";
			}
		}
	}

	/**
	 * Check if this gateway is enabled and available in the user's country.
	 * Note: Not used for the time being
	 * @return bool
	 */
	public function is_valid_for_use() {
		return in_array( get_woocommerce_currency(), array( 'MYR' ) );
	}
}