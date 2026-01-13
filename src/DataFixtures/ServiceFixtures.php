<?php

namespace App\DataFixtures;

use App\Entity\Service;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ServiceFixtures extends Fixture implements OrderedFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $serviceCounts = FixturesConstants::CLIENTS_COUNT;
        $serviceIcons = FixturesConstants::SERVICE_ICONS;
        $serviceSlugs = FixturesConstants::SERVICE_SLUGS;
        $serviceNames = FixturesConstants::SERVICE_NAMES;
        $serviceDescriptions = FixturesConstants::SERVICE_DESCRIPTIONS;

        for ($i = 0; $i < $serviceCounts; $i++) {
            $service = new Service();
            $service->setName($serviceNames[$i]);
            $service->setDescription($serviceDescriptions[$i]);
            $service->setIcon($serviceIcons[$i]);
            $service->setUrl("http://mattermost.com");
            $service->setSlug($serviceSlugs[$i]);
            $service->setPublic(rand(0, 1));

            $this->addReference('service_' . ($i + 1), $service);

            $manager->persist($service);
        }

        $manager->flush();

        echo $serviceCounts . " services created\n";
    }

    public function getOrder(): int
    {
        return 4;
    }
}
