<?php

namespace App\Entity;

use App\Repository\SwipeCardRepository;
use CodeItNow\BarcodeBundle\Utils\BarcodeGenerator;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'swipe_card')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: SwipeCardRepository::class)]
class SwipeCard
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'disabled_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $disabled_at = null;

    #[ORM\Column(name: 'number', type: 'integer')]
    private ?int $number = null;

    #[ORM\Column(name: 'enable', type: 'boolean', nullable: true, options: ['default' => 0])]
    private ?bool $enable = null;

    #[ORM\Column(name: 'code', type: 'string', length: 50, unique: true)]
    private ?string $code = null;

    #[ORM\ManyToOne(targetEntity: Beneficiary::class, inversedBy: 'swipe_cards')]
    #[ORM\JoinColumn(name: 'beneficiary_id', referencedColumnName: 'id')]
    private ?Beneficiary $beneficiary = null;

    /** @var Collection<int, SwipeCardLog> */
    #[ORM\OneToMany(targetEntity: SwipeCardLog::class, mappedBy: 'swipeCard', cascade: ['persist'])]
    private Collection $logs;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->logs = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
        $this->disabled_at = null;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setNumber(?int $number): self
    {
        $this->number = $number;

        return $this;
    }

    public function getNumber(): ?int
    {
        return $this->number;
    }

    public function setCode(?string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setEnable(?bool $enable = null): self
    {
        $this->enable = $enable;

        if (!$enable){
            $this->setDisabledAt(new \DateTime('now'));
        }else{
            $this->setDisabledAt(null);
        }

        return $this;
    }

    public function getEnable(): ?bool
    {
        if ($this->getDisabledAt()) //forever
            return false;
        return $this->enable;
    }

    public function setBeneficiary(?Beneficiary $beneficiary = null): self
    {
        $this->beneficiary = $beneficiary;

        return $this;
    }

    public function getBeneficiary(): ?Beneficiary
    {
        return $this->beneficiary;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setDisabledAt(?\DateTimeInterface $disabledAt): self
    {
        $this->disabled_at = $disabledAt;

        return $this;
    }

    public function getDisabledAt(): ?\DateTimeInterface
    {
        return $this->disabled_at;
    }

    public function getBarcode(): string
    {
        $barcode = new BarcodeGenerator();
        $barcode->setText((string) $this->getCode());
        $barcode->setType(BarcodeGenerator::Ean13);
        $barcode->setScale(2);
        $barcode->setThickness(25);
        $barcode->setFontSize(10);
        return $barcode->generate();
    }

    //FROM : \CodeItNow\BarcodeBundle\Generator\CINean13::calculateChecksum
    public static function checkEAN13(string $code, ?string $checksum = null): bool
    {
        $c = strlen($code);
        if ($c === 13) {
            if (!$checksum){
                $checksum = substr($code, -1, 1);
            }
            $code = substr($code, 0, 12);
        } elseif ($c !== 12 || !$checksum) {
            return false;
        }
        $odd = true;
        $checksumValue = 0;
        $keys = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
        $c = strlen($code);
        for ($i = $c; $i > 0; $i--) {
            if ($odd === true) {
                $multiplier = 3;
                $odd = false;
            } else {
                $multiplier = 1;
                $odd = true;
            }

            if (!isset($keys[$code[$i - 1]])) {
                return false;
            }

            $checksumValue += $keys[$code[$i - 1]] * $multiplier;
        }

        $checksumValue = (10 - $checksumValue % 10) % 10;

        return (string) $checksumValue === (string) $checksum;
    }
}
