<?php

namespace App\Label;

use Symfony\Component\Routing\Router;

/**
 * Image of ZPL label.
 */
class ZplImage
{
    public $width;
    public $height;
    public $serverPath;

    public function __construct($width, $height, $serverPath)
    {
        $this->width = $width;
        $this->height = $height;
        $this->serverPath = $serverPath;
    }

    public static function createFromPath(string $serverPath, Router $router = null)
    {
        $width = null;
        $height = null;

        if (is_readable($serverPath)) {
            // Read image width/height
            if ($size = getimagesize($serverPath)) {
                list($width, $height) = $size; // Both sizes in px
            }

        }

        return new static($width, $height, $serverPath);
    }
}
