<?php

namespace App\Label;

use App\Entity\LabelPrinter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Router;
use Zpl\CommunicationException;
use Zpl\Printer;
use Zpl\PrinterStatus;

class ZplPrinting
{
    /**
     * Default: "text". Possible values: text, image, printer.
     *
     * - text -- API response contains array of ZPL labels generated.
     * - image -- API response contains array of URLs to view image of labels generated.
     * - network -- API response boolean, sends ZPL directly to network label printer.
     *
     * @var string
     */
    private $clientType;

    /**
     * Cache directory usable by any printing client
     * @var string
     */
    private $cacheDir;

    /**
     * To generate URLs to other content
     * @var Router
     */
    private $router;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * After printer has been sent a print job, some details about its success
     * or failure. Not all printers support synchronous communication.
     *
     * @var ZplPrinterResponse
     */
    private $lastPrinterResponse;

    public function __construct(EntityManagerInterface $em, string $clientType, string $cacheDir, Router $router)
    {
        $this->em = $em;
        $this->clientType = $clientType;
        $this->cacheDir = $cacheDir;
        $this->router = $router;
    }

    /**
     * Use a different printer type.
     */
    public function usePrinterType(string $printerType)
    {
        if (!$this->isValidPrinterType($printerType)) {
            throw new \InvalidArgumentException('Unknown ZPL printer type');
        }

        $this->clientType = $printerType;
    }

    /**
     * @param LabelPrinter $printer
     * @return PrinterStatus
     */
    public function getStatus(LabelPrinter $printer): ?PrinterStatus
    {
        try {
            $client = $this->getPrinterClient($printer);
        } catch (CommunicationException $e) {
            return null;
        }
        $status = $client->getStatus();
        return $status;
    }

    public function testPrint(LabelPrinter $printer)
    {
        $this->lastPrinterResponse = null;

        $client = $this->getPrinterClient($printer);
        $builderClass = $printer->getMedia()->getTestLabelTemplate()->getZplBuilderClass();
        $client->send($builderClass::testLabelZpl($printer));

        $this->lastPrinterResponse = $client->getResponse();
    }

    public function printZpl(LabelPrinter $printer, string $zplCode, int $copies = 1)
    {
        $this->lastPrinterResponse = null;

        $client = $this->getPrinterClient($printer);
        $zpl = "";
        for ($i = 0; $i < $copies; $i++) {
            $zpl .= $zplCode;
        }
        $client->send($zpl);

        $this->lastPrinterResponse = $client->getResponse();
    }

    public function getLastPrinterResponse(): ?ZplPrinterResponse
    {
        return $this->lastPrinterResponse;
    }

    /**
     * @param AbstractLabelBuilder $builder
     * @param int $copies
     * @param bool $queue
     * @throws \Exception
     */
    public function printBuilder(AbstractLabelBuilder $builder, int $copies = 1, bool $queue = false)
    {
        $this->printZpl($builder->getPrinter(), $builder->checkAndBuild(), $copies);
    }

    protected function isValidPrinterType(string $type): bool
    {
        // Must match what's in getPrinterClient()
        $valid = ['text', 'image', 'network'];

        return in_array($type, $valid);
    }

    /**
     * Get label printer strategy based on configuration.
     */
    protected function getPrinterClient(LabelPrinter $printer): ZplPrinterInterface
    {
        // If adding a new type, update isValidPrinterType()
        switch ($this->clientType) {
            case 'text':
                return new ZplTextPrinter();

            case 'image':
                return new ZplImagePrinter($printer, $this->cacheDir, $this->router);

            case 'network':
                // Physical network printer
                // Create new instance every time to ensure fresh socket connection
                return new ZplNetworkPrinter($printer);
        }

        throw new \RuntimeException('Unknown config value for ZPL printer type');
    }
}
