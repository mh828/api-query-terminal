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
        foreach ($input as $key => $option) {
            if (method_exists($this->entryPoint, $key))
                $this->responseResult[$key] = $this->responseStandardize(call_user_func([$this->entryPoint, $key], []), ($option['response'] ?? null));
        }
    }

    private function responseStandardize(mixed $response, ?array $responseOption)
    {
        if (is_array($responseOption)) {
            if (is_array($response)) return array_filter($response, fn($key) => in_array($key, $responseOption), ARRAY_FILTER_USE_KEY);
        }
        return $response;
    }

    public function response(): mixed
    {
        return $this->responseResult;
    }
}