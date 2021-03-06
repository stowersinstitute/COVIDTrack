<?php

namespace App\Form;


use App\Entity\LabelPrinter;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LabelPrinterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('title', TextType::class)
            ->add('isActive', CheckboxType::class, [
                'value' => true,
                'required' => false,
            ])
            ->add('host', TextType::class)
            ->add('description', TextType::class)
            ->add('dpi', NumberType::class)
            ->add('mediaWidthIn', NumberType::class, $options=['label' => 'Media Width (Inches)'])
            ->add('mediaHeightIn', NumberType::class, $options=['label' => 'Media Height (Inches)'])
            ->add('save', SubmitType::class, [
                'attr' => ['class' => 'btn-primary'],
            ])
            ->getForm();

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => LabelPrinter::class
        ]);
    }
}