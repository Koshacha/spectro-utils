<?php

class Sms {
    static $provider;

    public static function send($phone, $message) {
        if (empty(self::$provider)) {
            throw new \Exception("SMS provider is not set");
        }

        switch (self::$provider) {
            case 'smsint':
                return self::smsint([
                    'to' => is_array($phone) ? $phone : [$phone],
                    'text' => $message,
                    'token' => SMS_TOKEN
                ]);
            default:
                throw new \Exception("Unknown SMS provider");
        }
    }

    private static function smsint($params = []) {
        $token = $params['token'];
    
        $ch = curl_init();
    
        curl_setopt($ch, CURLOPT_URL, 'https://lcab.smsint.ru/json/v1.0/sms/send/text');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "X-Token: $token",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            # следующую строку закомментить, она для тестирования
            // 'validate' => true,
            'messages' => array_map(function ($phone) use ($params) {
                return [
                    'recipient' => $phone,
                    'text' => $params['text'],
                ];
            }, $params['to']),
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
    
        curl_close($ch);
    
        $response_data = json_decode($response);
    
        return $response_data;
    }
}