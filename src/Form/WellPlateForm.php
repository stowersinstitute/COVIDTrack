<?php

namespace App\Form;

use App\Entity\WellPlate;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WellPlateForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('barcode', TextType::class, [
                'label' => 'Barcode',
                'required' => true,
            ])
            ->add('storageLocation', TextType::class, [
                'label' => 'Storage Location',
            ])
            ->add('save', SubmitType::class, [
                'attr' => ['class' => 'btn-primary'],
            ])
            ->getForm();
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => WellPlate::class,
            'empty_data' => function(FormInterface $form) {
                $barcode = $form->get('barcode')->getData();
                $plate = new WellPlate($barcode);

                return $plate;
            }
        ]);
    }
}
