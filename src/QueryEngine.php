<?php

namespace Mh828\ApiQueryTerminal;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;
use function PHPUnit\Framework\isArray;

class QueryEngine
{
    const CONFIG__SUPPRESS_DEFAULT_EXCEPTIONS = '__suppress-default-exceptions';
    private array $responseResult = [];
    private array $namespaces = [];
    private array $configuration = [];
    private static ?array $process;
    private array $objectsCache = [];
    public Collection $throwables;

    public function __construct(public ?object $entryPoint = null, public ?array $request = null)
    {
        $this->throwables = new Collection([]);
    }

    /**
     * @param object|null $entryPoint
     */
    public function setEntryPoint(?object $entryPoint): void
    {
        $this->entryPoint = $entryPoint;
    }

    /**
     * @param array|null $request
     */
    public function setRequest(?array $request): void
    {
        $this->request = $request;
    }

    public function startProcess(?object $entryPoint = null, ?array $request = null): void
    {
        $this->processor($entryPoint ?? $this->entryPoint, $request ?? $this->request, $this->responseResult);
    }

    private function processor(object $object, array $input, array &$result)
    {
        self::$process = ['object' => $object, 'options' => $input];
        request()->route()->setParameter('terminal_object', $object);
        request()->route()->setParameter('terminal_options', $input);
        foreach ($input as $key => $option) {
            $key = array_is_list($input) ? $option : $key;
            $methodName = $option['as'] ?? $key;
            $callable = method_exists($object, $methodName) ? [$object, $methodName] : $this->findClassFromString($methodName);
            if ($callable) {
                try {
                    App::bind(ProcessOption::class, fn() => new ProcessOption($option));
                    $result[$key] = $this->responseStandardize(App::call($callable, ($option['arguments'] ?? [])),
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
                } catch (\Throwable $exception) {
                    //track exceptions and errors
                    $this->throwables->push($exception);
                    //suppress default engine exception handler
                    if ($this->configuration[self::CONFIG__SUPPRESS_DEFAULT_EXCEPTIONS] ?? null)
                        return throw $exception;
                    $result[$key] = ['status' => 'invalid', 'code' => $exception->getCode(), 'errors' => $exception->getMessage()];
                    if (is_a($exception, ValidationException::class)) {
                        $result[$key]['code'] = $exception->status;
                        $result[$key]['errors'] = $exception->errors();
                    } else if (is_a($exception, AuthorizationException::class)) {
                        $result[$key]['code'] = 403;
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

    protected function findClassFromString($classMethod): ?array
    {
        $class = str_replace('.', '\\', substr($classMethod, 0, $position = strrpos($classMethod, '.')));
        $method = substr($classMethod, $position + 1);
        foreach ($this->namespaces as $namespace) {
            if ($this->objectsCache[$nc = $namespace . '\\' . $class] ?? null) return [$this->objectsCache[$nc], $method];
            if (class_exists($nc))
                return [$this->objectsCache[$nc] = App::make($nc), $method];
        }
        return null;
    }

    public function setConfig($key, $value): self
    {
        $this->configuration[$key] = $value;
        return $this;
    }

    public function getConfig($key)
    {
        return $this->configuration[$key] ?? null;
    }

    public function suppressDefaultExceptions($bool): self
    {
        if ($bool) $this->configuration[self::CONFIG__SUPPRESS_DEFAULT_EXCEPTIONS] = true;
        if (!$bool && ($this->configuration[self::CONFIG__SUPPRESS_DEFAULT_EXCEPTIONS] ?? null)) unset($this->configuration[self::CONFIG__SUPPRESS_DEFAULT_EXCEPTIONS]);
        return $this;
    }
}