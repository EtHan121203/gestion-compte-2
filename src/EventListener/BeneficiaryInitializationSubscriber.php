<?php

namespace App\EventListener;

use App\Entity\Beneficiary;
use App\Entity\User;
use App\Event\BeneficiaryCreatedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class BeneficiaryInitializationSubscriber implements EventSubscriberInterface
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var UserPasswordHasherInterface
     */
    private $passwordHasher;


    public function __construct(EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher)
    {
        $this->em = $em;
        $this->passwordHasher = $passwordHasher;
    }

    public static function getSubscribedEvents()
    {
        return array(
            FormEvents::SUBMIT       => 'postInitializeMembership',
        );
    }

    public function onBeforePersist(BeneficiaryCreatedEvent $event)
    {
        $this->makeUser($event->getBeneficiary());
    }

    public function postInitializeMembership(FormEvent $event)
    {
        $this->makeUser($event->getData());
    }

    private function makeUser(Beneficiary $beneficiary){
        if ($beneficiary) {
            if (!$beneficiary->getUser()) {
                $user = new User();
                $beneficiary->setUser($user);
            }

            if (!$beneficiary->getUser()->getUsername()) {

                $username = $this->generateUsername($beneficiary);
                $beneficiary->getUser()->setUsername($username);
            }

            if (!$beneficiary->getUser()->getPassword()) {
                $user = $beneficiary->getUser();
                $randomPassword = User::randomPassword();
                $user->setPassword(
                    $this->passwordHasher->hashPassword(
                        $user,
                        $randomPassword
                    )
                );
                // We might want to save the plain password somewhere or send it by email
                // but for now let's at least hash it so login is possible later after reset
            }
        }
    }

    private function generateUsername(Beneficiary $beneficiary)
    {
        if (!$beneficiary->getFirstname() || !$beneficiary->getLastname()) {
            return null;
        }
        $username = User::makeUsername($beneficiary->getFirstname(), $beneficiary->getLastname());
        $qb = $this->em->createQueryBuilder();
        $usernames = $qb->select('u')->from('App\Entity\User', 'u')
            ->where($qb->expr()->like('u.username', $qb->expr()->literal($username . '%')))
            ->orderBy('u.username', 'DESC')
            ->getQuery()
            ->getResult();

        if (count($usernames)) {
            $username = $username . + count($usernames);
        }
        return $username;
    }
}
