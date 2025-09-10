<?php

require_once KYUTILS_PATH . '/modules/Request.php';

class Inject {
    private $reflection;
    private $arguments = [];
    private $className = null;

    public function __construct($callback) {
        $reflection = null;

        if (is_string($callback)) {
            if (strpos($callback, '::') !== false) {
                list($class, $method) = explode('::', $callback, 2);
                $reflection = new ReflectionMethod($class, $method);
                $this->className = $class;
            } elseif (function_exists($callback)) {
                $reflection = new ReflectionFunction($callback);
            } else {
                $reflection = new ReflectionFunction(function () use ($callback) {
                    return $callback;
                });
            }
        } elseif (count($callback) === 2 && is_object($callback[0])) {
            $reflection = new ReflectionMethod($callback[0], $callback[1]);
        } elseif ($callback instanceof Closure) {
            $reflection = new ReflectionFunction($callback);
        }

        if ($reflection === null) {
            trigger_error("Invalid callback format.", E_USER_WARNING);
            return;
        }

        $this->reflection = $reflection;
    }

    public function setArguments($arguments = []) {
        $args = [];
        $parameters = $this->reflection->getParameters();
        $req = Request::get();
        $files = Request::files();

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();
            $value = null; $index = null;

            foreach ($arguments as $key => $val) {
                if($key === $name) {
                  $value = $val;
                  $index = $key;
                  break;
                }
            }

            if ($value === null) {
                if ($name === 'request' || $name === 'req') {
                    $value = $req;
                } elseif ($name === 'files') {
                    $value = $files;
                } elseif (array_key_exists($name, $req)) {
                    $value = $req[$name];
                } elseif (array_key_exists($name, $GLOBALS)) {
                  $value = $GLOBALS[$name];
                } elseif (array_key_exists($name, $GLOBALS[KYUTILS_PROVIDE_KEY])) {
                    $value = $GLOBALS[KYUTILS_PROVIDE_KEY][$name];
                }
            }

            if ($value === null) {
              if ($parameter->isOptional()) {
                $value = $parameter->getDefaultValue();
              } else {
                trigger_error("Missing argument: " . $name, E_USER_WARNING);
              }
            }

            $args[] = $value;
        }

        $this->arguments = $args;

        return $this;
    }

    public function exec() {
        if ($this->className !== null) {
            return $this->reflection->invokeArgs($this->className, $this->arguments);
        } else {
            return $this->reflection->invokeArgs($this->arguments);
        }
    }
}