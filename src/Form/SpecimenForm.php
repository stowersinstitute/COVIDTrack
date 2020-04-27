<?php

namespace App\Form;

use App\Entity\CollectionEvent;
use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SpecimenForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('participantGroup', EntityType::class, [
                'class' => ParticipantGroup::class,
                'required' => true,
                'placeholder' => '- Select -',
            ])
            ->add('collectionEvent', EntityType::class, [
                'class' => CollectionEvent::class,
                'required' => true,
                'placeholder' => '- Select -',
            ])
            ->add('status', ChoiceType::class, [
                'choices' => Specimen::getFormStatuses(),
            ])
            ->add('save', SubmitType::class)
            ->getForm();
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => SpecimenFormData::class,
        ]);
    }
}
