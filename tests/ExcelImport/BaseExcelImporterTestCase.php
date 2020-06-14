<?php

namespace App\Tests\ExcelImport;

use App\Entity\Specimen;
use App\Entity\SpecimenRepository;
use App\Entity\Tube;
use App\Entity\WellPlate;
use App\Repository\TubeRepository;
use App\Repository\WellPlateRepository;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;

class BaseExcelImporterTestCase extends TestCase
{
    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|EntityManager
     */
    protected function buildMockEntityManager()
    {
        $builder = $this->getMockBuilder(EntityManager::class)
            ->disableOriginalConstructor();

        $mockEM = $builder->getMock();

        $repositories = [
            [Tube::class, $this->buildTubeRepo()],
            [Specimen::class, $this->buildSpecimenRepo()],
            [WellPlate::class, $this->buildWellPlateRepo()],
        ];
        $mockEM
            ->expects($this->any())
            ->method('getRepository')
            ->willReturnMap($repositories);

        return $mockEM;
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|TubeRepository
     */
    protected function buildTubeRepo()
    {
        $builder = $this->getMockBuilder(TubeRepository::class)
            ->disableOriginalConstructor();

        $mock = $builder->getMock();

        $methodsAndReturn = [
            'findOneBy' => null,
            'findOneByAccessionId' => null,
        ];
        foreach ($methodsAndReturn as $method => $return) {
            $mock
                ->expects($this->any())
                ->method($method)
                ->willReturn($return);
        }

        return $mock;
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|SpecimenRepository
     */
    protected function buildSpecimenRepo()
    {
        $builder = $this->getMockBuilder(SpecimenRepository::class)
            ->disableOriginalConstructor();

        $mock = $builder->getMock();

        $methodsAndReturn = [
            'findOneBy' => null,
        ];
        foreach ($methodsAndReturn as $method => $return) {
            $mock
                ->expects($this->any())
                ->method($method)
                ->willReturn($return);
        }

        return $mock;
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|WellPlateRepository
     */
    protected function buildWellPlateRepo()
    {
        $builder = $this->getMockBuilder(WellPlateRepository::class)
            ->disableOriginalConstructor();

        $mock = $builder->getMock();

        $methodsAndReturn = [
            'findOneByBarcode' => null,
        ];
        foreach ($methodsAndReturn as $method => $return) {
            $mock
                ->expects($this->any())
                ->method($method)
                ->willReturn($return);
        }

        return $mock;
    }
}
