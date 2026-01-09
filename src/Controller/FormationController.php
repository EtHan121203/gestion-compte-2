<?php

namespace App\Controller;

use App\Entity\Formation;
use App\Form\FormationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/formations')]
#[IsGranted('ROLE_ADMIN')]
class FormationController extends AbstractController
{
    #[Route('/', name: 'admin_formations', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $formations = $em->getRepository(Formation::class)->findAll();
        return $this->render('admin/formation/list.html.twig', ['formations' => $formations]);
    }

    #[Route('/new', name: 'formation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $formation = new Formation();
        $form = $this->createForm(FormationType::class, $formation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($formation);
            $em->flush();

            $this->addFlash('success', 'La nouvelle formation a bien été créée !');

            return $this->redirectToRoute('admin_formations');
        }

        return $this->render('admin/formation/new.html.twig', [
            'formation' => $formation,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'formation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Formation $formation, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(FormationType::class, $formation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'La formation a bien été éditée !');

            return $this->redirectToRoute('admin_formations');
        }

        return $this->render('admin/formation/edit.html.twig', [
            'formation' => $formation,
            'form' => $form->createView(),
            'delete_form' => $this->getDeleteForm($formation)->createView(),
        ]);
    }

    #[Route('/{id}', name: 'formation_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function remove(Request $request, Formation $formation, EntityManagerInterface $em): Response
    {
        $form = $this->getDeleteForm($formation);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->remove($formation);
            $em->flush();
            $this->addFlash('success', 'La formation a bien été supprimée !');
        }
        return $this->redirectToRoute('admin_formations');
    }

    protected function getDeleteForm(Formation $formation)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('formation_delete', ['id' => $formation->getId()]))
            ->setMethod('DELETE')
            ->getForm();
    }
}
