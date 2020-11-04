<?php

namespace App\Form;

use App\AccessionId\SpecimenAccessionIdGenerator;
use App\Entity\ParticipantGroup;
use App\Entity\ParticipantGroupRepository;
use App\Entity\Specimen;
use App\Entity\Tube;
use App\Repository\TubeRepository;
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
        /**
         * Specimen being edited, else NULL when form is creating a new Specimen
         * @var Specimen $specimen
         */
        $specimen = $builder->getData();

        $builder
            ->add('participantGroup', EntityType::class, [
                'label' => 'Participant Group',
                'class' => ParticipantGroup::class,
                'required' => true,
                'placeholder' => '- Select -',
                'query_builder' => function(ParticipantGroupRepository $repo) {
                    return $repo->getDefaultQueryBuilder('g');
                },
                'choice_label' => function(ParticipantGroup $g) {
                    $display = (string)$g;

                    return $g->isActive() ? $display : $display.' (Inactive)';
                },
            ])
            ->add('tube', EntityType::class, [
                'label' => 'Tube',
                'class' => Tube::class,
                'required' => true,
                'placeholder' => '- Select -',
                // Disabled when editing existing Specimen
                'disabled' => (bool)$specimen,
                // Help explains why disabled
                'help' => (bool)$specimen ? 'Tube cannot be changed' : '',
                'query_builder' => function(TubeRepository $repo) use ($specimen) {
                    $qb = $repo->createQueryBuilder('t')
                        ->where('t.specimen IS NULL')
                        ->orderBy('t.accessionId');

                    // Ensure Specimen being edited has current Tube in select list
                    if ($specimen) {
                        $qb->orWhere('t.specimen = :specimenBeingEdited')
                            ->setParameter('specimenBeingEdited', $specimen);
                    }

                    return $qb;
                },
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
