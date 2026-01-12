<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ResettingController extends AbstractController
{
    #[Route(path: '/resetting/request', name: 'fos_user_resetting_request')]
    public function requestAction(): Response
    {
        return $this->render('bundles/FOSUserBundle/Resetting/request.html.twig');
    }

    #[Route(path: '/resetting/send-email', name: 'fos_user_resetting_send_email')]
    public function sendEmailAction(): Response
    {
        // TODO: Implement email sending logic
        return $this->render('bundles/FOSUserBundle/Resetting/check_email.html.twig');
    }

    #[Route(path: '/resetting/reset/{token}', name: 'fos_user_resetting_reset')]
    public function resetAction(string $token): Response
    {
        // TODO: Implement password reset logic
        return $this->render('bundles/FOSUserBundle/Resetting/reset.html.twig', [
            'token' => $token,
        ]);
    }
}