<?php

namespace App\Controller;

use App\Entity\Beneficiary;
use App\Entity\Commission;
use App\Event\CommissionJoinOrLeaveEvent;
use App\Form\AutocompleteBeneficiaryType;
use App\Form\CommissionType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;

#[Route('/commissions')]
class CommissionController extends AbstractController
{
    #[Route('/', name: 'admin_commissions', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(EntityManagerInterface $em): Response
    {
        $commissions = $em->getRepository(Commission::class)->findAll();
        return $this->render('admin/commission/list.html.twig', ['commissions' => $commissions]);
    }

    #[Route('/new', name: 'commission_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $commission = new Commission();
        $form = $this->createForm(CommissionType::class, $commission);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($commission);
            $em->flush();

            $this->addFlash('success', 'La nouvelle commission a bien été créée !');

            return $this->redirectToRoute('commission_edit', ['id' => $commission->getId()]);
        }

        return $this->render('admin/commission/new.html.twig', [
            'commission' => $commission,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'commission_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function edit(Request $request, Commission $commission, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $beneficiary = $user->getBeneficiary();

        if (!$this->isGranted('ROLE_ADMIN') && !$beneficiary->getOwnedCommissions()->contains($commission)) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(CommissionType::class, $commission);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($commission->getBeneficiaries() as $b) {
                $b->setOwn();
                $em->persist($b);
            }
            $owners = $commission->getOwners();
            foreach ($owners as $o) {
                $o->setOwn($commission);
                $em->persist($o);
            }

            $em->persist($commission);
            $em->flush();

            $this->addFlash('success', 'La commission a bien été éditée !');

            if ($this->isGranted('ROLE_SUPER_ADMIN')) {
                return $this->redirectToRoute('admin_commissions');
            }
        }

        $add_form = $this->getAddBeneficiaryForm($commission);

        return $this->render('admin/commission/edit.html.twig', [
            'commission' => $commission,
            'form' => $form->createView(),
            'add_form' => $add_form->createView(),
            'remove_beneficiary_form' => $this->getRemoveBeneficiaryForm($commission)->createView(),
            'delete_form' => $this->getDeleteForm($commission)->createView(),
        ]);
    }

    private function getAddBeneficiaryForm(Commission $commission)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('commission_add_beneficiary', ['id' => $commission->getId()]))
            ->add('beneficiary', AutocompleteBeneficiaryType::class, [
                'label' => 'Email ou nom de la personne',
                'required' => true
            ])
            ->setMethod('POST')
            ->getForm();
    }

    #[Route('/{id}/add_beneficiary/', name: 'commission_add_beneficiary', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addBeneficiary(Request $request, Commission $commission, EntityManagerInterface $em, EventDispatcherInterface $dispatcher, Environment $twig): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$this->isGranted('ROLE_SUPER_ADMIN') && !$user->getBeneficiary()->getOwnedCommissions()->contains($commission)) {
            throw $this->createAccessDeniedException();
        }

        $success = true;
        $message = '';
        $html = '';
        $form = $this->getAddBeneficiaryForm($commission);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $beneficiary = $form->get('beneficiary')->getData()['beneficiary'];
            if (!$commission->getBeneficiaries()->contains($beneficiary)) {
                $beneficiary->addCommission($commission);
                $em->persist($beneficiary);
                $em->flush();
                $message = $beneficiary->getFirstname() . ' a bien été ajouté à la commission';
                
                $dispatcher->dispatch(
                    new CommissionJoinOrLeaveEvent($beneficiary, $commission),
                    CommissionJoinOrLeaveEvent::JOIN_EVENT_NAME
                );
            } else {
                $success = false;
                $message = $beneficiary->getFirstname() . ' fait déjà partie de la commission';
            }
        }

        if ($request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            if (isset($beneficiary)) {
                $html = $twig->render('beneficiary/_partial/chip.html.twig', [
                    'beneficiary' => $beneficiary,
                    'close' => true,
                ]);
            }
            return new JsonResponse(['success' => $success, 'message' => $message, 'html' => $html]);
        }

        $this->addFlash($success ? 'success' : 'error', $message);

        return $this->redirectToRoute('commission_edit', ['id' => $commission->getId()]);
    }

    #[Route('/{id}/remove_beneficiary/', name: 'commission_remove_beneficiary', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function removeBeneficiary(Request $request, Commission $commission, EntityManagerInterface $em, EventDispatcherInterface $dispatcher): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$this->isGranted('ROLE_SUPER_ADMIN') && !$user->getBeneficiary()->getOwnedCommissions()->contains($commission)) {
            throw $this->createAccessDeniedException();
        }

        $beneficiaryId = $request->request->get('beneficiary');
        /** @var Beneficiary|null $beneficiary */
        $beneficiary = $em->getRepository(Beneficiary::class)->find($beneficiaryId);

        if ($beneficiary && $beneficiary->getId()) {
            $beneficiary->removeCommission($commission);
            $em->persist($beneficiary);
            $em->flush();
            
            $dispatcher->dispatch(
                new CommissionJoinOrLeaveEvent($beneficiary, $commission),
                CommissionJoinOrLeaveEvent::LEAVE_EVENT_NAME
            );
        }

        if ($request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            return new JsonResponse([
                'success' => true,
                'message' => $beneficiary ? $beneficiary->getFirstname() . ' a bien été retiré de la commission' : 'Membre non trouvé'
            ]);
        }

        $this->addFlash('success', 'Le membre ' . $beneficiary . ' a bien été retiré de la commission !');

        return $this->redirectToRoute('commission_edit', ['id' => $commission->getId()]);
    }

    #[Route('/{id}', name: 'commission_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function remove(Request $request, Commission $commission, EntityManagerInterface $em): Response
    {
        $form = $this->getDeleteForm($commission);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($commission->getBeneficiaries() as $beneficiary) {
                $beneficiary->removeCommission($commission);
                $em->persist($beneficiary);
            }
            foreach ($commission->getOwners() as $owner) {
                $owner->setOwn();
                $em->persist($owner);
            }
            $em->remove($commission);
            $em->flush();
            $this->addFlash('success', 'La commission a bien été supprimée !');
        }

        return $this->redirectToRoute('admin_commissions');
    }

    protected function getDeleteForm(Commission $commission)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('commission_delete', ['id' => $commission->getId()]))
            ->setMethod('DELETE')
            ->getForm();
    }

    protected function getRemoveBeneficiaryForm(Commission $commission)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('commission_remove_beneficiary', ['id' => $commission->getId()]))
            ->setMethod('POST')
            ->getForm();
    }
}
