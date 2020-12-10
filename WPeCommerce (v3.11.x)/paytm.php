<?php
/**
 * This is the Paytm Gateway.
 * It uses the wpsc_merchant class as a base class which is handy for collating user details and cart contents.
 */

 /*
  * This is the gateway variable $nzshpcrt_gateways, it is used for displaying gateway information on the wp-admin pages and also
  * for internal operations.
  */
include_once('paytm/PaytmChecksum.php');
$nzshpcrt_gateways[$num] = array(
	'name' 					=>  __( 'Paytm Payment Solutions', 'wpsc' ),
	'api_version' 			=> 2.0,
	'class_name' 			=> 'wpsc_merchant_paytm',
	'has_recurring_billing' => false,
	'wp_admin_cannot_cancel'=> true,
	'display_name' 			=> __( 'Paytm', 'wpsc' ),
	'internalname' 			=> 'wpsc_merchant_paytm',
	'form' 					=> 'form_paytm',
	'submit_function' 		=> 'submit_paytm',
	'payment_type' 			=> 'paytm'
);


function getDefaultCallbackUrl(){
	global $wpdb, $wpsc_gateways;
	return add_query_arg('gateway', 'wpsc_merchant_paytm', add_query_arg('wpsc_action', 'gateway_notification', site_url('/')));
}

