<?php
/**
 * WC wcCpg1 Gateway Class.
 * Built the wcCpg1 method.
 */
class WC_Custom_Payment_Gateway_1 extends WC_Payment_Gateway_CC {


    /**
     * Constructor for the gateway.
     *
     * @return void
     */
    public function __construct() {
        global $woocommerce;

        $this->id             = 'ncgw1';
        $this->icon           = '';
        $this->has_fields     = true;
        $this->method_title   = __( 'Custom Gateway Merchant API', 'ncgwApi' );
        // Load the form fields.
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        // Define user set variables.
        $this->title          = $this->settings['title'];
        $this->description    = $this->settings['description'];
        $this->api_key    = $this->settings['api-key'];
        $this->secret_key    = $this->settings['secret-key'];
		$this->instructions       = $this->get_option( 'instructions' );
		$this->enable_for_methods = $this->get_option( 'enable_for_methods', array() );

        // Actions.
        if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) )
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
        else
            add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );

        // Hooks.
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );

    }


    /* Admin Panel Options.*/
	function admin_options() {
		?>
		<h3><?php _e('Pay with Custom API','wcwcCpg1'); ?></h3>
    	<table class="form-table">
    		<?php $this->generate_settings_html(); ?>
		</table> <?php
    }

    public function admin_notices() {
        if ( $this->enabled == 'no') {
            return false;
        }

        // Check for API Keys
        if ( ! $this->settings['api-key'] && ! $this->settings['secret-key'] ) {
            echo '<div class="error"><p>' . __( 'You need the API Key & Secret Key in order to work, please find your API key and Secret at <a href="https://www.google.com.com" target="_blank">Custom Gateway Authentication Keys</a>.', 'beanstream-for-woocommerce' ) . '</p></div>';
            return false;
        }

    }

    /* Initialise Gateway Settings Form Fields. */
    public function init_form_fields() {
    	global $woocommerce;

    	$shipping_methods = array();

    	if ( is_admin() )
	    	foreach ( $woocommerce->shipping->load_shipping_methods() as $method ) {
		    	$shipping_methods[ $method->id ] = $method->get_title();
	    	}
			
        $this->form_fields = array(
            'enabled' => array(
                'title' => __( 'Enable/Disable', 'wcwcCpg1' ),
                'type' => 'checkbox',
                'label' => __( 'Enable your gateway API Payment Method', 'wcwcCpg1' ),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __( 'Title', 'wcwcCpg1' ),
                'type' => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'wcwcCpg1' ),
                'desc_tip' => true,
                'default' => __( 'Your API Gateway', 'wcwcCpg1' )
            ),
            'description' => array(
                'title' => __( 'Description', 'wcwcCpg1' ),
                'type' => 'textarea',
                'description' => __( 'This controls the description which the user sees during checkout.', 'wcwcCpg1' ),
                'default' => __( 'Description for API payment method.', 'wcwcCpg1' )
            ),
			'api-key' => array(
				'title' => __( 'API Key', 'wcwcCpg1' ),
				'type' => 'text',
				'description' => __( 'Your API key.', 'wcwcCpg1' ),
				'default' => __( '', 'wcwcCpg1' )
			),
            'secret-key' => array(
				'title' => __( 'Secret Key', 'wcwcCpg1' ),
				'type' => 'text',
				'description' => __( 'Your Secret key.', 'wcwcCpg1' ),
				'default' => __( '', 'wcwcCpg1' )
			)
        );
    }

    public function validate_fields() {

        $form = array(
            'card-number'   => isset( $_POST['ncgw1-card-number'] ) ? $_POST['ncgw1-card-number'] : '',
            'card-expiry'   => isset( $_POST['ncgw1-card-expiry'] ) ? $_POST['ncgw1-card-expiry'] : '',
            'card-cvc'      => isset( $_POST['ncgw1-card-cvc'] ) ? $_POST['ncgw1-card-cvc'] : '',
        );
        
        if ( ! $this->is_valid_luhn($form['card-number'])) {
            $field = __( 'Credit Card Number', 'beanstream-for-woocommerce' );
            wc_add_notice( $this->get_form_error_message( $field, 'invalid' ), 'error');
        }
        if ( $form['card-number'] == '' ) {
            $field = __( 'Credit Card Number', 'beanstream-for-woocommerce' );
            wc_add_notice( $this->get_form_error_message( $field, $form['card-number'] ), 'error' );
        }
        if ( $form['card-expiry'] == '' ) {
            $field = __( 'Credit Card Expiration', 'beanstream-for-woocommerce' );
            wc_add_notice( $this->get_form_error_message( $field, $form['card-expiry'] ), 'error' );
        }
        if ( $form['card-cvc'] == '' ) {
            $field = __( 'Credit Card CVC', 'beanstream-for-woocommerce' );
            wc_add_notice( $this->get_form_error_message( $field, $form['card-cvc'] ), 'error' );
        }
    }
    
    // Verify the creditcard number via the Luhn algorithm
    public function is_valid_luhn($num) {
        $num = preg_replace('/[^\d]/', '', $num);
        $sum = '';
        
        for ($i = strlen($num) - 1; $i >= 0; -- $i) {
            $sum .= $i & 1 ? $num[$i] : $num[$i] * 2;
        }
        return array_sum(str_split($sum)) % 10 === 0;
    }
    
    protected function get_form_error_message( $field, $type = 'undefined' ) {

        if ( $type === 'invalid' ) {
            return sprintf( __( 'Please enter a valid %s.', 'beanstream-for-woocommerce' ), "<strong>$field</strong>" );
        } else {
            return sprintf( __( '%s is a required field.', 'beanstream-for-woocommerce' ), "<strong>$field</strong>" );
        }
    }

    /* Process the payment and return the result. */
	function process_payment ($order_id) {
        $order = new WC_Order( $order_id );
		global $woocommerce;
        $order_amount = $order->get_total();
        $order_currency = $order->get_order_currency();
        $payment_attempt = $this->attempt_payment($order_amount, $order_currency, $_POST);
        if ($payment_attempt != false) {
            wc_add_notice( __('Payment error: ', 'woothemes') . $payment_attempt['message'], 'error' );
            return;
        } else {
            $order->payment_complete();
            $order->update_status( 'completed' );
            return array(
                'result' 	=> 'success',
                'redirect' => $this->get_return_url( $order )

            );
        }

	}

    function attempt_payment ($order_amount, $order_currency, $postData) {
        $number = str_replace(' ', '', $postData['ncgw1-card-number']);
        $date = array_map('trim', explode('/', $postData['ncgw1-card-expiry']));
        $api_key = $this->api_key;
        $secret_key = $this->secret_key;
        $postData = json_encode(array(
            'first_name' => $postData['billing_first_name'],
            'last_name' => $postData['billing_last_name'],
            'email' => $postData['billing_email'],
            'address' => $postData['billing_address_1'],
            'city' => $postData['billing_city'],
            'state' => $postData['billing_state'],
            'country' => $postData['billing_country'],
            'zip' => $postData['billing_postcode'],
            'phone' => $postData['billing_phone'],
            'ip' => $_SERVER['REMOTE_ADDR'],
            'currency' => $order_currency,
            'card' => array(
                'number' => $number,
                'expiry_month' => $date[0],
                'expiry_year' => $date[1],
                'ccv' => $postData['ncgw1-card-cvc']
            ),
            'invoice_number' => '1',
            'amount' => $order_amount
        ));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,"http://localhost:3000/api/v1/payment");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_USERPWD, $api_key . ":" . $secret_key);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec ($ch);
        curl_close ($ch);
        $json = json_decode($server_output, true);
        if ($json['status'] == 0) {
            return $json;
        }
        return false;
    }
    /* Output for the order received page.   */
	function thankyou() {
		echo $this->instructions != '' ? wpautop( $this->instructions ) : '';
	}

}
