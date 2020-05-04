<?php

namespace App\Label;

/**
 * Implementing this interface allows plugging into service
 * App\Label\ZplPrinting to handle ZPL print jobs.
 */
interface ZplPrinterInterface
{
    /**
     * Send ZPL to printer client. How the job is handled is up to the client,
     * which may execute immediately or queue it or other custom action.
     */
    public function send(string $zpl): void;

    /**
     * Return data about the previous job passed to send() method. Some clients
     * are synchronous and execute immediately, while others are asynchronous
     * and execute in the future.
     */
    public function getResponse(): ZplPrinterResponse;
}
