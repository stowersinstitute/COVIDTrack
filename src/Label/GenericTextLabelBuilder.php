<?php


namespace App\Label;


use App\Entity\LabelPrinter;
use App\Entity\ParticipantGroup;
use Zpl\ZplBuilder;

class GenericTextLabelBuilder extends AbstractLabelBuilder
{
    /**
     * @var string
     */
    protected $text;

    public function setText(string $text)
    {
        $this->text = $text;
    }

    public function getBuilderName(): string
    {
        return "Generic Text Label";
    }

    public function build(): string
    {

        // Font sizing
        $font = 'Font 0';
        $fontSize = 10;

        // All positioning code is below here

        $zpl = $this->getZplBuilder();

        $zpl->setHome(0, 0);
        $fontSize = 28;

        if (strlen($this->text) < 10) {
            $fontSize = 42;
        }

        $zpl->setFont($font, $fontSize);

        $zpl->drawCell(64, 25, $this->text, false, false,'C');

        return $zpl->toZpl();
    }

    public static function testLabelZpl(LabelPrinter $printer): string
    {
        $builder = new self($printer);
        $builder->setText('COVIDTrack');

        return $builder->build();
    }
}