<?php

namespace App\Command\WebHook;

use App\Api\WebHook\Client\ViralResultHttpClient;
use App\Api\WebHook\Request\NewViralResultsWebHookRequest;
use App\Command\BaseAppCommand;
use App\Entity\SpecimenResultQPCR;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Fire web hooks related to Viral Results.
 */
class ViralResultCommand extends BaseAppCommand
{
    protected static $defaultName = 'app:webhook:viral-results';

    /**
     * @var ViralResultHttpClient
     */
    private $httpClient;

    public function __construct(EntityManagerInterface $em, ViralResultHttpClient $httpClient)
    {
        parent::__construct($em);

        $this->httpClient = $httpClient;
    }

    protected function configure()
    {
        $this
            ->setDescription('Publishes Viral Results changes to web hook URL')
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

        try {
            $request = new NewViralResultsWebHookRequest($newResults);
            $response = $this->httpClient->get($request);

            // TODO: Parse response for success
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

        $this->outputDebug(sprintf("\nSent %d Results", count($newResults)));

        return 0;
    }

    /**
     * Find latest Results to send to Web Hook
     *
     * @return SpecimenResultQPCR[]
     */
    private function findResults(): array
    {
        return $this->em
            ->getRepository(SpecimenResultQPCR::class)
            ->findDueForWebHook();
    }
}
