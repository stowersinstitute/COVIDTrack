<?php


namespace App\Tests\AccessionId;


use App\AccessionId\SpecimenAccessionIdGenerator;
use App\Configuration\AppConfiguration;
use App\Entity\Specimen;
use PHPUnit\Framework\TestCase;

class SpecimenAccessionIdGeneratorTest extends TestCase
{
    /** @var AppConfiguration */
    protected $appConfig;

    /** @var SpecimenAccessionIdGenerator */
    protected $generator;

    public function setUp()
    {
        parent::setUp();

        $this->appConfig = new AppConfiguration();

        $this->appConfig->set(SpecimenAccessionIdGenerator::BASE_KEY_CONFIG_ID, '7b16a3c74899a5dd000f589c26adfc6a');
        $this->appConfig->set(SpecimenAccessionIdGenerator::PASSWORD_CONFIG_ID, 'a0d2315ffb219c245deff98483e78a88');
        $this->appConfig->set(SpecimenAccessionIdGenerator::IV_CONFIG_ID, 'a0d2315ffb219c245deff98483e78a88');

        $this->generator = new SpecimenAccessionIdGenerator($this->appConfig);
    }

    /**
     * Smoke test to ensure no duplicate IDs are generated
     */
    public function testGeneratesUniqueIds()
    {
        $generator = new SpecimenAccessionIdGenerator($this->appConfig);
        $numToGenerate = 1024;
        $generated = [];

        for ($i=0; $i < $numToGenerate; $i++) {
            $generated[] = $generator->generate();
        }

        // All entries must be unique
        $this->assertCount($numToGenerate, array_unique($generated));
    }

}