<?php
// src/Entity/Client.php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Client
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    protected $id;

    #[ORM\Column(type: 'string', length: 255)]
    protected $randomId;

    #[ORM\Column(type: 'string', length: 255)]
    protected $secret;

    #[ORM\Column(type: 'json')]
    protected $redirectUris = [];

    #[ORM\Column(type: 'json')]
    protected $allowedGrantTypes = [];

    #[ORM\ManyToOne(inversedBy: 'clients', targetEntity: Service::class)]
    #[ORM\JoinColumn(name: 'service_id', referencedColumnName: 'id')]
    private ?Service $service = null;

    #[ORM\ManyToMany(mappedBy: 'clients', targetEntity: User::class)]
    private Collection $users;

    public function __construct()
    {
        $this->users = new ArrayCollection();
    }

    public function getRandomId()
    {
        return $this->randomId;
    }

    public function setRandomId($randomId)
    {
        $this->randomId = $randomId;
        return $this;
    }

    public function getSecret()
    {
        return $this->secret;
    }

    public function setSecret($secret)
    {
        $this->secret = $secret;
        return $this;
    }

    public function getRedirectUris()
    {
        return $this->redirectUris;
    }

    public function setRedirectUris(array $redirectUris)
    {
        $this->redirectUris = $redirectUris;
        return $this;
    }

    public function getAllowedGrantTypes()
    {
        return $this->allowedGrantTypes;
    }

    public function setAllowedGrantTypes(array $allowedGrantTypes)
    {
        $this->allowedGrantTypes = $allowedGrantTypes;
        return $this;
    }

    /**
     *
     * @return String
     */
    public function getUrls(){
        return implode(',', $this->getRedirectUris());
    }

    /**
     * Set service
     *
     * @param Service|null $service
     *
     * @return Client
     */
    public function setService(Service $service = null)
    {
        $this->service = $service;

        return $this;
    }

    /**
     * Get service
     *
     * @return Service|null
     */
    public function getService()
    {
        return $this->service;
    }


    /**
     * Add user
     *
     * @param User $user
     *
     * @return Client
     */
    public function addUser(User $user)
    {
        $this->users[] = $user;

        return $this;
    }

    /**
     * Remove user
     *
     * @param User $user
     */
    public function removeUser(User $user)
    {
        $this->users->removeElement($user);
    }

    /**
     * Get users
     *
     * @return Collection
     */
    public function getUsers()
    {
        return $this->users;
    }
}
