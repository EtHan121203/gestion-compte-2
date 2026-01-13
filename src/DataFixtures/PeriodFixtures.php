<?php

namespace App\DataFixtures;

use App\Entity\Formation;
use App\Entity\Job;
use App\Entity\Period;
use App\Entity\PeriodPosition;
use DateInterval;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Exception;

class PeriodFixtures extends Fixture implements OrderedFixtureInterface
{
    /**
     * @throws Exception
     */
    public function load(ObjectManager $manager): void
    {
        $enabled_jobs_count = FixturesConstants::ENABLED_JOBS_COUNT;

        for ($i = 0; $i < 7; $i++) {
            $period = new Period();

            $randomTime = rand(9, 18);
            $startDate = new DateTime();
            $startDate->setTime($randomTime, 0);
            $endDate = (clone $startDate)->add(new DateInterval('PT' . 2 . 'H'));

            $period->setStart($startDate);
            $period->setEnd($endDate);

            $period->setDayOfWeek($i % 7);

            $randJobId = rand(1, $enabled_jobs_count);
            $job = $this->getReference('job_' . $randJobId, Job::class);
            $period->setJob($job);

            $weekCycles = ["A", "B", "C", "D"];
            for ($j = 0; $j < 4; $j++) {

                $weekCycle = $weekCycles[$j];

                for ($k = 0; $k < rand(2, 5); $k++) {
                    $periodPosition = new PeriodPosition();
                    $periodPosition->setPeriod($period);
                    $periodPosition->setFormation($this->getReference('formation_' . $randJobId, Formation::class));
                    $periodPosition->setWeekCycle($weekCycle);
                    $period->addPosition($periodPosition);
                    $manager->persist($periodPosition);
                }
            }

            $manager->persist($period);
        }

        $manager->flush();

        echo "7 periods per week with random number of positions created\n";
    }

    public function getOrder(): int
    {
        return 13;
    }
}
