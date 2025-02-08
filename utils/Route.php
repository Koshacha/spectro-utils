<?php

require_once __DIR__ . '/Template.php';

class Route {
    private static $routes = [];
    private static $template;
    public static $data = [];

    public static function page($path, $callback) {
        $uri = $_SERVER['REQUEST_URI'];
        $pattern = self::pathToRegex($path);

        if ($GLOBALS['framemode'] != 0) {
            $uri = str_replace('frame/', '', $uri);
        }
    
        if (preg_match($pattern, $uri, $matches)) {
            array_shift($matches);
    
            if (is_string($callback) && strpos($callback, '::') !== false) {
                list($class, $method) = explode('::', $callback, 2);
                $reflection = new ReflectionMethod($class, $method);
            } elseif (is_array($callback) && count($callback) === 2 && is_object($callback[0])) {
                $reflection = new ReflectionMethod($callback[0], $callback[1]);
            } elseif (is_string($callback) && function_exists($callback)) {
                $reflection = new ReflectionFunction($callback);
            } elseif (is_object($callback) && ($callback instanceof Closure)) {
                $reflection = new ReflectionFunction($callback);
            } else {
                throw new InvalidArgumentException("Invalid callback format.");
            }
    
            $parameters = $reflection->getParameters();
            $args = [];
    
            foreach ($parameters as $parameter) {
                $name = $parameter->getName();
                $value = null;
                $index = null;
    
                foreach ($matches as $key => $matchValue) {
                  if($key === $name) {
                    $value = $matchValue;
                    $index = $key;
                    break;
                  }
                }

                if (array_key_exists($key, $GLOBALS)) {
                  $value = $GLOBALS[$key];
                }
    
                if ($value === null) {
                  if ($parameter->isOptional()) {
                      try {
                        $value = $parameter->getDefaultValue();
                      } catch (ReflectionException $e) {
                          
                      }
                  } else {
                      throw new InvalidArgumentException("Missing argument: " . $name);
                  }
                }
    
                if($index !== null) {
                  unset($matches[$index]);
                }
    
                $args[] = $value;
            }
    
            self::$data = $reflection->invokeArgs($args);
            // Для методов объекта:
            // self::$data = $reflection->invokeArgs($callback[0], $args);
    
            if (self::$template) {
                self::renderTemplate(self::$template, self::$data);
            }
        }
    }

    public static function lazyPage($path, $callback) {
        if ($GLOBALS['framemode'] == 0) {
            $GLOBALS['data'] = '<div id="spectro_block5"><center><img src="{SITE}spectro-cms-loading.gif" height="50"></center><input id="spectro_dynamicblock5" type="hidden" value="' . $_SERVER['REQUEST_URI'] . '/frame/"></div>';
            $GLOBALS['dosomething'] = 1;
        } else {
            self::page($path, $callback);
        }
    }

    public static function useTemplate($template) {
        self::$template = $template;
        Template::useTemplates($template);
    }

    private static function pathToRegex($path) {
        $path = preg_replace('/\/:([^\/]+)/', '/(?<$1>[^\/]+)', $path);
        $path = str_replace('/', '\/', $path);
        return '/^' . $path . '$/';
    }

    private static function renderTemplate($template, $data) {
        $GLOBALS['data'] = Template::assert($data);
        $GLOBALS['dosomething'] = 1;
    }
}