<?php

namespace App\Controller;

use App\Entity\DynamicContent;
use App\Entity\User;
use App\Form\DynamicContentType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/content')]
class DynamicContentController extends AbstractController
{
    #[Route('/', name: 'dynamic_content_list', methods: ['GET'])]
    #[IsGranted('ROLE_PROCESS_MANAGER')]
    public function list(EntityManagerInterface $em): Response
    {
        $dynamicContents = $em->getRepository(DynamicContent::class)->findAll();
        $dynamicContentsByType = [];

        foreach ($dynamicContents as $dynamicContent) {
            $type = $dynamicContent->getType();
            if (!isset($dynamicContentsByType[$type])) {
                $dynamicContentsByType[$type] = [];
            }
            $dynamicContentsByType[$type][] = $dynamicContent;
        }

        return $this->render('admin/content/list.html.twig', [
            'dynamicContentsByType' => $dynamicContentsByType,
        ]);
    }

    #[Route('/{id}/edit', name: 'dynamic_content_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_PROCESS_MANAGER')]
    public function edit(Request $request, DynamicContent $dynamicContent, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(DynamicContentType::class, $dynamicContent);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($dynamicContent->getContent() === null) {
                $dynamicContent->setContent('');
            }
            /** @var User $user */
            $user = $this->getUser();
            $dynamicContent->setUpdatedBy($user);
            
            $em->persist($dynamicContent);
            $em->flush();

            $this->addFlash('success', 'Contenu dynamique édité !');
            return $this->redirectToRoute('dynamic_content_list');
        }

        return $this->render('admin/content/edit.html.twig', [
            'dynamicContent' => $dynamicContent,
            'form' => $form->createView()
        ]);
    }
}
