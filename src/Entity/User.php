<?php
// src/Entity/User.php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'fos_user')]
#[UniqueEntity(fields: ['email'], message: 'Cette adresse e-mail est déjà utilisée par un autre compte')]
#[UniqueEntity(fields: ['username'], message: "Ce nom d'utilisateur est déjà utilisé par un autre compte")]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    private ?string $username = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\OneToMany(mappedBy: 'registrar', targetEntity: Registration::class, cascade: ['persist'])]
    #[ORM\OrderBy(['date' => 'DESC'])]
    private Collection $recordedRegistrations;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: Beneficiary::class)]
    #[Assert\Valid]
    private ?Beneficiary $beneficiary = null;

    #[ORM\ManyToMany(inversedBy: 'users', targetEntity: Client::class)]
    #[ORM\JoinTable(name: 'users_clients')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'client_id', referencedColumnName: 'id')]
    private Collection $clients;

    #[ORM\OneToMany(mappedBy: 'author', targetEntity: Note::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $annotations;

    #[ORM\OneToMany(mappedBy: 'author', targetEntity: ProcessUpdate::class, cascade: ['persist'])]
    #[ORM\OrderBy(['date' => 'DESC'])]
    private Collection $processUpdates;

    public function __construct()
    {
        $this->recordedRegistrations = new ArrayCollection();
        $this->clients = new ArrayCollection();
        $this->annotations = new ArrayCollection();
        $this->processUpdates = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return (string) $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getGroups()
    {
        if ($this->getBeneficiary()){
            return $this->getBeneficiary()->getFormations()->toArray();
        }else{
            return new ArrayCollection();
        }
    }

    public function __get($property) {
        if (property_exists($this, $property)) {
            return $this->$property;
        } elseif ($this->getBeneficiary() && property_exists($this->getBeneficiary(), $property)) {
            return $this->getBeneficiary()->$property;
        }
    }

    public function __set($property, $value) {
        if (property_exists($this, $property)) {
            $this->$property = $value;
        }

        return $this;
    }

    public function getFirstname() {
        $beneficiary = $this->getBeneficiary();
        if ($beneficiary)
            return $beneficiary->getFirstname();
        else
            return $this->getUsername();

    }

    public function getLastname() {
        $beneficiary = $this->getBeneficiary();
        if ($beneficiary)
            return $beneficiary->getLastname();
        else
            return '';
    }

    public function __toString()
    {
        if (!$this->getBeneficiary())
            return (string) $this->getUsername();
        else{
            return (string)$this->getBeneficiary();
        }
    }

    public function getTmpToken($key = '')
    {
        return md5($this->getEmail() . $this->getLastname() . $this->getPassword() . $key . date('d'));
    }

    public function getAnonymousEmail()
    {
        $email = $this->getEmail();
        $splited = explode("@", $email);
        $return = '';
        foreach ($splited as $part) {
            $splited_part = explode(".", $part);
            foreach ($splited_part as $mini_part) {
                $first_char = substr($mini_part, 0, 1);
                $last_char = substr($mini_part, strlen($mini_part) - 1, 1);
                $center = substr($mini_part, 1, strlen($mini_part) - 2);
                if (strlen($center) > 0)
                    $return .= $first_char . preg_replace('/./', '_', $center) . $last_char;
                elseif (strlen($mini_part) > 1)
                    $return .= $first_char . $last_char;
                else
                    $return .= $first_char;
                $return .= '.';
            }
            $return = substr($return, 0, strlen($return) - 1);
            $return .= '@';
        }
        $return = substr($return, 0, strlen($return) - 1);
        return preg_replace('/_{3}_*/', '___', $return);
    }

    public function getAnonymousLastname()
    {
        $lastname = $this->getLastname();
        $splited = explode(" ", $lastname);
        $return = '';
        foreach ($splited as $part) {
            $splited_part = explode("-", $part);
            foreach ($splited_part as $mini_part) {
                $first_char = substr($mini_part, 0, 1);
                $last_char = substr($mini_part, strlen($mini_part) - 1, 1);
                $center = substr($mini_part, 1, strlen($mini_part) - 2);
                if (strlen($center) > 0)
                    $return .= $first_char . preg_replace('/./', '*', $center) . $last_char;
                else
                    $return .= $first_char . $last_char;
                $return .= '-';
            }
            $return = substr($return, 0, strlen($return) - 1);
            $return .= ' ';
        }
        $return = substr($return, 0, strlen($return) - 1);
        return $return;
    }

    static function makeUsername($firstname, $lastname, $extra = '')
    {
        $unwanted_array = array(    'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
            'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U',
            'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
            'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
            'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y' );

        $firstname = strtr( $firstname, $unwanted_array );
        $lastname = strtr( $lastname, $unwanted_array );

        $lastname = preg_replace('/[-\/]+/', ' ', $lastname);
        $ln = explode(' ', $lastname);

        if (count($ln) > 1 && strlen($ln[0]) < 3)
            $ln = $ln[0] . $ln[1];
        else
            $ln = $ln[0];
        $username = strtolower(substr(explode(' ', $firstname)[0], 0, 1) . $ln);
        $username = preg_replace('/[^a-z0-9]/', '', $username);
        $username .= $extra;
        return $username;
    }

    static function randomPassword()
    {
        return \bin2hex(\random_bytes(20));
    }

    /**
     * Add recordedRegistration
     *
     * @param Registration $recordedRegistration
     *
     * @return User
     */
    public function addRecordedRegistration(Registration $recordedRegistration)
    {
        $this->recordedRegistrations[] = $recordedRegistration;

        return $this;
    }

    /**
     * Remove recordedRegistration
     *
     * @param Registration $recordedRegistration
     */
    public function removeRecordedRegistration(Registration $recordedRegistration)
    {
        $this->recordedRegistrations->removeElement($recordedRegistration);
    }

    /**
     * Get recordedRegistrations
     *
     * @return Collection
     */
    public function getRecordedRegistrations()
    {
        return $this->recordedRegistrations;
    }

    /**
     * determine whether the given client is allowed by the user, or not.
     * @param Client $client
     * @return bool
     */
    public function isAuthorizedClient(Client $client)
    {
        return $this->getClients()->contains($client);
    }

    /**
     * Add client
     *
     * @param Client $client
     *
     * @return User
     */
    public function addClient(Client $client)
    {
        $this->clients[] = $client;

        return $this;
    }

    /**
     * Remove client
     *
     * @param Client $client
     */
    public function removeClient(Client $client)
    {
        $this->clients->removeElement($client);
    }

    /**
     * Get clients
     *
     * @return Collection
     */
    public function getClients()
    {
        return $this->clients;
    }


    /**
     * Add annotation
     *
     * @param Note $annotation
     *
     * @return User
     */
    public function addAnnotation(Note $annotation)
    {
        $this->annotations[] = $annotation;

        return $this;
    }

    /**
     * Remove annotation
     *
     * @param Note $annotation
     */
    public function removeAnnotation(Note $annotation)
    {
        $this->annotations->removeElement($annotation);
    }

    /**
     * Get annotations
     *
     * @return Collection
     */
    public function getAnnotations()
    {
        return $this->annotations;
    }

    /**
     * @return Beneficiary|null
     */
    public function getBeneficiary()
    {
        return $this->beneficiary;
    }

    /**
     * @param mixed $beneficiary
     */
    public function setBeneficiary($beneficiary)
    {
        $this->beneficiary = $beneficiary;
    }

    /**
     * @return string
     */
    public function getBeneficiaryStringWithLink()
    {
        if ($this->getBeneficiary()) {
            return '<a href="{{ path("member_show", { \'member_number\': '. $this->getBeneficiary()->getMembership()->getMemberNumber() .' }) }}">'. $this->getBeneficiary() .'</a>';
        } else {
            return (string) $this;
        }
    }

    /**
     * @return Collection
     */
    public function getProcessUpdates()
    {
        return $this->processUpdates;
    }
}
