<?php

namespace App\Command\Migrate;

use App\Entity\SpecimenWell;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Convert SpecimenWell.position from integer 12 to alphanumeric B4.
 */
class IntegerToAlphanumericPositionCommand extends Command
{
    protected static $defaultName = 'app:migrate:positions';

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
            ->setDescription('Convert SpecimenWell.position from integer 12 to alphanumeric B4.')
            ->addOption('testing', null, InputOption::VALUE_NONE, 'Use to ignore saving and output all debug messages')
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
    }

    private function outputDebug(string $line)
    {
        if ($this->output->isVerbose() || $this->input->getOption('testing')) {
            $this->output->writeln($line);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $wells = $this->findWellsNeedingConverting();
        if (count($wells) < 1) {
            $this->outputDebug('No wells need positions converted');
            return 0;
        }

        foreach ($wells as $well) {
            $int = $well->getPosition();
            $alpha = SpecimenWell::positionAlphanumericFromInt($int);

            $specimenId = $well->getSpecimen()->getAccessionId();
            $this->outputDebug(sprintf('Updating %s on %s %d converting to %s', $specimenId, $well->getWellPlateBarcode(), $int, $alpha));

            $well->setPositionAlphanumeric($alpha);
        }

        if (!$input->getOption('testing')) {
            $this->em->flush();
        }

        return 0;
    }

    /**
     * @return SpecimenWell[]
     */
    private function findWellsNeedingConverting(): array
    {
        /** @var EntityRepository $repo */
        $repo = $this->em->getRepository(SpecimenWell::class);

        $query = $repo->createQueryBuilder('w')
            ->join('w.wellPlate', 'plate')
            ->where('w.position IS NOT NULL')
            ->andWhere('w.positionAlphanumeric IS NULL')
            ->addOrderBy('plate.id')
            ->addOrderBy('w.position')
            ->getQuery();
        $this->outputDebug($query->getSQL());

        return $query->execute();
    }
}
