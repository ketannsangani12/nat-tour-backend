<?php

namespace App\Exceptions\Core;

class GeneralCoreException extends GeneralException
{
    /**
     * @return string|null
     */
    public function message(): ?string
    {
        return $this->exception->message;
    }
}
