<?php

namespace App\Form;

use App\Entity\Tube;
use App\Form\Type\RadioButtonGroupType;
use App\Form\Type\TextLookupType;
use App\Util\DateUtils;
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

        $resolver->setDefault('numDaysInPastForCollectionDate', 3);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
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
        foreach (range(6, 22, 2) as $hour) {
            $H = strlen($hour) === 1 ? sprintf('0%d', $hour) : (string)$hour;
            $date = \DateTime::createFromFormat('H:i', $H.':00');
            // Array Keys: Display value
            // Array Value: Submit value
            $times[$date->format('g:ia')] = $date->format('H:i');
        }

        $builder
            ->add('accessionId', TextLookupType::class, [
                'label' => 'Tube Label ID',
                'attr' => [ 'data-scanner-input' => null ],
                'button_text' => 'Lookup',
            ])
            ->add('tubeType', RadioButtonGroupType::class, [
                'choices' => [
                    'Saliva' => Tube::TYPE_SALIVA,
                    'Swab' => Tube::TYPE_SWAB,
                    'Blood' => Tube::TYPE_BLOOD,
                ],
                'required' => true,
                'constraints' => [new NotBlank()],
            ])
            ->add('collectedAtDate', RadioButtonGroupType::class, [
                'choices' => $days,
                'layout' => 'vertical',
                'label' => 'Collection Date',
                'required' => true,
                'constraints' => [new NotBlank()],
            ])
            ->add('collectedAtTime', RadioButtonGroupType::class, [
                'choices' => $times,
                'layout' => 'vertical',
                'label' => 'Approximate Collection Time',
                'required' => true,
                'constraints' => [new NotBlank()],
            ])
            ->add('save', SubmitType::class, [
                'label' => '+ Save and Add Another',
                'attr' => ['class' => 'btn-lg btn-primary'],
            ])
            ->add('review', SubmitType::class, [
                'label' => 'Save and Continue >',
                'attr' => ['class' => 'btn-lg btn-success'],
            ])
            ->getForm();
    }

    /**
     * Returns an array with the following keys:
     *
     *  day \DateTimeImmutable representing the day
     *  collectionTimes[] array of \DateTimeImmutable representing hours within that day
     *
     * The earliest date appears first in the array
     *
     * For example (dates converted to strings):
     *
     * [
     *   [
     *     'day' => '5/27/20',
     *     'collectionTimes' => ['06:00 AM', '08:00 AM', '10:00 AM', ... ]
     *   ],
     *   [
     *     'day' => '5/28/20',
     *     'collectionTimes' => ['06:00 AM', '08:00 AM', '10:00 AM', ... ]
     *   ],
     * ]
     *
     */
    protected function buildCollectedAtTimes($numDaysToDisplay)
    {
        $timesByDay = [];

        // 6am - 10pm in two-hour blocks
        $firstDay = (new \DateTimeImmutable())->modify(sprintf('-%s days', $numDaysToDisplay));
        $firstWindowAt = DateUtils::copyTimeOfDay(new \DateTimeImmutable('06:00:00'), $firstDay);
        $windowSizeHours = 2;
        $numWindowsPerDay = 9;

        $refDate = $firstWindowAt;
        // Populate each day
        for ($currDayIdx = 0; $currDayIdx < $numDaysToDisplay; $currDayIdx++) {
            $dayTimes = [
                'day' => $refDate,
                'collectionTimes' => [],
            ];

            // Generate hours for current day
            for ($hourIdx = 0; $hourIdx < $numWindowsPerDay; $hourIdx++) {
                $refTime = $refDate->modify(sprintf('+%s hours', ($hourIdx * $windowSizeHours)));

                $dayTimes['collectionTimes'][] = $refTime;
            }

            $refDate = $refDate->modify('+1 day');
            $timesByDay[] = $dayTimes;
        }

        return $timesByDay;
    }
}
