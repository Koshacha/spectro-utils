<?php

class Push {
    public static $token = '';
    public static $oauth = null;
    public static $users = [];

    private static function auth() {
        $authConfigString = file_get_contents(PUSH_CREDENTIALS_FILE);
        $authConfig = json_decode($authConfigString);
    
        $secret = openssl_get_privatekey($authConfig->private_key);
    
        $header = json_encode([
            'typ' => 'JWT',
            'alg' => 'RS256'
        ]);
    
        $time = time();
        $start = $time - 60;
        $end = $start + 3600;
    
        $payload = json_encode([
            "iss" => $authConfig->client_email,
            "scope" => "https://www.googleapis.com/auth/firebase.messaging",
            "aud" => "https://oauth2.googleapis.com/token",
            "exp" => $end,
            "iat" => $start
        ]);
    
        $base64UrlHeader = base64UrlEncode($header);
        $base64UrlPayload = base64UrlEncode($payload);
    
        $result = openssl_sign($base64UrlHeader . "." . $base64UrlPayload, $signature, $secret, OPENSSL_ALGO_SHA256);
    
        $base64UrlSignature = base64UrlEncode($signature);
    
        $jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    
        $options = array('http' => array(
            'method'  => 'POST',
            'content' => 'grant_type=urn:ietf:params:oauth:grant-type:jwt-bearer&assertion='.$jwt,
            'header'  => "Content-Type: application/x-www-form-urlencoded"
        ));
        $context  = stream_context_create($options);
        $responseText = file_get_contents("https://oauth2.googleapis.com/token", false, $context);
    
        $response = json_decode($responseText);

        self::$access_token = $response->access_token;
        self::$oauth = $response;

        self::fetchApps();

        return $response;
    }

    private static function googlePush($token = '', $title = '', $text = '') {
        $apiurl = "https://fcm.googleapis.com/v1/projects/{$GLOBAL['options']['firebase_project']}/messages:send";
    
        $headers = [
            'Authorization: Bearer ' . self::$access_token,
            'Content-Type: application/json'
        ];
       
        $notification_tray = [
            'title' => $title,
            'body' => $text,
        ];
       
        $in_app_module = [
            "title"  => $title,
            "body"  => $text,
        ];
       
        $message = [
        'message' => [
            'token' => $token,
            'notification'     => $notification_tray,
            'data'             => $in_app_module,
        ],
        ];
    
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiurl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
    
        $result = curl_exec($ch);
        curl_close($ch);
        if ($result === FALSE) {
            return null;
        }
         
        return json_decode($result, true);
    }

    public static function send($tokenOrId = '', $options = []) {
        if (!$tokenOrId || !$options['title'] || !$options['text']) {
            return false;
        }

        if (is_numeric($tokenOrId)) {
            foreach (self::$users as $user) {
                if ($user['user_id'] == $tokenOrId || $user['app_id'] == $tokenOrId) {
                    $token = $user['token'];
                    break;
                }
            }
        } else {
            $token = $tokenOrId;
        }

        if (!$token) {
            return false;
        }

        if (!self::$access_token) {
            $self::auth();
        }

        return self::googlePush($token, $options['title'], $options['text']);
    }

    private static function fetchApps() {
        $sql = "SELECT f.S01, a.ID, a.N01 FROM TEMPSIT15_ROWS AS f JOIN TEMPSIT15_ROWS AS a ON f.N01 = a.ID WHERE f.type = 'firebase' AND a.type = 'app' AND f.S01<>''";

        $users = [];

        if ($res = db_query($sql)) {
            while ($row = mysql_fetch_array($res)) {
                $users[] = [
                    'token' => $row['S01'],
                    'user_id' => $row['N01'],
                    'app_id' => $row['ID']
                ];
            }
        }

        self::$users = $users;
    }
}