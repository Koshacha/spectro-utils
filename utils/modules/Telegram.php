<?php

class Telegram {
    public static function sendToBot($message, $chat) {
        if (!defined('TELEGRAM_TOKEN') || !TELEGRAM_TOKEN) {
            throw new \Exception("TELEGRAM_TOKEN is not set");
        }

        $apiurl = "https://api.telegram.org/bot". TELEGRAM_TOKEN ."/sendMessage";
    
        $body = [
            "chat_id" => $chat,
            "text" => $message
        ];
    
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiurl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $result = curl_exec($ch);
        curl_close($ch);
       
        return json_decode($result, true);
    }
}