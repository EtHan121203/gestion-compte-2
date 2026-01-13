<?php

namespace App\DataFixtures;

use App\Entity\Job;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class JobFixtures extends Fixture implements OrderedFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $jobTitles = FixturesConstants::JOB_TITLES;
        $jobColors = FixturesConstants::JOB_COLORS;
        $jobDescriptions = FixturesConstants::JOB_DESCRIPTIONS;
        $jobsCount = FixturesConstants::JOBS_COUNT;

        for ($i = 0; $i < $jobsCount; $i++) {
            $job = new Job();
            $job->setName($jobTitles[$i]);
            $job->setColor($jobColors[$i]);
            $job->setDescription($jobDescriptions[$i]);
            $job->setMinShifterAlert(rand(3, 5));

            if ($i == 4) {
                $job->setEnabled(false);
            } else {
                $job->setEnabled(true);
            }

            $this->setReference('job_' . ($i + 1), $job);

            $manager->persist($job);
        }

        $manager->flush();

        echo $jobsCount . " jobs created\n";
    }

    public function getOrder(): int
    {
        return 5;
    }
}
