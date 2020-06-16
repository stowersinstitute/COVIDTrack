<?php


namespace App\Label;


use App\Entity\LabelPrinter;
use App\Entity\ParticipantGroup;
use Zpl\ZplBuilder;

class GenericTextLabelBuilder extends AbstractLabelBuilder
{
    /**
     * @var ParticipantGroup
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

        // Write rectangle label text

        // All positioning code is below here

        $zpl = $this->getZplBuilder();

        $zpl->setHome(2, 2);

        $fontSize = 32;
        $zpl->setFont($font, $fontSize);

        $zpl->drawText(20, 18, $this->text, 'N', ZplBuilder::JUSTIFY_AUTO, 64, $fontSize);

        return $zpl->toZpl();
    }

    public static function testLabelZpl(LabelPrinter $printer): string
    {
        $builder = new self($printer);
        $group = new ParticipantGroup('Generic Text', 5);
        $builder->setGroup($group);

        return $builder->build();
    }
}