<?php

namespace App\Api\WebHook\Client;

use App\Entity\WebHookLog;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Handler\AbstractProcessingHandler;

/**
 * Writes Monolog records to a database.
 */
class DatabaseLoggerHandler extends AbstractProcessingHandler
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
