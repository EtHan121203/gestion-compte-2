<?php

namespace App\Controller;

use DateTime;
use App\Entity\Job;
use App\Entity\Shift;
use App\Entity\Beneficiary;
use App\Event\ShiftBookedEvent;
use App\Event\ShiftFreedEvent;
use App\Event\ShiftValidatedEvent;
use App\Event\ShiftInvalidatedEvent;
use App\Event\ShiftDeletedEvent;
use App\Form\AutocompleteBeneficiaryType;
use App\Form\RadioChoiceType;
use App\Form\ShiftType;
use App\Security\MembershipVoter;
use App\Security\ShiftVoter;
use App\Service\ShiftService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

#[Route('/shift')]
class ShiftController extends AbstractController
{
    private bool $useFlyAndFixed;
    private bool $useTimeLogSaving;
    private int $timeLogSavingShiftFreeMinTimeInAdvanceDays;

    public function __construct(
        #[Autowire(param: 'use_fly_and_fixed')] bool $use_fly_and_fixed = false,
        #[Autowire(param: 'use_time_log_saving')] bool $use_time_log_saving = false,
        #[Autowire(param: 'time_log_saving_shift_free_min_time_in_advance_days')] int $time_log_saving_shift_free_min_time_in_advance_days = 0
    ) {
        $this->useFlyAndFixed = $use_fly_and_fixed;
        $this->useTimeLogSaving = $use_time_log_saving;
        $this->timeLogSavingShiftFreeMinTimeInAdvanceDays = $time_log_saving_shift_free_min_time_in_advance_days;
    }

