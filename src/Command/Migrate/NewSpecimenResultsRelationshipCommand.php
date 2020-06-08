<?php

namespace App\Command\Migrate;

use App\Email\EmailBuilder;
use App\Entity\AppUser;
use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use App\Entity\SpecimenResultQPCR;
use App\Entity\StudyCoordinatorNotification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Router;
use Symfony\Component\Routing\RouterInterface;

class NewSpecimenResultsRelationshipCommand extends Command
{
    protected static $defaultName = 'app:migrate:new-specimen-results-relationship';

    /** @var EntityManagerInterface */
    private $em;

    /** @var InputInterface */
    private $input;

    /** @var OutputInterface */
    private $output;

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();

        $this->em = $em;
    }

    protected function configure()
    {
        $this
            ->setDescription('Migrates to new SpecimenResults relationship')
            ->addOption('testing', null, InputOption::VALUE_NONE, 'Use to only output without saving')
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
    }

    private function outputDebug(string $line)
    {
        if ($this->output->isVerbose()) {
            $this->output->writeln($line);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Get all Specimens
        /** @var Specimen[] $specimens */
        $specimens = $this->em->getRepository(Specimen::class)->findAll();

        foreach ($specimens as $specimen) {
            $specimenAccessionId = $specimen->getAccessionId();

            foreach ($specimen->getResultsForDataMigration() as $result) {
                // Must have 1 qPCR Result
                $results = $specimen->getResultsForDataMigration();
                $resultsCount = count($results);
                if ($resultsCount === 0) {
                    $this->outputDebug(sprintf('Skip migrating %s. Has no Results.', $specimenAccessionId));
                    continue;
                }
                if ($resultsCount > 1) {
                    throw new \RuntimeException(sprintf('Cannot migrate Specimen %s. Has %d Results and can only migrate when has 1.', $specimenAccessionId, $resultsCount));
                }

                // Must have 1 Well
                $wells = $specimen->getWells();
                $wellCount = count($wells);
                if ($wellCount === 0) {
                    $this->outputDebug(sprintf('Skip migrating %s. Has no Wells.', $specimenAccessionId));
                    continue;
                }
                if ($wellCount > 1) {
                    throw new \RuntimeException(sprintf('Cannot migrate Specimen %s. Has %d Wells and can only migrate when has 1.', $specimenAccessionId, $wellCount));
                }

                $well = $wells[0];
                $result = $results[0];

                // Prevent duplicate results updating
                if ($well->getResultQPCR()->getId() === $result->getId()) {
                    $this->outputDebug(sprintf('Skip migrating %s. Already related to Result.id %d', $specimenAccessionId, $result->getId()));
                    continue;
                }

                // Result migration to be directly related to Well
                $wellPlateBarcode = $well->getWellPlateBarcode();
                $wellPosition = $well->getPosition();
                $resultConclusion = $result->getConclusion();
                $this->outputDebug(sprintf('Migrating Specimen %s in Well Plate %s at Well Position %d with Result Conclusion %s', $specimenAccessionId, $wellPlateBarcode, $wellPosition, $resultConclusion));

                $well->setQPCRResult($result);
            }

            if (!$input->getOption('testing')) {
//                $this->em->flush();
            }
        }

        return 0;
    }
}
