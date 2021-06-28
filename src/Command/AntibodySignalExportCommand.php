<?php

namespace App\Command;

use App\Entity\SpecimenResultAntibody;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

class AntibodySignalExportCommand extends BaseAppCommand
{
    protected static $defaultName = "app:export:antibody-signals";

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var Filesystem
     */
    private $filesystem;

    public function __construct(EntityManagerInterface $em, ContainerInterface $container, Filesystem $filesystem)
    {
        $this->container = $container;
        $this->filesystem = $filesystem;

        parent::__construct($em);
    }

    protected function configure()
    {
        $this
            ->setDescription('Exports antibody signal results with tube and specimen accession IDs as CSV')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'When given, output the export to the the console')
            ->addOption('output-path', 'o', InputOption::VALUE_REQUIRED, 'Location to save the export file.', $this->generateDefaultOutputPath())
        ;
    }

    private function generateDefaultOutputPath(): string {
        $path = $this->container->getParameter('kernel.project_dir') . '/export/';

        return $path;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $results = $this->getEm()->getRepository(SpecimenResultAntibody::class)->findAllWithSignal();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $data = array_map(function(SpecimenResultAntibody $result) {
            return [
                $result->getSpecimenAccessionId(),
                $result->getTubeAccessionId(),
                $result->getSignal(),
            ];
        }, $results);

        array_unshift($data, [
            'Specimen',
            'Tube',
            'Signal',
        ]);

        $sheet->fromArray($data);

        $writer = new Csv($spreadsheet);
        $writer->setDelimiter(',');
        $writer->setEnclosure('"');
        $writer->setLineEnding("\n");

        $this->filesystem->mkdir($input->getOption('output-path'));

        $now = new \DateTimeImmutable();
        $filename = sprintf("%santibody-signals.%s.csv",$input->getOption('output-path'), $now->format('Ymd-His'));

        // Output to Stdout if dry-run
        if($input->getOption('dry-run')) {
            $writer->save('php://stdout');
            exit();
        }

        $writer->save($filename);

        $output->writeln(sprintf("Exported to %s", $filename));
    }
}