<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'swipe_card_log')]
#[ORM\Entity]
class SwipeCardLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SwipeCard::class, inversedBy: 'logs')]
    #[ORM\JoinColumn(name: 'swipe_card_id', referencedColumnName: 'id', nullable: true)]
    private ?SwipeCard $swipeCard = null;

    #[ORM\Column(name: 'counter', type: 'integer')]
    private ?int $counter = null;

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $date = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setSwipeCard(?SwipeCard $swipeCard): self
    {
        $this->swipeCard = $swipeCard;

        return $this;
    }

    public function getSwipeCard(): ?SwipeCard
    {
        return $this->swipeCard;
    }

    public function setCounter(?int $counter): self
    {
        $this->counter = $counter;

        return $this;
    }

    public function getCounter(): ?int
    {
        return $this->counter;
    }

    public function setDate(?\DateTimeInterface $date = null): self
    {
        $this->date = $date;

        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }
}
