<?php

namespace App\Form;

use App\Entity\Tube;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
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
            ->add('tubeType', ChoiceType::class, [
                'label' => false,
                'choices' => Tube::getValidTubeTypes(),
                'placeholder' => '- Any -',
                'required' => false,
            ])
            ->add('status', ChoiceType::class, [
                'label' => false,
                'choices' => Tube::getValidStatuses(),
                'placeholder' => '- Any -',
                'required' => false,
            ])
            ->add('checkInDecision', ChoiceType::class, [
                'label' => false,
                'choices' => Tube::getValidCheckInDecisions(),
                'placeholder' => '- Any -',
                'required' => false,
            ])
            ->add('createdAt', DateType::class, [
                'attr' => [
                    'placeholder' => '- Any Date -',
                ],
                'label' => false,
                'html5' => false, // Frontend uses JS datepicker, explicitly enabled client-side
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd',
                'input'  => 'datetime_immutable',
                'required' => false,
            ])
            ->add('externalProcessingAt', DateType::class, [
                'attr' => [
                    'placeholder' => '- Any Date -',
                ],
                'label' => false,
                'html5' => false, // Frontend uses JS datepicker, explicitly enabled client-side
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd',
                'input'  => 'datetime_immutable',
                'required' => false,
            ]);

        // Web Hook fields only visible to some users
        if ($options['userCanViewWebHooks']) {
            $builder
                ->add('webHookStatus', ChoiceType::class, [
                'label' => false,
                'choices' => Tube::getValidWebHookStatuses(),
                'placeholder' => '- Any -',
                'required' => false,
            ])
            ->add('webHookLastTriedPublishingAt', DateType::class, [
                'attr' => [
                    'placeholder' => '- Any Date -',
                ],
                'label' => false,
                'html5' => false, // Frontend uses JS datepicker, explicitly enabled client-side
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd',
                'input'  => 'datetime_immutable',
                'required' => false,
            ]);
        }

        $builder->getForm();
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            // Disable CSRF because this is a "GET" form that's only used for filtering
            'csrf_protection' => false,

            // Used to enable/disable ability to filter on web hook fields
            'userCanViewWebHooks' => false,
        ]);
    }
}
