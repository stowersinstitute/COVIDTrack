<?php


namespace App\Label;


use App\Entity\LabelPrinter;
use App\Entity\ParticipantGroup;
use Zpl\ZplBuilder;

class ParticipantGroupBadgeLabelBuilder extends AbstractLabelBuilder
{
    /**
     * @var ParticipantGroup
     */
    protected $group;

    public function setGroup(ParticipantGroup $group)
    {
        $this->group = $group;
    }

    public function getBuilderName(): string
    {
        return "Participant Group Label";
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

        $fontSize = 6;
        $zpl->setFont($font, $fontSize);

        $zpl->drawQrCode(0, 0, $this->group->getTitle(), 14);
        $zpl->drawText(0, 18, $this->group->getTitle(), 'N', ZplBuilder::JUSTIFY_LEFT, 18, 6);

        return $zpl->toZpl();
    }

    public static function testLabelZpl(LabelPrinter $printer): string
    {
        $builder = new self($printer);
        $group = new ParticipantGroup('Purple People Eaters', 5);
        $builder->setGroup($group);

        return $builder->build();
    }
}