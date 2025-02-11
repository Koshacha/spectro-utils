<?php

class SpectroError extends \Exception {
    public function __construct($message = null, $code = 0, Exception $previous = null) {
        parent::__construct(json_encode($message), $code, $previous);
    }

    public function getDecodedMessage($assoc = true) {
        return json_decode($this->getMessage(), $assoc);
    }
}