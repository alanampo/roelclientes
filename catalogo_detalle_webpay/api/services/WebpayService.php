<?php
/**
 * WebpayService - Integración con Transbank Webpay Plus
 * Encapsula toda la lógica de pagos con Webpay
 */
declare(strict_types=1);

class WebpayService {
  private string $environment;
  private string $commerceCode;
  private string $apiKey;
  private string $baseUrl;

  public function __construct(string $environment = 'integration', string $commerceCode = '', string $apiKey = '') {
    $this->environment = $environment;
    $this->commerceCode = $commerceCode;
    $this->apiKey = $apiKey;

    // Determinar URL base según ambiente
    if ($this->environment === 'production') {
      $this->baseUrl = 'https://webpay3g.transbank.cl';
    } else {
      $this->baseUrl = 'https://webpay3gint.transbank.cl';
    }
  }

  /**
   * Crear una transacción de pago
   *
   * @param int $amount Monto en CLP
   * @param string $buyOrder Orden de compra única (máx 26 caracteres)
   * @param string $sessionId ID de sesión (máx 61 caracteres)
   * @param string $returnUrl URL de retorno después del pago
   * @return array ['ok' => bool, 'token' => string, 'url' => string, 'error' => string]
   */
  public function createTransaction(int $amount, string $buyOrder, string $sessionId, string $returnUrl): array {
    try {
      // Validar parámetros
      if ($amount <= 0) {
        return ['ok' => false, 'error' => 'El monto debe ser mayor a 0'];
      }
      if (strlen($buyOrder) > 26) {
        return ['ok' => false, 'error' => 'La orden de compra no debe exceder 26 caracteres'];
      }
      if (strlen($sessionId) > 61) {
        return ['ok' => false, 'error' => 'El ID de sesión no debe exceder 61 caracteres'];
      }

      $payload = [
        'buy_order' => $buyOrder,
        'session_id' => $sessionId,
        'amount' => $amount,
        'return_url' => $returnUrl
      ];

      $response = $this->_request(
        'POST',
        '/rswebpaytransaction/api/webpay/v1.2/transactions',
        $payload
      );

      if (!$response['ok']) {
        return $response;
      }

      $data = $response['data'];
      return [
        'ok' => true,
        'token' => $data['token'] ?? '',
        'url' => $data['url'] ?? '',
        'error' => ''
      ];
    } catch (Exception $e) {
      return ['ok' => false, 'error' => 'Error creando transacción: ' . $e->getMessage()];
    }
  }

  /**
   * Confirmar una transacción de pago (después de que el usuario retorna)
   *
   * @param string $token Token de la transacción
   * @return array Resultado de la transacción
   */
  public function commitTransaction(string $token): array {
    try {
      if (empty($token)) {
        return ['ok' => false, 'error' => 'Token es requerido'];
      }

      $response = $this->_request(
        'PUT',
        "/rswebpaytransaction/api/webpay/v1.2/transactions/{$token}",
        []
      );

      if (!$response['ok']) {
        return $response;
      }

      $data = $response['data'];

      // Verificar si el pago fue autorizado
      $isAuthorized = ($data['status'] ?? '') === 'AUTHORIZED' && ($data['response_code'] ?? -1) === 0;

      // Extraer detalles de la tarjeta
      $cardDetail = $data['card_detail'] ?? [];
      $cardNumber = $cardDetail['card_number'] ?? '';
      $cardLastDigits = substr($cardNumber, -4);

      // Extraer información de cuotas
      $installmentsNumber = (int)($data['installments_number'] ?? 0);

      return [
        'ok' => $isAuthorized,
        'authorized' => $isAuthorized,
        'status' => $data['status'] ?? 'UNKNOWN',
        'response_code' => $data['response_code'] ?? -1,
        'buy_order' => $data['buy_order'] ?? '',
        'amount' => $data['amount'] ?? 0,
        'authorization_code' => $data['authorization_code'] ?? '',
        'transaction_date' => $data['transaction_date'] ?? '',
        'card_number' => $cardNumber,
        'card_last_digits' => $cardLastDigits,
        'vci' => $data['vci'] ?? '',
        'payment_type_code' => $data['payment_type_code'] ?? '',
        'installments_number' => $installmentsNumber,
        'error' => !$isAuthorized ? 'Pago no autorizado' : ''
      ];
    } catch (Exception $e) {
      return ['ok' => false, 'error' => 'Error confirmando transacción: ' . $e->getMessage()];
    }
  }

  /**
   * Obtener estado de una transacción en cualquier momento
   *
   * @param string $token Token de la transacción
   * @return array Resultado de la transacción
   */
  public function getTransactionStatus(string $token): array {
    try {
      if (empty($token)) {
        return ['ok' => false, 'error' => 'Token es requerido'];
      }

      $response = $this->_request(
        'GET',
        "/rswebpaytransaction/api/webpay/v1.2/transactions/{$token}",
        []
      );

      if (!$response['ok']) {
        return $response;
      }

      $data = $response['data'];
      return [
        'ok' => true,
        'status' => $data['status'] ?? 'UNKNOWN',
        'response_code' => $data['response_code'] ?? -1,
        'amount' => $data['amount'] ?? 0,
        'authorization_code' => $data['authorization_code'] ?? '',
        'data' => $data
      ];
    } catch (Exception $e) {
      return ['ok' => false, 'error' => 'Error obteniendo estado: ' . $e->getMessage()];
    }
  }

  /**
   * Realizar una solicitud HTTP a la API de Webpay
   *
   * @param string $method GET, POST, PUT
   * @param string $path Ruta del endpoint
   * @param array $payload Cuerpo de la solicitud
   * @return array ['ok' => bool, 'data' => mixed, 'error' => string]
   */
  private function _request(string $method, string $path, array $payload): array {
    try {
      if (!extension_loaded('curl')) {
        return ['ok' => false, 'error' => 'Extensión cURL no disponible'];
      }

      $url = $this->baseUrl . $path;
      $ch = curl_init();

      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
      curl_setopt($ch, CURLOPT_TIMEOUT, 30);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

      // Headers
      $headers = [
        'Content-Type: application/json',
        'Tbk-Api-Key-Id: ' . $this->commerceCode,
        'Tbk-Api-Key-Secret: ' . $this->apiKey
      ];
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

      // Body (solo para POST y PUT)
      if ($method === 'POST' || $method === 'PUT') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
      }

      $response = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $curlError = curl_error($ch);
      curl_close($ch);

      if ($curlError) {
        return ['ok' => false, 'error' => "Error de conexión: {$curlError}"];
      }

      if (empty($response)) {
        return ['ok' => false, 'error' => "Respuesta vacía de Webpay (HTTP {$httpCode})"];
      }

      $data = json_decode($response, true);
      if (!is_array($data)) {
        return ['ok' => false, 'error' => "Respuesta inválida de Webpay: " . substr($response, 0, 200)];
      }

      // Webpay retorna error en el JSON
      if (isset($data['error_message'])) {
        return ['ok' => false, 'error' => $data['error_message']];
      }

      return ['ok' => true, 'data' => $data];
    } catch (Exception $e) {
      return ['ok' => false, 'error' => 'Excepción: ' . $e->getMessage()];
    }
  }
}
