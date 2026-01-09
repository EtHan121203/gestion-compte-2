<?php

namespace App\Controller;

use DateTime;
use Exception;
use App\Entity\Beneficiary;
use App\Entity\Job;
use App\Entity\Shift;
use App\Entity\ShiftBucket;
use App\Entity\PeriodPosition;
use App\Event\ShiftDeletedEvent;
use App\Form\AutocompleteBeneficiaryType;
use App\Form\RadioChoiceType;
use App\Form\ShiftType;
use App\Repository\JobRepository;
use App\Security\ShiftVoter;
use App\Service\MembershipService;
use App\Service\ShiftService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

#[Route('/booking')]
class BookingController extends AbstractController
{
    private bool $useFlyAndFixed;

    public function __construct(
        #[Autowire(param: 'use_fly_and_fixed')] bool $useFlyAndFixed
    ) {
        $this->useFlyAndFixed = $useFlyAndFixed;
    }

    public function homepageDashboard(): Response
    {
        return $this->render('booking/home_dashboard.html.twig');
    }

    public function homepageShifts(EntityManagerInterface $em, MembershipService $membershipService, ParameterBagInterface $parameterBag): Response
    {
        $user = $this->getUser();
        if (!$user || !method_exists($user, 'getBeneficiary')) {
            return new Response('User not found', 404);
        }
        
        $membership = $user->getBeneficiary()->getMembership();
        $beneficiaries = $membership->getBeneficiaries();

        $preceding_previous_cycle_start = $membershipService->getStartOfCycle($membership, -1 * $parameterBag->get('max_nb_of_past_cycles_to_display'));
        $next_cycle_end = $membershipService->getEndOfCycle($membership, 1);
        $shifts_by_cycle = $em->getRepository(Shift::class)->findShiftsByCycles($membership, $preceding_previous_cycle_start, $next_cycle_end);
        $period_positions = $em->getRepository(PeriodPosition::class)->findByBeneficiaries($beneficiaries);

        $shiftFreeForms = [];
        foreach ($shifts_by_cycle as $shifts) {
            foreach ($shifts as $shift) {
                $shiftFreeForms[$shift->getId()] = $this->createShiftFreeForm($shift)->createView();
            }
        }

        return $this->render('booking/home_booked_shifts.html.twig', [
            'shift_free_forms' => $shiftFreeForms,
            'period_positions' => $period_positions,
            'shiftsByCycle' => $shifts_by_cycle,
        ]);
    }

