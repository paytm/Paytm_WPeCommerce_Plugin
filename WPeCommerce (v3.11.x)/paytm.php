<?php
/**
 * This is the Paytm Gateway.
 * It uses the wpsc_merchant class as a base class which is handy for collating user details and cart contents.
 */

 /*
  * This is the gateway variable $nzshpcrt_gateways, it is used for displaying gateway information on the wp-admin pages and also
  * for internal operations.
  */
include_once('encdec_paytm.php');
$nzshpcrt_gateways[$num] = array(
	'name' =>  __( 'Paytm Payment Solutions', 'wpsc' ),
	'api_version' => 2.0,
	'class_name' => 'wpsc_merchant_paytm',
	'has_recurring_billing' => false,
	'wp_admin_cannot_cancel' => true,
	'display_name' => __( 'Paytm', 'wpsc' ),
	'internalname' => 'wpsc_merchant_paytm',
	'form' => 'form_paytm',
	'submit_function' => 'submit_paytm',
	'payment_type' => 'paytm'
);

class wpsc_merchant_paytm extends wpsc_merchant
{
	function submit()
	{		
		$parameters = array();
		$transact_url = get_option('transact_url');
		if(get_option('permalink_structure') != '')
			$separator ="?";
		else
			$separator ="&";
		//echo "<pre>"; print_r($this->cart_data); die;
		$post_variables = array(
			"MID" => get_option('paytm_merchantid'),
			"ORDER_ID" => $this->purchase_id,
			"CUST_ID" => $this->cart_data['email_address'],
			"TXN_AMOUNT" => $this->cart_data["total_price"],
			"CHANNEL_ID" => get_option('paytm_channelid'),
			"INDUSTRY_TYPE_ID" => get_option('paytm_industrytype'),
			"WEBSITE" => get_option('paytm_website'),	
			"MERC_UNQ_REF" => $this->cart_data["session_id"],
		);
		if(get_option('paytm_callback')=='1')
		{
			$post_variables['CALLBACK_URL'] = add_query_arg('gateway', 'wpsc_merchant_paytm', $this->cart_data['notification_url']);
		}
		
		$secret_key = get_option('paytm_merchantkey');
		$checksum = getChecksumFromArray($post_variables, $secret_key);
		$amt = $this->cart_data["total_price"];
		$call = add_query_arg('gateway', 'wpsc_merchant_paytm', $this->cart_data['notification_url']);
		$paytm_args_array = array();
		$paytm_args_array[] = "<input type='hidden' name='MID' value='".  get_option('paytm_merchantid') ."'/>";
		$paytm_args_array[] = "<input type='hidden' name='ORDER_ID' value='". $this->purchase_id ."'/>";
		$paytm_args_array[] = "<input type='hidden' name='WEBSITE' value='". get_option('paytm_website') ."'/>";
		$paytm_args_array[] = "<input type='hidden' name='INDUSTRY_TYPE_ID' value='". get_option('paytm_industrytype') ."'/>";
		$paytm_args_array[] = "<input type='hidden' name='CHANNEL_ID' value='". get_option('paytm_channelid') ."'/>";
		$paytm_args_array[] = "<input type='hidden' name='TXN_AMOUNT' value='". $amt ."'/>";
		$paytm_args_array[] = "<input type='hidden' name='CUST_ID' value='". $this->cart_data['email_address'] ."'/>";
		$paytm_args_array[] = "<input type='hidden' name='MERC_UNQ_REF' value='". $this->cart_data['session_id'] ."'/>";
		
		if(get_option('paytm_callback')=='1')
		{
			$paytm_args_array[] = "<input type='hidden' name='CALLBACK_URL' value='". $call ."'/>";
		}
		

		
		$paytm_args_array[] = "<input type='hidden' name='txnDate' value='". date('Y-m-d H:i:s') ."'/>";
		$paytm_args_array[] = "<input type='hidden' name='CHECKSUMHASH' value='". $checksum ."'/>";
		if(get_option('paytm_mode')=='0')
		{
			$gateway_url = 'https://pguat.paytm.com/oltp-web/processTransaction';
		}
		else
		{
			$gateway_url = 'https://secure.paytm.in/oltp-web/processTransaction';
		}
		//status_header(302);
		//wp_redirect("https://pguat.paytm.com/oltp-web/processTransaction" . implode("", array_values($paytm_args_array)));
		//exit;

		echo '<form action="'.$gateway_url.'" method="post" id="paytm_payment_form" name="f1">
						' . implode('', $paytm_args_array) . '
						<input type="submit" class="button-alt" id="submit_paytm_payment_form" value="'.__('Pay via paytm').'" /> <a class="button cancel" href="'.get_option('shopping_cart_url').'">'.__('Cancel order &amp; restore cart').'</a>
						
							<script type="text/javascript">
								document.f1.submit();
							</script>
						</form>';
	}
	
