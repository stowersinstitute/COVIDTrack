<?php

namespace App\Command\WebHook;

use App\Api\WebHook\Client\ResultHttpClient;
use App\Api\WebHook\Request\NewResultsWebHookRequest;
use App\Command\BaseAppCommand;
use App\Entity\SpecimenResult;
use App\Entity\SpecimenResultAntibody;
use App\Entity\SpecimenResultQPCR;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Exception\ClientException;
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
     * @var NewResultsWebHookRequest|null
     */
    private $request;

    /**
     * @var ResultHttpClient
     */
    private $httpClient;

    public function __construct(EntityManagerInterface $em, ResultHttpClient $httpClient, ?NewResultsWebHookRequest $request = null)
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
        $lastCheckedForResults = new \DateTimeImmutable('now');
        $newResults = $this->findResults();
        if (count($newResults) < 1) {
            $this->outputDebug('No results to send');
            return 0;
        }

        $this->outputDebugResultsByGroup($newResults);

        $request = $this->buildNewRequest($newResults);

        $this->outputDebug('<comment>Pending Request Body:</comment>');
        $this->outputDebug($request->toJson());
        $this->outputDebug('');

        // Abort now if setting CLI option --dry-run
        if ($this->input->getOption('dry-run')) {
            return 0;
        }

        try {
            $response = $this->httpClient->post($request);
        } catch (ClientException $e) {
            $output->writeln('<error>Exception calling WebHook endpoint</error>');
            $output->writeln(sprintf('Status Code: %d %s', $e->getResponse()->getStatusCode(), $e->getResponse()->getReasonPhrase()));
            $output->writeln('Exception Message: ' . $e->getMessage());
            return 1;
        } catch (\Exception $e) {
            $output->writeln('Unknown Exception: ' . $e->getMessage());
            return 1;
        }

        if (401 === $response->getStatusCode()) {
            $output->writeln('Authentication failure. Check credential constants in file .env.local');
            return 1;
        }
        $output->writeln('<info>√ Authentication success</info>');

        if (500 === $response->getStatusCode()) {
            $output->writeln('Error response from Web Hook endpoint');
            return 1;
        }

        if (200 !== $response->getStatusCode()) {
            $output->writeln(sprintf('Unhandled HTTP Response Code %d from Web Hook endpoint', $response->getStatusCode()));
            return 1;
        }

        $output->writeln('<info>√ Success Response</info>');
        $output->writeln('');

        $output->writeln('<comment>Headers:</comment>');
        foreach ($response->getHeaders() as $name => $values) {
            $output->writeln($name . ": " . implode(", ", $values));
        }
        $output->writeln('');

        $output->writeln('<comment>Response Body:</comment>');
        $output->writeln($response->getBodyContents());
        $output->writeln('');

        // Update success date
        $save = !$this->input->getOption('skip-saving');
        if ($save) {
            foreach ($newResults as $result) {
                $result->setLastWebHookSuccessAt($lastCheckedForResults);
            }

            $this->em->flush();
        }

        $this->outputDebugResultsByGroup($newResults);

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
        $this->outputDebug('<comment>Pending Results:</comment>');
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

    private function buildNewRequest(array $newResults): NewResultsWebHookRequest
    {
        if (!$this->request) {
            $this->request = new NewResultsWebHookRequest();
        }

        $request = clone $this->request;
        $request->setResults($newResults);

        return $request;
    }
}
