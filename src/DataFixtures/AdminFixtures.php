<?php

namespace App\DataFixtures;

use App\Entity\User;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AdminFixtures extends Fixture implements OrderedFixtureInterface
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $adminsCount = FixturesConstants::ADMINS_COUNT;

        // 5 admin ( ids = 51 to 55 )
        for ($i = 1; $i <= $adminsCount; $i++) {
            $user = new User();
            $user->setUsername('admin' . $i);
            $user->setEmail('admin' . $i . '@email.com');
            
            $hashedPassword = $this->passwordHasher->hashPassword($user, 'password');
            $user->setPassword($hashedPassword);
            
            $user->setEnabled(true);
            $user->setRoles(['ROLE_ADMIN']);

            $this->addReference('admin_' . $i, $user);

            $manager->persist($user);
        }

        $manager->flush();

        echo $adminsCount . " admins created\n";
    }

    public function getOrder(): int
    {
        return 2;
    }
}