class wpsc_merchant_paytm extends wpsc_merchant
{
	function submit()
	{		
		$parameters = array();
		//$paytm_transact_url = get_option('paytm_transact_url');

		// $this->purchase_id = "TEST_".strtotime("now")."_ORDERID-".$this->purchase_id; // just for testing

		if(get_option('paytm_environment')=='staging'){
        $paytmurl = 'https://securegw-stage.paytm.in/';
        $paytminiturl = $paytmurl . 'theia/api/v1/initiateTransaction?mid=';
		}else{
        $paytmurl = 'https://securegw.paytm.in/';
        $paytminiturl = $paytmurl . 'theia/api/v1/initiateTransaction?mid=';
		}
	    // payload //
		$post_variables = array(
							"MID" 				=> get_option('paytm_merchantid'),
							"ORDER_ID" 			=> $this->purchase_id.time(),
							"CUST_ID" 			=> $this->cart_data['email_address'],
							"TXN_AMOUNT" 		=> $this->cart_data["total_price"],
							//"CHANNEL_ID" 		=> get_option('paytm_channelid'),
							"INDUSTRY_TYPE_ID" 	=> get_option('paytm_industrytype'),
							"WEBSITE" 			=> get_option('paytm_website'),	
							"MERC_UNQ_REF" 		=> $this->cart_data["session_id"],
							"CALLBACK_URL" 		=> get_option('paytm_callback_url'),
						);

		$secret_key 	= get_option('paytm_merchantkey');


		$paytmParams["body"] = array(
            "requestType" => "Payment",
            "mid" => $post_variables["MID"],
            "websiteName" => $post_variables["WEBSITE"],
            "orderId" => $post_variables["ORDER_ID"],
            "callbackUrl" => $post_variables["CALLBACK_URL"],
            "txnAmount" => array(
                "value" => $post_variables["TXN_AMOUNT"],
                "currency" => "INR",
            ),
            "userInfo" => array(
                "custId" => $post_variables["CUST_ID"],
            ),
            "extendInfo" => array(
                        "mercUnqRef" => $post_variables["MERC_UNQ_REF"],
            ),
        );


		 $generateSignature = PaytmChecksum::generateSignature(json_encode($paytmParams['body'], JSON_UNESCAPED_SLASHES), $secret_key);

        $paytmParams["head"] = array(
            "signature" => $generateSignature
        );


        $url = $paytminiturl . $post_variables["MID"] . "&orderId=" . $post_variables["ORDER_ID"];
        $post_data_string = json_encode($paytmParams, JSON_UNESCAPED_SLASHES);
        $headers = array("Content-Type: application/json");

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
        $response = curl_exec($ch);
      //echo $response;
        $response_array = json_decode($response, TRUE);
        $txnToken = $response_array['body']['txnToken'];
        $post_variables['TXN_TOKEN'] = $txnToken;


        get_header();
        echo '<div id="paytm-pg-spinner" class="paytm-pg-loader"><div class="bounce1"></div><div class="bounce2"></div><div class="bounce3"></div><div class="bounce4"></div><div class="bounce5"></div></div><div class="paytm-overlay paytm-pg-loader"></div>';

		echo '<script type="application/javascript" crossorigin="anonymous" src="'.$paytmurl.'/merchantpgpui/checkoutjs/merchants/'.$post_variables['MID'].'.js"></script>
			<div style="margin: 10% 28%;" >
            <input type="button" class="button-alt" id="submit_paytm_payment_form" onClick="openJsCheckout();"  value="'.__('Pay via paytm').'" /> 
            <a class="button cancel" href="'.get_option('shopping_cart_url').'">'.__('Cancel order & restore cart').'</a>
			</div>	
			        <style type="text/css">
            #paytm-pg-spinner {margin: 0% auto 0;width: 70px;text-align: center;z-index: 999999;position: relative;display: none}

            #paytm-pg-spinner > div {width: 10px;height: 10px;background-color: #012b71;border-radius: 100%;display: inline-block;-webkit-animation: sk-bouncedelay 1.4s infinite ease-in-out both;animation: sk-bouncedelay 1.4s infinite ease-in-out both;}

            #paytm-pg-spinner .bounce1 {-webkit-animation-delay: -0.64s;animation-delay: -0.64s;}

            #paytm-pg-spinner .bounce2 {-webkit-animation-delay: -0.48s;animation-delay: -0.48s;}
            #paytm-pg-spinner .bounce3 {-webkit-animation-delay: -0.32s;animation-delay: -0.32s;}

            #paytm-pg-spinner .bounce4 {-webkit-animation-delay: -0.16s;animation-delay: -0.16s;}
            #paytm-pg-spinner .bounce4, #paytm-pg-spinner .bounce5{background-color: #48baf5;} 
            @-webkit-keyframes sk-bouncedelay {0%, 80%, 100% { -webkit-transform: scale(0) }40% { -webkit-transform: scale(1.0) }}

            @keyframes sk-bouncedelay { 0%, 80%, 100% { -webkit-transform: scale(0);transform: scale(0); } 40% { 
                                            -webkit-transform: scale(1.0); transform: scale(1.0);}}
            .paytm-overlay{width: 100%;position: fixed;top: 0px;opacity: .4;height: 100%;background: #000;display: none;z-index: 99999999;}

        </style>	

		
						<script type="text/javascript">
							 
						jQuery(".paytm-overlay").css("display","block");
						jQuery("#paytm-pg-spinner").css("display","block");

					  function openJsCheckout(){
			           var config = {
                        "root": "",
                        "flow": "DEFAULT",
                        "data": {
                            "orderId": "'.$post_variables['ORDER_ID'].'",
                            "token": "'.$post_variables['TXN_TOKEN'].'",
                            "tokenType": "TXN_TOKEN",
                            "amount": "'.$post_variables['TXN_AMOUNT'].'"
                        },
                        "merchant": {
                            "redirect": true
                        },
                        "handler": {
                            
                            "notifyMerchant": function (eventName, data) {
                                //console.log("notifyMerchant handler function called");
                                //console.log("eventName => ",eventName);
                                //console.log("data => ",data);
                                location.reload();
                            }
                        }
                    };
                    if (window.Paytm && window.Paytm.CheckoutJS) {
                        // initialze configuration using init method 
                        window.Paytm.CheckoutJS.init(config).then(function onSuccess() {
                            // after successfully updating configuration, invoke checkoutjs
                            window.Paytm.CheckoutJS.invoke();

                        jQuery(".paytm-overlay").css("display","none");
						jQuery("#paytm-pg-spinner").css("display","none");

                        }).catch(function onError(error) {
                            console.log("error => ", error);
                        });
                    }

							}

				
				setTimeout(function(){openJsCheckout()},4000);
					 
							</script>
						';
						get_footer();
						exit;
	}
	
	function parse_gateway_notification() {
		ob_start();
		global $wpdb;
		
		//echo "<pre>"; print_r($this->cart_data);print_r($_GET);print_r($this); print_r($_POST); die;
		//$transact_url = get_option('transact_url');
		$this->purchase_id 	= sanitize_text_field($_POST['ORDERID']);

		// $this->purchase_id = substr($this->purchase_id, strpos($this->purchase_id, "-") + 1); // just for testing	

		$paytmChecksum 		= "";
		$paramList 			= array();
		$isValidChecksum 	= "FALSE";
		//$transact_url 		= get_option('paytm_transact_url');
		//$accepturl = $transact_url.$separator."sessionid=".$_POST["MERC_UNQ_REF"]."&gateway=paytm";

		$paramList 			= array_map('sanitize_text_field', $_POST);
		$paytmChecksum 		= isset($_POST["CHECKSUMHASH"]) ? sanitize_text_field($_POST["CHECKSUMHASH"]) : ""; 
		
		$secret_key 		= get_option('paytm_merchantkey');
		if(get_option('paytm_environment')=='staging'){
        $PAYTM_DOMAIN_THEIA = 'https://securegw-stage.paytm.in/';
        }else{
        
        $PAYTM_DOMAIN_THEIA = 'https://securegw.paytm.in/';

        }
		
		//$isValidChecksum 	= PaytmPayment::verifychecksum_e($paramList, $secret_key, $paytmChecksum); 
		$isValidChecksum = PaytmChecksum::verifySignature($paramList, $secret_key, $paytmChecksum);





		if($isValidChecksum == "TRUE" || $isValidChecksum == "true" || $isValidChecksum == "1") 
		{			
			if (sanitize_text_field($_POST["STATUS"]) == "TXN_SUCCESS" && sanitize_text_field($_POST["RESPCODE"]) == "01") 
			{
				// Create an array having all required parameters for status query.
				$requestParamList = array("MID" => get_option('paytm_merchantid') , "ORDERID" => $this->purchase_id);

				// $requestParamList["ORDERID"] = $_POST["ORDERID"]; // just for testing

				$paytmParamsStatus = array();
                /* body parameters */
                $paytmParamsStatus["body"] = array(
                    /* Find your MID in your Paytm Dashboard at https://dashboard.paytm.com/next/apikeys */
                    "mid" => get_option('paytm_merchantid'),
                    /* Enter your order id which needs to be check status for */
                    "orderId" => $_POST['ORDERID'],
                );
                $checksumStatus = PaytmChecksum::generateSignature(json_encode($paytmParamsStatus["body"], JSON_UNESCAPED_SLASHES), $secret_key);

                /* head parameters */
                $paytmParamsStatus["head"] = array(
                    /* put generated checksum value here */
                    "signature" => $checksumStatus
                );



                 /* prepare JSON string for request */
                $post_data_status = json_encode($paytmParamsStatus, JSON_UNESCAPED_SLASHES);
                $paytstsusmurl = $PAYTM_DOMAIN_THEIA . 'v3/order/status';
                //$StatusCheckSum = getChecksumFromArray($requestParamList, $this->config->get('paytm_payments_merchant_key'));
             //   $requestParamList['CHECKSUMHASH'] = $StatusCheckSum;
                $ch = curl_init($paytstsusmurl);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data_status);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
                $responseJson = curl_exec($ch);
                $responseStatusArray = json_decode($responseJson, true);
 


				if ($responseStatusArray['body']['resultInfo']['resultStatus'] == 'TXN_SUCCESS' && $responseStatusArray['body']['txnAmount'] == sanitize_text_field($_POST["TXNAMOUNT"])) {
					//$this->set_purchase_processed_by_purchid(3);
					//echo $_POST['TXNID'];exit;
					$this->set_transaction_details(sanitize_text_field($_POST['TXNID']), 3);
					$this->go_to_transaction_results(sanitize_text_field($_POST["MERC_UNQ_REF"]));
				}
				else{
					$this->set_purchase_processed_by_purchid(6);

					$message = 'It seems some issue in server to server communication. Kindly connect with administrator.';
					redirect_checkout_page($message);
				}
			}
			else 
			{				
				$this->set_purchase_processed_by_purchid(6);

				$message = 'Oops! Your transaction get failed due to ' . sanitize_text_field($_POST["RESPMSG"]);
				redirect_checkout_page($message);
			}
		}
		else 
		{
			$this->set_purchase_processed_by_purchid(6);

			$message = 'Security Error. Illegal access detected. Checksum mismatched.';
			redirect_checkout_page($message);
		}		
	}
	
	
}

