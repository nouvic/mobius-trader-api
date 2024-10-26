<?php
  namespace MobiusTrader;

  use Exception;

  class MobiusTraderClient
  {
      const STATUS_OK = 'OK';
      const STATUS_ERROR = 'ERROR';

      private $options;

      public function __construct(array $options = [])
      {
          if (! function_exists('base64_encode')) {
              throw new Exception('base64_encode not supported');
          }
          if (! function_exists('json_encode')) {
              throw new Exception('JSON not supported');
          }
          if (! function_exists('curl_init') || ! extension_loaded('curl')) {
              throw new Exception('cURL must be installed');
          }

          $default_options = array(
              'url' => 'https://mtrader7api.com/v2',
              'user_agent' => 'MT7-PHP/3.0.1',
              'token' => null,
              'broker' => null,
              'password' => null,
              'float_mode' => false,
              'response' => array(
                  'status' => array(
                      'field' => 'status',
                      'ok' => true,
                      'error' => false,
                  ),
                  'result' => array(
                      'field' => 'data',
                  ),
              ),
          );

          $this->options = array_replace_recursive($default_options, $options);
      }

      public function call($method, array $params = null)
      {
          $url = $this->options['url'];
          $payload = new \stdClass;

          $payload->jsonrpc = '2.0';
          $payload->id = $this->generate_id();
          $payload->method = $method;

          if ($params) {
              $payload->params = $params;
          }

          $curl = curl_init();

          $authorization = $this->options['token']
              ? 'Bearer ' . $this->options['token']
              : 'Basic ' . base64_encode($this->options['broker'] . ':' . $this->options['password']);

          $headers = array(
              'Content-Type: application/json',
              'Authorization: ' . $authorization,
          );

          if ($this->options['float_mode']) {
              $headers[] = 'X-FloatMode: true';
          }

          // Set cURL options
          curl_setopt_array($curl, array(
              CURLOPT_RETURNTRANSFER => 1,
              CURLOPT_URL => $url,
              CURLOPT_USERAGENT => $this->options['user_agent'],
              CURLOPT_POST => 1,
              CURLOPT_CONNECTTIMEOUT => 10,
              CURLOPT_TIMEOUT => 30,
              CURLOPT_SSL_VERIFYPEER => false,
              CURLOPT_HTTPHEADER => $headers,
              CURLOPT_POSTFIELDS => json_encode($payload),
          ));

          $response = curl_exec($curl);
          $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

          if ($http_code !== 200) {
              return array(
                  'status' => self::STATUS_ERROR,
                  'data' => $http_code ? $http_code : 'UnknownError',
                  'message' => curl_error($curl) ? curl_error($curl) : $response,
              );
          }

          curl_close($curl);
          $response = json_decode($response, true);

          // Handle response
          $status = self::STATUS_OK;
          $data = $response['result'] ?? $response['error'];
          $message = $response['error']['error']['Message'] ?? '';
          $args = $response['error']['error']['Args'] ?? array();

          return array(
              'status' => $status,
              'data' => $data,
              'message' => $message,
              'args' => $args,
          );
      }

      protected function generate_id(): int
      {
          return mt_rand(1, 100000000);
      }
  }
