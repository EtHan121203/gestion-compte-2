<?php

namespace App\Entity;

use App\Repository\AnonymousBeneficiaryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Validator\Constraints as AppAssert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Table(name: 'anonymous_beneficiary')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: AnonymousBeneficiaryRepository::class)]
#[UniqueEntity('email')]
class AnonymousBeneficiary
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'email', type: 'string', length: 191, unique: true)]
    #[Assert\Email]
    #[Assert\NotBlank(message: "L'email doit être saisie")]
    #[AppAssert\UniqueEmail]
    private ?string $email = null;

    #[ORM\OneToOne(targetEntity: Beneficiary::class)]
    #[ORM\JoinColumn(name: 'join_to', referencedColumnName: 'id', onDelete: 'SET NULL')]
    #[AppAssert\BeneficiaryCanHost]
    private ?Beneficiary $join_to = null;

    #[ORM\Column(name: 'beneficiaries_emails', type: 'string', length: 255, nullable: true)]
    private ?string $beneficiaries_emails = null;

    #[ORM\Column(name: 'amount', type: 'string', length: 255, nullable: true)]
    private ?string $amount = null;

    #[ORM\Column(name: 'mode', type: 'integer', nullable: true)]
    private ?int $mode = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'registrar_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $registrar = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'recall_date', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $recallDate = null;

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
     * Set amount
     *
     * @param string|null $amount
     *
     * @return self
     */
    public function setAmount(?string $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * Get amount
     *
     * @return string|null
     */
    public function getAmount(): ?string
    {
        return $this->amount;
    }

    /**
     * Set beneficiaries_emails
     *
     * @param string|null $beneficiaries_emails
     *
     * @return self
     */
    public function setBeneficiariesEmails(?string $beneficiaries_emails): self
    {
        $this->beneficiaries_emails = $beneficiaries_emails;

        return $this;
    }

    /**
     * Get beneficiaries_emails
     *
     * @return string|null
     */
    public function getBeneficiariesEmails(): ?string
    {
        return $this->beneficiaries_emails;
    }

    /**
     * Get beneficiaries_emails as array
     *
     * @return array
     */
    public function getBeneficiariesEmailsAsArray(): array
    {
        return array_filter(explode(', ', (string)$this->getBeneficiariesEmails()));
    }

    /**
     * Set mode
     *
     * @param int|null $mode
     *
     * @return self
     */
    public function setMode(?int $mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    /**
     * Get mode
     *
     * @return int|null
     */
    public function getMode(): ?int
    {
        return $this->mode;
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
     * Set recallDate
     *
     * @param \DateTimeInterface|null $date
     *
     * @return self
     */
    public function setRecallDate(?\DateTimeInterface $date): self
    {
        $this->recallDate = $date;

        return $this;
    }

    /**
     * Get recallDate
     *
     * @return \DateTimeInterface|null
     */
    public function getRecallDate(): ?\DateTimeInterface
    {
        return $this->recallDate;
    }

    /**
     * Set email
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
     * Get email
     *
     * @return string|null
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }


    /**
     * Set join_to
     *
     * @param Beneficiary|null $beneficiary
     *
     * @return self
     */
    public function setJoinTo(?Beneficiary $beneficiary): self
    {
        $this->join_to = $beneficiary;

        return $this;
    }

    /**
     * Get join_to
     *
     * @return Beneficiary|null
     */
    public function getJoinTo(): ?Beneficiary
    {
        return $this->join_to;
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

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context, $payload): void
    {
        // check data consistency
        if (!$this->getJoinTo()){
            // Note: Registration::TYPE_HELLOASSO needs to be defined in Registration entity
            if (!$this->getAmount() && $this->getMode() != (\App\Entity\Registration::TYPE_HELLOASSO ?? null)) {
                $context->buildViolation('Pour une nouvelle adhésion, merci de saisir un montant')
                    ->atPath('amount')
                    ->addViolation();
            }
            if (!$this->getMode()) {
                $context->buildViolation('Merci de saisir le moyen de paiement')
                    ->atPath('mode')
                    ->addViolation();
            }
        }else{
            if ($this->getAmount()) {
                $context->buildViolation('Pour un ajout de beneficiaire sur un compte existant, merci de ne pas enregistrer de paiement')
                    ->atPath('amount')
                    ->addViolation();
            }
            if ($this->getMode()) {
                $context->buildViolation('Pour un ajout de beneficiaire sur un compte existant, merci de ne pas enregistrer de mode paiement')
                    ->atPath('mode')
                    ->addViolation();
            }
        }
    }
}
