<?php

require_once __DIR__ . '/Template.php';
require_once __DIR__ . '/Router.php';

class Route {
    private static $routes = [];
    private static $template;
    public static $data = [];

    public static function page($path, $callback, $staticMeta = []) {
        $uri = $_SERVER['REQUEST_URI'];
        $pattern = self::pathToRegex($path);
        self::setMeta($staticMeta);

        $uri = str_replace('frame/', '', $uri);
    
        if (preg_match($pattern, $uri, $matches)) {
            array_shift($matches);
    
            if (is_string($callback) && strpos($callback, '::') !== false) {
                list($class, $method) = explode('::', $callback, 2);
                $reflection = new ReflectionMethod($class, $method);
            } elseif (is_array($callback) && count($callback) === 2 && is_object($callback[0])) {
                $reflection = new ReflectionMethod($callback[0], $callback[1]);
            } elseif (is_string($callback) && function_exists($callback)) {
                $reflection = new ReflectionFunction($callback);
            } elseif (is_string($callback) && !function_exists($callback)) {
                $GLOBALS['data'] = $callback;
                $GLOBALS['dosomething'] = 1;
                return;
            } elseif (is_object($callback) && ($callback instanceof Closure)) {
                $reflection = new ReflectionFunction($callback);
            } else {
                trigger_error("Invalid callback format.", E_USER_WARNING);
                return;
            }
    
            $parameters = $reflection->getParameters();
            $args = [];

            $req = Request::get();
            $files = Request::files();
    
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

                if ($name === 'request') {
                    $value = $req;
                } elseif ($name === 'files') {
                    $value = $files;
                } elseif (array_key_exists($name, $req)) {
                    $value = $req[$name];
                }
                
                if ($value === null && array_key_exists($name, $GLOBALS)) {
                  $value = $GLOBALS[$name];
                }
    
                if ($value === null) {
                  if ($parameter->isOptional()) {
                      try {
                        $value = $parameter->getDefaultValue();
                      } catch (ReflectionException $e) {
                          
                      }
                  } else {
                    trigger_error("Missing argument: " . $name, E_USER_WARNING);
                    return;
                  }
                }
    
                if($index !== null) {
                //   unset($matches[$index]);
                }
    
                $args[] = $value;
            }

            self::$data = $reflection->invokeArgs($args);
            // Для методов объекта:
            // self::$data = $reflection->invokeArgs($callback[0], $args);

            if (array_key_exists('json', $staticMeta) && $staticMeta['json']) {
                header('Content-Type: application/json');
                echo json_encode(self::$data);
                die();
            }
    
            if (self::$template) {
                self::renderTemplate(self::$template, self::$data);
            } else {
                self::renderTemplate($GLOBALS['data'], self::$data);
            }
        }
    }

    public static function lazyPage($path, $callback, $staticMeta = []) {
        $uri = $_SERVER['REQUEST_URI'];
        $pattern = self::pathToRegex($path);
        self::setMeta($staticMeta);

        $uri = str_replace('frame/', '', $uri);

        if (preg_match($pattern, $uri, $matches)) {
            array_shift($matches);

            $frameUrl = $uri . '/frame/';
            $frameUrl = str_replace('//', '/', $frameUrl);

            if ($GLOBALS['framemode'] == 0) {
                $GLOBALS['data'] = '<div id="spectro_block5"><center><img src="{SITE}spectro-cms-loading.gif" height="50"></center><input id="spectro_dynamicblock5" type="hidden" value="' . $frameUrl .'"></div>';
                $GLOBALS['dosomething'] = 1;
            } else {
                
                self::page($path, $callback, $staticMeta);
            }
        }
    }

    public static function json($path, $callback) {
        self::page($path, $callback, ['json' => true]);
    }

    public static function useTemplate($template) {
        self::$template = $template;
        Template::useTemplates($template);
    }

    private static function pathToRegex($path) {
        $path = preg_replace('/\/:([^\/]+)/', "/(?'$0'[^\/]+)", $path);
        $path = str_replace('/', '\/', $path);
        $path = '^' . $path;
        $path = str_replace('\/^', '^\/?', $path);
        $path .= '(frame)?\/?$';
        $path = str_replace("'\/:", "'", $path);
        $path = str_replace("\\\\", "\\", $path);

        return '/' . $path . '/';
    }

    private static function renderTemplate($template, $data) {
        $GLOBALS['data'] = is_array($data) ? Template::assert($data) : $data;
        $GLOBALS['dosomething'] = 1;
    }

    public static function setMeta($key, $value = '') {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                self::setMeta($k, $v);
            }
            return;
        }

        if ($key[0] == '<') {
            $GLOBALS['scripts'] = $key . $GLOBALS['scripts'];
        } else {
            switch ($key) {
                case 'h1':
                    $GLOBALS['h1'] = $value;
                    break;
                case 'title':
                    $GLOBALS['title'] = $value;
                    break;
                case 'description':
                    $GLOBALS['description'] = $value;
                    break;
                case 'keywords':
                    $GLOBALS['keywords'] = $value;
                    break;
                default:
                    $GLOBALS['scripts'] = '<meta name="' . $key . '" content="' . $value . '">' . $GLOBALS['scripts'];
            }
        }

        return;
    }

    public static function addScript($script) {
        if ($script[0] == '<') {
            $GLOBALS['scripts'] .= $script;
        } elseif ($script[0] == '/' || preg_match('/^https?:\/\//', $script)) {
            $GLOBALS['scripts'] .= '<script src="' . $script . '"></script>';
        } else {
            $GLOBALS['scripts'] .= '<script>' . $script . '</script>';
        }
        
        return;
    }

    public static function redirect($url, $status = 302) {
        header('Location: ' . $url, true, $status);
        exit();
    }

    public static function status($status = 200) {
        http_response_code($status);
    }
}