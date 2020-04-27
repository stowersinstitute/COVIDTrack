<?php


namespace App\Label;

use App\Entity\LabelPrinter;
use Symfony\Component\HttpFoundation\ParameterBag;
use Zpl\Fonts\Zebra\ZD420;
use Zpl\ZplBuilder;

/**
 * Builds ZPL to print a label. Extend and customize method build() to write
 * ZPL commands using ZplBuilder service.
 */
abstract class AbstractLabelBuilder
{
    /**
     * @var ZplBuilder
     * @deprecated Instead use method getZplBuilder()
     */
    protected $zpl;

    /**
     * @var LabelPrinter
     */
    protected $printer;

    public function __construct(?LabelPrinter $printer = null)
    {
        $this->parameters = new ParameterBag();

        if ($printer) {
            $this->setPrinter($printer);
        }
    }

    public function setPrinter(LabelPrinter $printer): void
    {
        $this->printer = $printer;

        $this->zpl = $this->getZplBuilder();
    }

    /**
     * Use at beginning of StowersLabelBuilder->build() to begin constructing ZPL:
     *
     * $zpl = $this->getZplBuilder();
     */
    protected function getZplBuilder(): ZplBuilder
    {
        $zpl = new ZplBuilder(ZplBuilder::UNIT_MM, $this->printer->getDpi());
        $zpl->setMediaWidth($this->printer->getMedia()->getWidth());
        $zpl->setFontMapper(new ZD420());

        return $zpl;
    }

    public function getPrinter(): ?LabelPrinter
    {
        return $this->printer;
    }

    /**
     * @return string
     */
    public function getItemDisplayString(): string
    {
        return '';
    }

    /**
     * @return ParameterBag
     */
    public function getParameters(): ParameterBag
    {
        return $this->parameters;
    }

    /**
     * @param ParameterBag $parameters
     */
    public function setParameters(ParameterBag $parameters): void
    {
        $this->parameters = $parameters;
    }

    /**
     * @throws \Exception
     */
    public function checkAndBuild()
    {
        if (!$this->printer) {
            throw new \Exception("Label cannot be built because no printer has been selected");
        }

        return $this->build();
    }

    /**
     * User readable name of the builder. Used in print job queue UI.
     *
     * @return string
     */
    abstract public function getBuilderName(): string;

    /**
     * Main method that builds the label ZPL
     *
     * @return string
     */
    abstract public function build(): string;

    public static function testLabelZpl(LabelPrinter $printer): string
    {
        return '';
    }

}