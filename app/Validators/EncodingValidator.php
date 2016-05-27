<?php

namespace App\Validators;

class EncodingValidator
{
    public function validateEncoding($attribute, $value, $parameters)
    {
        $value = (string) $value;

        return mb_check_encoding($value, isset($parameters[0]) ? $parameters[0] : 'UTF-8');
    }
}
