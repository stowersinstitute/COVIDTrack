<?php

namespace App\Entity;

use Doctrine\ORM\EntityRepository;

/**
 * Query for ParticipantGroup entities.
 */
class ParticipantGroupRepository extends EntityRepository
{
    /**
     * @param int|string $id ParticipantGroup.id or ParticipantGroup.accessionId
     */
    public function findOneByAnyId($id): ?ParticipantGroup
    {
        if (is_int($id)) {
            return $this->find($id);
        }

        return $this->findOneBy([
            'accessionId' => $id,
        ]);
    }

    /**
     * Specimen results count, indexed by ParticipantGroup.accessionId
     *
     * @return array[] Key == accessionId; Value == ['Positive'=>5, 'Negative'=>24, ...]
     */
    public function findResultsMap(): array
    {
        /** @var ParticipantGroup[] $groups */
        $groups = $this->createQueryBuilder('g')
            ->select('g, s')
            ->join('g.specimens', 's')
            ->getQuery()
            ->execute();

        $map = [];
        foreach ($groups as $group) {
            /**
             * For example: [
             *     'Negative' => 50,
             *     'Positive' => 2,
             * ];
             * If group's samples don't have an "Inconclusive" result,
             * the array will not contain an index "Inconclusive"
             */
            $sampleResults = array_reduce($group->getSpecimens(), function(array $carry, Specimen $s) {
                $r = $s->getResultText();

                // Default to 0
                if (!isset($carry[$r])) {
                    $carry[$r] = 0;
                }

                // Increment
                $carry[$r]++;

                return $carry;
            }, []);

            $map[$group->getAccessionId()] = $sampleResults;
        }

        return $map;
    }
}
