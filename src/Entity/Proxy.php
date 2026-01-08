<?php

namespace App\Entity;

use App\Repository\ProxyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'proxy')]
#[ORM\Entity(repositoryClass: ProxyRepository::class)]
class Proxy
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'proxies')]
    #[ORM\JoinColumn(name: 'event_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Event $event = null;

    #[ORM\ManyToOne(targetEntity: Beneficiary::class, inversedBy: 'received_proxies')]
    #[ORM\JoinColumn(name: 'owner', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Beneficiary $owner = null;

    #[ORM\ManyToOne(targetEntity: Membership::class, inversedBy: 'given_proxies')]
    #[ORM\JoinColumn(name: 'giver', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Membership $giver = null;

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
     * Set createdAt
     *
     * @param \DateTimeInterface|null $createdAt
     *
     * @return Proxy
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
     * Set event
     *
     * @param Event|null $event
     *
     * @return Proxy
     */
    public function setEvent(?Event $event = null): self
    {
        $this->event = $event;

        return $this;
    }

    /**
     * Get event
     *
     * @return Event|null
     */
    public function getEvent(): ?Event
    {
        return $this->event;
    }

    /**
     * Set owner
     *
     * @param Beneficiary|null $owner
     *
     * @return Proxy
     */
    public function setOwner(?Beneficiary $owner = null): self
    {
        $this->owner = $owner;

        return $this;
    }

    /**
     * Get owner
     *
     * @return Beneficiary|null
     */
    public function getOwner(): ?Beneficiary
    {
        return $this->owner;
    }

    /**
     * Set giver
     *
     * @param Membership|null $giver
     *
     * @return Proxy
     */
    public function setGiver(?Membership $giver = null): self
    {
        $this->giver = $giver;

        return $this;
    }

    /**
     * Get giver
     *
     * @return Membership|null
     */
    public function getGiver(): ?Membership
    {
        return $this->giver;
    }
}
