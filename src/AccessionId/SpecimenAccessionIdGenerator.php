<?php


namespace App\AccessionId;

use App\Configuration\AppConfiguration;
use App\Util\StringUtils;
use Cryptomute\Cryptomute;

/**
 * Generates specimen accession IDs by encrypting the tube accession ID
 */
class SpecimenAccessionIdGenerator
{
    const BASE_KEY_CONFIG_ID    = 'SpecimenAccessionIdGenerator.baseKey';
    const PASSWORD_CONFIG_ID    = 'SpecimenAccessionIdGenerator.password';
    const IV_CONFIG_ID          = 'SpecimenAccessionIdGenerator.iv';

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

    /** @var int unsigned integer in the range 0 - 4294967295 */
    protected $counter;

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

        $this->encrypter->setValueRange(
            // 10 digits
            '0000000000',
            '4294967295'
        );
    }

    public function generate()
    {
        $counterRefId = 'FpeSpecimenAccessionIdGenerator.counter';
        // Changes must be written to the database immediately to minimize contention with other requests
        $this->appConfig->setAutoFlush(true);

        $counter = 0;
        if ($this->appConfig->hasReferenceId($counterRefId)) {
            $counter = $this->appConfig->get($counterRefId);
        }
        $this->appConfig->set($counterRefId, ++$counter);

        $input = $counter;
        $encrypted = $this->encrypter->encrypt($input, 10, true, $this->password, $this->iv);

        $numBase20DigitsRequired = ceil(log(intval(4294967295), 20));
        $encrypted = StringUtils::base10ToBase20($encrypted, $numBase20DigitsRequired);

        return sprintf('C%s', $encrypted);
    }

    protected function loadEncryptionParameters(AppConfiguration $appConfig)
    {
        $randomValueSettings = [
            self::BASE_KEY_CONFIG_ID,
            self::PASSWORD_CONFIG_ID,
            self::IV_CONFIG_ID,
        ];

        // Create new random values if the settings don't exist
        foreach ($randomValueSettings as $referenceId) {
            if ($appConfig->hasReferenceId($referenceId)) continue;

            // Note: aes-128-cbc IV requires 16 bytes, going with 16 for everything since this is a best-effort obfuscation
            $randomValue = bin2hex(random_bytes(16));
            $appConfig->create($referenceId, $randomValue);
        }

        // Load settings from configuration
        $this->baseKey  = hex2bin($appConfig->get(self::BASE_KEY_CONFIG_ID));
        $this->password = hex2bin($appConfig->get(self::PASSWORD_CONFIG_ID));
        $this->iv       = hex2bin($appConfig->get(self::IV_CONFIG_ID));

        if (!$this->baseKey)  throw new \LogicException('Unable to get baseKey');
        if (!$this->password) throw new \LogicException('Unable to get password');
        if (!$this->iv)       throw new \LogicException('Unable to get iv');
    }
}