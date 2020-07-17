<?php

namespace App\Form;

use App\Entity\Specimen;
use App\Entity\SpecimenResultAntibody;
use App\Entity\SpecimenResultQPCR;
use App\Entity\SpecimenWell;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form to enter Antibody Results
 */
class AntibodyResultsForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var Specimen $specimen */
        $specimen = $options['specimen'];
        /** @var SpecimenResultAntibody $editResult */
        $editResult = $options['editResult'];

        $builder
            ->add('well', EntityType::class, [
                'label' => 'Wells Without Viral Results',
                'class' => SpecimenWell::class,
                'placeholder' => '- Select -',
                'required' => true,
                'disabled' => (bool)$editResult,
                'choices' => $editResult ? [$editResult->getWell()] : $specimen->getWellsWithoutAntibodyResult(),
            ])
            ->add('conclusion', ChoiceType::class, [
                'label' => 'Conclusion',
                'choices' => SpecimenResultAntibody::getFormConclusions(),
                'placeholder' => '- Select -',
                'required' => true,
            ])
            ->add('signal', ChoiceType::class, [
                'label' => 'Signal',
                'choices' => SpecimenResultAntibody::getFormSignal(),
                'placeholder' => '- Select -',
            ])
            ->add('save', SubmitType::class, [
                'attr' => ['class' => 'btn-primary'],
            ])
            ->getForm();
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $dataClass = SpecimenResultAntibody::class;
        $resolver->setDefaults([
            'data_class' => $dataClass,
            'empty_data' => function(FormInterface $form) {
                $well = $form->get('well')->getData();
                $conclusion = $form->get('conclusion')->getData();
                $signal = $form->get('signal')->getData();

                return new SpecimenResultAntibody($well, $conclusion, $signal);
            }
        ]);

        $resolver->setRequired('specimen');
        $resolver->addAllowedTypes('specimen', Specimen::class);

        $resolver->setDefault('editResult', null);
        $resolver->addAllowedTypes('editResult', [$dataClass, 'null']);
    }
}
