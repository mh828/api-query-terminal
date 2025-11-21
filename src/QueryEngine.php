<?php

namespace Mh828\ApiQueryTerminal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use function PHPUnit\Framework\isArray;

class QueryEngine
{
    private array $responseResult = [];
    private static ?array $process;

    public function __construct(public object $entryPoint, public array $request)
    {
        $this->processor($this->entryPoint, $this->request, $this->responseResult);
    }

    private function processor(object $object, array $input, array &$result)
    {
        self::$process = ['object' => $object, 'options' => $input];
        request()->route()->setParameter('terminal_object', $object);
        request()->route()->setParameter('terminal_options', $input);
        foreach ($input as $key => $option) {
            if (method_exists($object, $key)) {
                $result[$key] = $this->responseStandardize(App::call([$object, $key], ($option['arguments'] ?? [])),
                    array_is_list($responseArray = ($option['response'] ?? [])) ? $responseArray : array_keys($responseArray));
                if(is_array($result[$key]) || is_object($result[$key])) {
                    foreach ($result[$key] as $k => $v) {
                        if (is_object($v)) {
                            $result[$key][$k] = [];
                            $this->processor($v, ($option['response'] ?? [])[$k] ?? [], $result[$key][$k]);
                        }
                    }
                }
            }
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

    /**
     * @return array{object:object,options:array}|null
     */
    public static function getProcess(): ?array
    {
        return self::$process;
    }

    public static function getProcessObject(): ?object
    {
        return self::getProcess()['object'] ?? null;
    }

    public static function getProcessOptions(): ?array
    {
        return self::getProcess()['options'] ?? null;
    }
}