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

    /**
     * Smoke test to ensure no duplicate IDs are generated
     */
    public function testGeneratesUniqueIds()
    {
        $generator = new FpeSpecimenAccessionIdGenerator($this->appConfig);
        $numToGenerate = 1024;
        $generated = [];

        for ($i=0; $i < $numToGenerate; $i++) {
            $generated[] = $generator->generate();
        }

        // All entries must be unique
        $this->assertCount($numToGenerate, array_unique($generated));
    }

}