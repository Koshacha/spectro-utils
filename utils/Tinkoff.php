<?php
namespace Utils;

class Tinkoff {
    private static function request($path, $body) {
        $curl = curl_init();
      
        curl_setopt_array($curl, array(
          CURLOPT_URL => 'https://securepay.tinkoff.ru/v2/' . $path,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
          CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json'
          ),
        ));
      
        $response = curl_exec($curl);
      
        curl_close($curl);
        return $response;
    }

    public static function createPayment($orderId, $amount, $phone, $email) {
        $body = array(
          "Token" => hash('sha256', $amount . '00' . SITE . 'payment-fail/' . $orderId . TINKOFF_PASSWORD . SITE . 'tinkoff-handler/' . TINKOFF_TERMINAL_KEY),
          'TerminalKey' => TINKOFF_TERMINAL_KEY,
          'Amount' => $amount . '00',
          'OrderId' => $orderId,
          'SuccessURL' => SITE . 'tinkoff-handler/',
          'FailURL' => SITE . 'payment-fail/',
          'Receipt' => array(
            "Email" => $email,
            "Phone" => $phone,
            "Taxation" => "usn_income",
            'Items' => array(array(
              "Name" => "Оплата заказа",
              "Price" => $amount . '00',
              "Quantity" => 1.00,
              "Amount" => $amount . '00',
              "PaymentMethod" => "full_prepayment",
              "PaymentObject" => "good",
              "Tax" => "none"
            ))
          )
        );
      
        $response = Tinkoff::request('Init', $body);
      
        return json_decode($response, true);
    }

    public static function getPaymentState($paymentId) {
        $body = array(
          'TerminalKey' => TINKOFF_TERMINAL_KEY,
          "PaymentId" => $paymentId,
          "Token" => hash('sha256', TINKOFF_PASSWORD . $paymentId . TINKOFF_TERMINAL_KEY)
        );
        $response = Tinkoff::request('GetState', $body);
      
        return json_decode($response, true);
    }
}