<?php

namespace App\Command\WebHook;

use App\Api\WebHook\Client\HttpClient;
use App\Api\WebHook\Client\ServiceNowHttpClient;
use App\Api\WebHook\Request\TubeExternalProcessingWebHookRequest;
use App\Api\WebHook\Request\WebHookRequest;
use App\Command\BaseAppCommand;
use App\Entity\Tube;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Fire web hooks related to Tubes sent for External Processing.
 *
 * Usage to see next results available:
 *
 *     $ bin/console app:webhook:tubes-external -v --dry-run
 *
 * Usage send web hook:
 *
 *     $ bin/console app:webhook:tubes-external -v
 *
 */
class TubesExternalProcessingCommand extends BaseAppCommand
{
    protected static $defaultName = 'app:webhook:tubes-external';

    /**
     * Prototype Request object. Internally cloned for each executed request.
     *
     * @var TubeExternalProcessingWebHookRequest|null
     */
    private $request;

    /**
     * @var ServiceNowHttpClient
     */
    private $httpClient;

    /**
     * @param HttpClient $httpClient Compatible with HttpClient or any subclass like ServiceNowHttpClient
     * @param TubeExternalProcessingWebHookRequest|null $request Prototype Request object. Only inject for testing purposes.
     */
    public function __construct(EntityManagerInterface $em, HttpClient $httpClient, ?TubeExternalProcessingWebHookRequest $request = null)
    {
        parent::__construct($em);

        $this->request = $request;
        $this->httpClient = $httpClient;
    }

    protected function configure()
    {
        $this
            ->setDescription('Publishes list of Tubes sent for External Processing to Web Hook API')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'When given, the Web Hook API URL will not be contacted')
            ->addOption('skip-saving', null, InputOption::VALUE_NONE, 'Whether to save timestamp when results successfully published')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // These are the Specimen Results to be sent to Web Hooks
        $tubes = $this->findTubes();

        if (count($tubes) < 1) {
            $this->outputDebug('No Tubes to send');
            return 0;
        }

        $this->outputDebugTubes($tubes);

        $request = $this->buildWebHookRequest($tubes);

        $this->outputDebug('<comment>Pending Request Body:</comment>');
        $this->outputDebug($request->toJson());
        $this->outputDebug('');

        $this->outputDebug(sprintf("<comment>Request URL:</comment> %s\n", $this->httpClient->getUrl()));

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
        $this->outputDebug('<info>√ Authentication Success</info>');

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

        // Update record with data from Response
        $save = !$this->input->getOption('skip-saving');
        if ($save) {
            $this->outputDebug("<comment>Updating Tubes Web Hook Status...</comment>\n");
            $response->updateResultWebHookStatus($tubes);

            $this->em->flush();
        }

        $this->outputDebug("<comment>Done!</comment>\n");

        return 0;
    }

    /**
     * Find latest Tube records to send to Web Hook.
     *
     * @return Tube[]
     */
    private function findTubes(): array
    {
        return $this->em
            ->getRepository(Tube::class)
            ->findDueForExternalProcessingWebHook();
    }

    /**
     * Output debug info about Tubes sent through Web Hook.
     * Use CLI flag "-v" to print results.
     *
     * @param Tube[] $tubes
     */
    private function outputDebugTubes(array $tubes)
    {
        $this->outputDebug('<comment>Queued Tubes:</comment>');
        foreach ($tubes as $tube) {
            $externalId = $tube->getParticipantGroupExternalId() ?? '(empty)';
            $accessionId = $tube->getAccessionId() ?? '(empty)';
            $collectedAt = WebHookRequest::dateToRequestDataFormat($tube->getCollectedAt());
            $externalProcessingAt = WebHookRequest::dateToRequestDataFormat($tube->getReturnedAt());

            $this->outputDebug(sprintf('* %s Group External ID: %s; Collected At: %s; External Processing At: %s', $accessionId, $externalId, $collectedAt, $externalProcessingAt));
        }
        $this->outputDebug("");
    }

    /**
     * Build Request object to send to Web Hook API.
     *
     * @param Tube[] $tubes
     */
    private function buildWebHookRequest(array $tubes): TubeExternalProcessingWebHookRequest
    {
        if (!$this->request) {
            $this->request = new TubeExternalProcessingWebHookRequest();
        }

        $request = clone $this->request;
        $request->setTubes($tubes);

        return $request;
    }
}
