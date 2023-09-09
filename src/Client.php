<?php

namespace Sarahman\SmsService;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Sarahman\SmsService\Interfaces\ProviderInterface;
use Sarahman\SmsService\Providers;

class Client
{
    const PROVIDER_SSL = Providers\Ssl::class;

    private $provider;

    public function __construct(ProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    public static function getProvider($providerName = self::PROVIDER_SSL, array $config = [], $url = null)
    {
        switch ($providerName) {
            case self::PROVIDER_SSL:
                return new $providerName($config, $url);

            default:
                throw new Exception('Invalid SMS provider name is given.');
        }
    }

    public function send($recipients, $message, array $params = [])
    {
        $log = ['sent' => [], 'failed' => []];
        is_array($recipients) || $recipients = [$recipients];

        foreach ($recipients as $recipient) {
            $options = ['url' => $this->provider->getUrl()];

            try {
                if (!$data = $this->provider->mapParams($recipient, $message, $params)) {
                    throw new Exception(json_encode('Failed to map the params.'), 422);
                }

                $data = array_merge($this->provider->getConfig(), $data);
                $validator = Validator::make($data, $this->provider->getValidationRules());

                if ($validator->fails()) {
                    throw new Exception(json_encode($validator->messages()->all()), 422);
                }

                $options = $this->prepareCurlOptions($data);
                $response = $this->provider->parseResponse($this->executeWithCurl($options));

                if (!$response['success']) {
                    throw new Exception($response['response'], 500);
                }

                $log['sent'][$recipient] = $response;
            } catch (Exception $e) {
                $errorCode = $e->getCode() >= 100 ? $e->getCode() : 500;
                $errorMessage = 422 != $errorCode ? $e->getMessage() : json_decode($e->getMessage(), true);
                $log['failed'][$recipient] = [
                    'success' => false,
                    'response' => $errorMessage,
                ];
            }
        }

        return $this->getSummaryWithLogs($log);
    }

    public function sendWithFallback($recipients, $message, array $params = [])
    {
        $log = ['sent' => [], 'failed' => []];
        is_array($recipients) || $recipients = [$recipients];

        foreach ($recipients as $recipient) {
            $options = ['url' => $this->provider->getUrl()];

            try {
                if (!$data = $this->provider->mapParams($recipient, $message, $params)) {
                    throw new Exception(json_encode('Failed to map the params.'), 422);
                }

                $data = array_merge($this->provider->getConfig(), $data);
                $validator = Validator::make($data, $this->provider->getValidationRules());

                if ($validator->fails()) {
                    throw new Exception(json_encode($validator->messages()->all()), 422);
                }

                $options = $this->prepareCurlOptions($data);

                try {
                    $response = $this->executeWithCurl($options);
                } catch (Exception $e) {
                    $log['failed'][$recipient] = [
                        'success' => false,
                        'response' => $e->getMessage(),
                    ];
                    $response = '';
                }

                $response = $this->provider->parseResponse($response);

                if (!$response['success']) {
                    //Resend sms
                    Log::info('SMS sending failed response!');

                    try {
                        $response = $this->provider->parseResponse($this->executeWithCurl($options));
                        Log::info('Second try of sending SMS', $response);

                        if (!$response['success']) {
                            throw new Exception($response['response'], 500);
                        }
                    } catch (Exception $e) {
                        Log::error('Curl error response: ' . $e->getMessage());
                        throw $e;
                    }
                }

                $log['sent'][$recipient] = $response;
            } catch (Exception $e) {
                $errorCode = $e->getCode() >= 100 ? $e->getCode() : 500;
                $errorMessage = 422 != $errorCode ? $e->getMessage() : json_decode($e->getMessage(), true);
                $log['failed'][$recipient] = [
                    'success' => false,
                    'response' => $errorMessage,
                ];
            }
        }

        return $this->getSummaryWithLogs($log);
    }

    private function prepareCurlOptions(array $data)
    {
        return [
            'url' => $this->provider->getUrl(),
            'post' => count($data),
            'postfields' => http_build_query($data),
            'timeout' => 30,
        ];
    }

    private function executeWithCurl(array $options, $withHttpStatus = false)
    {
        $curlOptions = [];
        isset($options['returntransfer']) || $options['returntransfer'] = true;

        foreach ($options as $key => $value) {
            $option = 'CURLOPT_' . strtoupper($key);
            $curlOptions[constant($option)] = $value;
        }

        $ch = curl_init();

        curl_setopt_array($ch, $curlOptions);

        $response = curl_exec($ch);

        if ($response != true) {
            $eMsg = 'cURL Error # ' . curl_errno($ch) . ' | cURL Error Message: ' . curl_error($ch);

            curl_close($ch);

            throw new Exception($eMsg);
        }

        if ($withHttpStatus) {
            $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        }

        curl_close($ch);

        if (!$withHttpStatus) {
            return $response;
        }

        return [
            'httpStatusCode' => $httpStatusCode,
            'body' => $response,
        ];
    }

    private function getSummaryWithLogs(array $log)
    {
        $sent = count($log['sent']);
        $failed = count($log['failed']);

        return [
            'summary' => [
                'sent' => $sent,
                'failed' => $failed,
                'total' => $sent + $failed,
            ],
            'log' => $log,
        ];
    }
}