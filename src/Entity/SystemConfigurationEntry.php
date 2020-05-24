<?php


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * System configuration settings stored in the database
 *
 * See AppConfiguration for a service to work with these settings, do not use the repository
 *
 * @ORM\Entity(repositoryClass="App\Entity\SystemConfigurationEntryRepository")
 * @ORM\Table(name="system_configuration_entries")
 */
class SystemConfigurationEntry
{
    /**
     * @var int|null
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     *
     */
    protected $id;

    /**
     * Internal reference ID so settings can be reliably retrieved by code
     *
     * Example: SpecimenAccessionIdGenerator.key
     *
     * @var string|null
     *
     * @ORM\Column(name="reference_id", type="string", length=255, nullable=true, unique=true)
     */
    protected $referenceId;

    /**
     * Human-readable label for this configuration entry
     * @var string|null
     *
     * @ORM\Column(name="label", type="string", length=255, nullable=true)
     */
    protected $label;

    /**
     * @var string|null Json-encoded value of this setting
     *
     * @ORM\Column(name="value", type="json", nullable=true)
     */
    protected $value;

    public function __construct(string $referenceId, string $label = null)
    {
        $this->referenceId = $referenceId;
        $this->label = $label;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    public function getReferenceId(): ?string
    {
        return $this->referenceId;
    }

    public function setReferenceId(?string $referenceId): void
    {
        $this->referenceId = $referenceId;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): void
    {
        $this->value = $value;
    }
}