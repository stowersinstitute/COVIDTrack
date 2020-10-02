<?php

namespace App\Form;

use App\AccessionId\SpecimenAccessionIdGenerator;
use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use App\Entity\Tube;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SpecimenForm extends AbstractType
{
    /** @var EntityManagerInterface */
    private $em;

    /**
     * @var SpecimenAccessionIdGenerator
     */
    private $specimenIdGen;

    public function __construct(EntityManagerInterface $em, SpecimenAccessionIdGenerator $specimenIdGen)
    {
        $this->em = $em;
        $this->specimenIdGen = $specimenIdGen;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('participantGroup', EntityType::class, [
                'label' => 'Participant Group',
                'class' => ParticipantGroup::class,
                'required' => true,
                'placeholder' => '- Select -',
                'choices' => $this->em->getRepository(ParticipantGroup::class)->findActive(),
            ])
            ->add('tube', EntityType::class, [
                'label' => 'Tube',
                'class' => Tube::class,
                'required' => true,
                'placeholder' => '- Select -',
                'choices' => $this->em->getRepository(Tube::class)->findWithoutSpecimen(),
            ])
            ->add('type', ChoiceType::class, [
                'choices' => Specimen::getFormTypes(),
                'placeholder' => '- Select -',
                'required' => false,
            ])
            ->add('collectedAt', DateTimeType::class, [
                'label' => 'Collection Time',
                'input' => 'datetime',
                'required' => false,
            ])
            ->add('status', ChoiceType::class, [
                'choices' => Specimen::getFormStatuses(),
            ])
            ->add('save', SubmitType::class, [
                'attr' => ['class' => 'btn-primary'],
            ])
            ->getForm();
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Specimen::class,
            'empty_data' => function(FormInterface $form) {
                $accessionId = $this->specimenIdGen->generate();
                $group = $form->get('participantGroup')->getData();
                $tube = $form->get('tube')->getData();

                return new Specimen($accessionId, $group, $tube);
            }
        ]);
    }
}
