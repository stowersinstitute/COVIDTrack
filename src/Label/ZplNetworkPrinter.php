<?php

namespace App\Label;

use App\Entity\LabelPrinter;
use Zpl\Printer;

/**
 * Prints ZPL labels to physical printer on the network.
 */
class ZplNetworkPrinter extends Printer implements ZplPrinterInterface
{
    /**
     * Most recent ZPL sent to this printer
     * @var string
     */
    private $receivedZpl;

    public function __construct(LabelPrinter $printer)
    {
        return parent::__construct($printer->getHost());
    }

    public function send(string $zpl): void
    {
        $this->receivedZpl = $zpl;

        parent::send($zpl);
    }

    public function getResponse(): ZplPrinterResponse
    {
        if (!$this->receivedZpl) {
            throw new \RuntimeException('Cannot getResponse() before printing by calling send()');
        }

        // Network printer may have errors
        $lastErrors = $this->getLastError();
        if (!empty($lastErrors)) {
            return new ZplPrinterResponse('network', true, $lastErrors);
        }

        // Success
        return new ZplPrinterResponse('network', true, $this->receivedZpl);
    }
}
