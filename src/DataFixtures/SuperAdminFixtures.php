<?php

namespace App\DataFixtures;

use App\Entity\User;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SuperAdminFixtures extends Fixture implements OrderedFixtureInterface
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // 1 super admin ( id = 56 )
        $user = new User();
        $user->setUsername('admin');
        $user->setEmail('superadmin@email.com');
        
        $hashedPassword = $this->passwordHasher->hashPassword($user, 'password');
        $user->setPassword($hashedPassword);
        
        $user->setEnabled(true);
        $user->setRoles(['ROLE_SUPER_ADMIN', 'ROLE_ADMIN']);

        $this->addReference('superadmin', $user);

        $manager->persist($user);
        $manager->flush();

        echo "1 super admin created\n";
    }

    public function getOrder(): int
    {
        return 3;
    }
}
