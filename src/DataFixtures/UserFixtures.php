<?php

namespace App\DataFixtures;

use App\Entity\User;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture implements OrderedFixtureInterface
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $firstnames = FixturesConstants::FIRSTNAMES;
        $lastnames = FixturesConstants::LASTNAMES;
        $userCount = FixturesConstants::USERS_COUNT;

        // 50 users
        for ($i = 1; $i <= $userCount; $i++) {
            $user = new User();

            $user->setEmail($firstnames[$i - 1] . $lastnames[$i - 1] . '@email.com');
            
            $hashedPassword = $this->passwordHasher->hashPassword($user, 'password');
            $user->setPassword($hashedPassword);
            
            $user->setEnabled(true);
            $user->setRoles(['ROLE_USER']);
            $user->setUsername($firstnames[$i - 1] . " " . $lastnames[$i - 1]);

            $this->addReference('user_' . $i, $user);

            $manager->persist($user);
        }

        $manager->flush();

        echo $userCount . " users created\n";
    }

    public function getOrder(): int
    {
        return 1;
    }
}
