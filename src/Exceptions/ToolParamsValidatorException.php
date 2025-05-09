<?php

namespace KLP\KlpMcpServer\Exceptions;

use Exception;

class ToolParamsValidatorException extends Exception {
    public function __construct(string $message, private readonly array $errors) {
        parent::__construct($message);
    }

    public function getErrors(): array {
        return $this->errors;
    }
}
