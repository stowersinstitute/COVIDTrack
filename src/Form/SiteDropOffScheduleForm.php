<?php


namespace App\Form;


use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;

class SiteDropOffScheduleForm extends AbstractType
{
    const DAYS = ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'];

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->addWeekdayControls($builder);

        // Start time picker
        $builder->add('startTime', TimeType::class, [
            'label' => 'Open Time',
        ]);

        // End time picker
        $builder->add('endTime', TimeType::class, [
            'label' => 'Close Time',
        ]);

        // Window length dropdown
        $builder->add('interval', ChoiceType::class, [
            'label' => 'Drop-off Window',
            'data' => 30,
            'choices' => [
                '15 Minutes' => 15,
                '20 Minutes' => 20,
                '30 Minutes' => 30,
                '1 Hour'     => 60,
            ],
        ]);

        $builder->add('submit', SubmitType::class, [
            'label' => 'Save Schedule',
            'attr' => ['class' => 'btn-primary'],
        ]);

        $builder->getForm();
    }

    public function addWeekdayControls(FormBuilderInterface $builder)
    {
        foreach (static::DAYS as $day) {
            // Checkbox to enable scheduling on that day
            $builder->add($day . '_enabled', CheckboxType::class, [
                'label' => 'Enable',
                'required' => false,
            ]);

            // Enable if we need day-specific start/end times
            /*
            // Start time picker
            $builder->add($day . '_startTime', TimeType::class, [
                'label' => 'Open Time',
            ]);

            // End time picker
            $builder->add($day . '_endTime', TimeType::class, [
                'label' => 'Close Time',
            ]);

            // Window length dropdown
            $builder->add($day . '_interval', ChoiceType::class, [
                'label' => 'Drop-off Window',
                'data' => 30,
                'choices' => [
                    '15 Minutes' => 15,
                    '20 Minutes' => 20,
                    '30 Minutes' => 30,
                    '1 Hour'     => 60,
                ],
            ]);
            */
        }
    }
}