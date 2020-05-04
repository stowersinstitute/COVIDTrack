<?php

namespace App\Label;

/**
 * Response object containing data about communication to/from printer
 * after sending ZPL data to be printed. Some printers support synchronous
 * communication.
 */
class ZplPrinterResponse
{
    private $printerType;
    private $success;
    private $data;
    private $message;

    public function __construct(string $printerType, bool $success, $data = '', $message = '')
    {
        $this->printerType = $printerType;
        $this->success = $success;
        $this->data = $data;
        $this->message = $message;
    }

    public function getPrinterType(): string
    {
        return $this->printerType;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Different response data depending on printer
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
