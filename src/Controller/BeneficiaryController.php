<?php

namespace App\Controller;

use App\Entity\Beneficiary;
use App\Entity\Membership;
use App\Form\BeneficiaryType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[Route('/beneficiary')]
class BeneficiaryController extends AbstractController
{
    #[Route('/{id}/edit', name: 'beneficiary_edit', methods: ['GET', 'POST'])]
    public function editBeneficiary(Request $request, Beneficiary $beneficiary, EntityManagerInterface $em): Response
    {
        $member = $beneficiary->getMembership();
        $this->denyAccessUnlessGranted('edit', $member);

        $editForm = $this->createForm(BeneficiaryType::class, $beneficiary);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Mise à jour effectuée');

            return $this->redirectToShow($member, $request);
        }

        return $this->render('beneficiary/edit_beneficiary.html.twig', [
            'beneficiary' => $beneficiary,
            'edit_form' => $editForm->createView(),
        ]);
    }

    #[Route('/beneficiary/{id}/set_main', name: 'beneficiary_set_main', methods: ['GET'])]
    public function setAsMainBeneficiary(Beneficiary $beneficiary, EntityManagerInterface $em, Request $request): RedirectResponse
    {
        $member = $beneficiary->getMembership();
        $this->denyAccessUnlessGranted('edit', $member);
        
        $member->setMainBeneficiary($beneficiary);
        $em->persist($member);
        $em->flush();
        
        $this->addFlash('success', 'Le changement de bénéficiaire principal a été effectué');
        
        return $this->redirectToShow($member, $request);
    }

    #[Route('/{id}/detach', name: 'beneficiary_detach', methods: ['GET', 'POST'])]
    public function detachBeneficiary(Request $request, Beneficiary $beneficiary, EntityManagerInterface $em): RedirectResponse
    {
        $member = $beneficiary->getMembership();
        $this->denyAccessUnlessGranted('edit', $member);

        if ($beneficiary->isMain()) {
            $this->addFlash('error', 'Un bénéficiaire principal ne peut pas être détaché');
            return $this->redirectToShow($member, $request);
        }

        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('beneficiary_detach', ['id' => $beneficiary->getId()]))
            ->setMethod('POST')
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $member->removeBeneficiary($beneficiary);
            $em->persist($member);

            $existing_member = $em->getRepository(Membership::class)->findOneBy(['mainBeneficiary' => $beneficiary]);
            if ($existing_member) {
                $new_member = $existing_member;
                $new_member->setMainBeneficiary($beneficiary);
            } else {
                $new_member = new Membership();
                $m = $em->getRepository(Membership::class)->findOneBy([], ['member_number' => 'DESC']);
                $mm = 1;
                if ($m) {
                    $mm = $m->getMemberNumber() + 1;
                }
                $new_member->setMemberNumber($mm);
                $new_member->setMainBeneficiary($beneficiary);
            }
            
            $new_member->setWithdrawn(false);
            $new_member->setFrozen(false);
            $new_member->setFrozenChange(false);

            $em->persist($new_member);
            $em->flush();

            $this->addFlash('success', 'Le bénéficiaire a été détaché ! Il a maintenant son propre compte.');
            return $this->redirectToShow($new_member, $request);
        }

        return $this->redirectToShow($member, $request);
    }

    #[Route('/beneficiary/{id}', name: 'beneficiary_delete', methods: ['GET', 'POST'])]
    public function deleteBeneficiary(Request $request, Beneficiary $beneficiary, EntityManagerInterface $em): RedirectResponse
    {
        $member = $beneficiary->getMembership();
        $this->denyAccessUnlessGranted('edit', $member);

        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('beneficiary_delete', ['id' => $beneficiary->getId()]))
            ->setMethod('DELETE')
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->remove($beneficiary);
            $em->flush();
        }

        return $this->redirectToShow($member, $request);
    }

    #[Route('/find_member_number', name: 'find_member_number')]
    public function findMemberNumber(Request $request, AuthorizationCheckerInterface $authorizationChecker, EntityManagerInterface $em): Response
    {
        if ($authorizationChecker->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            $form = $this->createFormBuilder()
                ->add('firstname', TextType::class, [
                    'label' => 'Le prénom',
                    'attr' => ['placeholder' => 'babar'],
                ])
                ->add('find', SubmitType::class, ['label' => 'Trouver le numéro'])
                ->getForm();
        } else {
            $form = $this->createFormBuilder()
                ->add('firstname', TextType::class, [
                    'label' => 'Mon prénom',
                    'attr' => ['placeholder' => 'babar'],
                ])
                ->add('find', SubmitType::class, ['label' => 'Trouver mon numéro'])
                ->getForm();
        }

        if ($form->handleRequest($request)->isSubmitted() && $form->isValid()) {
            $firstname = $form->get('firstname')->getData();
            $qb = $em->createQueryBuilder();
            $beneficiaries = $qb->select('b')->from(Beneficiary::class, 'b')
                ->join('b.membership', 'm')
                ->where($qb->expr()->like('b.firstname', $qb->expr()->literal('%' . $firstname . '%')))
                ->andWhere("m.withdrawn != 1 or m.withdrawn is NULL")
                ->orderBy("m.member_number", 'ASC')
                ->getQuery()
                ->getResult();
                
            return $this->render('beneficiary/find_member_number.html.twig', [
                'form' => null,
                'beneficiaries' => $beneficiaries,
                'return_path' => 'confirm',
                'routeParam' => 'id',
                'params' => []
            ]);
        }
        
        return $this->render('beneficiary/find_member_number.html.twig', [
            'form' => $form->createView(),
            'beneficiaries' => ''
        ]);
    }

    #[Route('/{id}/confirm', name: 'confirm', methods: ['POST'])]
    public function confirm(Beneficiary $beneficiary): Response
    {
        return $this->render('beneficiary/confirm.html.twig', ['beneficiary' => $beneficiary]);
    }

    private function redirectToShow(Membership $member, Request $request): RedirectResponse
    {
        $user = $member->getMainBeneficiary()->getUser();
        $session = $request->getSession();
        
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('member_show', ['member_number' => $member->getMemberNumber()]);
        }
        
        return $this->redirectToRoute('member_show', [
            'member_number' => $member->getMemberNumber(),
            'token' => $user->getTmpToken($session->get('token_key') . $this->getUser()->getUserIdentifier())
        ]);
    }
}