	function parse_gateway_notification() {
		global $wpdb;
		
		//echo "<pre>"; print_r($this->cart_data);print_r($_GET);print_r($this); print_r($_POST); die;
		//$transact_url = get_option('transact_url');
		$this->purchase_id = $_POST['ORDERID'];		
		$paytmChecksum = "";
		$paramList = array();
		$isValidChecksum = "FALSE";
		$transact_url = get_option('transact_url');
		//$accepturl = $transact_url.$separator."sessionid=".$_POST["MERC_UNQ_REF"]."&gateway=paytm";

		$paramList = $_POST;	
		$paytmChecksum = isset($_POST["CHECKSUMHASH"]) ? $_POST["CHECKSUMHASH"] : ""; 
		
		$secret_key = get_option('paytm_merchantkey');
		
		$isValidChecksum = verifychecksum_e($paramList, $secret_key, $paytmChecksum); 

		if($isValidChecksum == "TRUE") 
		{			
			if ($_POST["STATUS"] == "TXN_SUCCESS" && $_POST["RESPCODE"] == "01") 
			{
				// Create an array having all required parameters for status query.
				$requestParamList = array("MID" => get_option('paytm_merchantid') , "ORDERID" => $this->purchase_id);
				
				$StatusCheckSum = getChecksumFromArray($requestParamList, get_option('paytm_merchantkey'));
							
				$requestParamList['CHECKSUMHASH'] = $StatusCheckSum;
				
				// Call the PG's getTxnStatus() function for verifying the transaction status.
				if(get_option('paytm_mode')=='0')
				{
					$check_status_url = 'https://pguat.paytm.com/oltp/HANDLER_INTERNAL/getTxnStatus';
				}
				else
				{
					$check_status_url = 'https://secure.paytm.in/oltp/HANDLER_INTERNAL/getTxnStatus';
				}
				$responseParamList = callNewAPI($check_status_url, $requestParamList);				
				if($responseParamList['STATUS']=='TXN_SUCCESS' && $responseParamList['TXNAMOUNT']==$_POST["TXNAMOUNT"])
				{
					//$this->set_purchase_processed_by_purchid(3);
					$this->set_transaction_details($_POST['TXNID'], 3);
			
					//echo "OK - " . $_POST["TXNID"];
					$this->go_to_transaction_results($_POST["MERC_UNQ_REF"]);
					//exit();
				}
				else{
					echo "<b>Security Error. Illegal access detected. Checksum mismatched.</b>";	
					$this->set_purchase_processed_by_purchid(6);
					exit();
				}
			}
			else 
			{				
				echo '<p style="font-size: 14px;color:#b94a48;background: #f2dede;border-radius: 3px;padding:8px">Oops! Your transaction get failed due to ' . $_POST["RESPMSG"]. '</p>';
				$this->set_purchase_processed_by_purchid(6);
				wp_redirect($transact_url);
			}
		}
		else 
		{
			echo "<b>Security Error. Illegal access detected. Checksum mismatched.</b>";	
			$this->set_purchase_processed_by_purchid(6);
			exit();
		}		
	}
	
	
}


function submit_paytm() {
	if(isset($_POST['paytm_merchantkey']))
		update_option('paytm_merchantkey', $_POST['paytm_merchantkey']);
		
	if(isset($_POST['paytm_merchantid']))
		update_option('paytm_merchantid', $_POST['paytm_merchantid']);
		
	if(isset($_POST['paytm_industrytype']))
		update_option('paytm_industrytype', $_POST['paytm_industrytype']);
		
	if(isset($_POST['paytm_channelid']))
		update_option('paytm_channelid', $_POST['paytm_channelid']);
		
	if(isset($_POST['paytm_website']))
		update_option('paytm_website', $_POST['paytm_website']);
		
	if(isset($_POST['paytm_mode']))
		update_option('paytm_mode', $_POST['paytm_mode']);
	
	if(isset($_POST['paytm_mode']))
		update_option('paytm_callback', $_POST['paytm_callback']);
		
	return true;
}

function form_paytm() {
	global $wpdb, $wpsc_gateways;

	$output = "
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
		  <td>" . __('Channel ID', 'wpsc' ) . "
		  </td>
		  <td>
		  <input type='text' size='' value='".get_option('paytm_channelid')."' name='paytm_channelid' />
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
		  <td>" . __('Enable Live Mode', 'wpsc' ) . "
		  </td>
		  <td>
  				<input type='radio' name='paytm_mode' value='1' ". (intval(get_option('paytm_mode')) == 1 ? "checked='checked'" : "") ." /> " . __('Yes', 'wpsc' ) . "
				<input type='radio' name='paytm_mode' value='0' ". (intval(get_option('paytm_mode')) == 0 ? "checked='checked'" : "") ." /> " . __('No', 'wpsc' ) . "
		  </td>
		</tr>
		
		<tr>
		  <td>" . __('Enable Callback URL', 'wpsc' ) . "
		  </td>
		  <td>
  				<input type='radio' name='paytm_callback' value='1' ". (intval(get_option('paytm_callback')) == 1 ? "checked='checked'" : "") ." /> " . __('Yes', 'wpsc' ) . "
				<input type='radio' name='paytm_callback' value='0' ". (intval(get_option('paytm_callback')) == 0 ? "checked='checked'" : "") ." /> " . __('No', 'wpsc' ) . "
		  </td>
		</tr>
		
		
		";


  	return $output;
}
?>
