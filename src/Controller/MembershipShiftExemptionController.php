<?php

namespace App\Controller;

use App\Entity\MembershipShiftExemption;
use App\Entity\ShiftExemption;
use App\Entity\Shift;
use App\Form\AutocompleteMembershipType;
use App\Form\MembershipShiftExemptionType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use \Datetime;

#[Route('/admin/membershipshiftexemption')]
class MembershipShiftExemptionController extends AbstractController
{
    private function filterFormFactory(Request $request): array
    {
        // default values
        $res = [
            "membership" => null,
            "shiftExemption" => null,
        ];

        // filter creation ----------------------
        $res["form"] = $this->createFormBuilder()
            ->setAction($this->generateUrl('admin_membershipshiftexemption_index'))
            ->add('membership', AutocompleteMembershipType::class, array(
                'label' => 'Membre',
                'required' => false,
            ))
            ->add('shiftExemption', EntityType::class, array(
                'label' => 'Motif',
                'class' => ShiftExemption::class,
                'choice_label' => 'name',
                'multiple' => false,
                'required' => false,
            ))
            ->add('submit', SubmitType::class, array(
                'label' => 'Filtrer',
                'attr' => array('class' => 'btn', 'value' => 'filtrer')
            ))
            ->getForm();

        $res['form']->handleRequest($request);

        if ($res['form']->isSubmitted() && $res['form']->isValid()) {
            $res["membership"] = $res["form"]->get("membership")->getData();
            $res["shiftExemption"] = $res["form"]->get("shiftExemption")->getData();
        }

        return $res;
    }

    #[Route('/', name: 'admin_membershipshiftexemption_index', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER_MANAGER')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $filter = $this->filterFormFactory($request);
        $findByFilter = array();
        $sort = 'createdAt';
        $order = 'DESC';

        if ($filter['membership']) {
            $findByFilter['membership'] = $filter['membership'];
        }
        if ($filter['shiftExemption']) {
            $findByFilter['shiftExemption'] = $filter['shiftExemption'];
        }

        $page = (int) $request->get('page', 1);
        $limit = 50;

        $nb_exemptions = $em->getRepository(MembershipShiftExemption::class)->count([]);
        if ($nb_exemptions == 0) {
            $max_page = 1;
        } else {
            $max_page = (int) (($nb_exemptions - 1) / $limit) + 1;
        }

        $membershipShiftExemptions = $em->getRepository(MembershipShiftExemption::class)
            ->findBy($findByFilter, array($sort => $order), $limit, ($page - 1) * $limit);

        return $this->render('admin/membershipshiftexemption/index.html.twig', array(
            'membershipShiftExemptions' => $membershipShiftExemptions,
            'filter_form' => $filter['form']->createView(),
            'current_page' => $page,
            'max_page' => $max_page,
        ));
    }

    #[Route('/new', name: 'admin_membershipshiftexemption_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER_MANAGER')]
    public function new(Request $request, EntityManagerInterface $em, TokenStorageInterface $tokenStorage): Response
    {
        $membershipShiftExemption = new MembershipShiftExemption();
        $form = $this->createForm(MembershipShiftExemptionType::class, $membershipShiftExemption);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $membership = $form->get("beneficiary")->getData()->getMembership();
            $membershipShiftExemption->setMembership($membership);

            if ($this->isMembershipHasShiftsOnExemptionPeriod($em, $membershipShiftExemption)) {
                $this->addFlash("error", "Désolé, les bénéficiaires ont déjà des créneaux planifiés sur la plage d'exemption.");
                return $this->redirectToRoute('admin_membershipshiftexemption_new');
            }

            $current_user = $tokenStorage->getToken()->getUser();
            $membershipShiftExemption->setCreatedBy($current_user);
            $em->persist($membershipShiftExemption);
            $em->flush();

            $this->addFlash('success', 'L\'exemption de créneau a bien été crée !');
            return $this->redirectToRoute('admin_membershipshiftexemption_index');
        }

        return $this->render('admin/membershipshiftexemption/new.html.twig', array(
            'membershipShiftExemption' => $membershipShiftExemption,
            'form' => $form->createView(),
        ));
    }

    #[Route('/{id}/edit', name: 'admin_membershipshiftexemption_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER_MANAGER')]
    public function edit(Request $request, MembershipShiftExemption $membershipShiftExemption, EntityManagerInterface $em): Response
    {
        $deleteForm = $this->createDeleteForm($membershipShiftExemption);
        $editForm = $this->createForm(MembershipShiftExemptionType::class, $membershipShiftExemption);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            if ($this->isMembershipHasShiftsOnExemptionPeriod($em, $membershipShiftExemption)) {
                $this->addFlash("error", "Désolé, les bénéficiaires ont déjà des créneaux planifiés sur la plage d'exemption.");
            } else {
                $this->addFlash('success', 'L\'exemption de créneau a bien été éditée !');
                $em->flush();
            }

            return $this->redirectToRoute('admin_membershipshiftexemption_index');
        }

        return $this->render('admin/membershipshiftexemption/edit.html.twig', array(
            'membershipShiftExemption' => $membershipShiftExemption,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    #[Route('/{id}', name: 'admin_membershipshiftexemption_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER_MANAGER')]
    public function delete(Request $request, MembershipShiftExemption $membershipShiftExemption, EntityManagerInterface $em, TokenStorageInterface $tokenStorage): Response
    {
        $form = $this->createDeleteForm($membershipShiftExemption);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $current_user = $tokenStorage->getToken()->getUser();
            $today = new Datetime('now');
            $today->setTime(0, 0, 0);
            if (($membershipShiftExemption->getStart() < $today) && !$this->isGranted('ROLE_SUPER_ADMIN')) {
                $this->addFlash('warning', 'Vous n\'avez pas les droits pour supprimer une exemption déjà commencée');
                return $this->redirectToRoute('admin_membershipshiftexemption_edit', array('id' => $membershipShiftExemption->getId()));
            }

            $em->remove($membershipShiftExemption);
            $em->flush();
            $this->addFlash('success', 'L\'exemption de créneau a bien été supprimée !');
        }

        return $this->redirectToRoute('admin_membershipshiftexemption_index');
    }

    private function createDeleteForm(MembershipShiftExemption $membershipShiftExemption)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('admin_membershipshiftexemption_delete', array('id' => $membershipShiftExemption->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }

    private function isMembershipHasShiftsOnExemptionPeriod(EntityManagerInterface $em, MembershipShiftExemption $membershipShiftExemption)
    {
        $shifts = $em->getRepository(Shift::class)->findInProgressAndUpcomingShiftsForMembership($membershipShiftExemption->getMembership());
        foreach ($shifts as $shift) {
            if ($membershipShiftExemption->isCurrent($shift->getStart())) {
                return true;
            }
        }
        return false;
    }
}
