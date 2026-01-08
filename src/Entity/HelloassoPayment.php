<?php

namespace App\Entity;

use App\Repository\HelloassoPaymentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'helloasso_payment')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: HelloassoPaymentRepository::class)]
class HelloassoPayment
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'payment_id', type: 'integer', unique: true)]
    private ?int $paymentId = null;

    #[ORM\Column(name: 'campaign_id', type: 'integer', nullable: true)]
    private ?int $campaignId = null;

    #[ORM\Column(name: 'date', type: 'datetime')]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(name: 'amount', type: 'float')]
    private ?float $amount = null;

    #[ORM\Column(name: 'email', type: 'string')]
    private ?string $email = null;

    #[ORM\Column(name: 'payer_first_name', type: 'string')]
    private ?string $payer_first_name = null;

    #[ORM\Column(name: 'payer_last_name', type: 'string')]
    private ?string $payer_last_name = null;

    #[ORM\Column(name: 'status', type: 'string')]
    private ?string $status = null;

    #[ORM\OneToOne(targetEntity: Registration::class, inversedBy: 'helloassoPayment')]
    #[ORM\JoinColumn(name: 'registration_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Registration $registration = null;

    public function __toString(): string
    {
        return '#' . $this->getId() . ' de ' . $this->getEmail() . ' le ' . ($this->getCreatedAt() ? $this->getCreatedAt()->format('d/m/Y à H:i') : '') . ' ' . $this->getAmount() . ' €';
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
    }

    /**
     * Get id.
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
     * @param \DateTimeInterface|null $date
     *
     * @return self
     */
    public function setCreatedAt(?\DateTimeInterface $date): self
    {
        $this->createdAt = $date;

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
     * Set date.
     *
     * @param \DateTimeInterface|null $date
     *
     * @return self
     */
    public function setDate(?\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    /**
     * Get date.
     *
     * @return \DateTimeInterface|null
     */
    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    /**
     * Set amount.
     *
     * @param float|null $amount
     *
     * @return self
     */
    public function setAmount(?float $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * Get amount.
     *
     * @return float|null
     */
    public function getAmount(): ?float
    {
        return $this->amount;
    }

    /**
     * Set payerFirstName.
     *
     * @param string|null $payerFirstName
     *
     * @return self
     */
    public function setPayerFirstName(?string $payerFirstName): self
    {
        $this->payer_first_name = $payerFirstName;

        return $this;
    }

    /**
     * Get payerFirstName.
     *
     * @return string|null
     */
    public function getPayerFirstName(): ?string
    {
        return $this->payer_first_name;
    }

    /**
     * Set payerLastName.
     *
     * @param string|null $payerLastName
     *
     * @return self
     */
    public function setPayerLastName(?string $payerLastName): self
    {
        $this->payer_last_name = $payerLastName;

        return $this;
    }

    /**
     * Get payerLastName.
     *
     * @return string|null
     */
    public function getPayerLastName(): ?string
    {
        return $this->payer_last_name;
    }

    /**
     * Set registration.
     *
     * @param Registration|null $registration
     *
     * @return self
     */
    public function setRegistration(?Registration $registration = null): self
    {
        $this->registration = $registration;

        return $this;
    }

    /**
     * Get registration.
     *
     * @return Registration|null
     */
    public function getRegistration(): ?Registration
    {
        return $this->registration;
    }

    /**
     * Set paymentId.
     *
     * @param int|null $paymentId
     *
     * @return self
     */
    public function setPaymentId(?int $paymentId): self
    {
        $this->paymentId = $paymentId;

        return $this;
    }

    /**
     * Get paymentId.
     *
     * @return int|null
     */
    public function getPaymentId(): ?int
    {
        return $this->paymentId;
    }

    /**
     * populate payment with action object.
     * https://dev.helloasso.com/v3/resources#detail-action
     *
     * @param object $ha_action_obj
     *
     * @return self
     */
    public function fromActionObj(object $ha_action_obj): self
    {
        $date = new \DateTime();
        $date->setTimestamp(strtotime($ha_action_obj->date));

        $amount = (float)str_replace(',', '.', (string)$ha_action_obj->amount);

        $this->setPaymentId($ha_action_obj->id_payment);
        $this->setDate($date);
        $this->setAmount($amount);
        $this->setCampaignId($ha_action_obj->id_campaign);
        $this->setPayerFirstName($ha_action_obj->first_name);
        $this->setPayerLastName($ha_action_obj->last_name);
        $this->setStatus($ha_action_obj->status);
        $this->setEmail($ha_action_obj->email);

        return $this;
    }

    public function fromPaymentObj(object $paymentObject, int $campaignId): self
    {
        $date = new \DateTime();
        $date->setTimestamp(strtotime($paymentObject->date));

        $amount = (float)$paymentObject->amount;

        $this->setPaymentId($paymentObject->id);
        $this->setDate($date);
        $this->setAmount($amount);
        $this->setCampaignId($campaignId);
        $this->setPayerFirstName($paymentObject->payer_first_name);
        $this->setPayerLastName($paymentObject->payer_last_name);
        $this->setStatus($paymentObject->status);
        $this->setEmail($paymentObject->payer_email);

        return $this;
    }

    /**
     * Set campaignId.
     *
     * @param int|null $campaignId
     *
     * @return self
     */
    public function setCampaignId(?int $campaignId): self
    {
        $this->campaignId = $campaignId;

        return $this;
    }

    /**
     * Get campaignId.
     *
     * @return int|null
     */
    public function getCampaignId(): ?int
    {
        return $this->campaignId;
    }

    /**
     * Set status.
     *
     * @param string|null $status
     *
     * @return self
     */
    public function setStatus(?string $status): self
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get status.
     *
     * @return string|null
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * Set email.
     *
     * @param string|null $email
     *
     * @return self
     */
    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get email.
     *
     * @return string|null
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }
}
