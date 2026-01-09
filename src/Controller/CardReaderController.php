<?php

namespace App\Controller;

use App\Entity\Shift;
use App\Entity\SwipeCard;
use App\Event\SwipeCardEvent;
use App\Event\ShiftValidatedEvent;
use App\Service\MembershipService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[Route('/card_reader')]
class CardReaderController extends AbstractController
{
    private bool $swipeCardLogging;
    private bool $swipeCardLoggingAnonymous;

    public function __construct(
        #[Autowire(param: 'swipe_card_logging')] bool $swipeCardLogging,
        #[Autowire(param: 'swipe_card_logging_anonymous')] bool $swipeCardLoggingAnonymous
    ) {
        $this->swipeCardLogging = $swipeCardLogging;
        $this->swipeCardLoggingAnonymous = $swipeCardLoggingAnonymous;
    }

    #[Route('/check', name: 'card_reader_check', methods: ['POST'])]
    public function check(Request $request, EntityManagerInterface $em, MembershipService $membershipService, EventDispatcherInterface $eventDispatcher): Response
    {
        $code = $request->get('swipe_code');

        if (!$code) {
            return $this->redirectToRoute('cardReader');
        }
        if (!SwipeCard::checkEAN13($code)) {
            return $this->redirectToRoute('cardReader');
        }

        $code = substr($code, 0, -1);
        $card = $em->getRepository(SwipeCard::class)->findOneBy(['code' => $code, 'enable' => 1]);

        if (!$card) {
            $this->addFlash("error", "Oups, ce badge n'est pas actif ou n'existe pas");
        } else {
            $beneficiary = $card->getBeneficiary();
            $membership = $beneficiary->getMembership();
            $cycle_end = $membershipService->getEndOfCycle($membership, 0);
            $counter = $membership->getShiftTimeCount($cycle_end);
            
            if ($this->swipeCardLogging) {
                $scEvent = new SwipeCardEvent($this->swipeCardLoggingAnonymous ? null : $card, $counter);
                $eventDispatcher->dispatch($scEvent, SwipeCardEvent::SWIPE_CARD_SCANNED);
            }
            
            $shifts = $em->getRepository(Shift::class)->getOnGoingShifts($beneficiary);
            foreach ($shifts as $shift) {
                if ($shift->getWasCarriedOut() == 0) {
                    $shift->validateShiftParticipation();
                    $em->persist($shift);
                    $em->flush();
                    $eventDispatcher->dispatch(new ShiftValidatedEvent($shift), ShiftValidatedEvent::NAME);
                }
            }
            
            return $this->render('user/check.html.twig', [
                'beneficiary' => $beneficiary,
                'counter' => $counter
            ]);
        }

        return $this->redirectToRoute('cardReader');
    }
}
