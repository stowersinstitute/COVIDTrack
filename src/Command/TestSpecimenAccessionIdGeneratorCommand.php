<?php


namespace App\Command;


use App\AccessionId\FpeSpecimenAccessionIdGenerator;
use App\Configuration\AppConfiguration;
use App\Entity\Specimen;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestSpecimenAccessionIdGeneratorCommand extends Command
{
    protected static $defaultName = 'test:app:specimen-id-generator';

    /** @var EntityManagerInterface */
    protected $em;

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct(static::$defaultName);

        $this->em = $em;
    }

    protected function configure()
    {
        $this
            ->setDescription('Tests FPE specimen accession ID generation')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $appConfig = new AppConfiguration($this->em);
        $generator = new FpeSpecimenAccessionIdGenerator($appConfig);

        $max = 2**32-1;
        $progressBar = new ProgressBar($output, $max, 1.0);
        $progressBar->setFormat('very_verbose');

        $buffer = 4096;
        $cids = [];
        for ($i=1; $i <= $max; $i++) {
            $specimen = Specimen::createWithId($i);
            $cids[] = $generator->generate($specimen);

            if ($i % $buffer === 0) {
                file_put_contents('cids.txt', join("\n", $cids) . "\n", FILE_APPEND);
                $cids = [];
            }

            $progressBar->advance();
            unset($specimen);
        }
        $progressBar->finish();
    }
}