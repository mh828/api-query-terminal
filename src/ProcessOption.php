<?php

namespace Mh828\ApiQueryTerminal;

/**
 * @property-read ?array $arguments
 * @property-read ?string $as
 * @property-read ?array $response
 */
class ProcessOption
{
    public function __construct(public ?array $options)
    {
    }

    public function __get(string $name)
    {
        return $this->options[$name] ?? null;
    }
}