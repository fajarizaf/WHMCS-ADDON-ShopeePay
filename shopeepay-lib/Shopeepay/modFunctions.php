<?php

use WHMCS\Database\Capsule as DB;

class modFunctions {
    public function cekQRCode($invoiceid) 
    {
        $get = DB::table('shopeepay_qrcode')->where([
            'invoiceid' => $invoiceid,
        ])->first();
        $qrcode = @$get->qrcode;
        return $qrcode;
    }

    public function saveQRCode($invoiceid,$signature,$reference_id,$request_id)
    { 
        try {
            DB::table('shopeepay_qrcode')->insert([
                'invoiceid' => $invoiceid,
                'signature' => $signature,
                'reference_id' => $reference_id,
                'request_id' => $request_id,
                'qrcode' => $request_id.'.png',
            ]);
            return array('errcode' => 0);
        } catch (\Exception $e) {
            return array('errcode' => 1, 'debug_msg' => $e->getMessage());
        }
    }

    public function updateQRCode($invoiceid,$signature,$reference_id,$request_id)
    {
        try {
            DB::table('shopeepay_qrcode')
                ->where('invoiceid', $invoiceid)
                ->update(
                [
                    'signature' => $signature,
                    'reference_id' => $reference_id,
                    'request_id' => $request_id,
                    'qrcode' => $request_id.'.png',
                ]
            );
            return array('errcode' => 0);
        } catch (\Exception $e) {
            $e->getMessage();
        }
    }

    function downloadQRCode($qrcode,$requestid)
    { 
        
        try {
            // stored folder data qrcode images
            $folder = $_SERVER['DOCUMENT_ROOT'].'/shopeepay_qrcode';
            $filename = $folder.'/'.$requestid.'.png';

            if (!file_exists($folder)) {
                mkdir($folder, 0777, true);
            }

            file_put_contents($filename, file_get_contents($qrcode));
            return array('errcode' => 0);
        } catch (\Exception $e) {
            $e->getMessage();
        }

    }

    public function gatewayConfig($gatewayname) 
    {
        $config = DB::table('tblpaymentgateways')->where([
            'gateway' => $gatewayname,
        ])->get();
        
        $data = Array();
        foreach($config as $conf) {
            if($conf->setting == 'key') {
                $data['key'] = $conf->value;
            }
            if($conf->setting == 'secret') {
                $data['secret'] = $conf->value;
            }
            if($conf->setting == 'merchantid') {
                $data['merchantid'] = $conf->value;
            }
            if($conf->setting == 'storeid') {
                $data['storeid'] = $conf->value;
            }
            if($conf->setting == 'environment') {
                $data['environment'] = $conf->value;
            }
        }
        return $data;
    }

    public function getRequestId($invoiceid) 
    {
        $get = DB::table('shopeepay_qrcode')->where([
            'invoiceid' => $invoiceid,
        ])->first();
        $request_id = @$get->request_id;
        return $request_id;
    }

    public function getReferenceId($invoiceid) 
    {
        $get = DB::table('shopeepay_qrcode')->where([
            'invoiceid' => $invoiceid,
        ])->first();
        $reference_id = @$get->reference_id;
        return $reference_id;
    }

    public function reqSignature($invoiceid) {
        $get = DB::table('shopeepay_qrcode')->where([
            'invoiceid' => $invoiceid,
        ])->first();
        $signature = @$get->signature;
        return $signature;
    }


    public function rawRequest($invoiceid) {
        $signature = $this->reqSignature($invoiceid);
        $get = DB::table('shopeepay_log')->where([
            'signature' => $signature,
        ])->first();
        $request = json_decode(@$get->request,true);
        return $request;
    }


    public function getInvoice($reference_id) 
    {
        $get = DB::table('shopeepay_qrcode')->where([
            'reference_id' => $reference_id,
        ])->first();
        $invoiceid = @$get->invoiceid;
        return $invoiceid;
    }

    public function cekInvoice($invoiceid) 
    {
        $get = DB::table('tblinvoices')->where([
            'id' => $invoiceid,
        ])->first();
        $status = @$get->status;
        return $status;
    }


    public function getInvoiceAmount($invoiceid) 
    {
        $get = DB::table('tblinvoices')->where([
            'id' => $invoiceid,
        ])->first();
        $total = @$get->total;
        return $total;
    }


    public function getPayMethod($invoiceid) 
    {
        $get = DB::table('tblinvoices')->where([
            'id' => $invoiceid,
        ])->first();
        $paymentmethod = @$get->paymentmethod;
        return $paymentmethod;
    }


    public function amountFormat($amount) {
        // inflated by factor of 100
        $factor		= ($amount*100);
        $newformat	= round($factor, -2);
        return $newformat;
    }

    public function getorderid($invoiceid) {
        $get = DB::table('tblorders')->where([
            'invoiceid' => $invoiceid,
        ])->first();
        $orderid = @$get->id;
        return $orderid;
    }

    public function cancelhosting($id) {
        try {
            DB::table('tblhosting')
                ->where('orderid', $id)
                ->update(
                [
                    'domainstatus' => 'Cancelled'
                ]
            );
            return array('errcode' => 0);
        } catch (\Exception $e) {
            $e->getMessage();
        }
    }

    public function canceldomain($id) {
        try {
            DB::table('tbldomains')
                ->where('orderid', $id)
                ->update(
                [
                    'status' => 'Cancelled'
                ]
            );
            return array('errcode' => 0);
        } catch (\Exception $e) {
            $e->getMessage();
        }
    }

    public function cancelorder($invoiceid) {
        try {
            DB::table('tblorders')
                ->where('invoiceid', $invoiceid)
                ->update(
                [
                    'status' => 'Cancelled'
                ]
            );
            return array('errcode' => 0);
        } catch (\Exception $e) {
            $e->getMessage();
        }
    }

    public function cancelnotes($invoiceid,$error) {
        try {
            DB::table('tblorders')
                ->where('invoiceid', $invoiceid)
                ->update(
                [
                    'notes' => 'Shopeepay Payment Failed. Error Description : '.$error.''
                ]
            );
            return array('errcode' => 0);
        } catch (\Exception $e) {
            $e->getMessage();
        }
    }

    public function apilogs($url,$signature,$post,$res) 
    {
        try {
            DB::table('shopeepay_log')->insert([
                'url' => $url,
                'signature' => $signature,
                'request' => $post,
                'response' => $res,
                'date' => date('Y-m-d H:i:s'),
            ]);
            return array('errcode' => 0);
        } catch (\Exception $e) {
            $e->getMessage();
        }
    }



    
}



?>