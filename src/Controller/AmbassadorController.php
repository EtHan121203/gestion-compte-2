<?php

namespace App\Controller;

use App\Entity\Membership;
use App\Entity\Note;
use App\Entity\TimeLog;
use App\Form\NoteType;
use App\Service\SearchUserFormHelper;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[Route('/ambassador')]
class AmbassadorController extends AbstractController
{
    private $timeAfterWhichMembersAreLateWithShifts;
    private $registrationEveryCivilYear;

    public function __construct(
        #[Autowire(param: 'time_after_which_members_are_late_with_shifts')] $timeAfterWhichMembersAreLateWithShifts,
        #[Autowire(param: 'registration_every_civil_year')] $registrationEveryCivilYear
    ) {
        $this->timeAfterWhichMembersAreLateWithShifts = $timeAfterWhichMembersAreLateWithShifts;
        $this->registrationEveryCivilYear = $registrationEveryCivilYear;
    }

    #[Route('/noregistration', name: 'ambassador_noregistration_list', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER_VIEWER')]
    public function memberNoRegistration(Request $request, SearchUserFormHelper $formHelper, EntityManagerInterface $em): Response
    {
        $defaults = [
            'sort' => 'r.date',
            'dir' => 'DESC',
            'withdrawn' => 1,
            'registration' => 1,
        ];
        $disabledFields = ['withdrawn', 'registration', 'lastregistrationdatelt', 'lastregistrationdategt'];

        $form = $formHelper->createMemberNoRegistrationFilterForm($this->createFormBuilder(), $defaults, $disabledFields);
        $form->handleRequest($request);

        $qb = $formHelper->initSearchQuery($em);
        $qb = $qb->leftJoin("o.timeLogs", "c")->addSelect("c")
            ->addSelect("(SELECT SUM(ti.time) FROM " . TimeLog::class . " ti WHERE ti.membership = o.id) AS HIDDEN time");

        if ($form->isSubmitted() && $form->isValid()) {
            $formHelper->processSearchFormAmbassadorData($form, $qb);
            $sort = $form->get('sort')->getData();
            $order = $form->get('dir')->getData();
            $currentPage = $form->get('page')->getData();
        } else {
            $sort = $defaults['sort'];
            $order = $defaults['dir'];
            $currentPage = 1;
            $qb = $qb->andWhere('o.withdrawn = :withdrawn')
                ->setParameter('withdrawn', $defaults['withdrawn'] - 1);
            $qb = $qb->andWhere('r.date IS NULL');
        }

        $limitPerPage = 25;
        $qb = $qb->orderBy($sort, $order);
        $paginator = new Paginator($qb);
        $totalItems = count($paginator);
        $pagesCount = ($totalItems == 0) ? 1 : (int) ceil($totalItems / $limitPerPage);
        $currentPage = ($currentPage > $pagesCount) ? $pagesCount : $currentPage;

        $paginator
            ->getQuery()
            ->setFirstResult($limitPerPage * ($currentPage - 1))
            ->setMaxResults($limitPerPage);

        return $this->render('ambassador/phone/list.html.twig', [
            'reason' => "adhésion",
            'members' => $paginator,
            'form' => $form->createView(),
            'nb_of_result' => $totalItems,
            'page' => $currentPage,
            'nb_of_pages' => $pagesCount
        ]);
    }

    #[Route('/lateregistration', name: 'ambassador_lateregistration_list', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER_VIEWER')]
    public function memberLateRegistration(Request $request, SearchUserFormHelper $formHelper, EntityManagerInterface $em): Response
    {
        if ($this->registrationEveryCivilYear) {
            $endLastRegistration = new \DateTime('last day of December last year');
        } else {
            $endLastRegistration = new \DateTime('last year');
        }
        $endLastRegistration->setTime(0, 0);

        $defaults = [
            'sort' => 'r.date',
            'dir' => 'DESC',
            'withdrawn' => 1,
            'lastregistrationdatelt' => $endLastRegistration,
            'registration' => 2,
        ];
        $disabledFields = ['withdrawn', 'lastregistrationdatelt', 'registration'];

        $form = $formHelper->createMemberLateRegistrationFilterForm($this->createFormBuilder(), $defaults, $disabledFields);
        $form->handleRequest($request);

        $qb = $formHelper->initSearchQuery($em);
        $qb = $qb->leftJoin("o.registrations", "lr", Join::WITH, 'lr.date > r.date')->addSelect("lr")
            ->where('lr.id IS NULL')
            ->leftJoin("o.timeLogs", "c")->addSelect("c")
            ->addSelect("(SELECT SUM(ti.time) FROM " . TimeLog::class . " ti WHERE ti.membership = o.id) AS HIDDEN time");

        if ($form->isSubmitted() && $form->isValid()) {
            $formHelper->processSearchFormAmbassadorData($form, $qb);
            $sort = $form->get('sort')->getData();
            $order = $form->get('dir')->getData();
            $currentPage = $form->get('page')->getData();
        } else {
            $sort = $defaults['sort'];
            $order = $defaults['dir'];
            $currentPage = 1;
            $qb = $qb->andWhere('o.withdrawn = :withdrawn')
                ->setParameter('withdrawn', $defaults['withdrawn'] - 1);
            $qb = $qb->andWhere('r.date < :lastregistrationdatelt')
                ->setParameter('lastregistrationdatelt', $defaults['lastregistrationdatelt']->format('Y-m-d'));
        }

        $limitPerPage = 25;
        $qb = $qb->orderBy($sort, $order);
        $paginator = new Paginator($qb);
        $totalItems = count($paginator);
        $pagesCount = ($totalItems == 0) ? 1 : (int) ceil($totalItems / $limitPerPage);
        $currentPage = ($currentPage > $pagesCount) ? $pagesCount : $currentPage;

        $paginator
            ->getQuery()
            ->setFirstResult($limitPerPage * ($currentPage - 1))
            ->setMaxResults($limitPerPage);

        return $this->render('ambassador/phone/list.html.twig', [
            'reason' => "de ré-adhésion",
            'members' => $paginator,
            'form' => $form->createView(),
            'nb_of_result' => $totalItems,
            'page' => $currentPage,
            'nb_of_pages' => $pagesCount
        ]);
    }

