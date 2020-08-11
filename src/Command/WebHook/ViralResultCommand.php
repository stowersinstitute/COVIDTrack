<?php

namespace App\Command\WebHook;

use App\Api\WebHook\Client\ViralResultHttpClient;
use App\Api\WebHook\Request\NewViralResultsWebHookRequest;
use App\Command\BaseAppCommand;
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
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $request = new NewViralResultsWebHookRequest();

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

        return 0;
    }
}
