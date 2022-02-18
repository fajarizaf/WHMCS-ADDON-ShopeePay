<?php

require_once('modFunctions.php');

class apiRequestor {

    public $clientid;
    public $secret;
    public $merchantid;
    public $storeid;
    public $environment;

    public function set_environtment($status) {
        if($status == "on")
        { 
            $this->environtment = "https://api.uat.wallet.airpay.co.id";
        }
        else
        {
            $this->environtment = "https://api.wallet.airpay.co.id";
        }
    }

    public function set_clientid($params) {
        $this->clientid = $params;
    }

    public function set_secret($params) {
        $this->secret = $params;
    }

    public function set_merchantid($params) {
        $this->merchantid = $params;
    }

    public function set_storeid($params) {
        $this->storeid = $params;
    }

    public function createQRCode($post,$invoiceid) 
    {
        $result     =
        $params     = '/v3/merchant-host/qr/create';
        $signature  = $this->createSignature($post);
        $services   = $this->services($params,$signature,$post);
        
        modFunctions::apilogs($params,$signature,$post,json_encode($services));

        $obj = json_decode($post);

        // no error create
		if($services['errcode'] == 0) {
           $save =  modFunctions::saveQRCode($invoiceid,$signature,$obj->payment_reference_id,$obj->request_id);
            if($save['errcode'] == 0) {
                $download = modFunctions::downloadQRCode($services['qr_url'],$obj->request_id);
                if($download['errcode'] == 0) {
                    $result = array(
                        'errcode' => 0,
                        'invoiceid' => $invoiceid,
                        'amount' => $obj->amount,
                        'qrcode' => $obj->request_id.'png'
                    );
                } else {
                    $result = array ('errcode' => 1, 'messages' => $download['debug_msg']);
                }
            } else {
                $result = array ('errcode' => 1, 'messages' => $save['debug_msg']);
            }
        } else {
            $result = array ('errcode' => 1, 'messages' => $services['debug_msg']);
        }

        return $result;   
    }

    public function updateQRCode($post,$invoiceid) 
    {
        $params     = '/v3/merchant-host/qr/create';
        $signature  = $this->createSignature($post);
        $services   = $this->services($params,$signature,$post);

        modFunctions::apilogs($params,$signature,$post,json_encode($services));

        $obj = json_decode($post);

        // no error create
		if($services['errcode'] == 0) {
            $update = modFunctions::updateQRCode($invoiceid,$signature,$obj->payment_reference_id,$obj->request_id);
            if($update['errcode'] == 0) {
                $download = modFunctions::downloadQRCode($services['qr_url'],$obj->request_id);
                if($download['errcode'] == 0) {
                    $result = array(
                        'errcode' => 0,
                        'invoiceid' => $invoiceid,
                        'amount' => $obj->amount,
                        'qrcode' => $obj->request_id.'.png'
                    );
                }  else {
                    $result = array ('errcode' => 1, 'messages' => $download['debug_msg']);
                }
            } else {
                $result = array ('errcode' => 1, 'messages' => $update['debug_msg']);
            } 
        } else {
            $result = array ('errcode' => 1, 'messages' => $services['debug_msg']);
        }

        return $result;   
    }

    function cekPaymentStatus($requestid,$referenceid) {
        $params = '/v3/merchant-host/transaction/payment/check';

        $post = array(
            "request_id" => $requestid,
            "payment_reference_id" => $referenceid,
            "merchant_ext_id"=> $this->merchantid,
            "store_ext_id"=> $this->storeid,
        );

        $post = json_encode($post);

        $signature  = $this->createSignature($post);
        $services   = $this->services($params,$signature,$post);
        
        if($services['payment_status'] == '1') {
            modFunctions::apilogs($params,$signature,$post,json_encode($services));
        }

        return $services;
    }

    function refund($requestid,$referenceid) {
        $params = '/v3/merchant-host/transaction/refund/create';

        $post = array(
            "request_id" => $requestid,
            "payment_reference_id" => $referenceid,
            "refund_reference_id" => strval(uniqid("refund")),
            "merchant_ext_id"=> $this->merchantid,
            "store_ext_id"=> $this->storeid,
        );

        $post = json_encode($post);

        $signature  = $this->createSignature($post);
        $services   = $this->services($params,$signature,$post);

        modFunctions::apilogs($params,$signature,$post,json_encode($services));

        return $services;
    }

    function createSignature($source)
    {
        return base64_encode(hash_hmac('sha256', $source, $this->secret, true));
    }

    function services($params,$signature,$post) 
    {

        $environment   = $this->environtment;
        $url           = $environment.$params;
        $headers = array (
            'X-Airpay-ClientId:'.$this->clientid,
            'X-Airpay-Req-H:'.$signature
            );
        
        $c = curl_init ();
        curl_setopt ($c, CURLOPT_URL, $url);
        curl_setopt ($c, CURLOPT_POST, true);
        curl_setopt ($c, CURLOPT_POSTFIELDS, $post);
        curl_setopt ($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt ($c, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt ($c, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt ($c, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($c);
        $res = json_decode($result, true);

        return $res;
        
    }

    
}

    

?>