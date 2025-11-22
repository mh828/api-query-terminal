<?php

namespace Mh828\ApiQueryTerminal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;
use function PHPUnit\Framework\isArray;

class QueryEngine
{
    private array $responseResult = [];
    private array $namespaces = [];
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
            $key = array_is_list($input) ? $option : $key;
            $methodName = $option['as'] ?? $key;
            if (method_exists($object, $methodName)) {
                try {
                    $result[$key] = $this->responseStandardize(App::call([$object, $methodName], ($option['arguments'] ?? [])),
                        array_is_list($responseArray = ($responses = $option['response'] ?? [])) ? $responseArray : array_keys($responseArray));
                    if (is_object($result[$key])) {
                        if (empty($responses) && is_a($result[$key], TypeInterface::class)) {
                            $result[$key] = App::call([$result[$key], 'default']);
                        } else {
                            $objectResult = [];
                            $this->processor($result[$key], $responses, $objectResult);
                            $result[$key] = $objectResult;
                        }
                    }
                    if (is_array($result[$key])) {
                        foreach ($result[$key] as $k => $v) {
                            if (is_object($v)) {
                                $result[$key][$k] = [];
                                $this->processor($v, ($option['response'] ?? [])[$k] ?? [], $result[$key][$k]);
                            }
                        }
                    }
                } catch (ValidationException $exception) {
                    $result[$key] = [
                        'status' => 'invalid',
                        'code' => 422,
                        'errors' => $exception->errors()
                    ];
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

    public function setNamespace(...$namespaces): self
    {
        $this->namespaces = array_merge($this->namespaces, $namespaces);
        return $this;
    }

    public function removeNamespaces(...$namespaces): self
    {
        $this->namespaces = array_values(array_filter($this->namespaces, fn($ns) => !in_array($ns, $namespaces)));
        return $this;
    }

    public function resetNamespaces($namespaces = []): self
    {
        $this->namespaces = $namespaces;
        return $this;
    }

    public function getNamespaces(): array
    {
        return $this->namespaces;
    }
}