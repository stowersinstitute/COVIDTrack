<?php


namespace App\Command;


use App\Entity\ExcelImportWorkbook;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CleanupExcelImportData extends Command
{
    protected static $defaultName = 'app:cleanup:excel-import-data';

    /** @var EntityManagerInterface */
    protected $em;

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct(self::$defaultName);

        $this->em = $em;
    }

    protected function configure()
    {
        $this
            ->setDescription('Cleans up any partially-complete excel imports')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Apply changes to the database')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $toDelete = $this->em
            ->getRepository(ExcelImportWorkbook::class)
            ->findExpired();

        if (!$toDelete) {
            $output->writeln('No workbooks to clean up');
            return 0;
        }

        $output->writeln(sprintf('Found %s to delete', count($toDelete)));

        if ($input->getOption('force')) {
            foreach ($toDelete as $workbook) {
                $this->em->remove($workbook);
            }
            $this->em->flush();

            $output->writeln(sprintf('Cleanup complete (removed %s workbooks)', count($toDelete)));
        }
        else {
            $output->writeln('');
            $output->writeln('<comment>Run with --force to remove expired workbooks</comment>');
        }

        return 0;
    }
}