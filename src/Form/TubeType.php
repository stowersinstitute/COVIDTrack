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
            $date = new \DateTime();
            $date->sub(new \DateInterval(sprintf('P%dD', $daysAgo)));
            $days[$date->format('M d')] = $date->format('Y-m-d');
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
            ->add('collectedAt', RadioButtonGroupType::class, [
                'choices' => $days,
                'layout' => 'vertical'
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Save Tube and Add Another'
            ])
            ->add('done', SubmitType::class, [
                'label' => 'Save Tube and Complete Drop Off'
            ])
            ->getForm();

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Tube::class
        ]);
    }
}