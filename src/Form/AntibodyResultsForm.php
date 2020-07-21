<?php

namespace App\Form;

use App\Entity\Specimen;
use App\Entity\SpecimenResultAntibody;
use App\Entity\WellPlate;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form to enter Antibody Results
 */
class AntibodyResultsForm extends AbstractType
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
                        ->where('s.type = :type')
                        ->setParameter('type', Specimen::TYPE_BLOOD)
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
                'label' => 'Well Position',
                'required' => false,
                'disabled' => $isEditing,
                'attr' => [
                    'placeholder' => 'For example A04, G08, H12, etc',
                ],
            ])
            ->add('wellIdentifier', TextType::class, [
                'label' => 'Well ID',
                'required' => false,
                'attr' => [
                    'placeholder' => 'For example its Biobank Tube ID',
                ],
            ])
            ->add('conclusion', ChoiceType::class, [
                'label' => 'Conclusion',
                'choices' => SpecimenResultAntibody::getFormConclusions(),
                'placeholder' => '- Select -',
                'required' => true,
            ])
            ->add('signal', TextType::class, [
                'label' => 'Signal',
                'attr' => [
                    'placeholder' => 'For example 0 or 3',
                ],
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
