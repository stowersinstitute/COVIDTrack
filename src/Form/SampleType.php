<?php

namespace App\Form;


use App\Entity\Sample;
use App\Entity\WellPlate;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SampleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('title', TextType::class)
            ->add('status', ChoiceType::class, [
                'choices' => Sample::getFormStatuses()
            ])
            ->add('wellPlate', EntityType::class, [
                'class' => WellPlate::class,
                'choice_name' => 'barcode',
                'required' => false,
                'empty_data' => "",
                'placeholder' => '- None -',
            ])
            ->add('wellPlateRow', TextType::class)
            ->add('wellPlateColumn', TextType::class)
            ->add('save', SubmitType::class)
            ->getForm();

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Sample::class
        ]);
    }
}