    #[Route('/new', name: 'shift_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_SHIFT_MANAGER')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        ShiftService $shiftService,
        Environment $twig
    ): Response {
        $job = $em->getRepository(Job::class)->findOneBy([]);

        if (!$job) {
            $this->addFlash('warning', 'Commençons par créer un poste de bénévolat');
            return $this->redirectToRoute('job_new');
        }

        $shift = new Shift();
        $form = $this->container->get('form.factory')->createNamed('bucket_shift_add_form', ShiftType::class, $shift);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $number = $form->get('number')->getData();
            while (1 < $number) {
                $s = clone($shift);
                $em->persist($s);
                $number--;
            }
            $em->persist($shift);
            $em->flush();
            $success = true;
            $message = 'Le créneau a bien été créé !';
        } else {
            $success = false;
            $message = "Une erreur s'est produite... Impossible de créer le créneau. " . (string) $form->getErrors(true, false);
        }

        if ($request->isXmlHttpRequest()) {
            if ($success) {
                $bucket = $shiftService->getShiftBucketFromShift($shift);
                $card = $twig->render('admin/booking/_partial/bucket_card.html.twig', [
                    'bucket' => $bucket,
                    'start' => 6,
                    'end' => 22,
                    'line' => 0,
                ]);
                $modal = $this->forward(BookingController::class . '::showBucket', [
                    'bucket' => $bucket->getShiftWithMinId()
                ])->getContent();
                return new JsonResponse(['message' => $message, 'card' => $card, 'modal' => $modal], 201);
            } else {
                return new JsonResponse(['message' => $message], 400);
            }
        } else {
            if ($success) {
                $this->addFlash('success', $message);
                return $this->redirectToRoute('booking_admin');
            } else {
                if ($form->isSubmitted()) {
                    $this->addFlash('error', $message);
                }
                return $this->render('admin/shift/new.html.twig', [
                    'form' => $form->createView()
                ]);
            }
        }
    }

    #[Route('/{id}/book', name: 'shift_book', methods: ['POST'])]
    public function book(
        Request $request,
        Shift $shift,
        EntityManagerInterface $em,
        ShiftService $shiftService,
        EventDispatcherInterface $dispatcher
    ): Response {
        $content = json_decode($request->getContent());
        $beneficiaryId = $content->beneficiaryId;
        $isFixe = $content->typeService;

        $beneficiary = $em->getRepository(Beneficiary::class)->find($beneficiaryId);

        if (!$beneficiary
            || !$shiftService->isShiftBookable($shift, $beneficiary)
            || !$this->isGranted(MembershipVoter::EDIT, $beneficiary->getMembership())
        ) {
            $this->addFlash('error', 'Impossible de réserver ce créneau');
            return new Response($this->generateUrl('booking'), 205);
        }

        if (!$shift->getBooker()) {
            $shift->setBooker($this->getUser());
            $shift->setBookedTime(new DateTime('now'));
        }
        $shift->setShifter($beneficiary);
        $shift->setLastShifter(null);
        $shift->setFixe($isFixe);
        $em->persist($shift);

        $member = $beneficiary->getMembership();
        if ($member->getFirstShiftDate() == null) {
            $firstDate = clone($shift->getStart());
            $firstDate->setTime(0, 0, 0);
            $member->setFirstShiftDate($firstDate);
            $em->persist($member);
        }

        $em->flush();

        $dispatcher->dispatch(new ShiftBookedEvent($shift, false), ShiftBookedEvent::NAME);

        $this->addFlash('success', 'Ce créneau a bien été réservé !');
        return new Response($this->generateUrl('homepage'), 200);
    }

    #[Route('/{id}/book_admin', name: 'shift_book_admin', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_SHIFT_MANAGER')]
    public function bookAdmin(
        Request $request,
        Shift $shift,
        EntityManagerInterface $em,
        ShiftService $shiftService,
        EventDispatcherInterface $dispatcher,
        Environment $twig
    ): Response {
        $form = $this->createShiftBookAdminForm($shift);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $fixe = $form->get('fixe')->getData();
            $beneficiary = $form->get('shifter')->getData();

            if ($shift->getShifter()) {
                $message = 'Désolé, ce créneau est déjà réservé';
                $success = false;
            } elseif ($shift->getFormation() && !$beneficiary->getFormations()->contains($shift->getFormation())) {
                $message = "Désolé, ce bénévole n'a pas la qualification necessaire (" . $shift->getFormation()->getName() . ")";
                $success = false;
            } elseif ($beneficiary->getMembership()->isCurrentlyExemptedFromShifts($shift->getStart())) {
                $message = 'Désolé, ce bénévole est exempté de créneau sur cette période';
                $success = false;
            } else {
                $shift->setBooker($this->getUser());
                $shift->setBookedTime(new DateTime('now'));
                $shift->setShifter($beneficiary);
                $shift->setLastShifter(null);
                $shift->setFixe($fixe);

                $em->persist($shift);

                $member = $beneficiary->getMembership();
                if ($member->getFirstShiftDate() == null) {
                    $firstDate = clone($shift->getStart());
                    $firstDate->setTime(0, 0, 0);
                    $member->setFirstShiftDate($firstDate);
                    $em->persist($member);
                }
                $em->flush();

                $dispatcher->dispatch(new ShiftBookedEvent($shift, true), ShiftBookedEvent::NAME);

                $message = 'Créneau réservé avec succès pour ' . $shift->getShifter();
                $success = true;
            }
        } else {
            $message = "Une erreur s'est produite... Impossible de réserver le créneau. " . (string) $form->getErrors(true, false);
            $success = false;
        }

        if ($request->isXmlHttpRequest()) {
            if ($success) {
                $bucket = $shiftService->getShiftBucketFromShift($shift);
                $card = $twig->render('admin/booking/_partial/bucket_card.html.twig', [
                    'bucket' => $bucket,
                    'start' => 6,
                    'end' => 22,
                    'line' => 0,
                ]);
                $modal = $this->forward(BookingController::class . '::showBucket', [
                    'bucket' => $bucket->getShiftWithMinId()
                ])->getContent();
                return new JsonResponse(['message' => $message, 'card' => $card, 'modal' => $modal], 200);
            } else {
                return new JsonResponse(['message' => $message], 400);
            }
        } else {
            $this->addFlash($success ? 'success' : 'error', $message);
            return $this->redirectToRoute('booking_admin');
        }
    }

    #[Route('/{id}/free', name: 'shift_free', methods: ['POST'])]
    public function free(
        Request $request,
        Shift $shift,
        EntityManagerInterface $em,
        ShiftService $shiftService,
        EventDispatcherInterface $dispatcher
    ): Response {
        $this->denyAccessUnlessGranted(ShiftVoter::FREE, $shift);

        $form = $this->createShiftFreeForm($shift);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $beneficiary = $this->getUser()->getBeneficiary();
            $shift_can_be_freed = $shiftService->canFreeShift($beneficiary, $shift);
            if (!$shift_can_be_freed['result']) {
                $this->addFlash('error', $shift_can_be_freed['message'] ?? "Impossible d'annuler ce créneau.");
                return $this->redirectToRoute('homepage');
            }

            $oldBeneficiary = $shift->getShifter();
            $fixe = $shift->isFixe();
            $reason = $form->get('reason')->getData();

            $shift->free($reason);

            $em->persist($shift);
            $em->flush();

            $dispatcher->dispatch(new ShiftFreedEvent($shift, $oldBeneficiary, $fixe, $reason), ShiftFreedEvent::NAME);
        } else {
            return $this->redirectToRoute('homepage');
        }

        $this->addFlash('success', 'Le créneau a été annulé !');
        if ($this->useTimeLogSaving) {
            $this->addFlash('warning', 'Grâce au compteur épargne, votre créneau a été comptabilisé.<br />En échange, votre compteur épargne a été décrémenté de la durée du créneau.');
        }
        return $this->redirectToRoute('homepage');
    }

    #[Route('/{id}/free_admin', name: 'shift_free_admin', methods: ['POST'])]
    #[IsGranted('ROLE_SHIFT_MANAGER')]
    public function freeAdmin(
        Request $request,
        Shift $shift,
        EntityManagerInterface $em,
        ShiftService $shiftService,
        EventDispatcherInterface $dispatcher,
        Environment $twig
    ): Response {
        $this->denyAccessUnlessGranted(ShiftVoter::FREE, $shift);

        $form = $this->createShiftFreeAdminForm($shift);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $shift_can_be_freed = $shiftService->canFreeShift($shift->getShifter(), $shift, true);
            if (!$shift_can_be_freed['result']) {
                $this->addFlash('error', $shift_can_be_freed['message'] ?? "Impossible d'annuler ce créneau.");
                return $this->redirectToRoute('homepage');
            }

            $beneficiary = $shift->getShifter();
            $fixe = $shift->isFixe();
            $reason = $form->get('reason')->getData();

            $wasCarriedOut = $shift->getWasCarriedOut() == 1;
            if ($wasCarriedOut) {
                $shift->invalidateShiftParticipation();
            }

            $shift->free($reason);

            $em->persist($shift);
            $em->flush();

            if ($wasCarriedOut) {
                $dispatcher->dispatch(new ShiftInvalidatedEvent($shift, $beneficiary), ShiftInvalidatedEvent::NAME);
            }
            $dispatcher->dispatch(new ShiftFreedEvent($shift, $beneficiary, $fixe, $reason), ShiftFreedEvent::NAME);

            $success = true;
            $message = 'Le créneau a bien été libéré !';
        } else {
            $success = false;
            $message = "Une erreur s'est produite... Impossible de libérer le créneau. " . (string) $form->getErrors(true, false);
        }

        if ($request->isXmlHttpRequest()) {
            if ($success) {
                $bucket = $shiftService->getShiftBucketFromShift($shift);
                $card = $twig->render('admin/booking/_partial/bucket_card.html.twig', [
                    'bucket' => $bucket,
                    'start' => 6,
                    'end' => 22,
                    'line' => 0,
                ]);
                $modal = $this->forward(BookingController::class . '::showBucket', [
                    'bucket' => $bucket->getShiftWithMinId()
                ])->getContent();
                return new JsonResponse(['message' => $message, 'card' => $card, 'modal' => $modal], 200);
            } else {
                return new JsonResponse(['message' => $message], 400);
            }
        } else {
            $this->addFlash($success ? 'success' : 'error', $message);
            $referer = $request->headers->get('referer');
            return new RedirectResponse($referer);
        }
    }

    #[Route('/{id}/validate', name: 'shift_validate', methods: ['POST'])]
    public function validateShift(
        Request $request,
        Shift $shift,
        EntityManagerInterface $em,
        ShiftService $shiftService,
        EventDispatcherInterface $dispatcher,
        Environment $twig
    ): Response {
        $this->denyAccessUnlessGranted(ShiftVoter::VALIDATE, $shift);

        $form = $this->createShiftValidateInvalidateForm($shift);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $validate = $form->get('validate')->getData() == 1;
            $current = $shift->getWasCarriedOut() == 1;
            if ($validate == $current) {
                $success = false;
                $message = "La participation au créneau a déjà été " . ($validate ? 'validée' : 'invalidée');
            } else {
                if ($validate) {
                    $shift->validateShiftParticipation();
                } else {
                    $shift->invalidateShiftParticipation();
                }
                $em->persist($shift);
                $em->flush();

                if ($validate) {
                    $dispatcher->dispatch(new ShiftValidatedEvent($shift), ShiftValidatedEvent::NAME);
                } else {
                    $beneficiary = $shift->getShifter();
                    $dispatcher->dispatch(new ShiftInvalidatedEvent($shift, $beneficiary), ShiftInvalidatedEvent::NAME);
                }

                $message = "La participation au créneau a bien été " . ($validate ? 'validée' : 'invalidée') . " !";
                $success = true;
            }
        } else {
            $success = false;
            $message = "Une erreur s'est produite... Impossible de valider/invalider le créneau. " . (string) $form->getErrors(true, false);
        }

        if ($request->isXmlHttpRequest()) {
            if ($success) {
                $bucket = $shiftService->getShiftBucketFromShift($shift);
                $card = $twig->render('admin/booking/_partial/bucket_card.html.twig', [
                    'bucket' => $bucket,
                    'start' => 6,
                    'end' => 22,
                    'line' => 0,
                ]);
                $modal = $this->forward(BookingController::class . '::showBucket', [
                    'bucket' => $bucket->getShiftWithMinId()
                ])->getContent();
                return new JsonResponse(['message' => $message, 'card' => $card, 'modal' => $modal], 200);
            } else {
                return new JsonResponse(['message' => $message], 400);
            }
        } else {
            $this->addFlash($success ? 'success' : 'error', $message);
            $referer = $request->headers->get('referer');
            return new RedirectResponse($referer);
        }
    }

    #[Route('/{id}/accept', name: 'shift_accept_reserved', methods: ['GET'])]
    public function acceptReserved(Shift $shift, EntityManagerInterface $em, EventDispatcherInterface $dispatcher): Response
    {
        if (!$shift->getId() || !$this->isGranted('accept', $shift)) {
            $this->addFlash('error', "Impossible d'accepter la réservation");
            return $this->redirectToRoute('homepage');
        }

        if ($shift->getLastShifter()) {
            $shift->setBooker($this->getUser());
            $beneficiary = $shift->getLastShifter();
            $shift->setShifter($beneficiary);
            $shift->setBookedTime(new DateTime('now'));
            $shift->setLastShifter(null);
            $shift->setFixe(false);
            $em->persist($shift);
            $em->flush();

            $dispatcher->dispatch(new ShiftBookedEvent($shift, false), ShiftBookedEvent::NAME);

            $this->addFlash('success', 'Créneau réservé ! Merci ' . $shift->getShifter()->getFirstname());
        } else {
            $this->addFlash('error', 'Oups, ce créneau a déjà été confirmé / refusé ou le délais de reservation est écoulé.');
        }

        return $this->redirectToRoute('homepage');
    }

    #[Route('/{id}/reject', name: 'shift_reject_reserved', methods: ['GET'])]
    public function rejectReserved(Shift $shift, EntityManagerInterface $em): Response
    {
        if (!$this->isGranted('reject', $shift)) {
            $this->addFlash('error', 'Impossible de rejeter la réservation');
            return $this->redirectToRoute('homepage');
        }

        if ($shift->getLastShifter()) {
            $shift->setLastShifter(null);
            $shift->setFixe(false);
            $em->persist($shift);
            $em->flush();
            $this->addFlash('success', 'Créneau libéré');
            $this->addFlash('warning', 'Pense à revenir dans quelques jours choisir un autre créneau pour ton bénévolat');
        } else {
            $this->addFlash('error', 'Oups, ce créneau a déjà été confirmé / refusé ou le délais de reservation est écoulé.');
        }

        return $this->redirectToRoute('homepage');
    }

    #[Route('/{id}', name: 'shift_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(
        Request $request,
        Shift $shift,
        EntityManagerInterface $em,
        ShiftService $shiftService,
        EventDispatcherInterface $dispatcher,
        Environment $twig
    ): Response {
        $form = $this->createShiftDeleteForm($shift);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $beneficiary = $shift->getShifter();
            $dispatcher->dispatch(new ShiftDeletedEvent($shift, $beneficiary), ShiftDeletedEvent::NAME);
            $em->remove($shift);
            $em->flush();

            $success = true;
            $message = 'Le créneau a bien été supprimé !';
        } else {
            $success = false;
            $message = "Une erreur s'est produite... Impossible de supprimer le créneau. " . (string) $form->getErrors(true, false);
        }

        if ($request->isXmlHttpRequest()) {
            if ($success) {
                $bucket = $shiftService->getShiftBucketFromShift($shift);
                if (count($bucket->getShifts()) > 0) {
                    $card = $twig->render('admin/booking/_partial/bucket_card.html.twig', [
                        'bucket' => $bucket,
                        'start' => 6,
                        'end' => 22,
                        'line' => 0,
                    ]);
                    $modal = $this->forward(BookingController::class . '::showBucket', [
                        'bucket' => $bucket->getShiftWithMinId()
                    ])->getContent();
                } else {
                    $card = null;
                    $modal = null;
                }
                return new JsonResponse(['message' => $message, 'card' => $card, 'modal' => $modal], 200);
            } else {
                return new JsonResponse(['message' => $message], 400);
            }
        } else {
            $this->addFlash($success ? 'success' : 'error', $message);
            return $this->redirectToRoute('booking_admin');
        }
    }

    private function createShiftBookAdminForm(Shift $shift): \Symfony\Component\Form\FormInterface
    {
        $form = $this->container->get('form.factory')->createNamedBuilder('shift_book_forms_' . $shift->getId())
            ->setAction($this->generateUrl('shift_book_admin', ['id' => $shift->getId()]))
            ->add('shifter', AutocompleteBeneficiaryType::class, [
                'label' => "Numéro d'adhérent ou nom du membre",
                'required' => true
            ]);

        if ($this->useFlyAndFixed) {
            $form = $form->add('fixe', RadioChoiceType::class, [
                'choices'  => [
                    'Volant' => 0,
                    'Fixe' => 1,
                ],
                'data' => 0
            ]);
        } else {
            $form = $form->add('fixe', HiddenType::class, [
                'data' => 0
            ]);
        }

        return $form->getForm();
    }

    private function createShiftDeleteForm(Shift $shift): \Symfony\Component\Form\FormInterface
    {
        $form = $this->container->get('form.factory')->createNamedBuilder('shift_delete_forms_' . $shift->getId())
            ->setAction($this->generateUrl('shift_delete', ['id' => $shift->getId()]))
            ->setMethod('DELETE');

        return $form->getForm();
    }

    private function createShiftFreeForm(Shift $shift): \Symfony\Component\Form\FormInterface
    {
        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('shift_free', ['id' => $shift->getId()]))
            ->add('reason', TextareaType::class, ['required' => false])
            ->setMethod('POST');

        return $form->getForm();
    }

    private function createShiftFreeAdminForm(Shift $shift): \Symfony\Component\Form\FormInterface
    {
        $form = $this->container->get('form.factory')->createNamedBuilder('shift_free_forms_' . $shift->getId())
            ->setAction($this->generateUrl('shift_free_admin', ['id' => $shift->getId()]))
            ->add('reason', TextareaType::class, [
                'required' => false,
                'label' => 'Justification éventuelle',
                'attr' => ['class' => 'materialize-textarea']
            ])
            ->setMethod('POST');

        return $form->getForm();
    }

    private function createShiftValidateInvalidateForm(Shift $shift): \Symfony\Component\Form\FormInterface
    {
        $form = $this->container->get('form.factory')->createNamedBuilder('shift_validate_invalidate_forms_' . $shift->getId())
            ->setAction($this->generateUrl('shift_validate', ['id' => $shift->getId()]))
            ->add('validate', HiddenType::class, [
                'data'  => ($shift->getWasCarriedOut() ? 0 : 1),
            ])
            ->setMethod('POST');

        return $form->getForm();
    }
}
