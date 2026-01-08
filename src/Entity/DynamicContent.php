<?php

namespace App\Entity;

use App\Repository\DynamicContentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'dynamic_content')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: DynamicContentRepository::class)]
class DynamicContent
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    protected ?int $id = null;

    #[ORM\Column(name: 'code', type: 'string', length: 64)]
    protected ?string $code = null;

    #[ORM\Column(name: 'type', type: 'string', length: 64, options: ['default' => 'general'])]
    protected ?string $type = null;

    #[ORM\Column(name: 'name', type: 'string', length: 64)]
    protected ?string $name = null;

    #[ORM\Column(name: 'description', type: 'text')]
    protected ?string $description = null;

    #[ORM\Column(name: 'content', type: 'text')]
    protected ?string $content = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id')]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'updated_by_id', referencedColumnName: 'id')]
    private ?User $updatedBy = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
    }

    public function __toString(): string
    {
        return (string)$this->getName();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
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
     * @return string|null
     */
    public function getCode(): ?string
    {
        return $this->code;
    }

    /**
     * @param string|null $code
     * @return void
     */
    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    /**
     * Set type
     *
     * @param string|null $type
     *
     * @return self
     */
    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type
     *
     * @return string|null
     */
    public function getType(): ?string
    {
        if ($this->type === 'general') {
            return 'Général';
        }
        return $this->type;
    }

    /**
     * Set name
     *
     * @param string|null $name
     *
     * @return self
     */
    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @return string|null
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * @param string|null $content
     * @return void
     */
    public function setContent(?string $content): void
    {
        $this->content = $content;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param string|null $description
     * @return void
     */
    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    /**
     * Set createdBy
     *
     * @param User|null $user
     *
     * @return self
     */
    public function setCreatedBy(?User $user = null): self
    {
        $this->createdBy = $user;
        return $this;
    }

    /**
     * Get createdBy
     *
     * @return User|null
     */
    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    /**
     * Set updatedBy
     *
     * @param User|null $user
     *
     * @return self
     */
    public function setUpdatedBy(?User $user = null): self
    {
        $this->updatedBy = $user;
        return $this;
    }

    /**
     * Get updatedBy
     *
     * @return User|null
     */
    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
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
     * Get updatedAt
     *
     * @return \DateTimeInterface|null
     */
    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }
}
