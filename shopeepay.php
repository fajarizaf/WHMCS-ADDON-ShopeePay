<?php

if(!defined("WHMCS")) {
	die("This file cannot be accessed directly");
}

require_once(dirname(__FILE__) . '/shopeepay-lib/require.php');

function shopeepay_config() 
{
	$configarray = 	array("FriendlyName" => array("Type" => "System", "Value"=>"Shopeepay"),
     				
					"key" => array(	"FriendlyName" 	=> "Merchant Key", 
										"Type" 			=> "text", 
										"Size" 			=> "20"),
     				
					"secret" 	=> array(	"FriendlyName" 	=> "Merchant Secret", 
										"Type" 			=> "text", 
										"Size" 			=> "20"),
										
					"merchantid" => array( "FriendlyName" => "Merchant Ext ID", 
											 "Type" 		=> "text", 
                                             "Rows" 		=> "20"),
                                             
                    "storeid" => array( "FriendlyName" => "Store Ext ID", 
											 "Type" 		=> "text", 
											 "Rows" 		=> "20"),
     				
					"environment"  => array( "FriendlyName" => "Enable Sandbox", 
											 "Type" 		=> "yesno", 
											 "Description" 	=> "Sandbox mode provides you with a chance to test your gateway integration with shopeepay. The payment requests will be send to the shopeepay sandbox URL. Untick to start accepting Live payments."));										 					
	return $configarray;
}


