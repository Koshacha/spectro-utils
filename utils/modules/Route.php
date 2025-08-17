<?php

require_once KYUTILS_PATH . '/modules/Template.php';
require_once KYUTILS_PATH . '/modules/Inject.php';

class Route {
    private static $routes = [];
    private static $template;
    public static $data = [];

    private static function exec() {
        $uri = $_SERVER['REQUEST_URI'];
        $uri = str_replace('frame/', '', $uri);

        foreach (self::$routes as $path => $route) {
            $is_bool = is_bool($path);
            $is_current = false;

            if ($is_bool) {
                $is_current = $path;
                $matches = [];
            } else {
                $pattern = self::pathToRegex($path);
                $is_current = preg_match($pattern, $uri, $matches);
            }

            if ($is_current) {
                array_shift($matches);

                self::$data = (new Inject($route['callback']))->setArguments($matches)->exec();

                switch ($route['type']) {
                    case 'json': {
                        header('Content-Type: application/json');
                        echo json_encode(self::$data);
                        exit;
                    }
                    case 'lazy': {
                        self::setMeta($route['staticMeta']);

                        $frameUrl = $uri . '/frame/';
                        $frameUrl = str_replace('//', '/', $frameUrl);

                        $GLOBALS['data'] = '<div id="spectro_block5"><center><img src="{SITE}spectro-cms-loading.gif" height="50"></center><input id="spectro_dynamicblock5" type="hidden" value="' . $frameUrl .'"></div>';
                        $GLOBALS['dosomething'] = 1;
                        break;
                    }
                    case 'page':
                    default: {
                        self::setMeta($route['staticMeta']);

                        if (self::$template) {
                            self::renderTemplate(self::$template, self::$data);
                        } else {
                            self::renderTemplate($GLOBALS['data'], self::$data);
                        }
                    }
                }

                
            }
        }
    }

    public static function page($path, $callback, $staticMeta = []) {
        self::$routes[$path] = [
            'path' => $path,
            'callback' => $callback,
            'staticMeta' => $staticMeta,
            'type' => 'page'
        ];
    }

    public static function lazyPage($path, $callback, $staticMeta = []) {
        self::$routes[$path] = [
            'path' => $path,
            'callback' => $callback,
            'staticMeta' => $staticMeta,
            'type' => 'lazy'
        ];
    }

    public static function json($path, $callback) {
        self::$routes[$path] = [
            'path' => $path,
            'callback' => $callback,
            'type' => 'json'
        ];
    }

    public static function useLocalTemplate($template) {
        $template = str_replace('.html', '', $template);
        self::$template = $template;
        Template::useTemplates(IMGPATH . "html/{$template}.html");
    }

    public static function useTemplate($template) {
        $template = str_replace('.html', '', $template);
        self::$template = $template;
        Template::useTemplates(__DIR__ . "/html/{$template}.html");
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