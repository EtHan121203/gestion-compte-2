<?php

namespace App\Controller;

use App\Entity\EmailTemplate;
use App\Form\EmailTemplateType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/emailTemplate')]
class EmailTemplateController extends AbstractController
{
    #[Route('/', name: 'email_template_list', methods: ['GET'])]
    #[IsGranted('ROLE_PROCESS_MANAGER')]
    public function list(EntityManagerInterface $em): Response
    {
        $emailTemplates = $em->getRepository(EmailTemplate::class)->findAll();

        return $this->render('admin/mail/template/list.html.twig', [
            'emailTemplates' => $emailTemplates,
        ]);
    }

    #[Route('/new', name: 'email_template_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_PROCESS_MANAGER')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $emailTemplate = new EmailTemplate();
        $form = $this->createForm(EmailTemplateType::class, $emailTemplate);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($emailTemplate);
            $em->flush();

            $this->addFlash('success', "Modèle d'email créé");

            return $this->redirectToRoute('email_template_list');
        }

        return $this->render('admin/mail/template/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'email_template_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_PROCESS_MANAGER')]
    public function edit(Request $request, EmailTemplate $emailTemplate, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('edit', $emailTemplate);

        $form = $this->createForm(EmailTemplateType::class, $emailTemplate);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', "Modèle d'email édité");

            return $this->redirectToRoute('email_template_list');
        }

        return $this->render('admin/mail/template/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
