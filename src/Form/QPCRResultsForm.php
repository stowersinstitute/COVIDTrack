<?php

namespace App\Form;

use App\Entity\SpecimenResultQPCR;
use App\Entity\WellPlate;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class QPCRResultsForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $isEditing = $options['edit'];

        $builder
            ->add('wellPlate', EntityType::class, [
                'label' => 'Well Plate Barcode',
                'class' => WellPlate::class,
                'placeholder' => '- Select -',
                'required' => true,
                'disabled' => $isEditing,
                // Sort by Barcode
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('p')
                        ->orderBy('p.barcode', 'ASC');
                },
            ])
            ->add('position', TextType::class, [
                'label' => 'Well Position',
                'required' => true,
                'disabled' => $isEditing,
                'attr' => [
                    'placeholder' => 'For example A4, G8, H12, etc',
                ],
            ])
            ->add('conclusion', ChoiceType::class, [
                'label' => 'Conclusion',
                'choices' => SpecimenResultQPCR::getFormConclusions(),
                'placeholder' => '- Select -',
                'required' => true,
            ])
            ->add('save', SubmitType::class, [
                'attr' => ['class' => 'btn-primary'],
            ])
            ->getForm();
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'edit' => false,
        ]);
    }
}
