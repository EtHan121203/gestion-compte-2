<?php

namespace App\Controller;

use App\Entity\Client;
use App\Form\ClientType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/clients')]
class ClientController extends AbstractController
{
    #[Route('/', name: 'admin_clients', methods: ['GET'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function list(EntityManagerInterface $em): Response
    {
        $clients = $em->getRepository(Client::class)->findAll();
        return $this->render('admin/client/list.html.twig', ['clients' => $clients]);
    }

    #[Route('/client_new', name: 'client_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, $clientManager): Response
    {
        // $clientManager should be injected via type-hint if known, e.g., FOS\OAuthServerBundle\Model\ClientManagerInterface
        $form = $this->createForm(ClientType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $urls = $form->get('urls')->getData();
            $service = $form->get('service')->getData();

            $client = $clientManager->createClient();
            $client->setRedirectUris(explode(',', $urls));
            $client->setAllowedGrantTypes($form->get('grant_types')->getData());
            $client->setService($service);
            $clientManager->updateClient($client);

            $this->addFlash('success', 'Le client a bien été créé !');

            return $this->redirectToRoute('admin_clients');
        }
        
        return $this->render('admin/client/new.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/edit/{id}', name: 'client_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function edit(Request $request, Client $client, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ClientType::class);
        $form->get('urls')->setData($client->getUrls());
        $form->get('grant_types')->setData($client->getAllowedGrantTypes());
        $form->get('service')->setData($client->getService());

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $urls = $form->get('urls')->getData();
            $client->setRedirectUris(explode(',', $urls));
            $service = $form->get('service')->getData();
            $client->setService($service);
            $grant_types = $form->get('grant_types')->getData();
            $client->setAllowedGrantTypes($grant_types);

            $em->persist($client);
            $em->flush();

            $this->addFlash('success', 'Le client a bien été édité !');
            return $this->redirectToRoute('admin_clients');
        } elseif ($form->isSubmitted()) {
            foreach ($form->getErrors(true) as $key => $error) {
                $this->addFlash('error', 'Erreur ' . ($key + 1) . " : " . $error->getMessage());
            }
        }

        $delete_form = $this->createFormBuilder()
            ->setAction($this->generateUrl('client_remove', ['id' => $client->getId()]))
            ->setMethod('DELETE')
            ->getForm();

        return $this->render('admin/client/edit.html.twig', [
            'form' => $form->createView(),
            'delete_form' => $delete_form->createView()
        ]);
    }

    #[Route('/remove/{id}', name: 'client_remove', methods: ['DELETE'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function remove(Request $request, Client $client, EntityManagerInterface $em): Response
    {
        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('client_remove', ['id' => $client->getId()]))
            ->setMethod('DELETE')
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->remove($client);
            $em->flush();
            $this->addFlash('success', 'Le client a bien été supprimé !');
        }

        return $this->redirectToRoute('admin_clients');
    }
}
