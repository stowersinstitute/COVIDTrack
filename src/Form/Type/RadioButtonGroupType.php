<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RadioButtonGroupType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'choices' => [],
            'layout' => 'horizontal',
        ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['choices'] = $options['choices'];

        switch ($options['layout']) {
            case 'vertical':
                $view->vars['layout'] = 'btn-group-vertical';
                break;

            case 'horizontal':
            default:
                $view->vars['layout'] = 'btn-group btn-group-justified';
                break;
        }
    }

    public function getParent()
    {
        return TextType::class;
    }
}
