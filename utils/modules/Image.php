<?php

class Image {
    public static function function compress($source, $destination, $quality) {
        $info = getimagesize($source);
      
        if ($info['mime'] == 'image/jpeg') 
          $image = imagecreatefromjpeg($source);
        elseif ($info['mime'] == 'image/gif') 
          $image = imagecreatefromgif($source);
        elseif ($info['mime'] == 'image/png') 
          $image = imagecreatefrompng($source);
      
        // resize (max size 250x250)
        $width = imagesx($image);
        $height = imagesy($image);
        if ($width > 250 || $height > 250) {
          $scale = min(250 / $width, 250 / $height);
          $width = intval($width * $scale);
          $height = intval($height * $scale);
          $image = imagescale($image, $width, $height);
        }
      
        imagejpeg($image, $destination, $quality);
      
        // get ext and sizes to array 
        $sizes = getimagesize($destination);
        
        imagedestroy($image);
      
        return [
          "ext" => ".jpg",
          "sizes" => [$sizes[0], $sizes[1]],
          "file" => basename($destination)
        ];
    }
}