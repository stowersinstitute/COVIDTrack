<?php


namespace App\Label;


use App\Entity\LabelPrinter;
use App\Entity\Specimen;
use App\Entity\Tube;
use Zpl\ZplBuilder;

class SpecimenIntakeLabelBuilder extends AbstractLabelBuilder
{
    /**
     * @var Specimen
     */
    protected $tube;

    public function setTube(Tube $tube)
    {
        $this->tube = $tube;
    }

    public function getBuilderName(): string
    {
        return "Specimen Intake Label";
    }

    public function build(): string
    {

        // Font sizing
        $font = 'Font 0';
        $fontSize = 10;

        // Write rectangle label text

        // All positioning code is below here

        // Unit: mm
        $textLeftMargin = 8;
        $textTopMargin = 4;
        $lineHeight = 3;

        $zpl = $this->getZplBuilder();

        $zpl->setHome(2, 2);

        // These dots are only for troubleshooting. These can be removed once labels are working consistently.
//        $zpl->drawDot(0,2);
//        $zpl->drawDot(2,0);
//        $zpl->drawDot(41,13);
//        $zpl->drawDot(38,15);

        $fontSize = 6;
        $zpl->setFont($font, $fontSize);

        $date = $this->tube->getCreatedAt();

        $zpl->drawCode128(1, 2, 4, $this->tube->getAccessionId(), 1, false, 'R');
        $zpl->drawText(6, 5, $this->tube->getAccessionId(), 'N', ZplBuilder::JUSTIFY_LEFT, 18, 6);
        $zpl->drawQrCode(6, 4, $this->tube->getAccessionId(), 11);
        $zpl->drawText(6, 16, $date->format('Y.m.d'), 'N', ZplBuilder::JUSTIFY_LEFT, 18, 6);
        $zpl->drawText(6, 18, $date->format('H:i:s'), 'N', ZplBuilder::JUSTIFY_LEFT, 18, 6);

        return $zpl->toZpl();
    }

    public static function testLabelZpl(LabelPrinter $printer): string
    {
        $builder = new self($printer);
        $tube = new Tube('CVD-1234567');
        $builder->setTube($tube);

        return $builder->build();
    }
}