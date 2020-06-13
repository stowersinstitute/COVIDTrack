<?php

namespace App\Tests\ExcelImport;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
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

        $mock = $builder->getMock();

        $mockRepo = $this->buildMockRepository();
        $mock->method('getRepository')->willReturn($mockRepo);

        return $mock;
    }

    protected function buildMockRepository()
    {
        $builder = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor();

        return $builder->getMock();
    }
}
