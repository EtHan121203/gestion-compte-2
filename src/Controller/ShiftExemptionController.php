<?php

namespace App\Controller;

use App\Entity\ShiftExemption;
use App\Form\ShiftExemptionType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/shifts/exemptions')]
class ShiftExemptionController extends AbstractController
{
    #[Route('/', name: 'admin_shiftexemption_index', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(EntityManagerInterface $em): Response
    {
        $shiftExemptions = $em->getRepository(ShiftExemption::class)->findAll();

        return $this->render('admin/shiftexemption/index.html.twig', [
            'shiftExemptions' => $shiftExemptions,
        ]);
    }

    #[Route('/new', name: 'admin_shiftexemption_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $shiftExemption = new ShiftExemption();
        $form = $this->createForm(ShiftExemptionType::class, $shiftExemption);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($shiftExemption);
            $em->flush();

            $this->addFlash('success', "Le nouveau motif d'exemption a été créé !");
            return $this->redirectToRoute('admin_shiftexemption_index');
        }

        return $this->render('admin/shiftexemption/new.html.twig', [
            'shiftExemption' => $shiftExemption,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_shiftexemption_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, ShiftExemption $shiftExemption, EntityManagerInterface $em): Response
    {
        $deleteForm = $this->createDeleteForm($shiftExemption);
        $editForm = $this->createForm(ShiftExemptionType::class, $shiftExemption);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $em->flush();

            $this->addFlash('success', "Le motif d'exemption a bien été modifié !");
            return $this->redirectToRoute('admin_shiftexemption_index');
        }

        return $this->render('admin/shiftexemption/edit.html.twig', [
            'shiftExemption' => $shiftExemption,
            'form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_shiftexemption_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function delete(Request $request, ShiftExemption $shiftExemption, EntityManagerInterface $em): Response
    {
        $form = $this->createDeleteForm($shiftExemption);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->remove($shiftExemption);
            $em->flush();
            $this->addFlash('success', "Le motif d'exemption bien été supprimé !");
        }

        return $this->redirectToRoute('admin_shiftexemption_index');
    }

    private function createDeleteForm(ShiftExemption $shiftExemption): \Symfony\Component\Form\FormInterface
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('admin_shiftexemption_delete', ['id' => $shiftExemption->getId()]))
            ->setMethod('DELETE')
            ->getForm();
    }
}
