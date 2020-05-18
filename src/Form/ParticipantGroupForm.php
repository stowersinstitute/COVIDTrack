<?php

namespace App\Form;

use App\Entity\ParticipantGroup;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ParticipantGroupForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Title',
            ])
            ->add('accessionId', TextType::class, [
                'label' => 'Accession ID',
            ])
            ->add('participantCount', IntegerType::class, [
                'label' => 'Number of Participants',
                'attr' => [
                    'min' => ParticipantGroup::MIN_PARTICIPANT_COUNT,
                    'max' => ParticipantGroup::MAX_PARTICIPANT_COUNT,
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
            'data_class' => ParticipantGroup::class,
            'empty_data' => function(FormInterface $form) {
                $accessionId = $form->get('accessionId')->getData();
                $count = $form->get('participantCount')->getData();

                return new ParticipantGroup($accessionId, $count);
            }
        ]);
    }
}