function redirect_checkout_page($message = ''){
	if(empty($message)) return ;
	$shopping_cart_url = get_option('shopping_cart_url');
	$shopping_cart_url.= (strpos($shopping_cart_url,'?')!==false) ? '&' : '?';
	$shopping_cart_url.='paytm_error='.urlencode($message);

	wp_redirect($shopping_cart_url);
	exit;
}

function paytm_error_msg($content){
	if(!empty($_GET['paytm_error'])){
		$content = '<style>.alert{ padding: 15px;margin-bottom: 20px;border: 1px solid transparent;    border-radius: 4px;} .alert-danger{    color: #a94442;background-color: #f2dede;border-color: #ebccd1;}</style><div class="paytm_error"><p class="alert alert-danger">'. $_GET['paytm_error'] .'</p></div>'.$content;
	}
    return $content;
}

add_filter( 'the_content', 'paytm_error_msg' );


function submit_paytm() {

	if(isset($_POST['paytm_merchantkey']))
		update_option('paytm_merchantkey', sanitize_text_field($_POST['paytm_merchantkey']));
		
	if(isset($_POST['paytm_merchantid']))
		update_option('paytm_merchantid', sanitize_text_field($_POST['paytm_merchantid']));
		
	if(isset($_POST['paytm_industrytype']))
		update_option('paytm_industrytype', sanitize_text_field($_POST['paytm_industrytype']));
		
	
	if(isset($_POST['paytm_website']))
		update_option('paytm_website', sanitize_text_field($_POST['paytm_website']));

	if(isset($_POST['paytm_callback']))
		update_option('paytm_callback', sanitize_text_field($_POST['paytm_callback']));
		
	if(isset($_POST['paytm_callback_url']))
		update_option('paytm_callback_url', esc_url_raw($_POST['paytm_callback_url']));

	if(isset($_POST['paytm_environment']))
		update_option('paytm_environment', sanitize_text_field($_POST['paytm_environment']));
		
	return true;
}

