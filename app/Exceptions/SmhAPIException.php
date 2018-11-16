<?php

namespace App\Exceptions;

class SmhAPIException extends CustomException {


    /**
     * @return void  
     */
    public function __construct() {
        $message = $this->build(func_get_args());

        parent::__construct($message);
    }

}
