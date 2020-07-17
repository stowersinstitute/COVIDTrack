<?php

namespace App\Form;

use App\Entity\Specimen;
use App\Entity\SpecimenResultQPCR;
use App\Entity\SpecimenWell;
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
        /** @var Specimen $specimen */
        $specimen = $options['specimen'];

        $builder
            ->add('well', EntityType::class, [
                'label' => 'Well For Results',
                'class' => SpecimenWell::class,
                'placeholder' => '- Select -',
                'required' => true,
                'disabled' => $isEditing,
                'choices' => $specimen->getWells(),
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

        $resolver->setRequired('specimen');
        $resolver->addAllowedTypes('specimen', Specimen::class);
    }
}
