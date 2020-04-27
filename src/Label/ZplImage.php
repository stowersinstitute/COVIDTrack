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
    public $url;

    public function __construct($width, $height, $serverPath, $url)
    {
        $this->width = $width;
        $this->height = $height;
        $this->serverPath = $serverPath;
        $this->url = $url;
    }

    public static function createFromPath(string $serverPath, Router $router = null)
    {
        $width = null;
        $height = null;
        $url = null;

        if (is_readable($serverPath)) {
            // Read image width/height
            if ($size = getimagesize($serverPath)) {
                list($width, $height) = $size; // Both sizes in px
            }

            // Generate URL where viewable
            $url = $router ? self::generateUrl($router, $serverPath) : null;
        }

        return new static($width, $height, $serverPath, $url);
    }

    /**
     * Generates absolute URL where this image can be viewed.
     */
    private static function generateUrl(Router $router, string $serverPath): string
    {
        $controllerRouteName = 'samples_zpl_label_preview';
        $params = [
            'filename' => basename($serverPath),
        ];

        return $router->generate($controllerRouteName, $params, Router::ABSOLUTE_URL);
    }
}
