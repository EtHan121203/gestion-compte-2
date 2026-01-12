<?php

namespace App\Controller;

use App\Entity\Beneficiary;
use App\Entity\SwipeCard;
use App\Entity\User;
use App\Security\SwipeCardVoter;
use App\Helper\SwipeCard as SwipeCardHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

/**
 * User controller.
 */
#[Route('/sw')] //keep it short for qr size
class SwipeCardController extends AbstractController
{
    private $swipeCardHelper;
    private $eventDispatcher;
    private $tokenStorage;

    public function __construct(
        SwipeCardHelper $swipeCardHelper,
        EventDispatcherInterface $eventDispatcher,
        TokenStorageInterface $tokenStorage
    ) {
        $this->swipeCardHelper = $swipeCardHelper;
        $this->eventDispatcher = $eventDispatcher;
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * Swipe Card login
     * used to connect to the app using qr
     */
    #[Route('/in/{code}', name: 'swipe_in', methods: ['GET'])]
    public function swipeInAction(Request $request, string $code, EntityManagerInterface $em): Response
    {
        $code = $this->swipeCardHelper->vigenereDecode($code);
        $card = $em->getRepository(SwipeCard::class)->findLastEnable($code);
        
        if (!$card) {
            $this->addFlash('error', "Oups, ce badge n'est pas actif ou n'est pas associé à un compte");
            $card = $em->getRepository(SwipeCard::class)->findOneBy(['code' => $code]);
            if ($card && !$card->getEnable() && !$card->getDisabledAt()) {
                $this->addFlash('warning', 'Si c\'est le tiens, <a href="'.$this->generateUrl('app_login').'">connecte toi</a> sur ton espace membre pour l\'activer');
            }
        } else {
            $user = $card->getBeneficiary()->getUser();
            $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
            $this->tokenStorage->setToken($token);
            
            $event = new InteractiveLoginEvent($request, $token);
            $this->eventDispatcher->dispatch($event, 'security.interactive_login');
        }
        
        return $this->redirectToRoute('homepage');
    }

    #[Route('/', name: 'swipe_card_homepage', methods: ['GET'])]
    public function homepage(): Response
    {
        return $this->render('user/swipe_card/homepage.html.twig');
    }

    /**
     * activate / pair Swipe Card
     */
    #[Route('/active/', name: 'active_swipe', methods: ['GET', 'POST'])]
    #[Route('/active/{id}', name: 'active_swipe_for_beneficiary', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function activeSwipeCardAction(Request $request, EntityManagerInterface $em, ?Beneficiary $beneficiary = null): Response
    {
        $this->denyAccessUnlessGranted(SwipeCardVoter::PAIR, new SwipeCard());
        $referer = $request->headers->get('referer');
        $code = $request->get('code');

        //verify code :
        if (!SwipeCard::checkEAN13($code)) {
            $this->addFlash('error', 'Hum, ces chiffres ne correspondent pas à un code badge valide... 🤔');
            return new RedirectResponse($referer);
        }
        
        $code = substr($code, 0, -1); //remove controle
        if ($code === '421234567890') {
            $this->addFlash('warning', 'Hihi, ceci est le numéro d&rsquo;exemple 😁 Utilise un badge physique 🍌');
            return new RedirectResponse($referer);
        }

        if (!$beneficiary) {
            $beneficiary = $this->getUser()->getBeneficiary();
        }
        
        $cards = $beneficiary->getEnabledSwipeCards();
        if ($cards->count()) {
            if ($beneficiary->getUser() === $this->getUser()) {
                $this->addFlash('error', 'Ton compte possède déjà un badge actif');
            } else {
                $this->addFlash('error', 'Il existe déjà un badge actif associé à ce compte');
            }
            return new RedirectResponse($referer);
        }

        $card = $em->getRepository(SwipeCard::class)->findOneBy(['code' => $code]);

        if ($card) {
            if ($card->getBeneficiary() != $this->getUser()->getBeneficiary()) {
                $this->addFlash('error', 'Ce badge est déjà associé à un autre utilisateur 👮');
            } else {
                $this->addFlash('error', 'Oups ! Ce badge est déjà associé mais il est inactif. Reactive le !');
            }
            return new RedirectResponse($referer);
        } else {
            $lastCard = $em->getRepository(SwipeCard::class)->findLast($this->getUser()->getBeneficiary());
            $card = new SwipeCard();
            $card->setBeneficiary($beneficiary);
            $card->setCode($code);
            $card->setNumber($lastCard ? max($lastCard->getNumber(), $beneficiary->getSwipeCards()->count()) + 1 : 1);
            $card->setEnable(true);
            $em->persist($card);
            $em->flush();
            $this->addFlash('success', 'Le badge ' . $card->getCode() . ' a bien été associé à ton compte.');
            return new RedirectResponse($referer);
        }
    }

    /**
     * enable existing Swipe Card
     */
    #[Route('/enable/', name: 'enable_swipe', methods: ['POST'])]
    #[Route('/enable/{id}', name: 'enable_swipe_for_beneficiary', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function enableSwipeCardAction(Request $request, EntityManagerInterface $em, ?Beneficiary $beneficiary = null): Response
    {
        $referer = $request->headers->get('referer');
        $code = $request->get('code');
        $code = $this->swipeCardHelper->vigenereDecode($code);

        if (!$beneficiary) {
            $beneficiary = $this->getUser()->getBeneficiary();
        }
        
        $cards = $beneficiary->getEnabledSwipeCards();
        if ($cards->count()) {
            $this->addFlash('error', 'Tu as déjà un badge actif');
            return new RedirectResponse($referer);
        }

        /** @var SwipeCard|null $card */
        $card = $em->getRepository(SwipeCard::class)->findOneBy(['code' => $code]);

        if ($card) {
            $this->denyAccessUnlessGranted(SwipeCardVoter::ENABLE, $card);
            if ($card->getBeneficiary() != $beneficiary) {
                if ($beneficiary === $this->getUser()->getBeneficiary()) {
                    $this->addFlash('error', 'Ce badge ne t\'appartient pas');
                } else {
                    $this->addFlash('error', 'Ce badge n\'appartient pas au beneficiaire');
                }
            } else {
                $card->setEnable(true);
                $card->setDisabledAt(null);
                $em->persist($card);
                $em->flush();
                $this->addFlash('success', 'Le badge #' . $card->getNumber() . ' a bien été ré-activé');
            }
        } else {
            $this->addFlash('error', 'Aucun badge ne correspond à ce code');
        }
        return new RedirectResponse($referer);
    }

    /**
     * disable Swipe Card
     */
    #[Route('/disable/', name: 'disable_swipe', methods: ['POST'])]
    #[Route('/disable/{id}', name: 'disable_swipe_for_beneficiary', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function disableSwipeCardAction(Request $request, EntityManagerInterface $em, ?Beneficiary $beneficiary = null): Response
    {
        $referer = $request->headers->get('referer');
        $code = $request->get('code');
        $code = $this->swipeCardHelper->vigenereDecode($code);

        /** @var SwipeCard|null $card */
        $card = $em->getRepository(SwipeCard::class)->findOneBy(['code' => $code]);
        if (!$beneficiary) {
            $beneficiary = $this->getUser()->getBeneficiary();
        }

        if ($card) {
            $this->denyAccessUnlessGranted(SwipeCardVoter::DISABLE, $card);
            if ($card->getBeneficiary() != $beneficiary) {
                if ($beneficiary === $this->getUser()->getBeneficiary()) {
                    $this->addFlash('error', 'Ce badge ne t\'appartient pas');
                } else {
                    $this->addFlash('error', 'Ce badge n\'appartient pas au beneficiaire');
                }
            } else {
                $card->setEnable(false);
                $em->persist($card);
                $em->flush();
                $this->addFlash('success', 'Ce badge est maintenant désactivé');
            }
        } else {
            $this->addFlash('error', 'Aucune badge trouvé');
        }
        return new RedirectResponse($referer);
    }

    /**
     * remove Swipe Card
     */
    #[Route('/delete/', name: 'delete_swipe', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteAction(Request $request, EntityManagerInterface $em): Response
    {
        $referer = $request->headers->get('referer');
        $code = $request->get('code');
        $code = $this->swipeCardHelper->vigenereDecode($code);

        /** @var SwipeCard|null $card */
        $card = $em->getRepository(SwipeCard::class)->findOneBy(['code' => $code]);

        if ($card) {
            $this->denyAccessUnlessGranted(SwipeCardVoter::DELETE, $card);
            $em->remove($card);
            $em->flush();
            $this->addFlash('success', 'Le badge ' . $code . ' a bien été supprimé');
        } else {
            $this->addFlash('error', 'Aucune badge trouvé');
        }
        return new RedirectResponse($referer);
    }

    #[Route('/{id}/show', name: 'swipe_show', methods: ['GET'])]
    #[IsGranted('ROLE_USER_MANAGER')]
    public function showAction(SwipeCard $card): Response
    {
        return $this->render('user/swipe_card.html.twig', [
            'card' => $card
        ]);
    }

    #[Route('/{code}/qr.png', name: 'swipe_qr', methods: ['GET'])]
    public function qrAction(string $code, EntityManagerInterface $em): Response
    {
        $code = urldecode($code);
        $code = $this->swipeCardHelper->vigenereDecode($code);
        $card = $em->getRepository(SwipeCard::class)->findOneBy(['code' => $code]);
        
        if (!$card) {
            throw $this->createAccessDeniedException();
        }

        $this->addFlash('warning', 'La génération de QR code nécessite une librairie compatible avec Symfony 7 (ex: endroid/qr-code).');
        return $this->redirectToRoute('homepage');
    }

    #[Route('/{code}/br.png', name: 'swipe_br', methods: ['GET'])]
    public function brAction(string $code, EntityManagerInterface $em): Response
    {
        $code = urldecode($code);
        $code = $this->swipeCardHelper->vigenereDecode($code);
        $card = $em->getRepository(SwipeCard::class)->findOneBy(['code' => $code]);
        
        if (!$card) {
            throw $this->createAccessDeniedException();
        }
        
        $this->addFlash('warning', 'La génération de code-barres nécessite une librairie compatible avec Symfony 7.');
        return $this->redirectToRoute('homepage');
    }
}
