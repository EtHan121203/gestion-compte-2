<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;

use App\Entity\Beneficiary;
use App\Entity\SwipeCard;
use App\Entity\User;
use App\Security\SwipeCardVoter;
use App\Service\SearchUserFormHelper;
use CodeItNow\BarcodeBundle\Utils\BarcodeGenerator;
use CodeItNow\BarcodeBundle\Utils\QrCode;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

/**
 * User controller.
 *
 */
 #[Route("/sw")] //keep it short for qr size

class SwipeCardController extends AbstractController
{

    /**
     * Swipe Card login
     * used to connect to the app using qr
     *
     * @param String $code
     * @param Request $request
     * @return Response
     */
    #[Route("/in/{code}", name: "swipe_in", methods: ['GET'])]

    public function swipeInAction(Request $request, $code, EntityManagerInterface $em){
        $session = new Session();
        $code = $this->get('App\Helper\SwipeCard')->vigenereDecode($code);
        $card = $em->getRepository('App\Entity\SwipeCard')->findLastEnable($code);
        if (!$card){
            $session->getFlashBag()->add("error","Oups, ce badge n'est pas actif ou n'est pas associé à un compte");
            $card = $em->getRepository('App\Entity\SwipeCard')->findOneBy(array("code"=>$code));
            if ($card && !$card->getEnable() && !$card->getDisabledAt())
                $session->getFlashBag()->add("warning","Si c'est le tiens, <a href=\"".$this->generateUrl('fos_user_security_login')."\">connecte toi</a> sur ton espace membre pour l'activer");
        }else{
            $user = $card->getBeneficiary()->getUser();
            $token = new UsernamePasswordToken($user, $user->getPassword(), "main", $user->getRoles());
            $this->get("security.token_storage")->setToken($token);
            $event = new InteractiveLoginEvent($request, $token);
            $this->get("event_dispatcher")->dispatch("security.interactive_login", $event);
        }
        return $this->redirectToRoute('homepage');
    }

    EntityManagerInterface pEntityManagerInterface uEntityManagerInterface bEntityManagerInterface lEntityManagerInterface iEntityManagerInterface cEntityManagerInterface  EntityManagerInterface fEntityManagerInterface uEntityManagerInterface nEntityManagerInterface cEntityManagerInterface tEntityManagerInterface iEntityManagerInterface oEntityManagerInterface nEntityManagerInterface  EntityManagerInterface hEntityManagerInterface oEntityManagerInterface mEntityManagerInterface eEntityManagerInterface pEntityManagerInterface aEntityManagerInterface gEntityManagerInterface eEntityManagerInterface AEntityManagerInterface cEntityManagerInterface tEntityManagerInterface iEntityManagerInterface oEntityManagerInterface nEntityManagerInterface (EntityManagerInterface $em)EntityManagerInterface {
        return $this->render('user/swipe_card/homepage.html.twig');
    }

    /**
     * activate / pair Swipe Card
     *
     * @param Request $request
     * @param Beneficiary $beneficiary
     * @return Response
     */
    #[Route("/active/", name: "active_swipe", methods: ['GET', 'POST'])]

    #[Route("/active/{id}", name: "active_swipe_for_beneficiary", methods: ['POST'])]

    #[IsGranted('ROLE_USER')]

    public function activeSwipeCardAction(Request $request,Beneficiary $beneficiary = null, EntityManagerInterface $em)
    {
        $session = new Session();
        $this->denyAccessUnlessGranted(SwipeCardVoter::PAIR, new SwipeCard());
        $referer = $request->headers->get('referer');

        $code = $request->get("code");
        //verify code :
        if (!SwipeCard::checkEAN13($code)) {
            $session->getFlashBag()->add('error', 'Hum, ces chiffres ne correspondent pas à un code badge valide... 🤔');
            return new RedirectResponse($referer);
        }
        $code = substr($code, 0, -1); //remove controle
        if ($code === '421234567890'){
            $session->getFlashBag()->add('warning', 'Hihi, ceci est le numéro d&rsquo;exemple 😁 Utilise un badge physique 🍌');
            return new RedirectResponse($referer);
        }

        if (!$beneficiary){
            $beneficiary = $this->getUser()->getBeneficiary();
        }
        $cards = $beneficiary->getEnabledSwipeCards();
        if ($cards->count()) {
            if ($beneficiary->getUser() === $this->getUser())
                $session->getFlashBag()->add('error', 'Ton compte possède déjà un badge actif');
            else
                $session->getFlashBag()->add('error', 'Il existe déjà un badge actif associé à ce compte');
            return new RedirectResponse($referer);
        }

        $card = $em->getRepository('App\Entity\SwipeCard')->findOneBy(array('code' => $code));

        if ($card) {
            if ($card->getBeneficiary() != $this->getUser()->getBeneficiary()) {
                $session->getFlashBag()->add('error', 'Ce badge est déjà associé à un autre utilisateur 👮');
            } else {
                $session->getFlashBag()->add('error', 'Oups ! Ce badge est déjà associé mais il est inactif. Reactive le !');
            }
            return new RedirectResponse($referer);
        } else {
            $lastCard = $em->getRepository('App\Entity\SwipeCard')->findLast($this->getUser()->getBeneficiary());
            $card = new SwipeCard();
            $card->setBeneficiary($beneficiary);
            $card->setCode($code);
            $card->setNumber($lastCard ? max($lastCard->getNumber(),$beneficiary->getSwipeCards()->count()) + 1 : 1);
            $card->setEnable(1);
            $em->persist($card);
            $em->flush();
            $session->getFlashBag()->add('success', 'Le badge ' . $card->getcode() . ' a bien été associé à ton compte.');
            return new RedirectResponse($referer);
        }
    }

