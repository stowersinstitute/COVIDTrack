<?php

namespace App\Form;

use App\Entity\Tube;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @see TubeRepository::filterByFormData for code that uses these properties
 */
class TubeFilterForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->setMethod('GET')
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => Tube::getValidStatuses(),
                'placeholder' => '- Any -',
                'required' => false,
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
