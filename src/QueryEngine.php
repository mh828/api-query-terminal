<?php

namespace Mh828\ApiQueryTerminal;

use Illuminate\Database\Eloquent\Model;

class QueryEngine
{
    private array $responseResult = [];

    public function __construct(public object $entryPoint, public array $request)
    {
        $this->processor($this->request);
    }

    private function processor(array $input)
    {
        foreach ($input as $key => $option) {
            if (method_exists($this->entryPoint, $key))
                $this->responseResult[$key] = $this->responseStandardize(call_user_func([$this->entryPoint, $key], ...($option['arguments'] ?? [])), array_keys($option['response'] ?? []));
        }
    }

    private function responseStandardize(mixed $response, array $responseOption)
    {
        if (!empty($responseOption)) {
            if (is_array($response)) return array_filter($response, fn($key) => in_array($key, $responseOption), ARRAY_FILTER_USE_KEY);
            else if ($response instanceof Model) return $response->only($responseOption);
        }

        return $response;
    }

    public function response(): mixed
    {
        return $this->responseResult;
    }
}