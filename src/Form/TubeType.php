<?php

namespace App\Form;


use App\Entity\Tube;
use App\Form\Type\RadioButtonGroupType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TubeType extends AbstractType
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
            ->add('accessionId', TextType::class, [
                'label' => 'Tube Label ID'
            ])
            ->add('tubeType', RadioButtonGroupType::class, [
                'choices' => [
                    'Saliva' => Tube::TYPE_SALIVA,
                    'Swab' => Tube::TYPE_SWAB,
                    'Blood' => Tube::TYPE_BLOOD,
                ],
                'required' => true
            ])
            ->add('collectedAtDate', RadioButtonGroupType::class, [
                'choices' => $days,
                'layout' => 'vertical',
                'label' => 'Collection Date',
                'required' => true,
            ])
            ->add('collectedAtTime', RadioButtonGroupType::class, [
                'choices' => $times,
                'layout' => 'vertical',
                'label' => 'Approximate Collection Time',
                'required' => true,
            ])
            ->add('save', SubmitType::class, [
                'label' => '+ Save and Add Another',
                'attr' => ['class' => 'btn-lg btn-primary'],
            ])
            ->add('done', SubmitType::class, [
                'label' => '√ Save and Complete Drop Off',
                'attr' => ['class' => 'btn-lg btn-success'],
            ])
            ->getForm();

    }
}