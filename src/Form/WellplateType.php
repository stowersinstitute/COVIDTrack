<?php

namespace App\Form;


use App\Entity\Sample;
use App\Entity\Wellplate;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WellplateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('barcode', TextType::class)
            ->add('status', ChoiceType::class, [
                'choices' => Sample::getFormStatuses()
            ])
            ->add('save', SubmitType::class)
            ->getForm();

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Wellplate::class,
        ]);
    }
}