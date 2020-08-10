<?php

namespace App\Command\ServiceNow;

use App\Api\ServiceNow\HttpClient;
use App\Api\ServiceNow\Request\TestConnectionRequest;
use App\Command\BaseAppCommand;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Exception\ClientException;
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

        try {
            $response = $this->snClient->get('/api/now/table/u_covid_test_results?sysparm_limit=20', $request);
        } catch (ClientException $e) {
            $output->writeln('<error>Exception calling ServiceNow endpoint</error>');
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

        return 0;
    }
}
