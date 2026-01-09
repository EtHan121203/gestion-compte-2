<?php

namespace App\Controller;

use App\Entity\HelloassoPayment;
use App\Entity\Membership;
use App\Entity\Shift;
use App\Entity\ShiftBucket;
use App\Event\HelloassoEvent;
use App\Form\AutocompleteBeneficiaryCollectionType;
use App\Helper\Helloasso;
use App\Service\MembershipService;
use App\Service\ShiftService;
use App\Twig\Extension\AppExtension;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;

class DefaultController extends AbstractController
{
    #[Route('/', name: 'homepage')]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        AuthorizationCheckerInterface $authorizationChecker,
        TokenStorageInterface $tokenStorage,
        MembershipService $membershipService,
        ShiftService $shiftService,
        RouterInterface $router,
        Environment $twig,
        ParameterBagInterface $parameterBag
    ): Response {
        if ($authorizationChecker->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            $current_app_user = $tokenStorage->getToken()?->getUser();

            if ($current_app_user && method_exists($current_app_user, 'getBeneficiary') && $current_app_user->getBeneficiary() !== null) {
                /** @var Membership $membership */
                $membership = $current_app_user->getBeneficiary()->getMembership();

                if ($membership->getWithdrawn()) {
                    $tokenStorage->setToken(null);
                    $request->getSession()->invalidate();
                    $this->addFlash('error', 'Compte fermé !');
                    return $this->redirectToRoute('homepage');
                }

                $cycle_end = $membershipService->getEndOfCycle($membership);
                $dayAfterEndOfCycle = clone $cycle_end;
                $dayAfterEndOfCycle->modify('+1 day');
                $profileUrlHtml = "<a style=\"text-decoration:underline;color:white;\" href=\"" . $router->generate('fos_user_profile_show') . "\"><i class=\"material-icons tiny\">settings</i> ton profil</a>.";
                
                $appExtension = $twig->getExtension(AppExtension::class);
                
                if ($membership->getFrozenChange() && !$membership->getFrozen()) {
                    $now = new \DateTime('now');
                    $this->addFlash('warning',
                        'Comme demandé, ton compte sera gelé dans ' .
                        $now->diff($cycle_end)->format('%a jours') .
                        ', le <strong>' . $appExtension->date_fr_long($dayAfterEndOfCycle) . '</strong>.' .
                        "<br />Pour annuler, visite " . $profileUrlHtml);
                }
                if ($membership->getFrozenChange() && $membership->getFrozen()) {
                    $now = new \DateTime('now');
                    $this->addFlash('notice',
                        'Comme demandé, ton compte sera dégelé dans ' .
                        $now->diff($cycle_end)->format('%a jours') .
                        ', le <strong>' . $appExtension->date_fr_long($dayAfterEndOfCycle) . '</strong>.' .
                        "<br />Pour annuler, visite " . $profileUrlHtml);
                }

                if ($membershipService->canRegister($membership)) {
                    if ($membership->getRegistrations()->count() <= 0) {
                        $this->addFlash('warning', 'Pour poursuivre entre ton adhésion en ligne !');
                    } else {
                        $remainder = $membershipService->getRemainder($membership);
                        $remainingDays = (int) $remainder->format("%R%a");
                        if ($remainingDays < 0) {
                            $this->addFlash('error', 'Oups, ton adhésion a expiré il y a ' . $remainder->format('%a jours') . '... n\'oublie pas de ré-adhérer !');
                        } else {
                            $this->addFlash('warning',
                                'Ton adhésion expire dans ' . $remainingDays . ' jours.<br>' .
                                'Tu peux ré-adhérer en ligne par carte bancaire ou bien au bureau des membres par chèque, espèce ou ' .
                                $parameterBag->get('local_currency_name') .
                                '.');
                        }
                    }
                } elseif ($membership->getRegistrations()->count() <= 0) {
                    $this->addFlash('error', 'Aucune adhésion enregistrée !');
                }
            }
        } else {
            $to = new \DateTime();
            $to->modify('+7 days');
            $shifts = $em->getRepository(Shift::class)->findFutures($to);
            $bucketsByDay = $shiftService->generateShiftBucketsByDayAndJob($shifts);

            return $this->render('default/index_anon.html.twig', [
                'bucketsByDay' => $bucketsByDay,
                'hours' => $this->getHours()
            ]);
        }

        $eventsFuture = $em->getRepository(\App\Entity\Event::class)->findFutures();
        $dynamicContent = $em->getRepository(\App\Entity\DynamicContent::class)->findOneByCode("HOME")->getContent();

        return $this->render('default/index.html.twig', [
            'events' => $eventsFuture,
            'dynamicContent' => $dynamicContent
        ]);
    }

    #[Route('/schedule', name: 'schedule', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function schedule(EntityManagerInterface $em, ShiftService $shiftService): Response
    {
        $shifts = $em->getRepository(Shift::class)->findFutures();
        $bucketsByDay = $shiftService->generateShiftBucketsByDayAndJob($shifts);

        return $this->render('booking/schedule.html.twig', [
            'bucketsByDay' => $bucketsByDay,
            'hours' => $this->getHours()
        ]);
    }

    private function getHours(): array
    {
        $hours = [];
        for ($i = 6; $i < 22; $i++) {
            $hours[] = $i;
        }
        return $hours;
    }

    #[Route('/cardReader', name: 'cardReader')]
    public function cardReader(EntityManagerInterface $em, ShiftService $shiftService): Response
    {
        $this->denyAccessUnlessGranted('card_reader', $this->getUser());

        // in progress shifts
        $shifts_in_progress = $em->getRepository(Shift::class)->findInProgress();
        $buckets_in_progress = $shiftService->generateShiftBuckets($shifts_in_progress);
        
        // upcoming shifts
        $shifts_upcoming = $em->getRepository(Shift::class)->findUpcomingToday();
        $buckets_upcoming = $shiftService->generateShiftBuckets($shifts_upcoming);

        $dynamicContent = $em->getRepository(\App\Entity\DynamicContent::class)->findOneByCode('CARD_READER')->getContent();

        return $this->render('default/card_reader/index.html.twig', [
            "buckets_in_progress" => $buckets_in_progress,
            "buckets_upcoming" => $buckets_upcoming,
            "dynamicContent" => $dynamicContent
        ]);
    }

    #[Route('/helloassoNotify', name: 'helloasso_notify', methods: ['POST'])]
    public function helloassoNotify(
        Request $request,
        LoggerInterface $logger,
        Helloasso $helloassoHelper,
        EntityManagerInterface $em,
        EventDispatcherInterface $eventDispatcher
    ): JsonResponse {
        $logger->info('helloasso notify', $request->request->all());

        $actionId = $request->request->get('action_id');

        if (!$actionId) {
            $logger->critical("missing action id");
            return $this->json(['success' => false, "message" => "missing action id in POST content"]);
        }

        $actionId = str_pad($actionId, 12, '0', STR_PAD_LEFT);
        $action_json = $helloassoHelper->get('actions/' . $actionId);

        if (!isset($action_json->id)) {
            $message = 'Unable to find an action for action id ' . $actionId;
            if (isset($action_json->code)) {
                $logger->critical($message . ' code ' . $action_json->code);
                return $this->json(['success' => false, "code" => $action_json->code, "message" => $action_json->message]);
            } else {
                $logger->critical($message);
                return $this->json(['success' => false, "message" => "wrong api response for actions/" . $actionId]);
            }
        }
        
        $payment_json = $helloassoHelper->get('payments/' . $action_json->id_payment);
        if (!isset($payment_json->id)) {
            $message = 'Unable to find a payment for payment id ' . $action_json->id_payment;
            if (isset($payment_json->code)) {
                $logger->critical($message . ' code ' . $payment_json->code);
                return $this->json(['success' => false, "code" => $payment_json->code, "message" => $payment_json->message]);
            } else {
                $logger->critical($message);
                return $this->json(['success' => false, "message" => "wrong api response for payments/" . $action_json->id_payment]);
            }
        }

        $exist = $em->getRepository(HelloassoPayment::class)->findOneBy(['paymentId' => $payment_json->id]);

        if ($exist) {
            $logger->info("notification already exist");
            return $this->json(['success' => false, "message" => "notification already exist"]);
        }

        $payments = [];
        foreach ($payment_json->actions as $action) {
            $action_json = $helloassoHelper->get('actions/' . $action->id);
            $payment = $em->getRepository(HelloassoPayment::class)->findOneBy(['paymentId' => $payment_json->id]);
            if ($payment) {
                $amount = $action_json->amount;
                $amount = str_replace(',', '.', $amount);
                $payment->setAmount($payment->getAmount() + $amount);
            } else {
                $payment = new HelloassoPayment();
                $payment->fromActionObj($action_json);
            }
            $em->persist($payment);
            $em->flush();
            $payments[$payment->getId()] = $payment;
        }
        
        foreach ($payments as $payment) {
            $eventDispatcher->dispatch(
                new HelloassoEvent($payment),
                HelloassoEvent::PAYMENT_AFTER_SAVE
            );
        }

        return $this->json(['success' => true]);
    }

    #[Route('/shift/{id}/contact_form', name: 'shift_contact_form', methods: ['GET', 'POST'])]
    public function shiftContactForm(
        Shift $shift,
        Request $request,
        MailerInterface $mailer,
        EntityManagerInterface $em,
        ParameterBagInterface $parameterBag,
        RouterInterface $router
    ): Response {
        $coShifters = $em->getRepository(\App\Entity\Beneficiary::class)->findCoShifters($shift);
        $formBuilder = $this->createFormBuilder();
        $formBuilder->add('from', HiddenType::class, ['data' => $shift->getShifter()->getId()]);
        $formBuilder->add('to', AutocompleteBeneficiaryCollectionType::class, [
            'label' => 'A',
            'data' => $coShifters,
        ]);
        $formBuilder->add('message', TextareaType::class, [
            'attr' => ['class' => 'materialize-textarea'],
            'label' => 'Message',
            'data' => "Bonjour XX,\nTu n'es toujours pas arrivé pour notre créneau.\nEst-ce que tout va bien ?\nA très vite,\n" . $shift->getShifter()->getFirstName() . "\n\nBonjour à tou.te.s,\nJe vais en être en retard pour mon créneau.\nJe serai à l'épicerie d'ici XX minutes.\nA tout de suite,\n" . $shift->getShifter()->getFirstName()
        ]);
        $formBuilder->setAction($router->generate('shift_contact_form', ['id' => $shift->getId()]));
        $formBuilder->setMethod('POST');
        $form = $formBuilder->getForm();

        if ($form->handleRequest($request)->isSubmitted() && $form->isValid()) {
            $beneficiaries = $form->get('to')->getData();
            $fromId = $form->get('from')->getData();
            $from = $em->getRepository(\App\Entity\Beneficiary::class)->find($fromId);
            
            $emails = [];
            $firstnames = [];
            foreach ($beneficiaries as $beneficiary) {
                $emails[] = $beneficiary->getEmail();
                $firstnames[] = $beneficiary->getFirstname();
            }

            $email = (new Email())
                ->subject('[ESPACE MEMBRES] Un message de ' . $from->getFirstName() . " " . substr($from->getLastName(), 0, 1))
                ->from($parameterBag->get('transactional_mailer_user'))
                ->replyTo($from->getEmail())
                ->bcc(...$emails)
                ->html(
                    $this->renderView(
                        'emails/coshifter_message.html.twig',
                        [
                            'message' => trim($form->get('message')->getData()),
                            'from' => $from,
                            'firstnames' => $firstnames,
                            'shift' => $shift
                        ]
                    )
                );

            $mailer->send($email);

            if (count($firstnames) > 1) {
                $last_firstname = array_pop($firstnames);
                $firstnamesStr = implode(', ', $firstnames);
                $firstnamesStr .= ' et ' . $last_firstname;
            } else {
                $firstnamesStr = $firstnames[0];
            }

            $this->addFlash('success', 'Ton message a été transmis à ' . $firstnamesStr);
            return $this->redirectToRoute('homepage');
        }

        return $this->render('booking/_partial/home_shift_contactform.html.twig', [
            'shift' => $shift,
            'form' => $form->createView()
        ]);
    }

    #[Route('/widget', name: 'widget', methods: ['GET'])]
    public function widget(Request $request, EntityManagerInterface $em): Response
    {
        $job_id = $request->get('job_id');
        $buckets = [];
        $display_end = $request->query->getBoolean('display_end');
        $display_on_empty = $request->query->getBoolean('display_on_empty');
        $title = $request->query->getBoolean('title', true);
        $job = null;

        if ($job_id) {
            $job = $em->getRepository(\App\Entity\Job::class)->find($job_id);
            if ($job) {
                $shifts = $em->getRepository(Shift::class)->findFuturesWithJob($job);
                foreach ($shifts as $shift) {
                    $day = $shift->getStart()->format("d m Y");
                    $interval = $shift->getIntervalCode();
                    if (!isset($buckets[$interval . $day])) {
                        $buckets[$interval . $day] = new ShiftBucket();
                    }
                    $buckets[$interval . $day]->addShift($shift);
                }
            }
        }

        return $this->render('default/widget.html.twig', [
            'job' => $job,
            'buckets' => $buckets,
            'display_end' => $display_end,
            'display_on_empty' => $display_on_empty,
            'title' => $title
        ]);
    }
}
