<?php
namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TextLookupType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'input_group_attr' => [],
            'button_text' => 'Lookup',
            'button_attr' => [],
            'button_icon_class' => '',
        ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['input_group_attr'] = $options['input_group_attr'];
        $view->vars['button_text'] = $options['button_text'];
        $view->vars['button_attr'] = $options['button_attr'];
        $view->vars['button_icon_class'] = $options['button_icon_class'];
    }

    public function getParent()
    {
        return TextType::class;
    }
}