function shopeepay_link($params) 
{
	global $CONFIG;
	$module = new modFunctions();

	$environment	= $params['environment'];
	$merchantid		= $params['merchantid'];
	$storeid		= $params['storeid'];
	$invoiceid		= $params['invoiceid'];
	$amount			= $module->amountFormat($params['amount']);
	$ref			= $params['description'];

	$referenceid	= uniqid("ref").'-#'.$invoiceid;
	$requestid		= strval(uniqid("req"));

	$data = array(
		"request_id" => $requestid,
		"payment_reference_id" => $referenceid,
		"merchant_ext_id"=> $merchantid,
		"store_ext_id"=> $storeid,
		"amount"=>  $amount,
		"additional_info" => $ref,
		"qr_validity_period" => 300,
		"currency"=> "IDR",
	);

	$body = json_encode($data);

	try{

		$api = new apiRequestor();
		$api->set_environtment($environment);
		$api->set_clientid($params['key']);
		$api->set_secret($params['secret']);
		$api->set_merchantid($merchantid);
		$api->set_storeid($storeid);

		$invoiceStatus = $module->cekInvoice($invoiceid);

		if($invoiceStatus == 'Unpaid') {	

			$cekQRCode = $module->cekQRCode($invoiceid);
			if($cekQRCode == null) {
				$request = $api->createQRCode($body,$invoiceid);
			} else {
				// for case customer pays later
				$request = $api->updateQRCode($body,$invoiceid);
			}

			$interface .= '

				<br><center>
				<form method="post" onsubmit="return false" name="ePayment" action="#">
					<input type="hidden" id="invoiceid" name="invoiceid" value="'.$invoiceid.'" />
					<input type="hidden" id="baseurl" name="baseurl" value="'.$CONFIG['SystemURL'].'" />
					<a href="#barcodebox" id="scanbarcode" class="btn btn-primary">Bayar Sekarang</a>
				</form>
				<center>

				<link href="'.$CONFIG['SystemURL'].'/modules/gateways/shopeepay-lib/css/popup.css" rel="stylesheet">
				
				<div id="barcodebox" style="display:none">
					<div class="popup_header">
					<img src="'.$CONFIG['SystemURL'].'/modules/gateways/shopeepay-lib/img/shopeepay-logo.png" style="width:220px;height:50px" />
					<div id="countdown" style="float:right;margin-top:15px;font-weight:bold"></div>
					<div style="clear:both"></div>
					</div>';

			if($request['errcode'] == 0) {

				$interface .= '
						<div class="barcode">
							<img src="'.$CONFIG['SystemURL'].'/shopeepay_qrcode/'.$request['qrcode'].'" style="width:400px;height:400px" />
						</div>
						<div class="boxloading" style="display:none">
							<img src="'.$CONFIG['SystemURL'].'/modules/gateways/shopeepay-lib/img/loading.gif" style="width:400px;height:300px" />
							<p style="width:100%;text-align: center;margin-top:-90px">Pembayaran sedang diproses...</p>
							<p style="width:100%;text-align: center;margin-top:-10px;">Mohon tunggu</p>
						</div>
						<div class="boxsuccess" style="display:none">
							<img src="'.$CONFIG['SystemURL'].'/modules/gateways/shopeepay-lib/img/success.png" style="width:400px;height:300px" />
						</div>
						<div class="boxtimeout" style="display:none">
							<img src="'.$CONFIG['SystemURL'].'/modules/gateways/shopeepay-lib/img/timeout.png" style="width:400px;height:300px" />
							<p style="width:100%;text-align:center;margin-top:-35px;">
							<a href="'.$CONFIG['SystemURL'].'/viewinvoice.php?id='.$invoiceid.'">Terbitkan Ulang QR</a>
							</p>
						</div>
						<div class="boxfailed" style="display:none">
							<img src="'.$CONFIG['SystemURL'].'/modules/gateways/shopeepay-lib/img/failed.png" style="width:400px;height:300px" />
							<p style="width:100%;text-align:center;margin-top:-35px;">
							<a href="'.$CONFIG['SystemURL'].'/viewinvoice.php?id='.$invoiceid.'">Terbitkan Ulang QR</a>
							</p>
						</div>
					
				';

			} else {
				$interface .= '<div>'.$request['debug_msg'].'</div>';
			}

			$interface .= '</div>';
			
			if($request['qrcode']) {

				$link = actual();
				if($link == 'viewinvoice') {
					$interface .= '
					<script src="'.$CONFIG['SystemURL'].'/modules/gateways/shopeepay-lib/js/jquery.1.8.min.js"></script>';
				}

				$interface .= '
				<script src="'.$CONFIG['SystemURL'].'/modules/gateways/shopeepay-lib/js/jquery.popup.js"></script>
				<script src="'.$CONFIG['SystemURL'].'/modules/gateways/shopeepay-lib/js/script.js"></script>
				<script async>
					$(document).ready(function(){
						$("#scanbarcode").popup();
						$("#scanbarcode").click();
					});	
				</script>
				';
			}

			return $interface;

		}

	}catch(Exception $e){
		$e->getMessage();
	}

}

function shopeepay_refund($params)
{
    global $CONFIG;
	$module = new modFunctions();

	$environment	= $params['environment'];
	$merchantid		= $params['merchantid'];
	$storeid		= $params['storeid'];
	$invoiceid		= $params['invoiceid'];

	try{

		$api = new apiRequestor();
		$api->set_environtment($environment);
		$api->set_clientid($params['key']);
		$api->set_secret($params['secret']);
		$api->set_merchantid($merchantid);
		$api->set_storeid($storeid);

		$referenceid = $module->getReferenceId($invoiceid);
		$requestid   = $module->getRequestId($invoiceid);
		
		$refund = $api->refund($requestid,$referenceid);

		if($refund['errcode'] == 0) {
			$result = array(
				'status' => 'success',
				'rawdata' => $refund,
				'transid' => $refund['transaction_list']['reference_id'],
				'fees' => $refund['transaction_list']['amount'],
			);
		} else {
			$result = array(
				'status' => 'failed',
				'rawdata' => $refund,
				'transid' => $refund['transaction_list']['reference_id'],
				'fees' => $refund['transaction_list']['amount'],
			);
		}
		return $result;

	}catch(Exception $e){
		$e->getMessage();
	}

}

function actual() {
	$link = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
	$params = pathinfo($link);
	return $params['filename'];
}




?>