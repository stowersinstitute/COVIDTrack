<?php


namespace App\Label;


use App\Entity\LabelPrinter;
use App\Entity\Tube;
use Zpl\ZplBuilder;

class MBSBloodTubeLabelBuilder extends AbstractLabelBuilder
{
    /**
     * @var Tube
     */
    protected $tube;

    public function setTube(Tube $tube)
    {
        $this->tube = $tube;
    }

    public function getBuilderName(): string
    {
        return "MBS Blood Tube Label";
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

        $zpl->setHome(2, 0);

        // These dots are only for troubleshooting. These can be removed once labels are working consistently.
//        $zpl->drawDot(0,2);
//        $zpl->drawDot(2,0);
//        $zpl->drawDot(41,13);
//        $zpl->drawDot(38,15);

        $fontSize = 6;
        $zpl->setFont($font, $fontSize);

        $date = $this->tube->getCreatedAt();

        $accessionId = $this->tube->getAccessionId();
        if (!$accessionId) {
            throw new \RuntimeException('Cannot print Tube Label without Tube Accession ID');
        }

        $zpl->drawCode128(2, 0, 8, $accessionId, 1);
        $zpl->drawText(20, 2, substr($this->tube->getAccessionId(), -4, 4), "R");

        $zpl->newPage();

        $zpl->setFont($font, $fontSize);

        $zpl->drawRect(0, 0, 24, .25, .25);
        $zpl->drawText(2, 5, $accessionId, 'N', ZplBuilder::JUSTIFY_LEFT, 18, 6);
        $zpl->drawText(2, 7, $date->format('Y.m.d H:i:s'), 'N', ZplBuilder::JUSTIFY_LEFT, 18, 6);

        return $zpl->toZpl();
    }

    public static function testLabelZpl(LabelPrinter $printer): string
    {
        $builder = new self($printer);
        $tube = new Tube('T12345678');
        $builder->setTube($tube);

        return $builder->build();
    }
}