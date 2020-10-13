<?php

namespace App\Form;

use App\AccessionId\SpecimenAccessionIdGenerator;
use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use App\Entity\WellPlate;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @see SpecimenRepository::filterByFormData for code that uses these properties
 */
class SpecimenFilterForm extends AbstractType
{
    /** @var EntityManagerInterface */
    private $em;

    public function __construct(EntityManagerInterface $em, SpecimenAccessionIdGenerator $specimenIdGen)
    {
        $this->em = $em;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->setMethod('GET')
            ->add('participantGroup', EntityType::class, [
                'label' => false,
                'class' => ParticipantGroup::class,
                'placeholder' => '- Any -',
                'required' => false,
                'query_builder' => function (EntityRepository $repo) {
                    // All groups, alphabetical by Title
                    return $repo->createQueryBuilder('g')->orderBy('g.title');
                },
            ])
            ->add('type', ChoiceType::class, [
                'label' => false,
                'choices' => Specimen::getFormTypes(),
                'placeholder' => '- Any -',
                'required' => false,
            ])
            ->add('status', ChoiceType::class, [
                'label' => false,
                'choices' => Specimen::getFormStatuses(),
                'placeholder' => '- Any -',
                'required' => false,
            ])
            ->add('collectedAt', DateType::class, [
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
            ->add('wellPlate', EntityType::class, [
                'label' => false,
                'class' => WellPlate::class,
                'placeholder' => '- Any -',
                'required' => false,
                'query_builder' => function (EntityRepository $repo) {
                    // All plates, alphabetical by Barcode
                    return $repo->createQueryBuilder('p')->orderBy('p.barcode');
                },
            ])
        ;

        $builder->getForm();
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            // Disable CSRF because this is a "GET" form that's only used for filtering
            'csrf_protection' => false,
        ]);
    }
}
