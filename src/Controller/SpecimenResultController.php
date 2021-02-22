<?php

namespace App\Controller;

use App\Entity\SpecimenResult;
use App\Entity\SpecimenResultQPCR;
use Doctrine\ORM\EntityManagerInterface;
use SensioLabs\AnsiConverter\AnsiToHtmlConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Interact with Specimen Results, no matter their type.
 *
 * @Route(path="/results")
 */
class SpecimenResultController extends AbstractController
{
    /**
     * Set selected Result to a specific SpecimenResult::WEBHOOK_STATUS_* constant.
     *
     * Required params:
     *
     * - resultIds (string[]) Array of SpecimenResult.id to update
     * - webHookStatus (string) SpecimenResult::WEBHOOK_STATUS_* constant value
     *
     * @Route(path="/web-hook/status", methods={"POST"}, name="results_web_hook_status")
     */
    public function webhookStatus(Request $request, EntityManagerInterface $em)
    {
        $this->denyAccessUnlessGranted('ROLE_WEB_HOOKS', 'Web Hooks Access Required', 'You must have Web Hooks permission to set Web Hook status');

        if (!$request->request->has('resultIds')) {
            return $this->createJsonErrorResponse('Param tubeAccessionIds is required');
        }
        if (!$request->request->has('webHookStatus')) {
            return $this->createJsonErrorResponse('Param webHookStatus is required');
        }

        // Verify valid web hook status
        $webHookStatus = $request->request->get('webHookStatus');
        try {
            SpecimenResult::ensureValidWebHookStatus($webHookStatus);
        } catch (\InvalidArgumentException $e) {
            // Return error to client as JSON errorMsg
            return $this->createJsonErrorResponse($e->getMessage());
        }

        // Convert to Tubes
        $repo = $em->getRepository(SpecimenResult::class);
        foreach ($request->request->get('resultIds') as $id) {
            $result = $repo->find($id);
            if (!$result) {
                return $this->createJsonErrorResponse(sprintf('Cannot find Result by ID: "%s"', $id));
            }

            // Finally set web hook status from client
            try {
                $result->setWebHookStatus($webHookStatus, 'Status manually set');
            } catch (\LogicException $e) {
                // Exception thrown when required data missing.
                // Return error to client as JSON errorMsg.
                return $this->createJsonErrorResponse(sprintf('Specimen Result ID "%s": %s', $id, $e->getMessage()));
            }
        }

        $em->flush();

        return new JsonResponse([
            'success' => true,
        ]);
    }

    /**
     * @Route(path="/publish", methods={"GET", "POST"}, name="results_publish")
     * @throws \Exception
     */
    public function publish(Request $request, KernelInterface $kernel): Response
    {

        $this->denyAccessUnlessGranted('ROLE_WEB_HOOKS');

        $form = $this->createFormBuilder()
            ->add('send', SubmitType::class, [
                'label' => 'Publish Results',
                'attr' => ['class' => 'btn-primary center-block'],
            ])
            ->getForm();

        $form->handleRequest($request);

        $commandOutput = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $application = new Application($kernel);
            $application->setAutoExit(false);

            $input = new ArrayInput([
                'command' => 'app:webhook:results',
            ]);

            $output = new BufferedOutput(
                OutputInterface::VERBOSITY_NORMAL,
                true // true for decorated
            );

            $application->run($input, $output);

            $converter = new AnsiToHtmlConverter();
            $content = $output->fetch();
            $commandOutput = $converter->convert($content);
        }

        return $this->render('results/publish-results.twig', [
            'form' => $form->createView(),
            'command_output' => $commandOutput,
        ]);
    }

    private function createJsonErrorResponse(string $msg): JsonResponse
    {
        return new JsonResponse([
            'errorMsg' => $msg,
        ], 400);
    }

    private function findResult($id): SpecimenResultQPCR
    {
        $q = $this->getDoctrine()
            ->getRepository(SpecimenResultQPCR::class)
            ->find($id);

        if (!$q) {
            throw new \InvalidArgumentException('Cannot find Result');
        }

        return $q;
    }
}
