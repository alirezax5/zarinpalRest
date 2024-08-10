<?php

namespace Alirezax5\Zarinpal;
class Verify
{
    private $merchantId;

    /** @var int */
    private $amount;
    private $zarinpalUrl = 'https://api.zarinpal.com/pg/v4/payment/verify.json';
    /** @var string */
    private $zarinpalUrlSandbox = 'https://sandbox.zarinpal.com/pg/rest/WebGate/PaymentVerification.json';

    /** @var string */
    private $authority;
    private $node;
    private $sandBox = false;

    public function __construct(string $merchantId, int $amount, $node = 'api')
    {
        $this->merchantId = $merchantId;
        $this->amount = $amount;
        $this->node = $node;

    }

    public function send()
    {
        $data = [
            'merchant_id' => $this->merchantId,
            'authority' => $this->authority,
            'amount' => $this->amount,
        ];

        $jsonData = json_encode($data);
        $url = $this->sandBox ? $this->zarinpalUrlSandbox : $this->zarinpalUrl;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v1');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData)
        ));
        $result = curl_exec($ch);
        $result = json_decode($result, true, JSON_PRETTY_PRINT);

        return $this->sandBox ? $this->returnSandBox($result) : $this->return($result);
    }

    public function sandbox(): self
    {
        $this->sandBox = true;
        return $this;

    }

    public function authority(string $authority): self
    {
        $this->authority = $authority;

        return $this;
    }

    public function returnSandBox($result)
    {
        $status = $result['Status'] ?? 0;
        $refId = $result['RefID'] ?? '';
        $message = (new ErrorMessage($status, '', '', false))->msg();

        return [
            'status' => $status,
            'message' => $message,
            'refId' => $refId,
        ];
    }

    public function return($result)
    {

        if (!empty($result['errors'])) {
            return [
                'status' => $result['errors']['code'],
                'message' => $result['errors']['message'],
            ];
        }

        if ($result['data']['code'] == 100) {
            return [
                'status' => $result['data']['code'],
                'ref_id' => $result['data']['ref_id'],
            ];
        }


        return [
            'status' => 0,
            'message' => 'Unknown error',
        ];
    }
}