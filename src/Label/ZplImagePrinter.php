<?php

namespace App\Label;

use App\Entity\LabelPrinter;
use Symfony\Component\Routing\Router;
use Zpl\CommunicationException;
use Zpl\Printer;
use Zpl\PrinterStatus;

/**
 * Prints ZPL labels to image files.
 */
class ZplImagePrinter extends Printer implements ZplPrinterInterface
{
    /**
     * Most recent ZPL sent to this printer
     * @var string
     */
    private $receivedZpl;

    /**
     * Details about image preview generated for most recent sent ZPL
     * @var ZplImage
     */
    private $lastReceivedImage;

    /**
     * Physical printer for which an image will be created. Uses printer's currently
     * configured media.
     *
     * @var LabelPrinter
     */
    private $printer;

    /**
     * Directory path where image previews can be written.
     * @var string
     */
    private $cacheDir;

    /**
     * Generates URL to ZPL image preview
     * @var Router
     */
    private $router;

    public function __construct(LabelPrinter $printer, string $cacheDir, Router $router)
    {
        // Intentionally not called. This class DOES NOT accept parent's host/port parameters
//        parent::__construct();

        $this->printer = $printer;
        $this->cacheDir = $cacheDir;
        $this->router = $router;
    }

    public function getResponse(): ZplPrinterResponse
    {
        if (!$this->receivedZpl) {
            throw new \RuntimeException('Cannot getResponse() before printing by calling send()');
        }

        if (!$this->lastReceivedImage) {
            // Last ZPL label did not generate an image
            return new ZplPrinterResponse('image', false, null, 'No image preview available');
        }

        // Success
        return new ZplPrinterResponse('image', true, $this->lastReceivedImage);
    }

    /**
     * Writes ZPL labels as images to local cache.
     *
     * @param string $zpl
     */
    public function send(string $zpl): void
    {
        $this->receivedZpl = $zpl;
        $this->lastReceivedImage = null; // Reset last cache image path

        $density = $this->getDpiToDpmm($this->printer->getDpi()) . 'dpmm'; // AbstractBuilder::UNIT_DOTS . AbstractBuilder::UNIT_MM;
        $width = $this->printer->getMedia()->getWidth(); // inches
        $height = $this->printer->getMedia()->getHeight(); // inches
        $index = 0;
        $url = sprintf("http://api.labelary.com/v1/printers/%s/labels/%Fx%F/%d/", $density, $width, $height, $index);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, TRUE);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $zpl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
//        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Accept: application/pdf")); // Uncomment this line to get PDF back from API
        $result = curl_exec($curl);

        if (curl_getinfo($curl, CURLINFO_HTTP_CODE) == 200) {
            $this->writeZplImage($zpl, $result);
        } else {
            throw new \RuntimeException("ZPL API Error: " . $result);
        }

        curl_close($curl);
    }

    /**
     * Do not use. Create via new ZplImagePrinter();
     */
    public static function printer(string $host, int $port = 9100, LabelPrinter $printer = null, string $cacheDir = null) : Printer
    {
        if (!$printer || $cacheDir) {
            throw new CommunicationException('ZplImagePrinter must be created via new ZplImagePrinter()');
        }

        return new static($printer, $cacheDir);
    }

    protected function connect(string $host, int $port) : void
    {
        // Intentionally left blank, image printer handles connecting in other methods
    }

    protected function disconnect() : void
    {
        // Intentionally left blank, image printer handles disconnecting in other methods
    }

    protected function getLastError() : array
    {
        // Errors handled as Exceptions, never available as state
        return [];
    }

    public function getStatus(): ?PrinterStatus
    {
        // PrinterStatus never available
        return null;
    }

    /**
     * Write image of ZPL label to disk
     * @param string $image Raw binary image data
     */
    private function writeZplImage(string $zpl, $image)
    {
        // Ensure cache dir exists
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }

        // Assumes same ZPL will result in same label
        $imagePath = sprintf("%s/%s.png", $this->cacheDir, md5($zpl));

        $fp = fopen($imagePath, "w");
        if (!$fp) {
            throw new \RuntimeException('Cannot open ZPL image preview path');
        }

        if (false === fwrite($fp, $image)) {
            throw new \RuntimeException('Cannot write to ZPL image preview path');
        }

        fclose($fp);

        $this->lastReceivedImage = ZplImage::createFromPath($imagePath, $this->router);
    }

    /**
     * Convert printer DPI (dots per inch) to DPMM (dots per millimeter)
     */
    private function getDpiToDpmm(int $dpi): int
    {
        $conversionFactorToDpmm = 25.375;

        // Valid API values = [6, 8, 12, 24]
        return round($dpi / $conversionFactorToDpmm);
    }
}
