<?php


namespace App\Form;


use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;

class GenericExcelImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('excelFile', FileType::class, [
                'label' => 'Excel File',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new File([
                        'maxSize' => ini_get('upload_max_filesize'),
                        'mimeTypes' => [
                            'application/vnd.ms-excel', // office 2007
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // modern office
                            'text/csv', // comma-separated values
                        ]
                    ])
                ]
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Upload',
            ])
            ->getForm();

    }
}