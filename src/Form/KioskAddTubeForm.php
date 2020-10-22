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
    // 12:00am (Midnight)
    public const COLLECTION_TIME_DEFAULT_MIN = 0;
    // 11:00pm
    public const COLLECTION_TIME_DEFAULT_MAX = 23;

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'numDaysInPastForCollectionDate' => 0,
            'minCollectionTimeHour' => null,
            'maxCollectionTimeHour' => null,
            'participantGroup' => null,
        ]);

        $resolver->setAllowedTypes('participantGroup', ParticipantGroup::class);

        $isValidCollectionTime = function ($value) {
            // false so in_array() allows $value to be NULL, string number or int number
            // $value is NULL when Kiosk Config value is not set
            // $value is a string number when stored in Kiosk Config database setting
            // $value can be integer number when passed programmatically from somewhere else
            $strictComparison = false;
            $valid = range(static::COLLECTION_TIME_DEFAULT_MIN, static::COLLECTION_TIME_DEFAULT_MAX);

            return in_array($value, $valid, $strictComparison);
        };
        $resolver->setAllowedValues('minCollectionTimeHour', $isValidCollectionTime);
        $resolver->setAllowedValues('maxCollectionTimeHour', $isValidCollectionTime);

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
        foreach ($this->getCollectionTimeHours($options) as $hour) {
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
                'data' => count($days) == 1 ? reset($days) : null,
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

    /**
     * Get range of hours (0 thru 23) allowed to be selected for Collection Time.
     *
     * @param array $options Form Options passed to configureOptions($options)
     * @return int[]
     */
    private function getCollectionTimeHours(array $options): array
    {
        $min = self::COLLECTION_TIME_DEFAULT_MIN;
        if ($options['minCollectionTimeHour'] !== null) {
            $min = $options['minCollectionTimeHour'];
        }

        $max = self::COLLECTION_TIME_DEFAULT_MAX;
        if ($options['maxCollectionTimeHour'] !== null) {
            $max = $options['maxCollectionTimeHour'];
        }

        return range($min, $max, 1);
    }
}