    /**
     * enable existing Swipe Card
     *
     * @param Request $request
     * @param Beneficiary $beneficiary
     * @return Response
     */
    #[Route("/enable/", name: "enable_swipe")]

    #[Route("/enable/{id}", name: "enable_swipe_for_beneficiary", methods: ['POST'])]

    #[IsGranted('ROLE_USER')]

    public function enableSwipeCardAction(Request $request,Beneficiary $beneficiary = null, EntityManagerInterface $em){
        $session = new Session();
        $referer = $request->headers->get('referer');

        $code = $request->get("code");
        $code = $this->get('App\Helper\SwipeCard')->vigenereDecode($code);

        if (!$beneficiary){
            $beneficiary = $this->getUser()->getBeneficiary();
        }
        $cards = $beneficiary->getEnabledSwipeCards();
        if ($cards->count()) {
            $session->getFlashBag()->add('error', 'Tu as déjà un badge actif');
            return new RedirectResponse($referer);
        }

    /**
     * disable Swipe Card
     *
     * @param Request $request
     * @param Beneficiary $beneficiary
     * @return Response
     */
    #[Route("/disable/", name: "disable_swipe")]

    #[Route("/disable/{id}", name: "disable_swipe_for_beneficiary", methods: ['POST'])]

    #[IsGranted('ROLE_USER')]

    public function disableSwipeCardAction(Request $request,Beneficiary $beneficiary = null, EntityManagerInterface $em){
        $session = new Session();
        $referer = $request->headers->get('referer');

        $code = $request->get("code");
        $code = $this->get('App\Helper\SwipeCard')->vigenereDecode($code);

    /**
     * remove Swipe Card
     *
     * @param Request $request
     * @return Response
     */
    #[Route("/delete/", name: "delete_swipe", methods: ['POST'])]

    #[IsGranted('ROLE_ADMIN')]

    public function deleteAction(Request $request, EntityManagerInterface $em){
        $session = new Session();
        $referer = $request->headers->get('referer');

        $code = $request->get("code");
        $code = $this->get('App\Helper\SwipeCard')->vigenereDecode($code);

    /**
     * show Swipe Card
     *
     * @param SwipeCard $card
     * @return Response A Response instance
     */
    #[Route("/{id}/show", name: "swipe_show", methods: ['GET'])]

    #[IsGranted('ROLE_USER_MANAGER')]

    public function showAction(SwipeCard $card, EntityManagerInterface $em){
        return $this->render('user/swipe_card.html.twig', [
            'card' => $card
        ]);
    }

    private function _getQr($url){
        $qrCode = new QrCode();
        $qrCode
                ->setText($url)
                ->setSize(200)
                ->setPadding(0)
                ->setErrorCorrection('high')
                ->setForegroundColor(array('r' => 0, 'g' => 0, 'b' => 0, 'a' => 0))
                ->setBackgroundColor(array('r' => 255, 'g' => 255, 'b' => 255, 'a' => 0))
                ->setImageType(QrCode::IMAGE_TYPE_PNG);
        return $qrCode->generate();
    }

    /**
     * Swipe Card QR Code
     *
     * @param String $code
     * @return Response A Response instance
     */
    #[Route("/{code}/qr.png", name: "swipe_qr", methods: ['GET'])]

    public function qrAction(Request $request, $code, EntityManagerInterface $em){
        $code = urldecode($code);
        $code = $this->get('App\Helper\SwipeCard')->vigenereDecode($code);
        $card = $em->getRepository('App\Entity\SwipeCard')->findOneBy(array('code'=>$code));
        if (!$card){
            throw $this->createAccessDeniedException();
        }

        $url = $this->generateUrl('swipe_in',array('code'=>$this->get('App\Helper\SwipeCard')->vigenereEncode($card->getCode())),UrlGeneratorInterface::ABSOLUTE_URL);
        $content = base64_decode($this->_getQr($url));
        $response = new Response();
        $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_INLINE,'qr.png');
        $response->headers->set('Content-Disposition', $disposition);
        $response->headers->set("Content-length",strlen($content));
        $response->headers->set('Content-Type', 'image/png');
        $response->setContent($content);

        return $response;
    }

    /**
     * Swipe Card QR Code
     *
     * @param String $code
     * @return Response A Response instance
     */
    #[Route("/{code}/br.png", name: "swipe_br", methods: ['GET'])]

    public function brAction(Request $request, $code, EntityManagerInterface $em){
        $code = urldecode($code);
        $code = $this->get('App\Helper\SwipeCard')->vigenereDecode($code);
        $card = $em->getRepository('App\Entity\SwipeCard')->findOneBy(array('code'=>$code));
        if (!$card){
            throw $this->createAccessDeniedException();
        }
        $content = base64_decode($card->getBarcode());
        $response = new Response();
        $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_INLINE,'br.png');
        $response->headers->set('Content-Disposition', $disposition);
        $response->headers->set("Content-length",strlen($content));
        $response->headers->set('Content-Type', 'image/png');
        $response->setContent($content);

        return $response;
    }


}
