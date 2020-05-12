<?php


namespace App\AccessionId;


use App\Entity\ParticipantGroup;
use App\Entity\ParticipantGroupRepository;
use App\Util\StringUtils;
use Doctrine\ORM\EntityManager;

class ParticipantGroupAccessionIdGenerator
{
    /** @var ParticipantGroupRepository  */
    private $repository;

    /**
     * @var string[] IDs generated during the lifetime of this object
     */
    private $generatedThisInstance = [];

    public function __construct(EntityManager $entityManager)
    {
        $this->repository = $entityManager->getRepository(ParticipantGroup::class);
    }

    public function generate() : string
    {
        $id = null;

        // Sanity check to prevent infinite loop
        $maxTries = 1000;
        do {
            $id = sprintf('GRP-' . StringUtils::generateRandomString(6));
            $maxTries--;
        } while ($this->idExists($id) && $maxTries > 0);

        if ($maxTries === 0) throw new \ErrorException('Unable to generate a group ID (exceeded max tries)');

        $this->generatedThisInstance[] = $id;

        return $id;
    }

    protected function idExists($id)
    {
        // ID exists if it's attached to an existing record in the database
        $dbRecord = $this->repository->findOneBy(['accessionId' => $id]);
        if ($dbRecord) return true;

        // Also consider it existing if we've generated it
        // This makes working with unpersisted entities easier
        if (in_array($id, $this->generatedThisInstance)) return true;

        return false;
    }
}