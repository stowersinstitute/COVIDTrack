<?php

namespace App\Command\WebHook;

use App\Api\WebHook\Client\AntibodyResultHttpClient;
use App\Api\WebHook\Request\NewAntibodyResultsWebHookRequest;
use App\Command\BaseAppCommand;
use App\Entity\SpecimenResult;
use App\Entity\SpecimenResultAntibody;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Fire web hooks related to Antibody Results.
 */
class AntibodyResultCommand extends BaseAppCommand
{
    protected static $defaultName = 'app:webhook:antibody-results';

    /**
     * @var AntibodyResultHttpClient
     */
    private $httpClient;

    public function __construct(EntityManagerInterface $em, AntibodyResultHttpClient $httpClient)
    {
        parent::__construct($em);

        $this->httpClient = $httpClient;
    }

    protected function configure()
    {
        $this
            ->setDescription('Publishes Antibody Results changes to web hook URL')
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

        $request = new NewAntibodyResultsWebHookRequest($newResults);

        try {
            $response = $this->httpClient->get($request);
        } catch (ClientException $e) {
            $output->writeln('<error>Exception calling WebHook endpoint</error>');
            $output->writeln(sprintf('Status Code: %d %s', $e->getResponse()->getStatusCode(), $e->getResponse()->getReasonPhrase()));
            $output->writeln('');
            $output->writeln($e->getMessage());
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

        if (200 !== $response->getStatusCode()) {
            $output->writeln('Cannot connect to test endpoint');
            return 1;
        }

        $output->writeln('<info>√ Connection success</info>');
        $output->writeln('');

        $output->writeln('<comment>Headers:</comment>');
        foreach ($response->getHeaders() as $name => $values) {
            $output->writeln($name . ": " . implode(", ", $values));
        }
        $output->writeln('');

        $output->writeln('<comment>Body:</comment>');
        $output->writeln($response->getBody()->getContents());

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
     * @return SpecimenResultAntibody[]
     */
    private function findResults(): array
    {
        return $this->em
            ->getRepository(SpecimenResultAntibody::class)
            ->findDueForWebHook();
    }

    /**
     * Output debug info about results sent for each Participant Group.
     * Use CLI flag "-v" to print results.
     *
     * @param SpecimenResult[] $newResults
     */
    private function outputDebugResultsByGroup(array $newResults)
    {
        $this->outputDebug('');
        $this->outputDebug(sprintf("<info>√ Sent %d Results</info>", count($newResults)));

        $byGroup = [];
        foreach ($newResults as $result) {
            $group = $result->getSpecimen()->getParticipantGroup();

            if (!isset($byGroup[$group->getTitle()])) {
                $byGroup[$group->getTitle()] = [];
            }

            $byGroup[$group->getTitle()][] = $result;
        }

        // Will display groups alphabetical
        ksort($byGroup);

        /** @var SpecimenResult[] $groupResults */
        foreach ($byGroup as $groupResults) {
            $this->outputDebug('');

            $group = $groupResults[0]->getSpecimen()->getParticipantGroup();
            $this->outputDebug(sprintf('<comment>%s</comment>', $group->getTitle()));

            foreach ($groupResults as $result) {
                $this->outputDebug(sprintf('%s %s', $result->getUpdatedAt()->format("Y-m-d H:i:s"), $result->getConclusionText()));
            }
        }
    }
}
