<?php

class Template {
    private static $templates = [];
    private static $template = "";

    public static function useTemplates($filename) {
        $templates = [];
        $fileContent = file_get_contents(IMGPATH . "html/{$filename}.html");
      
        self::$template = $fileContent;
        
        $pattern = '/<!--\s*\$template\s*([\'"])(.*?)\1\s*-->\s*(.*?)(?=<!--\s*\$template\s*|\z)/s';
      
        preg_match_all($pattern, $fileContent, $matches, PREG_SET_ORDER);
      
        foreach ($matches as $match) {
            $name = trim($match[2]);
            $content = trim($match[3]);
            $templates[$name] = $content;
        }

        self::$templates = $templates;
      
        return $templates;
    }

    public static function assert($fields, $template = null) {
        if ($template === null && !array_key_exists('$template', $fields)) {
            $html = self::$template;
        } else {
            if (isset($fields['$template'])) {
                $template = $fields['$template'];
            } else {
                $fields['$template'] = $template;
            }
    
            if (!$template) {
                throw new \Exception("Template not specified");
            }
    
            if (!array_key_exists($template, self::$templates)) {
                throw new \Exception("Template $template not found");
            }
    
            $html = self::$templates[$template];
        }

        foreach ($fields as $k => $v) {
            if (is_array($v)) {
                if (isset($v['$items'])) {
                    $items_html = '';

                    if(isset($v['$items'])){
                        foreach ($v['$items'] as $item) {
                             if (is_array($item) && isset($item['$template'])) {
                                 $items_html .= self::assert($item, $item['$template']);
                             }
                             elseif (is_array($item)) {
                                $items_html .= self::assert($item, $v['$template']);
                             } else {
                                $items_html .= (string)$item;
                             }
                        }
                    }
                    else {
                         if (is_array($v) && isset($v['$template'])) {
                                 $items_html .= self::assert($v, $v['$template']);
                             }
                    }

                    $html = str_replace("<!--{" . $k . "}-->", $items_html, $html);
                    $html = str_replace("{" . $k . "}", $items_html, $html);

                } elseif ($k === '$template') {
                    continue;
                } elseif (isset($v['$template'])) {
                    $html = str_replace("<!--{" . $k . "}-->", self::assert($v, $v['$template']), $html);
                    $html = str_replace("{" . $k . "}", self::assert($v, $v['$template']), $html);
                }
            } else {
                $html = str_replace("<!--{" . $k . "}-->", $v, $html);
                $html = str_replace("{" . $k . "}", $v, $html);
            }
        }

        return $html;
    }
}