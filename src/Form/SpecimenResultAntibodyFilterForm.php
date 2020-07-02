<?php

namespace App\Form;

use App\Entity\SpecimenResultAntibody;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @see SpecimenResultAntibodyRepository::filterByFormData for code that uses these properties
 */
class SpecimenResultAntibodyFilterForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->setMethod('GET')
            ->add('conclusion', ChoiceType::class, [
                'label' => 'Conclusion',
                'choices' => SpecimenResultAntibody::getFormConclusions(),
                'placeholder' => '- Any -',
                'required' => false,
            ])
            ->add('createdAtOn', DateType::class, [
                'widget' => 'single_text', // HTML5 text field
                'label' => 'Reported At',
                'placeholder' => ' - Any -',
                'required' => false,
            ])
            ->add('submit', SubmitType::class, [
                'label' => ' Filter',
                'attr' => ['class' => 'btn-primary fa fa-filter'],
            ])
            ->getForm();
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            // Disable CSRF because this is a "GET" form that's only used for filtering
            'csrf_protection' => false,
        ]);
    }
}