function form_paytm() {
	global $wpdb, $wpsc_gateways;

	$output = "

		<tr>
		  <td>" . __('Environment', 'wpsc' ) . "
		  </td>
		  <td>

		  	<select name='paytm_environment'>
		  	<option value='staging' ". (get_option('paytm_environment') == 'staging' ? "selected='selected'" : "") .">" . __('Staging', 'wpsc' ) . "</option>
		  	<option value='live' ". (get_option('paytm_environment') == 'live' ? "selected='selected'" : "") .">" . __('Live', 'wpsc' ) . "</option>

		  	</select>
		  </td>
		</tr>


		<tr>
		  <td>" . __('Merchant Key', 'wpsc' ) . "
		  </td>
		  <td>
		  <input type='text' size='' value='".get_option('paytm_merchantkey')."' name='paytm_merchantkey' />
		  </td>
		</tr>
		
		<tr>
		  <td>" . __('Merchant ID', 'wpsc' ) . "
		  </td>
		  <td>
		  <input type='text' size='' value='".get_option('paytm_merchantid')."' name='paytm_merchantid' />
		  </td>
		</tr>
		
		<tr>
		  <td>" . __('Industry Type', 'wpsc' ) . "
		  </td>
		  <td>
		  <input type='text' size='' value='".get_option('paytm_industrytype')."' name='paytm_industrytype' />
		  </td>
		</tr>
		
		 

		<tr>
		  <td>" . __('Website', 'wpsc' ) . "
		  </td>
		  <td>
		  <input type='text' size='40' value='".get_option('paytm_website')."' name='paytm_website' />
		  </td>
		</tr>		
					
		<tr>
		  <td>" . __('Enable Callback URL', 'wpsc' ) . "
		  </td>
		  <td>

		  	<select name='paytm_callback'>
		  	<option value='0' ". (intval(get_option('paytm_callback')) == 0 ? "selected='selected'" : "") .">" . __('Disable', 'wpsc' ) . "</option>
		  	<option value='1' ". (intval(get_option('paytm_callback')) == 1 ? "selected='selected'" : "") .">" . __('Enable', 'wpsc' ) . "</option>

		  	</select>
		  </td>
		</tr>

		<tr class='callback_url_tr'>
		  <td>" . __('Callback URL', 'wpsc' ) . "
		  </td>
		  <td>
		  <input type='text' size='' value='".get_option('paytm_callback_url')."' name='paytm_callback_url' />
		  </td>
		</tr>
		";

		$last_updated = "";
		$path = plugin_dir_path( __FILE__ ) . "/paytm/paytm_version.txt";
		if(file_exists($path)){
			$handle = fopen($path, "r");
			if($handle !== false){
				$date = fread($handle, 10); // i.e. DD-MM-YYYY or 25-04-2018
				$last_updated = '<p>Last Updated: '. date("d F Y", strtotime($date)) .'</p>';
			}
		}

		$output .= '<tr><td align="center" colspan="2">'.$last_updated.'<p>WP eCommerce Version: ' . WPSC_VERSION . ' . ' . WPSC_MINOR_VERSION.'</td></tr>';

		$output .= '<script>
					var default_callback_url = "'. getDefaultCallbackUrl() .'";
					function toggleCallbackUrl(){
						if(jQuery("select[name=\"paytm_callback\"]").val() == "1"){
							jQuery("input[name=\"paytm_callback_url\"]").prop("readonly", false).parents("tr").removeClass("hidden");
						} else {
							jQuery("input[name=\"paytm_callback_url\"]").val(default_callback_url).prop("readonly", true).parents("tr.callback_url_tr").addClass("hidden");
						}
					}

					jQuery(document).on("change", "select[name=\"paytm_callback\"]", function(){
						toggleCallbackUrl();
					});
					toggleCallbackUrl();

					// add border around promo code configurations to keep them separate
					jQuery("select[name=\"paytm_promo_code_status\"]").parents("tr").css("border-top", "1px solid black");
					
				</script>';

		

  	return $output;
}

