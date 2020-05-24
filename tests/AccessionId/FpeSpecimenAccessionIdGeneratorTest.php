<?php


namespace App\tests\AccessionId;


use App\AccessionId\FpeSpecimenAccessionIdGenerator;
use App\Configuration\AppConfiguration;
use App\Entity\Specimen;
use PHPUnit\Framework\TestCase;

class FpeSpecimenAccessionIdGeneratorTest extends TestCase
{
    /** @var AppConfiguration */
    protected $appConfig;

    /** @var FpeSpecimenAccessionIdGenerator */
    protected $generator;

    public function setUp()
    {
        parent::setUp();

        $this->appConfig = new AppConfiguration();

        $this->appConfig->set('FpeSpecimenAccessionIdGenerator.baseKey', '7b16a3c74899a5dd000f589c26adfc6a');
        $this->appConfig->set('FpeSpecimenAccessionIdGenerator.password', 'a0d2315ffb219c245deff98483e78a88');
        $this->appConfig->set('FpeSpecimenAccessionIdGenerator.iv', 'a0d2315ffb219c245deff98483e78a88');

        $this->generator = new FpeSpecimenAccessionIdGenerator($this->appConfig);
    }

    public function testGenerateSpecimenAccessionId()
    {
        $testData = [
            '1'          => 'CDNFZFWSN',
            '12345678'   => 'CCSNPWRBR',
            '2147483647' => 'CDCXVSYLR',
            '4294967295' => 'CCWCZYPWJ',
        ];

        foreach ($testData as $specimenId => $expectedAccessionId) {
            $specimen = Specimen::createWithId(intval($specimenId));

            $this->assertEquals($expectedAccessionId, $this->generator->generate($specimen));
        }
    }

}