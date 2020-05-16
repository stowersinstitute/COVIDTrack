<?php

namespace App\Form;

use App\Entity\Specimen;
use App\Entity\SpecimenResultQPCR;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SpecimenResultQPCRForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('specimen', EntityType::class, [
                'class' => Specimen::class,
                'placeholder' => '- Select -',
                'required' => true,
                // Sort by Accession ID
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('s')
                        ->orderBy('s.accessionId', 'ASC');
                },
            ])
            ->add('conclusion', ChoiceType::class, [
                'choices' => SpecimenResultQPCR::getFormConclusions(),
                'placeholder' => '- Select -',
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
            'data_class' => SpecimenResultQPCR::class,
            'empty_data' => function(FormInterface $form) {
                $specimen = $form->get('specimen')->getData();

                return new SpecimenResultQPCR($specimen);
            }
        ]);
    }
}