    #[Route('/shifttimelog', name: 'ambassador_shifttimelog_list', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER_MANAGER')]
    public function memberShiftTimeLog(Request $request, SearchUserFormHelper $formHelper, EntityManagerInterface $em): Response
    {
        $defaults = [
            'sort' => 'time',
            'dir' => 'DESC',
            'withdrawn' => 1,
            'frozen' => 1,
            'compteurlt' => 0,
            'registration' => 2,
        ];
        $disabledFields = ['withdrawn', 'compteurlt', 'registration'];

        $form = $formHelper->createMemberShiftTimeLogFilterForm($this->createFormBuilder(), $defaults, $disabledFields);
        $form->handleRequest($request);

        $qb = $formHelper->initSearchQuery($em);
        $qb = $qb->leftJoin("o.registrations", "lr", Join::WITH, 'lr.date > r.date')->addSelect("lr")
            ->where('lr.id IS NULL')
            ->addSelect("(SELECT SUM(ti.time) FROM " . TimeLog::class . " ti WHERE ti.membership = o.id) AS HIDDEN time");

        if ($form->isSubmitted() && $form->isValid()) {
            $qb = $formHelper->processSearchFormAmbassadorData($form, $qb);
            $sort = $form->get('sort')->getData();
            $order = $form->get('dir')->getData();
            $currentPage = $form->get('page')->getData();
        } else {
            $sort = $defaults['sort'];
            $order = $defaults['dir'];
            $currentPage = 1;
            $qb = $qb->andWhere('o.withdrawn = :withdrawn')
                ->setParameter('withdrawn', $defaults['withdrawn'] - 1);
            $qb = $qb->andWhere('o.frozen = :frozen')
                ->setParameter('frozen', $defaults['frozen'] - 1);
            $qb = $qb->andWhere('b.membership IN (SELECT IDENTITY(t.membership) FROM ' . TimeLog::class . ' t GROUP BY t.membership HAVING SUM(t.time) < :compteurlt * 60)')
                ->setParameter('compteurlt', $defaults['compteurlt']);
        }

        $limitPerPage = 25;
        $qb = $qb->orderBy($sort, $order);
        $paginator = new Paginator($qb);
        $totalItems = count($paginator);
        $pagesCount = ($totalItems == 0) ? 1 : (int) ceil($totalItems / $limitPerPage);
        $currentPage = ($currentPage > $pagesCount) ? $pagesCount : $currentPage;

        $paginator
            ->getQuery()
            ->setFirstResult($limitPerPage * ($currentPage - 1))
            ->setMaxResults($limitPerPage);

        return $this->render('ambassador/phone/list.html.twig', [
            'reason' => "de créneaux",
            'members' => $paginator,
            'form' => $form->createView(),
            'nb_of_result' => $totalItems,
            'page' => $currentPage,
            'nb_of_pages' => $pagesCount
        ]);
    }

    #[Route('/phone/{member_number}', name: 'ambassador_phone_show', methods: ['GET'])]
    public function show(Membership $member): Response
    {
        return $this->redirectToRoute('member_show', ['member_number' => $member->getMemberNumber()]);
    }

    #[Route('/note/{member_number}', name: 'ambassador_new_note', methods: ['POST'])]
    public function newNote(Membership $member, Request $request, TokenStorageInterface $tokenStorage, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('annotate', $member);
        
        $note = new Note();
        $form = $this->createForm(NoteType::class, $note);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $note->setSubject($member);
            $note->setAuthor($tokenStorage->getToken()?->getUser());

            $em->persist($note);
            $em->flush();

            $this->addFlash('success', 'La note a bien été ajoutée');
        } else {
            $this->addFlash('error', 'Impossible d\'ajouter une note');
        }

        return $this->redirectToRoute("ambassador_phone_show", ['member_number' => $member->getMemberNumber()]);
    }
}
