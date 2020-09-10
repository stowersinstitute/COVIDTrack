<?php

namespace App\Api\WebHook\Client;

use App\Entity\WebHookLog;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Handler\AbstractProcessingHandler;

/**
 * Writes Monolog records to a database when logs written to the specified channel.
 *
 * Codebase currently uses the "webhook" Monolog channel to log data for webhooks.
 * WebHookLogHandler is the logic to write those log messages.
 */
class WebHookLogHandler extends AbstractProcessingHandler
{
    /** @var EntityManagerInterface */
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();

        $this->em = $em;
    }

    /**
     * Write log data to database.
     */
    protected function write(array $record)
    {
        $log = WebHookLog::buildFromMonologRecord($record);

        $this->em->persist($log);
        $this->em->flush();
    }
}
