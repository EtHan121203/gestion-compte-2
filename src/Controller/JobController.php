<?php

namespace App\Controller;

use App\Entity\Job;
use App\Form\JobType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/job')]
class JobController extends AbstractController
{
    private function filterFormFactory(Request $request): array
    {
        // default values
        $res = [
            "enabled" => 0,
        ];

        // filter creation ----------------------
        $res["form"] = $this->createFormBuilder()
            ->setAction($this->generateUrl('job_list'))
            ->add('enabled', ChoiceType::class, array(
                'label' => 'Poste activé ?',
                'required' => false,
                'choices' => [
                    'activé' => 2,
                    'désactivé' => 1,
                ]
            ))
            ->add('filter', SubmitType::class, array(
                'label' => 'Filtrer',
                'attr' => array('class' => 'btn', 'value' => 'filtrer')
            ))
            ->getForm();

        $res["form"]->handleRequest($request);

        if ($res["form"]->isSubmitted() && $res["form"]->isValid()) {
            $res["enabled"] = $res["form"]->get("enabled")->getData();
        }

        return $res;
    }

    #[Route('/', name: 'job_list', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function list(Request $request, EntityManagerInterface $em): Response
    {
        $filter = $this->filterFormFactory($request);
        $findByFilter = array();

        if ($filter["enabled"] > 0) {
            $findByFilter["enabled"] = $filter["enabled"] - 1;
        }

        $jobs = $em->getRepository(Job::class)->findBy($findByFilter);

        return $this->render('admin/job/list.html.twig', array(
            'jobs' => $jobs,
            "filter_form" => $filter['form']->createView(),
        ));
    }

    #[Route('/new', name: 'job_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $job = new Job();
        $job->setEnabled(true);

        $form = $this->createForm(JobType::class, $job);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($job);
            $em->flush();

            $this->addFlash('success', 'Le nouveau poste a été créé !');
            return $this->redirectToRoute('job_list');
        }

        return $this->render('admin/job/new.html.twig', array(
            'form' => $form->createView()
        ));
    }

    #[Route('/edit/{id}', name: 'job_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, Job $job, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(JobType::class, $job);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($job);
            $em->flush();

            $this->addFlash('success', 'Le poste a bien été édité !');
            return $this->redirectToRoute('job_list');
        }

        return $this->render('admin/job/edit.html.twig', array(
            'form' => $form->createView(),
            'delete_form' => $this->getDeleteForm($job)->createView()
        ));
    }

    #[Route('/{id}', name: 'job_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function remove(Request $request, Job $job, EntityManagerInterface $em): Response
    {
        $form = $this->getDeleteForm($job);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->remove($job);
            $em->flush();
            $this->addFlash('success', 'Le poste a bien été supprimée !');
        }

        return $this->redirectToRoute('job_list');
    }

    protected function getDeleteForm(Job $job)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('job_delete', array('id' => $job->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }
}
