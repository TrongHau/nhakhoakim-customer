<?php

namespace App\InsuranceHub\DTOs;

class ActionDto
{
    /** @var string action key, e.g. 'submit', 'cancel' */
    public $action;

    /** @var string label hiển thị */
    public $label;

    /** @var string HTTP method */
    public $method;

    /** @var string URL pattern tương đối */
    public $endpoint;

    /** @var bool */
    public $requiresConfirmation;

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public function toArray(): array
    {
        return [
            'action'               => $this->action,
            'label'                => $this->label,
            'method'               => $this->method,
            'endpoint'             => $this->endpoint,
            'requiresConfirmation' => $this->requiresConfirmation,
        ];
    }
}
