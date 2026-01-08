<?php

namespace App\Entity;

use App\Repository\ProcessUpdateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'process_update')]
#[ORM\Entity(repositoryClass: ProcessUpdateRepository::class)]
class ProcessUpdate
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    protected ?int $id = null;

    #[ORM\Column(name: 'date', type: Types::DATETIME_MUTABLE)]
    #[Assert\DateTime]
    private ?\DateTimeInterface $date = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'processUpdates')]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\Column(name: 'title', type: Types::STRING, length: 64)]
    protected ?string $title = null;

    #[ORM\Column(name: 'description', type: Types::TEXT)]
    protected ?string $description = null;

    #[ORM\Column(name: 'link', type: Types::STRING, length: 256, nullable: true)]
    protected ?string $link = null;

    /**
     * Constructor
     */
    public function __construct()
    {
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
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return string|null
     */
    public function getLink(): ?string
    {
        return $this->link;
    }

    /**
     * @param string|null $link
     */
    public function setLink(?string $link): void
    {
        $this->link = $link;
    }

    /**
     * @return \DateTimeInterface|null
     */
    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    /**
     * @param \DateTimeInterface $date
     */
    public function setDate(\DateTimeInterface $date): void
    {
        $this->date = $date;
    }

    /**
     * @return User|null
     */
    public function getAuthor(): ?User
    {
        return $this->author;
    }

    /**
     * @param User|null $author
     */
    public function setAuthor(?User $author): void
    {
        $this->author = $author;
    }

    public function __toString(): string
    {
        return (string) $this->getTitle();
    }
}
