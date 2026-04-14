<?php
namespace App\Exceptions;

use Exception;

class BotActionException extends Exception {
    protected $isFakeSuccess;

    public function __construct($message, $isFakeSuccess = false) {
        parent::__construct($message);
        $this->isFakeSuccess = $isFakeSuccess;
    }

    public function isFakeSuccess() {
        return $this->isFakeSuccess;
    }
}
