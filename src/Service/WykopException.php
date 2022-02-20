<?php

namespace App\Service;

use Exception;

class WykopException extends Exception
{
    public $wykopErrorCode;
    private $wykopMessageEN;
    private $wykopMessagePL;
    private $wykopField;

    const WARNING_ERROR_CODES = [
        528, // Unable to reply in this entry - the author has the account blocked
    ];

    public function __construct($message = "", $code = 0, Throwable $previous = null, $wykopErrorData = null)
    {
        parent::__construct($message, $code, $previous);

        if ($wykopErrorData !== null) {
            $this->wykopErrorCode = $wykopErrorData->code;
            $this->wykopMessageEN = $wykopErrorData->message_en;
            $this->wykopMessagePL = $wykopErrorData->message_pl;
            $this->wykopField = $wykopErrorData->field;
        }
    }

    /**
     * If the error is critical, you should not continue the script execution.
     * It might get you banned or something.
     *
     * @return bool
     */
    public function isCritical(): bool
    {
        if (in_array($this->getWykopErrorCode(), self::WARNING_ERROR_CODES)) {
            return true;
        }

        return false;
    }

    /**
     * Returns error code
     *
     * @return int
     */
    public function getWykopErrorCode(): ?int
    {
        return $this->wykopErrorCode;
    }

    /**
     * Returns error message in english
     *
     * @return string
     */
    public function getWykopMessageEN(): ?string
    {
        return $this->wykopMessageEN;
    }

    /**
     * Returns error message in polish
     *
     * @return string
     */
    public function getWykopMessagePL(): ?string
    {
        return $this->wykopMessagePL;
    }

    /**
     * Which request's field triggered the error
     *
     * @return string|null
     */
    public function getWykopField(): ?string
    {
        return $this->wykopField;
    }


}