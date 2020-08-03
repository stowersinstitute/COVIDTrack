<?php

namespace App\Form;

use App\Entity\Specimen;
use App\Entity\SpecimenResultQPCR;
use App\Entity\SpecimenWell;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class QPCRResultsForm extends AbstractType
{
    /**
     * @var Specimen
     */
    private $specimen;

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->specimen = $options['specimen'];
        /** @var SpecimenResultQPCR $editResult */
        $editResult = $options['editResult'];

        $builder
            ->add('well', EntityType::class, [
                'label' => 'Wells containing this Specimen',
                'class' => SpecimenWell::class,
                'placeholder' => '- Unknown -',
                'required' => false,
                'disabled' => ($editResult && $editResult->getWell()),
                'choices' => $this->specimen->getWells(),
            ])
            ->add('conclusion', ChoiceType::class, [
                'label' => 'Conclusion',
                'choices' => SpecimenResultQPCR::getFormConclusions(),
                'placeholder' => '- Select -',
                'required' => true,
            ])
            ->add('CT1', TextType::class, [
                'label' => 'Ct1',
                'required' => false,
            ])
            ->add('CT1AmpScore', TextType::class, [
                'label' => 'Amp Score1',
                'required' => false,
            ])
            ->add('CT2', TextType::class, [
                'label' => 'Ct2',
                'required' => false,
            ])
            ->add('CT2AmpScore', TextType::class, [
                'label' => 'Amp Score2',
                'required' => false,
            ])
            ->add('CT3', TextType::class, [
                'label' => 'Ct3',
                'required' => false,
            ])
            ->add('CT3AmpScore', TextType::class, [
                'label' => 'Amp Score3',
                'required' => false,
            ])
            ->add('save', SubmitType::class, [
                'attr' => ['class' => 'btn-primary'],
            ])
            ->getForm();
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $dataClass = SpecimenResultQPCR::class;
        $resolver->setDefaults([
            'data_class' => $dataClass,
            'empty_data' => function(FormInterface $form) {
                $well = $form->get('well')->getData();
                $conclusion = $form->get('conclusion')->getData();

                if ($well) {
                    return SpecimenResultQPCR::createFromWell($well, $conclusion);
                }

                return SpecimenResultQPCR::createFromSpecimen($this->specimen, $conclusion);
            }
        ]);

        $resolver->setRequired('specimen');
        $resolver->addAllowedTypes('specimen', Specimen::class);

        $resolver->setDefault('editResult', null);
        $resolver->addAllowedTypes('editResult', [$dataClass, 'null']);
    }
}
