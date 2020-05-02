<?php

namespace App\Label;

use Zpl\Printer;
use Zpl\PrinterStatus;

/**
 * Prints ZPL labels to text.
 */
class ZplTextPrinter extends Printer implements ZplPrinterInterface
{
    /**
     * @var string
     */
    private $receivedZpl = '';

    public function __construct()
    {
        // Does not accept parent's host/port parameters
    }

    /**
     * Writes ZPL labels as images to local cache.
     *
     * @param string $zpl
     */
    public function send(string $zpl): void
    {
        $this->receivedZpl = $zpl;
    }

    public function getResponse(): ZplPrinterResponse
    {
        if (!$this->receivedZpl) {
            throw new \RuntimeException('Cannot getResponse() before printing by calling send()');
        }

        // Success
        return new ZplPrinterResponse('text', true, $this->receivedZpl);
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
}
