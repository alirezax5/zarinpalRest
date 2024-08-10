<?php

namespace Alirezax5\Zarinpal;

use Alirezax5\Zarinpal\ErrorMessage;

class Request
{
    /** @var string */
    private $merchantId;

    /** @var int */
    private $amount;

    /** @var string */
    private $description;

    /** @var string */
    private $callbackUrl;

    /** @var string */
    private $mobile;
    /** @var string */
    private $order_id;

    /** @var string */
    private $email;
    /** @var string */
    private $currency = 'IRT';
    /** @var string */
    private $metadata = [];

    /** @var string */
    private $node;
    /** @var string */
    private $zarinpalUrl = 'https://api.zarinpal.com/pg/v4/payment/request.json';
    /** @var string */
    private $zarinpalUrlSandbox = 'https://sandbox.zarinpal.com/pg/rest/WebGate/PaymentRequest.json';
    /** @var string */
    private $zarinpalUrlPay = 'https://www.zarinpal.com/pg/StartPay/{Authority}';
    /** @var bool */
    private $sandBox = false;


    public function __construct(string $merchantId, int $amount, $node = 'api')
    {
        $this->merchantId = $merchantId;
        $this->amount = $amount;
        $this->node = $node;

    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function callbackUrl(string $callbackUrl): self
    {
        $this->callbackUrl = $callbackUrl;

        return $this;
    }

    public function sandbox(): self
    {
        $this->sandBox = true;
        return $this;

    }

    public function mobile(string $mobile): self
    {
        $this->mobile = $mobile;

        return $this;
    }

    public function email(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function currency(string $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    public function order_id(string $order_id): self
    {
        $this->order_id = $order_id;

        return $this;
    }

    public function metadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }


    public function send()
    {
        $data = match ($this->sandBox) {
            true => [
                'MerchantID' => $this->merchantId,
                'Amount' => $this->amount,
                'Description' => $this->description,
                'CallbackURL' => $this->callbackUrl,
            ],
            false => [
                'merchant_id' => $this->merchantId,
                'amount' => $this->amount,
                'description' => $this->description,
                'callback_url' => $this->callbackUrl,
                'currency' => $this->currency,
                'metadata' => [],
            ],
        };
        if ($this->mobile) {
            $data['metadata']['mobile'] = $this->mobile;
        }
        if ($this->email) {
            $data['metadata']["email"] = $this->email;
        }
        if ($this->order_id) {
            $data['metadata']["order_id"] = $this->order_id;
        }
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

    public function returnSandBox($result)
    {
        $status = $result['Status'] ?? 0;
        $message = (new ErrorMessage($status, $this->description, $this->callbackUrl, true))->msg();
        $authority = $result['Authority'] ?? '';

        if (empty($authority)) {
            return [
                'status' => $status,
                'message' => $message,
                'startPay' => '',
                'authority' => '',
            ];
        }

        $startPay = strtr($this->zarinpalUrlPay, ['{Authority}' => $authority]);
        $startPayUrl = $this->sandBox ? "$startPay/ZarinGate" : $startPay;

        return [
            'status' => $status,
            'message' => $message,
            'startPay' => $startPayUrl,
            'authority' => $authority,
        ];
    }

    public function return($result)
    {

        if (!empty($result['errors'])) {
            return [
                'status' => $result['errors']['code'] ?? 0,
                'message' => $result['errors']['message'] ?? '',
            ];
        }

        if ($result['data']['code'] == 100) {
            return [
                'status' => $result['data']['code'],
                'startPay' => strtr($this->zarinpalUrlPay, ['{Authority}' => $result['data']['authority']]),
                'authority' => $result['data']['authority'],
            ];
        }


        return [
            'status' => 0,
            'message' => 'Unknown error',
        ];
    }
}