    #[Route('/', name: 'booking', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function index(Request $request, MembershipService $membershipService, ShiftService $shiftService, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user || !method_exists($user, 'getBeneficiary') || $user->getBeneficiary() === null) {
            $this->addFlash('error', 'Oups, tu n\'as pas de bénéficiaire enregistré ! MODE ADMIN');
            return $this->redirectToRoute('booking_admin');
        }

        $membership = $user->getBeneficiary()->getMembership();

        if (!$membershipService->isUptodate($membership)) {
            $remainder = $membershipService->getRemainder($membership);
            $this->addFlash('warning', 'Oups, ton adhésion a expiré il y a ' . $remainder->format('%a jours') . '... n\'oublie pas de ré-adhérer pour effectuer ton bénévolat !');
            return $this->redirectToRoute('homepage');
        }

        if ($membership->getFrozen()) {
            $this->addFlash('warning', 'Oups, ton compte est gelé ❄️ !<br />Dégel pour réserver 😉');
            return $this->redirectToRoute('homepage');
        }

        $beneficiaries = $membership->getBeneficiaries();

        $beneficiaryForm = $this->createFormBuilder()
            ->setAction($this->generateUrl('booking'))
            ->setMethod('POST')
            ->add('beneficiary', EntityType::class, [
                'label' => 'Réserver un créneau pour',
                'required' => true,
                'class' => Beneficiary::class,
                'choices' => $beneficiaries,
                'choice_label' => 'firstname',
                'multiple' => false,
            ])
            ->getForm();

        $beneficiaryForm->handleRequest($request);

        if ($beneficiaryForm->isSubmitted() || $beneficiaries->count() == 1) {
            if ($beneficiaries->count() > 1) {
                $beneficiary = $beneficiaryForm->get('beneficiary')->getData();
            } else {
                $beneficiary = $beneficiaries->first();
            }

            $shifts = $em->getRepository(Shift::class)->findFutures();
            $bucketsByDay = $shiftService->generateShiftBucketsByDayAndJob($shifts);

            $hours = [];
            for ($i = 6; $i < 22; $i++) {
                $hours[] = $i;
            }

            return $this->render('booking/index.html.twig', [
                'bucketsByDay' => $bucketsByDay,
                'hours' => $hours,
                'beneficiary' => $beneficiary,
                'jobs' => $em->getRepository(Job::class)->findByEnabled(true)
            ]);
        }

        return $this->render('booking/index.html.twig', [
            'beneficiary_form' => $beneficiaryForm->createView(),
        ]);
    }

    private function adminFilterFormFactory(EntityManagerInterface $em, Request $request): array
    {
        $defaultFrom = new DateTime();
        $defaultFrom->setTimestamp(strtotime('last monday', strtotime('tomorrow')));
        $defaultTo = null;
        $defaultWeek = (new DateTime())->format('W');
        $defaultYear = (new DateTime())->format('Y');
        $years = $em->getRepository(Shift::class)->getYears();

        $filterForm = $this->createFormBuilder()
            ->setAction($this->generateUrl('booking_admin'))
            ->add('type', ChoiceType::class, [
                'label' => 'Type de filtre',
                'required' => true,
                'data' => "Date",
                'choices' => [
                    'Date' => "date",
                    'Semaine' => "week",
                ],
            ])
            ->add('from', TextType::class, [
                'label' => 'A partir de',
                'required' => true,
                'data' => $defaultFrom->format('Y-m-d'),
                'attr' => ['class' => 'datepicker'],
            ])
            ->add('to', TextType::class, [
                'label' => 'Jusqu\'à',
                'required' => false,
                'attr' => ['class' => 'datepicker'],
            ])
            ->add('year', ChoiceType::class, [
                'required' => false,
                'choices' => array_combine($years, $years),
                'label' => 'Année',
                'data' =>  $defaultYear,
                'placeholder' => false,
            ])
            ->add('week', IntegerType::class, [
                'required' => false,
                'label' => 'Numéro de semaine',
                'data' => $defaultWeek,
                'attr' => [
                    'min' => 1,
                    'max' => 52,
                ],
            ])
            ->add('job', EntityType::class, [
                'label' => 'Type de créneau',
                'class' => Job::class,
                'choice_label' => 'name',
                'multiple' => false,
                'required' => false,
                'query_builder' => function (JobRepository $repository) {
                    return $repository->createQueryBuilder('j')
                        ->where('j.enabled = :enabled')
                        ->setParameter('enabled', true)
                        ->orderBy('j.name', 'ASC');
                }
            ])
            ->add('filling', ChoiceType::class, [
                'label' => 'Remplissage',
                'required' => false,
                'choices' => [
                    'Complet' => 'full',
                    'Partiel' => 'partial',
                    'Vide' => 'empty',
                ],
            ])
            ->add('filter', SubmitType::class, [
                'label' => 'Filtrer',
                'attr' => ['class' => 'btn', 'value' => 'filtrer']
            ])
            ->getForm();

        $filterForm->handleRequest($request);
        $from = $defaultFrom;
        $to = $defaultTo;
        $job = null;
        $filling = null;

        try {
            if ($filterForm->isSubmitted() && $filterForm->isValid()) {
                $job = $filterForm->get("job")->getData();
                $filling = $filterForm->get("filling")->getData();

                if ($filterForm->get("type")->getData() == "date") {
                    $from = new DateTime($filterForm->get('from')->getData());
                    $toStr = $filterForm->get('to')->getData();
                    if ($toStr) {
                        $to = new DateTime($toStr);
                    }
                } else {
                    $week = $filterForm->get("week")->getData();
                    $year = $filterForm->get("year")->getData();
                    $from = new DateTime();
                    $from->setISODate($year, $week, 1);
                    $from->setTime(0, 0);
                    $to = clone $from;
                    $to->modify('+6 days');
                }
            }
        } catch (Exception) {
            $from = $defaultFrom;
            $to = $defaultTo;
            $job = null;
        }

        return [
            "form" => $filterForm,
            "from" => $from,
            "to" => $to,
            "job" => $job,
            "filling" => $filling,
        ];
    }

    #[Route('/admin', name: 'booking_admin', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_SHIFT_MANAGER')]
    public function admin(Request $request, EntityManagerInterface $em, ShiftService $shiftService): Response
    {
        $filter = $this->adminFilterFormFactory($em, $request);

        $jobs = $em->getRepository(Job::class)->findByEnabled(true);
        $beneficiaries = $em->getRepository(Beneficiary::class)->findAllActive();
        $shifts = $em->getRepository(Shift::class)->findFrom($filter["from"], $filter["to"], $filter["job"]);

        $bucketsByDay = $shiftService->generateShiftBucketsByDayAndJob($shifts);
        $bucketsByDay = $shiftService->filterBucketsByDayAndJobByFilling($bucketsByDay, $filter["filling"]);

        return $this->render('admin/booking/index.html.twig', [
            'filterForm' => $filter["form"]->createView(),
            'bucketsByDay' => $bucketsByDay,
            'jobs' => $jobs,
            'beneficiaries' => $beneficiaries,
        ]);
    }

    #[Route('/bucket/{id}/show', name: 'bucket_show', methods: ['GET'])]
    #[IsGranted('ROLE_SHIFT_MANAGER')]
    public function showBucket(Shift $bucket, EntityManagerInterface $em, FormFactoryInterface $formFactory): Response
    {
        $shifts = $em->getRepository(Shift::class)->findBucket($bucket);

        $shiftBookForms = [];
        $shiftDeleteForms = [];
        $shiftFreeForms = [];
        $shiftValidateInvalidateForms = [];
        foreach ($shifts as $shift) {
            $shiftBookForms[$shift->getId()] = $this->createShiftBookAdminForm($shift, $formFactory)->createView();
            $shiftDeleteForms[$shift->getId()] = $this->createShiftDeleteForm($shift, $formFactory)->createView();
            $shiftFreeForms[$shift->getId()] = $this->createShiftFreeAdminForm($shift, $formFactory)->createView();
            $shiftValidateInvalidateForms[$shift->getId()] = $this->createShiftValidateInvalidateForm($shift, $formFactory)->createView();
        }
        $bucketShiftAddForm = $this->createBucketShiftAddForm($bucket, $formFactory);
        $bucketDeleteform = $this->createBucketDeleteForm($bucket, $formFactory);
        $bucketLockUnlockForm = $this->createBucketLockUnlockForm($bucket, $formFactory);

        return $this->render('admin/booking/_partial/bucket_modal.html.twig', [
            'shifts' => $shifts,
            'bucket_shift_add_form' => $bucketShiftAddForm->createView(),
            'shift_book_forms' => $shiftBookForms,
            'shift_delete_forms' => $shiftDeleteForms,
            'shift_free_forms' => $shiftFreeForms,
            'shift_validate_invalidate_forms' => $shiftValidateInvalidateForms,
            'bucket_delete_form' => $bucketDeleteform->createView(),
            'bucket_lock_unlock_form' => $bucketLockUnlockForm->createView(),
        ]);
    }

    #[Route('/bucket/{id}/edit', name: 'bucket_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_SHIFT_MANAGER')]
    public function editBucket(Request $request, Shift $shift, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ShiftType::class, $shift);
        $bucketOrig = clone $shift;
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $shifts = $em->getRepository(Shift::class)->findBy([
                'job' => $bucketOrig->getJob(),
                'start' => $bucketOrig->getStart(),
                'end' => $bucketOrig->getEnd()
            ]);
            foreach ($shifts as $s) {
                $s->setStart($form->get('start')->getData());
                $s->setEnd($form->get('end')->getData());
                $s->setJob($form->get('job')->getData());
                $em->persist($s);
            }
            $em->flush();

            $this->addFlash('success', 'Le créneau a bien été édité !');
            return $this->redirectToRoute('booking_admin');
        }

        return $this->render('admin/shift/edit.html.twig', [
            "form" => $form->createView(),
            "shift" => $shift
        ]);
    }

    #[Route('/bucket/{id}/lock', name: 'bucket_lock_unlock', methods: ['POST'])]
    public function lockUnlockBucket(Request $request, Shift $shift, EntityManagerInterface $em, ShiftService $shiftService, Environment $twig, FormFactoryInterface $formFactory): Response
    {
        $this->denyAccessUnlessGranted(ShiftVoter::LOCK, $shift);

        $form = $this->createBucketLockUnlockForm($shift, $formFactory);
        $form->handleRequest($request);

        $success = false;
        $message = "";
        $bucket = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $lock = $form->get('lock')->getData() == 1;
            $bucket = $shiftService->getShiftBucketFromShift($shift);
            $current = $bucket->getFirst()->isLocked() == 1;
            if ($lock == $current) {
                $message = "Le créneau a déjà été " . ($lock ? "verrouillé" : "déverrouillé");
            } else {
                foreach ($bucket->getShifts() as $s) {
                    $s->setLocked($lock);
                }
                $em->flush();
                $message = "Le créneau a été " . ($lock ? "verrouillé" : "déverrouillé");
                $success = true;
            }
        } else {
            $message = "Une erreur s'est produite... Impossible de verrouiller/déverouiller le créneau. " . (string) $form->getErrors(true, false);
        }

        if ($request->isXmlHttpRequest()) {
            if ($success && $bucket) {
                $card = $twig->render('admin/booking/_partial/bucket_card.html.twig', [
                    'bucket' => $bucket,
                    'start' => 6,
                    'end' => 22,
                    'line' => 0,
                ]);
                
                $modal = $this->forward(self::class . '::showBucket', [
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

    #[Route('/bucket/{id}', name: 'bucket_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteBucket(Request $request, Shift $bucket, EntityManagerInterface $em, EventDispatcherInterface $eventDispatcher, FormFactoryInterface $formFactory): Response
    {
        $form = $this->createBucketDeleteForm($bucket, $formFactory);
        $form->handleRequest($request);

        $success = false;
        $message = "";

        if ($form->isSubmitted() && $form->isValid()) {
            $shifts = $em->getRepository(Shift::class)->findBy([
                'job' => $bucket->getJob(),
                'start' => $bucket->getStart(),
                'end' => $bucket->getEnd()
            ]);
            $count = 0;
            foreach ($shifts as $shift) {
                $beneficiary = $shift->getShifter();
                $eventDispatcher->dispatch(new ShiftDeletedEvent($shift, $beneficiary), ShiftDeletedEvent::NAME);
                $em->remove($shift);
                $count++;
            }
            $em->flush();
            $success = true;
            $message = $count . (($count > 1) ? " créneaux ont été supprimés" : " créneau a été supprimé") . " !";
        } else {
            $message = "Une erreur s'est produite... Impossible de supprimer le créneau. " . (string) $form->getErrors(true, false);
        }
        
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['message' => $message], $success ? 200 : 400);
        } else {
            $this->addFlash($success ? 'success' : 'error', $message);
            return $this->redirectToRoute('booking_admin');
        }
    }

    private function createBucketLockUnlockForm(Shift $bucket, FormFactoryInterface $formFactory): FormInterface
    {
        return $formFactory->createNamedBuilder('bucket_lock_unlock_form')
            ->setAction($this->generateUrl('bucket_lock_unlock', ['id' => $bucket->getId()]))
            ->add('lock', HiddenType::class, [
                'data'  => ($bucket->isLocked() ? 0 : 1),
            ])
            ->setMethod('POST')
            ->getForm();
    }

    private function createBucketShiftAddForm(Shift $bucket, FormFactoryInterface $formFactory): FormInterface
    {
        return $formFactory->createNamed(
            'bucket_shift_add_form',
            ShiftType::class,
            $bucket,
            [
                'action' => $this->generateUrl('shift_new'),
                'only_add_formation' => true,
            ]
        );
    }

    private function createBucketDeleteForm(Shift $bucket, FormFactoryInterface $formFactory): FormInterface
    {
        return $formFactory->createNamedBuilder('bucket_delete_form')
            ->setAction($this->generateUrl('bucket_delete', ['id' => $bucket->getId()]))
            ->setMethod('DELETE')
            ->getForm();
    }

    private function createShiftBookAdminForm(Shift $shift, FormFactoryInterface $formFactory): FormInterface
    {
        $builder = $formFactory->createNamedBuilder('shift_book_forms_' . $shift->getId())
            ->setAction($this->generateUrl('shift_book_admin', ['id' => $shift->getId()]))
            ->add('shifter', AutocompleteBeneficiaryType::class, ['label' => 'Numéro d\'adhérent ou nom du membre', 'required' => true]);

        if ($this->useFlyAndFixed) {
            $builder->add('fixe', RadioChoiceType::class, [
                'choices'  => [
                    'Volant' => 0,
                    'Fixe' => 1,
                ],
                'data' => 0
            ]);
        } else {
            $builder->add('fixe', HiddenType::class, [
                'data' => 0
            ]);
        }

        return $builder->getForm();
    }

    private function createShiftDeleteForm(Shift $shift, FormFactoryInterface $formFactory): FormInterface
    {
        return $formFactory->createNamedBuilder('shift_delete_forms_' . $shift->getId())
            ->setAction($this->generateUrl('shift_delete', ['id' => $shift->getId()]))
            ->setMethod('DELETE')
            ->getForm();
    }

    protected function createShiftFreeForm(Shift $shift): FormInterface
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('shift_free', ['id' => $shift->getId()]))
            ->add('reason', TextareaType::class, ['required' => false, 'label' => 'Justification éventuelle', 'attr' => ['class' => 'materialize-textarea']])
            ->setMethod('POST')
            ->getForm();
    }

    private function createShiftFreeAdminForm(Shift $shift, FormFactoryInterface $formFactory): FormInterface
    {
        return $formFactory->createNamedBuilder('shift_free_forms_' . $shift->getId())
            ->setAction($this->generateUrl('shift_free_admin', ['id' => $shift->getId()]))
            ->add('reason', TextareaType::class, ['required' => false, 'label' => 'Justification éventuelle', 'attr' => ['class' => 'materialize-textarea']])
            ->setMethod('POST')
            ->getForm();
    }

    private function createShiftValidateInvalidateForm(Shift $shift, FormFactoryInterface $formFactory): FormInterface
    {
        return $formFactory->createNamedBuilder('shift_validate_invalidate_forms_' . $shift->getId())
            ->setAction($this->generateUrl('shift_validate', ['id' => $shift->getId()]))
            ->add('validate', HiddenType::class, [
                'data' => ($shift->getWasCarriedOut() ? 0 : 1),
            ])
            ->setMethod('POST')
            ->getForm();
    }
}
