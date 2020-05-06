<?php

namespace App\Form;

use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SpecimenForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('participantGroup', EntityType::class, [
                'class' => ParticipantGroup::class,
                'required' => true,
                'placeholder' => '',
            ])
            ->add('type', ChoiceType::class, [
                'choices' => Specimen::getFormTypes(),
                'placeholder' => '- Select -',
                'required' => false,
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
            'data_class' => Specimen::class,
            'empty_data' => function(FormInterface $form) {
                $accessionId = 'CID'.time(); // TODO: CVDLS-30 Replace with real accession ID prefix
                $group = $form->get('participantGroup')->getData();
                $s = new Specimen($accessionId, $group);

                return $s;
            }
        ]);
    }
}
