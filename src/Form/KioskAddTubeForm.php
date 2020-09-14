<?php

namespace App\Form;

use App\Entity\ParticipantGroup;
use App\Entity\Tube;
use App\Form\Type\CollectionTimeType;
use App\Form\Type\RadioButtonGroupType;
use App\Form\Type\TextLookupType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\OptionsResolver\OptionsResolver;

class KioskAddTubeForm extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'numDaysInPastForCollectionDate' => 3,
            'participantGroup' => null,
        ]);

        $resolver->setAllowedTypes('participantGroup', ParticipantGroup::class);

        $resolver->setRequired('participantGroup');
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var ParticipantGroup $group */
        $group = $options['participantGroup'];

        $days = [];
        foreach (range($options['numDaysInPastForCollectionDate'], 0) as $daysAgo) {
            $date = new \DateTime(sprintf('-%d days', $daysAgo));
            $prnDate = $date->format('M d');
            if ($daysAgo === 0) {
                $prnDate .= " (Today)";
            }
            $days[$prnDate] = $date->format('Y-m-d');
        }

        $times = [];
        foreach (range(0, 22, 2) as $hour) {
            $H = strlen($hour) === 1 ? sprintf('0%d', $hour) : (string)$hour;
            $date = \DateTime::createFromFormat('H:i', $H.':00');

            $userReadableText = $date->format('g:ia');
            if ($userReadableText === '12:00am') {
                $userReadableText .= ' (Midnight)';
            }
            if ($userReadableText === '12:00pm') {
                $userReadableText .= ' (Noon)';
            }

            $formSubmitValue = $date->format('H:i');

            $times[$userReadableText] = $formSubmitValue;
        }

        $allowedDropOffTypeChoices = [];
        if ($group->acceptsSalivaSpecimens()) {
            $allowedDropOffTypeChoices['Saliva'] = Tube::TYPE_SALIVA;
        }
        if($group->acceptsBloodSpecimens()) {
            $allowedDropOffTypeChoices['Blood'] = Tube::TYPE_BLOOD;
        }

        $builder
            ->add('accessionId', TextLookupType::class, [
                'label' => 'Tube Label ID',
                'attr' => [ 'data-scanner-input' => null ],
                'button_text' => 'Lookup',
            ])
            ->add('tubeType', RadioButtonGroupType::class, [
                'choices' => $allowedDropOffTypeChoices,
                'required' => true,
                'constraints' => [new NotBlank()],
                'data' => count($allowedDropOffTypeChoices) == 1 ? reset($allowedDropOffTypeChoices) : null,
            ])
            ->add('collectedAtDate', RadioButtonGroupType::class, [
                'choices' => $days,
                'layout' => 'vertical',
                'label' => 'Collection Date',
                'required' => true,
                'constraints' => [new NotBlank()],
            ])
            ->add('collectedAtTime', CollectionTimeType::class, [
                'choices' => $times,
                'layout' => 'vertical',
                'label' => 'Approximate Collection Time',
                'required' => true,
                'constraints' => [new NotBlank()],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Save and Continue >',
                'attr' => ['class' => 'btn-lg btn-success'],
            ])
            ->getForm();
    }
}
