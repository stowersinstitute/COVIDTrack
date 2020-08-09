<?php

namespace App\Command\ServiceNow;

use App\Api\ServiceNow\HttpClient;
use App\Api\ServiceNow\Request\TestConnectionRequest;
use App\Command\BaseAppCommand;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TestConnectionRemoteCommand extends BaseAppCommand
{
    protected static $defaultName = 'app:service-now:test-connection-remote';

    /**
     * @var HttpClient
     */
    private $snClient;

    public function __construct(EntityManagerInterface $em, HttpClient $snClient)
    {
        parent::__construct($em);

        $this->snClient = $snClient;
    }

    protected function configure()
    {
        $this
            ->setDescription('Checks if ServiceNow client wired correctly to send requests')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $request = new TestConnectionRequest();
        $response = $this->snClient->get('/api/now/table/u_covid_test_results?sysparm_limit=20', $request);

        if (401 === $response->getStatusCode()) {
            $output->writeln('Authentication failure. Check .env constants.');
            return 1;
        }
        $output->writeln('<info>√ Authentication successful</info>');

        if (200 !== $response->getStatusCode()) {
            $output->writeln('Cannot connect to test endpoint');
            return 1;
        }

        $output->writeln('<info>√ Connection successful</info>');
        $output->writeln('');

        $output->writeln('<comment>Headers:</comment>');
        foreach ($response->getHeaders() as $name => $values) {
            $output->writeln($name . ": " . implode(", ", $values));
        }
        $output->writeln('');

        $output->writeln('<comment>Body:</comment>');
        $output->writeln($response->getBody()->getContents());

        return 0;
    }
}
