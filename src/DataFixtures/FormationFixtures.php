<?php

namespace App\DataFixtures;

use App\Entity\Beneficiary;
use App\Entity\Formation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class FormationFixtures extends Fixture implements OrderedFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $formation_names = FixturesConstants::FORMATION_NAMES;
        $formation_descriptions = FixturesConstants::FORMATION_DESCRIPTIONS;
        $formations_count = FixturesConstants::FORMATIONS_COUNT;

        for ($i = 0; $i < $formations_count; $i++) {
            $formation = new Formation();

            $formation->setDescription($formation_descriptions[$i]);
            $formation->setName($formation_names[$i]);

            // add beneficiary
            $beneficiary = $this->getReference('beneficiary_' . ($i + 1), Beneficiary::class);
            $formation->addBeneficiary($beneficiary);
            $beneficiary->addFormation($formation);

            $this->setReference('formation_' . ($i + 1), $formation);

            $manager->persist($formation);
            $manager->persist($beneficiary);
        }

        $manager->flush();

        echo $formations_count . " formations created\n";
    }

    public function getOrder(): int
    {
        return 10;
    }
}
