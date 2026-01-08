<?php

namespace App\Entity;

use App\Repository\NoteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'note')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: NoteRepository::class)]
class Note
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'text', type: 'text')]
    #[Assert\NotBlank]
    private ?string $text = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'annotations')]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id')]
    private ?User $author = null;

    #[ORM\ManyToOne(targetEntity: Membership::class, inversedBy: 'notes')]
    #[ORM\JoinColumn(name: 'membership_id', referencedColumnName: 'id')]
    private ?Membership $subject = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id')]
    private ?Note $parent = null;

    /**
     * @var Collection<int, Note>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent', cascade: ['persist', 'remove'])]
    private Collection $children;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->children = new ArrayCollection();
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
     * Set text
     *
     * @param string|null $text
     *
     * @return self
     */
    public function setText(?string $text): self
    {
        $this->text = $text;

        return $this;
    }

    /**
     * Get text
     *
     * @return string|null
     */
    public function getText(): ?string
    {
        return $this->text;
    }

    public function getTextWithBr(): string
    {
        return nl2br((string)$this->getText());
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
     * Set author
     *
     * @param User|null $author
     *
     * @return self
     */
    public function setAuthor(?User $author = null): self
    {
        $this->author = $author;

        return $this;
    }

    /**
     * Get author
     *
     * @return User|null
     */
    public function getAuthor(): ?User
    {
        return $this->author;
    }

    /**
     * Set subject
     *
     * @param Membership|null $subject
     *
     * @return self
     */
    public function setSubject(?Membership $subject = null): self
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Get subject
     *
     * @return Membership|null
     */
    public function getSubject(): ?Membership
    {
        return $this->subject;
    }

    /**
     * Set parent
     *
     * @param Note|null $parent
     *
     * @return self
     */
    public function setParent(?Note $parent = null): self
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Get parent
     *
     * @return Note|null
     */
    public function getParent(): ?Note
    {
        return $this->parent;
    }

    /**
     * Add child
     *
     * @param Note $child
     *
     * @return self
     */
    public function addChild(Note $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children[] = $child;
            $child->setParent($this);
        }

        return $this;
    }

    /**
     * Remove child
     *
     * @param Note $child
     *
     * @return self
     */
    public function removeChild(Note $child): self
    {
        if ($this->children->removeElement($child)) {
            // set the owning side to null (unless already changed)
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }

        return $this;
    }

    /**
     * Get children
     *
     * @return Collection<int, Note>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }
}
