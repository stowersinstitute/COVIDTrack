<?php

namespace App\Form;

use App\Entity\Tube;
use App\Form\Type\RadioButtonGroupType;
use App\Form\Type\TextLookupType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class KioskAddTubeForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $days = [];
        foreach (range(3, 0) as $daysAgo) {
            $date = new \DateTime(sprintf('-%d days', $daysAgo));
            $days[$date->format('M d')] = $date->format('Y-m-d');
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
}
