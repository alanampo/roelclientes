<?php
/**
 * FlowApi - Cliente simplificado para Flow (standalone, sin CodeIgniter)
 */
class FlowApi {
    protected $apiKey;
    protected $secretKey;
    protected $apiUrl;

    public function __construct($apiKey, $secretKey, $sandbox = true){
        $this->apiKey = $apiKey;
        $this->secretKey = $secretKey;
        $this->apiUrl = $sandbox ? "https://sandbox.flow.cl/api" : "https://www.flow.cl/api";
    }

    public function send($service, $params, $method = "GET"){
        $method = strtoupper($method);
        $url = $this->apiUrl . "/" . $service;

        $params = array("apiKey" => $this->apiKey) + $params;
        $data = $this->getPack($params, $method);
        $sign = $this->sign($params);

        if ($method == "GET"){
            $response = $this->httpGet($url, $data, $sign);
        } else {
            $response = $this->httpPost($url, $data, $sign);
        }

        if (isset($response["info"])){
            $code = $response["info"]["http_code"];
            $body = json_decode($response["output"], true);
            if ($code == "200"){
                return $body;
            } else if (in_array($code, array("400", "401"))){
                throw new Exception($body["message"] ?? "Flow API error", $body["code"] ?? $code);
            } else {
                throw new Exception("Unexpected error occurred. HTTP_CODE: " . $code, $code);
            }
        } else {
            throw new Exception("Unexpected error occurred");
        }
    }

    private function getPack($params, $method){
        $keys = array_keys($params);
        sort($keys);
        $data = "";
        foreach ($keys as $key){
            if ($method == "GET"){
                $data .= "&" . rawurlencode($key) . "=" . rawurlencode($params[$key]);
            } else {
                $data .= "&" . $key . "=" . $params[$key];
            }
        }
        return substr($data, 1);
    }

    private function sign($params){
        $keys = array_keys($params);
        sort($keys);
        $toSign = "";
        foreach ($keys as $key){
            $toSign .= "&" . $key . "=" . $params[$key];
        }
        $toSign = substr($toSign, 1);
        return hash_hmac("sha256", $toSign, $this->secretKey);
    }

    private function httpGet($url, $data, $sign){
        $url = $url . "?" . $data . "&s=" . $sign;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $output = curl_exec($ch);
        if ($output === false){
            $error = curl_error($ch);
            throw new Exception($error, 1);
        }
        $info = curl_getinfo($ch);
        curl_close($ch);
        return array("output" => $output, "info" => $info);
    }

    private function httpPost($url, $data, $sign){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data . "&s=" . $sign);

        // Debug: log del request
        $logFile = __DIR__ . '/../../../logs/flow_http.log';
        @mkdir(dirname($logFile), 0755, true);
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - HTTP POST:\n" .
            "URL: " . $url . "\n" .
            "Data: " . $data . "&s=" . substr($sign, 0, 20) . "...\n\n", FILE_APPEND);

        $output = curl_exec($ch);
        if ($output === false){
            $error = curl_error($ch);
            throw new Exception($error, 1);
        }
        $info = curl_getinfo($ch);

        // Debug: log de la respuesta
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - HTTP Response:\n" .
            "HTTP Code: " . $info['http_code'] . "\n" .
            "Response: " . substr($output, 0, 500) . "\n\n", FILE_APPEND);

        curl_close($ch);
        return array("output" => $output, "info" => $info);
    }
}
