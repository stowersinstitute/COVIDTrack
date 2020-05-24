<?php


namespace App\AccessionId;

use App\Configuration\AppConfiguration;
use App\Entity\Specimen;
use App\Entity\Tube;
use App\Util\StringUtils;
use Cryptomute\Cryptomute;

/**
 * Generates specimen accession IDs by encrypting the tube accession ID
 */
class FpeSpecimenAccessionIdGenerator
{
    /** @var string */
    protected $baseKey;

    /** @var string  */
    protected $password;

    /** @var string  */
    protected $iv;

    /** @var AppConfiguration */
    protected $appConfig;

    /** @var Cryptomute */
    protected $encrypter;

    public function __construct(AppConfiguration $appConfig)
    {
        $this->appConfig = $appConfig;

        $this->loadEncryptionParameters($appConfig);

        // Configure encrypter
        $this->encrypter = new Cryptomute(
            'aes-128-cbc', // changes to this may require changes to how IV is generated
            $this->baseKey,
            7
        );

        // SPECIMEN_ID_FORMAT_DEPENDENCY - this assumes maximum specimen ID is an unsigned 32-bit int in the range 0-4294967295
        $this->encrypter->setValueRange(
            // 10 digits
            '0000000000',
            '4294967295'
        );
    }

    public function generate(Specimen $specimen)
    {
        if ($specimen->getId() === null) throw new \InvalidArgumentException('Cannot generate an accession ID until the Specimen is persisted and has an ID');

        $input = $specimen->getId();
        $encrypted = $this->encrypter->encrypt($input, 10, true, $this->password, $this->iv);

        // SPECIMEN_ID_FORMAT_DEPENDENCY - different range of specimen IDs may impact padUntilLength
        $numBase20DigitsRequired = ceil(log(intval(4294967295), 20));
        $encrypted = StringUtils::base10ToBase20($encrypted, $numBase20DigitsRequired);

        return sprintf('C%s', $encrypted);
    }

    protected function loadEncryptionParameters(AppConfiguration $appConfig)
    {
        $randomValueSettings = [
            'FpeSpecimenAccessionIdGenerator.baseKey',
            'FpeSpecimenAccessionIdGenerator.password',
            'FpeSpecimenAccessionIdGenerator.iv',
        ];

        // Create new random values if the settings don't exist
        foreach ($randomValueSettings as $referenceId) {
            if ($appConfig->hasReferenceId($referenceId)) continue;

            // Note: aes-128-cbc IV requires 16 bytes, going with 16 for everything since this is a best-effort obfuscation
            $randomValue = bin2hex(random_bytes(16));
            $appConfig->create($referenceId, $randomValue);
        }

        // Load settings from configuration
        $this->baseKey  = hex2bin($appConfig->get('FpeSpecimenAccessionIdGenerator.baseKey'));
        $this->password = hex2bin($appConfig->get('FpeSpecimenAccessionIdGenerator.password'));
        $this->iv       = hex2bin($appConfig->get('FpeSpecimenAccessionIdGenerator.iv'));

        if (!$this->baseKey)  throw new \LogicException('Unable to get baseKey');
        if (!$this->password) throw new \LogicException('Unable to get password');
        if (!$this->iv)       throw new \LogicException('Unable to get iv');
    }
}