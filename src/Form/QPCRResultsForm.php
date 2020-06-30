<?php

namespace App\Form;

use App\Entity\Specimen;
use App\Entity\SpecimenResultQPCR;
use App\Entity\SpecimenWell;
use App\Entity\WellPlate;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Regex;

class QPCRResultsForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $isEditing = $options['edit'];

        $builder
            ->add('specimen', EntityType::class, [
                'label' => 'Specimen Accession ID',
                'class' => Specimen::class,
                'placeholder' => '- Select -',
                'required' => true,
                'disabled' => $isEditing,
                // Sort by Accession ID
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('s')
                        ->orderBy('s.accessionId', 'ASC');
                },
            ])
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
                'label' => 'RNA Well Position',
                'required' => false,
                'disabled' => $isEditing,
                'attr' => [
                    'placeholder' => 'For example A4, G8, H12, etc',
                ],
            ])
            ->add('conclusion', ChoiceType::class, [
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
