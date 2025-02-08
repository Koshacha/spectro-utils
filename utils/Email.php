<?php

namespace Utils;

class Email {
    private static $to;
    private static $from;
    private static $replyTo;
    private static $subject;
    private static $message;
    private static $contentType = 'text/html; charset=utf-8';
    private static $attachments = array();
    private static $headers = array();

    public static function to($to) {
        self::$to = $to;
        return self;
    }

    public static function from($from) {
        self::$from = $from;
        return self;
    }

    public static function replyTo($replyTo) {
        self::$replyTo = $replyTo;
        return self;
    }

    public static function subject($subject) {
        self::$subject = $subject;
        return self;
    }

    public static function message($message) {
        self::$message = $message;
        return self;
    }

    public static function contentType($contentType) {
        self::$contentType = $contentType;
        return self;
    }

    public static function attach($filePath, $fileName = null, $contentType = null) {
        if (file_exists($filePath)) {
            $fileName = $fileName ?: basename($filePath);
            $contentType = $contentType ?: mime_content_type($filePath);

            self::$attachments[] = array(
                'path' => $filePath,
                'name' => $fileName,
                'type' => $contentType
            );
        }
        return self;
    }

    public static function header($header) {
        self::$headers[] = $header;
        return self;
    }

    private static function buildHeaders() {
        $headers = array();

        if (self::$from) {
            $headers[] = 'From: ' . self::$from;
        }

        if (self::$replyTo) {
            $headers[] = 'Reply-To: ' . self::$replyTo;
        }

        $headers = array_merge($headers, self::$headers);

        if (!empty(self::$attachments)) {
            $boundary = md5(time());
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
            return implode("\r\n", $headers);
        }

        $headers[] = 'Content-Type: ' . self::$contentType;
        return implode("\r\n", $headers);
    }

    private static function buildMessage() {
        if (empty(self::$attachments)) {
            return self::$message;
        }

        $boundary = md5(time());
        $message = "--" . $boundary . "\r\n";
        $message .= "Content-Type: " . self::$contentType . "\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n";
        $message .= self::$message . "\r\n";

        foreach (self::$attachments as $attachment) {
            $file = fopen($attachment['path'], "rb");
            $data = fread($file, filesize($attachment['path']));
            fclose($file);
            $data = chunk_split(base64_encode($data));

            $message .= "--" . $boundary . "\r\n";
            $message .= "Content-Type: " . $attachment['type'] . '; name="' . $attachment['name'] . '"' . "\r\n";
            $message .= 'Content-Disposition: attachment; filename="' . $attachment['name'] . '"' . "\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $message .= $data . "\r\n\r\n";
        }

        $message .= "--" . $boundary . "--";

        return $message;
    }

    public static function send() {
        $to = self::$to;
        $subject = self::$subject;
        $message = self::buildMessage();
        $headers = self::buildHeaders();

        $result = mail($to, $subject, $message, $headers);

        self::$to = null;
        self::$from = null;
        self::$replyTo = null;
        self::$subject = null;
        self::$message = null;
        self::$contentType = 'text/html; charset=utf-8';
        self::$attachments = array();
        self::$headers = array();

        return $result;
    }
}