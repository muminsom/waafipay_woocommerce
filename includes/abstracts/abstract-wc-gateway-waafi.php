<?php
/**
 * Waafi Payment gateway .
 */

//directory access forbidden
if (!defined('ABSPATH')) {
    exit;
}

class WC_Waafi_Payment_Gateway extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->has_fields             = false;  // No additional fields in checkout page
        $this->method_title           = 'WaafiPay';
        $this->method_description     = 'WaafiPay Payment Gateway';
		if(empty(wc_gateway_waafipay()->settings->__get('btn_text'))){
			$this->order_button_text      = 'Proceed with WaafiPay';
		}else{
			$this->order_button_text      = wc_gateway_waafipay()->settings->__get('btn_text');
		}
        $this->supports = array(
            'products'
        );
    
        // Load the settings.
        $this->init_form_fields();
        
        // Configure page fields
        $this->init_settings();
		
	$this->testmode             =  'yes' === wc_gateway_waafipay()->settings->__get('testmode');
        $this->enabled              = wc_gateway_waafipay()->settings->__get('enabled');
        $this->title                = wc_gateway_waafipay()->settings->__get('title');
        $this->description          = wc_gateway_waafipay()->settings->__get('description');
        $this->waafi_payment_types   = wc_gateway_waafipay()->settings->__get('waafi_payment_types');
				
		$this->apiurl = $this->testmode ? 'https://sandbox.waafipay.net/asm' : 'https://api.waafipay.net/asm';
		
		$this->store_id = $this->testmode ? wc_gateway_waafipay()->settings->__get( 'test_waafi_store_id' ) : wc_gateway_waafipay()->settings->__get( 'waafi_store_id' );
		
		$this->publishable_key = $this->testmode ? wc_gateway_waafipay()->settings->__get( 'test_waafi_publishable_key' ) : wc_gateway_waafipay()->settings->__get( 'waafi_publishable_key' );
		
		$this->merchant_id = $this->testmode ? wc_gateway_waafipay()->settings->__get( 'test_waafi_merchant_id' ) : wc_gateway_waafipay()->settings->__get( 'waafi_merchant_id' );
		
        
        //$this->default_order_status = wc_gateway_waafipay()->settings->__get('default_order_status');
        
        //actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options'));
        
		add_action( 'woocommerce_api_waafisuccess', array( $this, 'waafisuccess' ) );
		add_action( 'woocommerce_api_waafifail', array( $this, 'waafifail' ) );
		add_action('wp_footer', array( $this,'addscript_function'));
        
    }


    /*
    * show gateway settings in woocommerce checkout settings
    */
    public function admin_options()
    {
        if (wc_gateway_waafipay()->admin->is_valid_for_use()) {
            $this->show_admin_options();
            return true;
        }
        
			wc_gateway_waafipay()->settings->__set('enabled', 'no');
			wc_gateway_waafipay()->settings->save();
        ?>
        <div class="inline error"><p><strong><?php 'Gateway disabled'; ?></strong>: <?php 'WaafiPay Payment Gateway does not support your store currency.'; ?></p></div>
        <?php
    }
    
    public function show_admin_options()
    {
        $plugin_data = get_plugin_data(ABSPATH. 'wp-content/plugins/wc_waafipay/wc_waafipay.php');
        $plugin_version = $plugin_data['Version'];
 
    ?>
        <h3><?php 'HPP WAAFIPAY'; ?><span><?php echo 'Version '.$plugin_version; ?></span> </h3>
        <div id="wc_get_started">
            <span class="main"><?php 'HPP Hosted Payment Page'; ?></span>
            <span><br><b>NOTE: </b> You must enter your store ID , publishable key and merchant key</span>
        </div>

        <table class="form-table">
        <?php $this->generate_settings_html(); ?>
        </table>
        <?php
    }
    
    
    /**
    * Process the payment and return the result.
    *
    * @access public
    * @param (int)order id
    * @return array
    */
    public function process_payment($order_id)
    {
        return wc_gateway_waafipay()->checkout->process_payment($order_id);
    }
	
	public function payment_fields() {
		 global $woocommerce;
		 if ( $this->description ) {
				echo '<p>'.$this->description.'</p>';
			}	
			echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
			
			$inputhtml = "";
			
			foreach($this->waafi_payment_types as $waafi_payment_types){
				
				if($waafi_payment_types == "CREDIT_CARD"){
					$txt_waafi = "Credit Card";
				}elseif($waafi_payment_types == "MWALLET_ACCOUNT"){
					$txt_waafi = "Mobile Account";
				}elseif($waafi_payment_types == "MWALLET_BANKACCOUNT"){
					$txt_waafi = "Bank Account";
				}
				$inputhtml .=  '<div class="form-row form-row-wide">
									<input id="waafi_pay_from_macc" name="waafi_pay_from" value="'.$waafi_payment_types.'" class="input-radio"  type="radio" />
									<span style="font-size: 16px;margin-left: 12px;">'.$txt_waafi.'</span>
								</div>';
			}

			// Add this action hook if you want your custom payment gateway to support it
			do_action( 'woocommerce_credit_card_form_start', $this->id );
			echo $inputhtml;
			do_action( 'woocommerce_credit_card_form_end', $this->id );
			echo '<div class="clear"></div></fieldset>';
	}

    /**
     * initialize Gateway Settings Form Fields.
     */
    public function init_form_fields()
    {
        $this->form_fields = wc_gateway_waafipay()->admin->init_form_fields();
    }
	
	public function addscript_function(){
		if ( is_checkout() ) {
			if ( isset( $_REQUEST['cancelled'] ) ){
				wc_add_notice( __( 'Payment Transaction Failed. Please try again.', 'woothemes' ), 'error' );
			}
		}
	}
	
	public function waafifail() {
			global $woocommerce;
			$storeId = $this->store_id;
			$hppKey = $this->publishable_key;
			$merchantUid = $this->merchant_id;
			$explodedid = explode("?",$_REQUEST['id']);
			$order_id = $explodedid[0];
			$explodedresponse = explode("=",$explodedid[1]);
			if($explodedresponse[0] == "hppResultToken"){
				$hppResultToken = $explodedresponse[1];
			}
			
			if(!empty($hppResultToken)){
				$timestamp = get_post_meta($order_id,'wc_waafipay_timestamp',true);
				$requestId = get_post_meta($order_id,'wc_waafipay_requestId',true);
				
				$curl = curl_init();

				curl_setopt_array($curl, array(
				  CURLOPT_URL => $this->apiurl,
				  CURLOPT_RETURNTRANSFER => true,
				  CURLOPT_ENCODING => '',
				  CURLOPT_MAXREDIRS => 10,
				  CURLOPT_TIMEOUT => 0,
				  CURLOPT_FOLLOWLOCATION => true,
				  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				  CURLOPT_CUSTOMREQUEST => 'POST',
				  CURLOPT_POSTFIELDS =>'{
					"schemaVersion"     : "1.0",            
					"requestId"         : "'.$requestId.'",
					"timestamp"         : "'.$timestamp.'",
					"channelName"       : "WEB",
					"serviceName"       : "HPP_GETRESULTINFO",
					"serviceParams"     : {
						"storeId"       : "'.$storeId.'",
						"hppKey"        : "'.$hppKey.'",
						"merchantUid"   : "'.$merchantUid.'", 
						"hppResultToken": "'.$hppResultToken.'"
					}
				}   ',
				  CURLOPT_HTTPHEADER => array(
					'Content-Type: application/json'
				  ),
				));

				$response = curl_exec($curl);

				curl_close($curl);
				
				$responsarr = json_decode($response,TRUE);
				
				if($responsarr['responseCode'] == 2001){
					
					wp_redirect( add_query_arg( 'cancelled', 'true', wc_get_checkout_url() ) );
					exit;
				}
				
				
			}

			
		}
		
		public function waafisuccess() {
			global $woocommerce;
			$storeId = $this->store_id;
			$hppKey = $this->publishable_key;
			$merchantUid = $this->merchant_id;
			$explodedid = explode("?",$_REQUEST['id']);
			$order_id = $explodedid[0];
			$explodedresponse = explode("=",$explodedid[1]);
			if($explodedresponse[0] == "hppResultToken"){
				$hppResultToken = $explodedresponse[1];
			}
			
			if(!empty($hppResultToken)){
				$timestamp = get_post_meta($order_id,'wc_waafipay_timestamp',true);
				$requestId = get_post_meta($order_id,'wc_waafipay_requestId',true);
				$curl = curl_init();

				curl_setopt_array($curl, array(
				  CURLOPT_URL => $this->apiurl,
				  CURLOPT_RETURNTRANSFER => true,
				  CURLOPT_ENCODING => '',
				  CURLOPT_MAXREDIRS => 10,
				  CURLOPT_TIMEOUT => 0,
				  CURLOPT_FOLLOWLOCATION => true,
				  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				  CURLOPT_CUSTOMREQUEST => 'POST',
				  CURLOPT_POSTFIELDS =>'{
					"schemaVersion"     : "1.0",            
					"requestId"         : "'.$requestId.'",
					"timestamp"         : "'.$timestamp.'",
					"channelName"       : "WEB",
					"serviceName"       : "HPP_GETRESULTINFO",
					"serviceParams"     : {
						"storeId"       : "'.$storeId.'",
						"hppKey"        : "'.$hppKey.'",
						"merchantUid"   : "'.$merchantUid.'", 
						"hppResultToken": "'.$hppResultToken.'"
					}
				}   ',
				  CURLOPT_HTTPHEADER => array(
					'Content-Type: application/json'
				  ),
				));

				$response = curl_exec($curl);

				curl_close($curl);
				
				$responsarr = json_decode($response,TRUE);
				
				if($responsarr['responseCode'] == 2001){
					$payment_method_title = get_post_meta($order_id,'_payment_method_title',true);
					$waafi_pay_from = get_post_meta($order_id,'waafi_pay_from',true);
					if($waafi_pay_from == "CREDIT_CARD"){
						$order_paymnttyp_name = $payment_method_title." ( Credit Card )";
					}elseif($waafi_pay_from == "MWALLET_ACCOUNT"){
						$order_paymnttyp_name = $payment_method_title." ( MWALLET ACCOUNT )";
					}elseif($waafi_pay_from == "MWALLET_BANKACCOUNT"){
						$order_paymnttyp_name = $payment_method_title." ( MWALLET BANKACCOUNT )";
					}
					
					update_post_meta($order_id, 'hppResultToken', $hppResultToken);	
					update_post_meta($order_id, 'procCode', $responsarr['params']['procCode']);	
					update_post_meta($order_id, 'procDescription', $responsarr['params']['procDescription']);	
					update_post_meta($order_id, 'transactionId', $responsarr['params']['transactionId']);	
					update_post_meta($order_id, 'issuerTransactionId', $responsarr['params']['issuerTransactionId']);	
					update_post_meta($order_id, 'txAmount', $responsarr['params']['txAmount']);	
					update_post_meta($order_id, 'state', $responsarr['params']['state']);	
					
					
					
					$order = wc_get_order( $order_id );
					$order->payment_complete();
					$order->reduce_order_stock();
					
					// some notes to customer (replace true with false to make it private)
					$order->add_order_note( 'Hey, your order is paid! Thank you!', true );

					// Empty cart
					$woocommerce->cart->empty_cart();
					delete_post_meta($order_id,'hppwaafiretrnurl');
					update_post_meta($order_id, '_payment_method_title', $order_paymnttyp_name);
					$redirecturl = $this->get_return_url( $order );
					wp_redirect( $redirecturl );
					exit;
				}
				
				
			}
			
			
		}
}
