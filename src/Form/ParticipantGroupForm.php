<?php

namespace App\Form;

use App\AccessionId\ParticipantGroupAccessionIdGenerator;
use App\Entity\ParticipantGroup;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ParticipantGroupForm extends AbstractType
{
    /**
     * @var ParticipantGroupAccessionIdGenerator
     */
    private $accessionIdGen;

    public function __construct(ParticipantGroupAccessionIdGenerator $gen)
    {
        $this->accessionIdGen = $gen;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Title',
            ])
            ->add('externalId', TextType::class, [
                'label' => 'External ID',
                'required' => false,
            ])
            ->add('participantCount', IntegerType::class, [
                'label' => 'Number of Participants',
                'attr' => [
                    'min' => ParticipantGroup::MIN_PARTICIPANT_COUNT,
                    'max' => ParticipantGroup::MAX_PARTICIPANT_COUNT,
                ],
            ])
            ->add('isControl', ChoiceType::class, [
                'label' => 'Is Control Group?',
                'choices' => ['Yes' => true, 'No' => false],
                'data' => false,
                'expanded' => true,
                'required' => true,
            ])
            ->add('enabledForResultsWebHooks', ChoiceType::class, [
                'label' => 'Publish Results to Web Hooks?',
                'choices' => ['Yes' => true, 'No' => false],
                'expanded' => true,
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
            'data_class' => ParticipantGroup::class,
            'empty_data' => function(FormInterface $form) {
                $accessionId = $this->accessionIdGen->generate();
                $count = $form->get('participantCount')->getData();

                return new ParticipantGroup($accessionId, $count);
            }
        ]);
    }
}