/*
* Code to test Curl
*/
if(isset($_GET['paytm_action']) && $_GET['paytm_action'] == "curltest"){
	add_action('the_content', 'curltest');
}
function curltest($content){

		// phpinfo();exit;
		$debug = array();

		if(!function_exists("curl_init")){
			$debug[0]["info"][] = "cURL extension is either not available or disabled. Check phpinfo for more info.";

		// if curl is enable then see if outgoing URLs are blocked or not
		} else {

			// if any specific URL passed to test for
			if(isset($_GET["url"]) && $_GET["url"] != ""){
				$testing_urls = array(esc_url_raw($_GET["url"]));
			
			} else {

				// this site homepage URL
				$server = get_site_url();

				$testing_urls = array(
										$server,
										"https://www.gstatic.com/generate_204",
										get_option('paytm_transact_url')
									);
			}

			// loop over all URLs, maintain debug log for each response received
			foreach($testing_urls as $key=>$url){
				
				$url = esc_url_raw($url);

				$debug[$key]["info"][] = "Connecting to <b>" . $url . "</b> using cURL";
				
				$response = wp_remote_get($url);

				if ( is_array( $response ) ) {

					$http_code = wp_remote_retrieve_response_code($response);
					$debug[$key]["info"][] = "cURL executed succcessfully.";
					$debug[$key]["info"][] = "HTTP Response Code: <b>". $http_code . "</b>";

					// $debug[$key]["content"] = $res;

				} else {
					$debug[$key]["info"][] = "Connection Failed !!";
					$debug[$key]["info"][] = "Error: <b>" . $response->get_error_message() . "</b>";

					// $debug[$key]["content"] = $res;
					break;
				}
			}
		}

		$content = "<center><h1>cURL Test for Paytm - WPeCommerce</h1></center><hr/>";
		foreach($debug as $k=>$v){
			$content .= "<ul>";
			foreach($v["info"] as $info){
				$content .= "<li>".$info."</li>";
			}
			$content .= "</ul>";

			// echo "<div style='display:none;'>" . $v["content"] . "</div>";
			$content .= "<hr/>";
		}

		return $content;
	}
/*
* Code to test Curl
*/
?>
