<?php

namespace App\Entity;

use App\Repository\CodeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'code')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: CodeRepository::class)]
class Code
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'value', type: 'string', length: 255, nullable: true)]
    private ?string $value = null;

    #[ORM\Column(name: 'closed', type: 'boolean', nullable: false, options: ['default' => 0])]
    private bool $closed = false;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'registrar_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $registrar = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
    }

    /**
     * Get id
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set value
     *
     * @param string|null $value
     *
     * @return self
     */
    public function setValue(?string $value): self
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Get value
     *
     * @return string|null
     */
    public function getValue(): ?string
    {
        return $this->value;
    }

    /**
     * Set createdAt
     *
     * @param \DateTimeInterface|null $createdAt
     *
     * @return self
     */
    public function setCreatedAt(?\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get createdAt
     *
     * @return \DateTimeInterface|null
     */
    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * Set registrar
     *
     * @param User|null $registrar
     *
     * @return self
     */
    public function setRegistrar(?User $registrar = null): self
    {
        $this->registrar = $registrar;

        return $this;
    }

    /**
     * Get registrar
     *
     * @return User|null
     */
    public function getRegistrar(): ?User
    {
        return $this->registrar;
    }

    /**
     * Set closed
     *
     * @param bool $closed
     *
     * @return self
     */
    public function setClosed(bool $closed): self
    {
        $this->closed = $closed;

        return $this;
    }

    /**
     * Get closed
     *
     * @return bool
     */
    public function getClosed(): bool
    {
        return $this->closed;
    }
}
