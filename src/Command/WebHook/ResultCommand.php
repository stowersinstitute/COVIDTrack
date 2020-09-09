<?php

namespace App\Command\WebHook;

use App\Api\WebHook\Client\HttpClient;
use App\Api\WebHook\Client\ServiceNowHttpClient;
use App\Api\WebHook\Request\NewResultsWebHookRequest;
use App\Command\BaseAppCommand;
use App\Entity\SpecimenResult;
use App\Entity\SpecimenResultAntibody;
use App\Entity\SpecimenResultQPCR;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Fire web hooks related to Results.
 *
 * Usage to see next results available:
 *
 *     $ bin/console app:webhook:results -v --dry-run
 *
 * Usage send web hook:
 *
 *     $ bin/console app:webhook:results -v
 *
 */
class ResultCommand extends BaseAppCommand
{
    protected static $defaultName = 'app:webhook:results';

    /**
     * Prototype Request object. Internally cloned for each executed request.
     *
     * @var NewResultsWebHookRequest|null
     */
    private $request;

    /**
     * @var ServiceNowHttpClient
     */
    private $httpClient;

    /**
     * @param HttpClient $httpClient Compatible with HttpClient or any subclass like ServiceNowHttpClient
     * @param NewResultsWebHookRequest|null $request Prototype Request object. Only inject for testing purposes.
     */
    public function __construct(EntityManagerInterface $em, HttpClient $httpClient, ?NewResultsWebHookRequest $request = null)
    {
        parent::__construct($em);

        $this->request = $request;
        $this->httpClient = $httpClient;
    }

    protected function configure()
    {
        $this
            ->setDescription('Publishes Specimen Results changes to web hook URL')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'When given, the Web Hook API URL will not be contacted')
            ->addOption('skip-saving', null, InputOption::VALUE_NONE, 'Whether to save timestamp when results successfully published')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // These are the Specimen Results to be sent to Web Hooks
        $newResults = $this->findResults();

        if (count($newResults) < 1) {
            $this->outputDebug('No results to send');
            return 0;
        }

        $this->outputDebugResultsByGroup($newResults);

        $request = $this->buildWebHookRequest($newResults);

        $this->outputDebug('<comment>Pending Request Body:</comment>');
        $this->outputDebug($request->toJson());
        $this->outputDebug('');

        // Abort now if setting CLI option --dry-run
        if ($this->input->getOption('dry-run')) {
            return 0;
        }

        // Send Request to Web Hook API
        try {
            $response = $this->httpClient->post($request);
        } catch (ClientException | ServerException $e) {
            // Known Exception type where we have the Request and Response
            $output->writeln('<error>Exception calling Web Hook endpoint</error>');
            $output->writeln('Request Body:');
            $output->writeln($e->getRequest()->getBody() . "\n");

            $exceptionResponse = $e->getResponse();
            if ($exceptionResponse) {
                $output->writeln(sprintf('Response Code: %d %s', $exceptionResponse->getStatusCode(), $exceptionResponse->getReasonPhrase()));
                $output->writeln('Response Body:');
                $output->writeln($exceptionResponse->getBody() . "\n");
            }

            $output->writeln('Exception Class: ' . get_class($e));
            $output->writeln('Exception Message: ' . $e->getMessage());
            return 1;
        } catch (\Exception $e) {
            // Not sure what Exception occurred, dump some generic info
            $output->writeln('Unknown Exception: ' . $e->getMessage());
            $output->writeln('Exception Class: ' . get_class($e));
            return 1;
        }

        if (401 === $response->getStatusCode()) {
            $output->writeln('Authentication failure. Check credential constants in file .env.local');
            return 1;
        }
        $this->outputDebug('<info>√ Authentication success</info>');

        if (500 === $response->getStatusCode()) {
            $output->writeln('500 Response from Web Hook endpoint');
            return 1;
        }

        if (200 !== $response->getStatusCode()) {
            $output->writeln(sprintf('Unhandled HTTP Response Code %d from Web Hook endpoint', $response->getStatusCode()));
            return 1;
        }

        $this->outputDebug("<info>√ Response Success</info>\n");

        $this->outputDebug('<comment>Response Headers:</comment>');
        foreach ($response->getHeaders() as $name => $values) {
            $this->outputDebug($name . ": " . implode(", ", $values));
        }
        $this->outputDebug('');

        $this->outputDebug('<comment>Response Body:</comment>');
        $this->outputDebug($response->getBodyContents());
        $this->outputDebug('');

        // Update SpecimenResult with data from Response
        $save = !$this->input->getOption('skip-saving');
        if ($save) {
            $this->outputDebug("<comment>Updating Results Web Hook Status...</comment>\n");
            $response->updateResultWebHookStatus($newResults);

            $this->em->flush();
        }

        $this->outputDebug("<comment>Done!</comment>\n");

        return 0;
    }

    /**
     * Find latest Results to send to Web Hook
     *
     * @return SpecimenResult[]
     */
    private function findResults(): array
    {
        $antibody = $this->em
            ->getRepository(SpecimenResultAntibody::class)
            ->findDueForWebHook();

        $viral = $this->em
            ->getRepository(SpecimenResultQPCR::class)
            ->findDueForWebHook();

        return array_merge($antibody, $viral);
    }

    /**
     * Output debug info about results sent for each Participant Group.
     * Use CLI flag "-v" to print results.
     *
     * @param SpecimenResult[] $newResults
     */
    private function outputDebugResultsByGroup(array $newResults)
    {
        $this->outputDebug('<comment>Queued Results:</comment>');
        foreach ($newResults as $result) {
            $groupId = $result->getSpecimen()->getParticipantGroup()->getExternalId();
            $reportedAt = $result->getUpdatedAt()->format('Y-m-d H:i:s');
            $conclusion = $result->getConclusionText();

            switch (get_class($result)) {
                case SpecimenResultAntibody::class:
                    $resultType = 'Antibody';
                    break;
                case SpecimenResultQPCR::class:
                    $resultType = 'Viral';
                    break;
            }

            $this->outputDebug(sprintf('* %s %s %s %s', $groupId, $reportedAt, $resultType, $conclusion));
        }
        $this->outputDebug("");
    }

    /**
     * Build Request object to send results to Web Hook API.
     *
     * @param SpecimenResult[] $newResults
     */
    private function buildWebHookRequest(array $newResults): NewResultsWebHookRequest
    {
        if (!$this->request) {
            $this->request = new NewResultsWebHookRequest();
        }

        $request = clone $this->request;
        $request->setResults($newResults);

        return $request;
    }
}
