<?php


namespace App\Label;


use App\Entity\CollectionEvent;
use App\Entity\LabelPrinter;
use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use Zpl\ZplBuilder;

class SpecimenIntakeLabelBuilder extends AbstractLabelBuilder
{
    /**
     * @var Specimen
     */
    protected $specimen;

    /**
     * @var ParticipantGroup
     */
    protected $participantGroup;

    public function setSpecimen(Specimen $specimen)
    {
        $this->specimen = $specimen;
    }

    public function setParticipantGroup(ParticipantGroup $participantGroup)
    {
        $this->participantGroup = $participantGroup;
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

        $zpl->setHome(4, 3);

        // These dots are only for troubleshooting. These can be removed once labels are working consistently.
        $zpl->drawDot(0,2);
        $zpl->drawDot(2,0);
        $zpl->drawDot(41,13);
        $zpl->drawDot(38,15);

        $zpl->setFont($font, $fontSize);

        $writeAtY = $textTopMargin;

        $zpl->drawText($textLeftMargin, $writeAtY, $this->specimen->getAccessionId(), 'N', ZplBuilder::JUSTIFY_LEFT, 33, $fontSize);

//        $zpl->drawCode128($textLeftMargin, 4, 4, $this->identifier, 1);
        $zpl->drawQrCode(28, 0, $this->specimen->getAccessionId(), 15);

        $fontSize = 6;
        $zpl->setFont($font, $fontSize);

        // Write circle label text
//        $zpl->drawText(45, 7.5, $this->identifier, 'N', ZplBuilder::JUSTIFY_LEFT, 8, 12);
        $zpl->drawText(45, 7.5, $this->specimen->getAccessionId(), 'N', ZplBuilder::JUSTIFY_LEFT, 8, 12);
//        $zpl->drawText(45, 9.5, str_replace('COV','', $this->specimen->getAccessionId()), 'N', ZplBuilder::JUSTIFY_LEFT, 8, 12);

        return $zpl->toZpl();
    }

    public static function testLabelZpl(LabelPrinter $printer): string
    {
        $builder = new self($printer);
        $group = new ParticipantGroup('CPG-1', 4);
        $specimen = new Specimen('CVD-1234567', $group);
        $builder->setParticipantGroup($group);
        $builder->setSpecimen($specimen);

        return $builder->build();
    }
}