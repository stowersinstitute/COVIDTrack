<?php

namespace App\Controller;

use App\Configuration\AppConfiguration;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Actions for configuring the application.
 *
 * @Route(path="/config")
 */
class ConfigController extends AbstractController
{
    public const TUBE_COLLECTED_AT_START = 'kiosk_tube_collectedAt_start';
    public const TUBE_COLLECTED_AT_END = 'kiosk_tube_collectedAt_end';
    public const TUBE_COLLECTION_TIME_EXPERIENCE = 'kiosk_tube_collection_experience';

    public const TUBE_COLLECTION_TIME_OPTION_AUTO = 'auto';
    public const TUBE_COLLECTION_TIME_OPTION_PRESELECT = 'preselect';
    public const TUBE_COLLECTION_TIME_OPTION_MANUAL = 'manual';

    /**
     * Configure options for how the Kiosk application works.
     *
     * @Route(path="/kiosk", methods={"GET", "POST"}, name="config_kiosk")
     */
    public function kiosk(Request $request, AppConfiguration $appConfig)
    {
        $this->denyAccessUnlessGranted('ROLE_CONFIG_ALL', 'Access Denied', 'You do not have permission to view this page.');

        $hoursChoices = [
            '12:00am' => '0',
            '1:00am' => '1',
            '2:00am' => '2',
            '3:00am' => '3',
            '4:00am' => '4',
            '5:00am' => '5',
            '6:00am' => '6',
            '7:00am' => '7',
            '8:00am' => '8',
            '9:00am' => '9',
            '10:00am' => '10',
            '11:00am' => '11',
            '12:00pm' => '12',
            '1:00pm' => '13',
            '2:00pm' => '14',
            '3:00pm' => '15',
            '4:00pm' => '16',
            '5:00pm' => '17',
            '6:00pm' => '18',
            '7:00pm' => '19',
            '8:00pm' => '20',
            '9:00pm' => '21',
            '10:00pm' => '22',
            '11:00pm' => '23',
        ];
        $form = $this->createFormBuilder()
            ->add(self::TUBE_COLLECTED_AT_START, ChoiceType::class, [
                'label' => 'Collection Time: Start',
                'help' => 'Add Tube: Earliest Collection Time selectable',
                'data' => $appConfig->get(self::TUBE_COLLECTED_AT_START),
                'choices' => $hoursChoices,
                'placeholder' => '- Select -',
                'required' => true,
            ])
            ->add(self::TUBE_COLLECTED_AT_END, ChoiceType::class, [
                'label' => 'Collection Time: End',
                'help' => 'Add Tube: Latest Collection Time selectable',
                'data' => $appConfig->get(self::TUBE_COLLECTED_AT_END),
                'choices' => $hoursChoices,
                'placeholder' => '- Select -',
                'required' => true,
                'constraints' => [
                    new Callback(
                        function ($max, ExecutionContextInterface $context) use (&$form) {
                            $min = $form->getData()[self::TUBE_COLLECTED_AT_START];

                            if (null === $max || null === $min) return;

                            if ($max < $min ) {
                                $context->addViolation('This time must be after Collection Time Start');
                            }
                        }
                    ),
                ],
            ])
            ->add(self::TUBE_COLLECTION_TIME_EXPERIENCE, ChoiceType::class, [
                'label' => 'Drop Off Collection Time Handling',
                'help' => 'Add Tube: Controls if and how to present the "Collection Time" input to participants',
                'data' => $appConfig->get(self::TUBE_COLLECTION_TIME_EXPERIENCE),
                'choices' => [
                    'Automatically set to the time of drop off' => self::TUBE_COLLECTION_TIME_OPTION_AUTO,
                    'Kiosk interface is defaulted to the current time' => self::TUBE_COLLECTION_TIME_OPTION_PRESELECT,
                    'Kiosk interface forces user time selection' => self::TUBE_COLLECTION_TIME_OPTION_MANUAL,
                ],
                'placeholder' => '- Select -',
                'required' => true,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Save',
                'attr' => ['class' => 'btn-primary'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Disable database interaction until all config options updated
            $appConfig->setAutoFlush(false);

            foreach ($form->getData() as $referenceId => $value) {
                $appConfig->set($referenceId, $value);
            }

            // Persist config options
            $appConfig->setAutoFlush(true);

            $this->addFlash('success', 'Configuration saved.');

            // Fall-through to redisplay kiosk form
        }

        return $this->render('config/kiosk.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
