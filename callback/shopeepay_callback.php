<?php

include("../../../init.php");
include("../../../includes/functions.php");
include("../../../includes/gatewayfunctions.php");
include("../../../includes/invoicefunctions.php");


require_once('../shopeepay-lib/Shopeepay/modFunctions.php');
require_once('../shopeepay-lib/Shopeepay/apiRequestor.php');
$module = new modFunctions();
$api    = new apiRequestor();

// ** cek payment status after customer scan barcode

if($_REQUEST['invoiceid']) {

    $referenceid = $module->getReferenceId($_REQUEST['invoiceid']);
    $requestid   = $module->getRequestId($_REQUEST['invoiceid']);
    $config      = $module->gatewayConfig('shopeepay');
    
    $api->set_environtment($config['environment']);
	$api->set_clientid($config['key']);
	$api->set_secret($config['secret']);
	$api->set_merchantid($config['merchantid']);
    $api->set_storeid($config['storeid']);
    
    // request cek payment status
    $result = $api->cekPaymentStatus($requestid,$referenceid);

    // invalid cek status
    
    if($result['payment_status'] == '1') {
        $request = $module->rawRequest($_REQUEST['invoiceid']);
        if($result['transaction_list']['0']['amount'] == $request['amount'] && $result['transaction_list']['0']['store_ext_id'] == $request['store_ext_id'] && $result['transaction_list']['0']['merchant_ext_id'] == $request['merchant_ext_id'] && $result['transaction_list']['0']['reference_id'] == $request['payment_reference_id']) {
            $paystatus = $module->cekInvoice($_REQUEST['invoiceid']);
            if($paystatus == 'Unpaid') {

                $gatewaymodule = $module->getPayMethod($_REQUEST['invoiceid']);
                $GATEWAY = getGatewayVariables($gatewaymodule);
                $invoiceid = checkCbInvoiceID($_REQUEST['invoiceid'],$GATEWAY["name"]);

                //set success payment
                $bill_amount = $module->getInvoiceAmount($_REQUEST['invoiceid']);
                addInvoicePayment($_REQUEST['invoiceid'],$request['payment_reference_id'],$bill_amount,0,$gatewaymodule);
                logTransaction($GATEWAY["name"], print_r($result, true),"Payment Successfull"); 
            }
        } else {
            logTransaction($GATEWAY["name"], print_r($result, true),"Invalid Payment Check");
        }
    }
    
    echo $result['payment_status'];
    
} else {

    // ** notify transaction handler

    $response   = json_decode(file_get_contents('php://input'), true);
    $header     = getallheaders();

    $invoiceid  = $module->getInvoice($response['payment_reference_id']);

    // payment reference match
    if($invoiceid != null) {
        $request    = $module->rawRequest($invoiceid);

        $config = $module->gatewayConfig('shopeepay');
        $api->set_secret($config['secret']);
        $shopeeSignature = $header['X-Airpay-Req-H'];
        $mwnSignature = $api->createSignature(json_encode($response));

        $gatewaymodule = $module->getPayMethod($invoiceid);
        $GATEWAY = getGatewayVariables($gatewaymodule);

        if(!$GATEWAY['type']) die('Module Not Activated');

        // signature match
        if($shopeeSignature == $mwnSignature) {

                $bill_amount = $module->getInvoiceAmount($invoiceid);

                // amount match
                if($request['amount'] == $response['amount']) {

                    $success = false;
                    // payment successfull
                    if($response['payment_status'] == 1) {
                        checkCbInvoiceID($invoiceid,$GATEWAY["name"]);
                        $success = true;
                    }
                    // payment not found
                    else if($response['payment_status'] == 2) {
                        logTransaction($GATEWAY["name"], $response,"Payment Not Found");
                        $success = false; 
                        echo "Payment Not Found";  
                    }
                    // payment refunded
                    else if($response['payment_status'] == 3) {
                        logTransaction($GATEWAY["name"], $response,"Payment Refunded");
                        $success = false;
                        echo "Payment Refunded";
                    }
                    // payment void
                    else if($response['payment_status'] == 4) {
                        logTransaction($GATEWAY["name"], $response,"Payment Void");
                        $success = false;
                        echo "Payment Void";
                    }
                    // payment processing
                    else if($response['payment_status'] == 5) {
                        logTransaction($GATEWAY["name"], $response,"Payment Processing");
                        $success = false;
                    }
                    // payment failed
                    else if($response['payment_status'] == 6) {

                        $orderid       = $module->getorderid($invoiceid);
                        $cancelhosting = $module->cancelhosting($orderid);
                        $canceldomain  = $module->canceldomain($orderid);
                        $cancelorder   = $module->cancelorder($invoiceid);
                        $cancelnotes   = $module->cancelnotes($invoiceid, $response['debug_msg']);

                        logTransaction($GATEWAY["name"], $response,"Payment Failed");
                        $success = false;
                    }

                    if($success) {
                        $paystatus = $module->cekInvoice($invoiceid);
                        if($paystatus == 'Unpaid') {
                            addInvoicePayment($invoiceid,$response['payment_reference_id'],$bill_amount,0,$gatewaymodule);
                        }
                        logTransaction($GATEWAY["name"], $response,"Payment Successfull");
                        echo "Payment Successfull";
                    }

                } else {
                    // not valid amount
                    logTransaction($GATEWAY["name"], $response,"Invalid Amount");
                    die("Invalid Amount");
                }

        } else {
            //invalid signature
            logTransaction($GATEWAY["name"], $response,"Invalid Signature");
            die("Invalid Signature");
        }

    } else {
        // reference id not valid
        logTransaction($GATEWAY["name"], $response,"Invalid Referenceid");
        die("Invalid Referenceid");
    }

}

?>