<?php
/**
* Checkout handler class.
*/

//directory access forbidden
if (!defined('ABSPATH')) {
    exit;
}
$includes_path = wc_gateway_waafipay()->includes_path;
require_once($includes_path. 'abstracts/abstract-wc-gateway-waafi.php');

class WC_Gateway_Waafi_Checkout_Handler
{
    public function __construct()
    {
        $this->waafi_payment_gateway = new WC_Waafi_Payment_Gateway();
        $this->payment_mode = wc_gateway_waafipay()->settings->__get('payment_mode');
        $this->payment_mode_woocomm = wc_gateway_waafipay()->settings->__get('payment_mode');
    }
    
    /*
    * Process payment for checkout
    *
    * @param order id (int)
    * @access public
    * @return array
    */
    public function process_payment($order_id)
    {
			global $woocommerce;

			if(empty($_POST['waafi_pay_from'])){
				$pay_from = "CREDIT_CARD";
			}else{
				$pay_from = $_POST['waafi_pay_from'];
			}
			$order = wc_get_order( $order_id );
			$order_amount = $order->get_total();
			$currency_code = $order->get_currency();
			$requestId = $this->generateRandomString();
			$orderdata = get_post($order_id);
			$post_password = $orderdata->post_password;
			
			$timestamp = time();
			$orderid = $order_id;
			
			
			$storeId = $this->waafi_payment_gateway->store_id;
			$hppKey = $this->waafi_payment_gateway->publishable_key;
			$merchantUid = $this->waafi_payment_gateway->merchant_id;

			$referenceId = $orderid;
			// $invoiceId = $timestamp.$order_id;
			$invoiceId = $post_password;

			update_post_meta($order_id, 'wc_waafipay_requestId', $requestId);	
			update_post_meta($order_id, 'wc_waafipay_referenceid', $referenceId);	
			update_post_meta($order_id, 'wc_waafipay_invoice', $invoiceId);	
			update_post_meta($order_id, 'wc_waafipay_timestamp', $timestamp);	

			$cust_redirecturlsuc = get_site_url().'/wc-api/waafisuccess/?id='.$order_id;
			$cust_redirecturlfail = get_site_url().'/wc-api/waafifail/?id='.$order_id;
			
			$apiurl = $this->waafi_payment_gateway->apiurl;

            $curl = curl_init();

				curl_setopt_array($curl, array(
				  CURLOPT_URL => $apiurl,
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
					"serviceName"       : "HPP_PURCHASE",
					"serviceParams": {
							"storeId"               : "'.$storeId.'",
							"hppKey"                : "'.$hppKey.'",  
							"merchantUid"           : "'.$merchantUid.'",
							"hppSuccessCallbackUrl" : "'.$cust_redirecturlsuc.'",
							"hppFailureCallbackUrl" : "'.$cust_redirecturlfail.'",
							"hppRespDataFormat"     : "4",  
							"paymentMethod"         : "'.$pay_from.'",
							"transactionInfo"       : {
									"referenceId"   : "'.$referenceId.'",
									"invoiceId"     : "'.$invoiceId.'",
									"amount"        : "'.$order_amount.'",
									"currency"      : "'.$currency_code.'",
									"description"   : "Woocommerce Order Payment"
							}
					}
				}',
				  CURLOPT_HTTPHEADER => array(
					'Content-Type: application/json'
				  ),
				));

				$response = curl_exec($curl);
				$err     = curl_errno( $curl );
				$errmsg  = curl_error( $curl );
				
				if( !is_wp_error( $response ) || !empty($response) ) {
					$arrresp = json_decode($response,TRUE);
					if($arrresp['errorCode'] == 0 && $arrresp['responseMsg'] == "RCS_SUCCESS"){
						$returnurl = $arrresp['params']['hppUrl'];
						$hppRequestId = $arrresp['params']['hppRequestId'];
						$referenceId = $arrresp['params']['referenceId'];
						update_post_meta($order_id, 'waafi_pay_from', $pay_from);	
						update_post_meta($order_id, 'wc_waafipay_referenceid', $referenceId);	
						update_post_meta($order_id, 'wc_waafipay_requestId', $hppRequestId);	
						update_post_meta($order_id, 'hppwaafiretrnurl', $returnurl);	
						$redirect_url = plugin_dir_url(__FILE__).'redirect.php?suc=OK&rurl='.$returnurl.'&hrid='.$hppRequestId.'&rfid='.$referenceId;	
						// Redirect to the thank you page
						return array(
							'result' => 'success',
							// 'redirect' =>  $this->get_return_url( $order )
							'redirect' =>  $redirect_url
						);	
					
					}else{								
							wc_add_notice(  $errmsg, 'error' );
							return;
					 }
					
				}else{
							
						wc_add_notice(  $errmsg, 'error' );
						return;
				 }	
    }
	
	public function generateRandomString($length = 15) {
		$characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$charactersLength = strlen($characters);
		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}
		return $randomString;
	}
            
}
