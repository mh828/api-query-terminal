<?php

namespace Mh828\ApiQueryTerminal;

class QueryEngine
{
    private array $responseResult = [];

    public function __construct(public TypeInterface $entryPoint, public array $request)
    {
        $this->processor($this->request);
    }

    private function processor(array $input)
    {
        foreach ($input as $key => $value) {
            if (method_exists($this->entryPoint, $key))
                $this->responseResult[$key] = call_user_func([$this->entryPoint, $key], ...($value ?? []));
        }
    }

    public function response(): mixed
    {
        return $this->responseResult;
    }
}