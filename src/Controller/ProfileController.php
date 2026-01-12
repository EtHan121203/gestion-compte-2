<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use App\Entity\User;

class ProfileController extends AbstractController
{
    #[Route(path: '/profile', name: 'fos_user_profile_show')]
    public function showAction(): Response
    {
        $user = $this->getUser();
        if (!is_object($user) || !$user instanceof User) {
            throw new AccessDeniedException('This user does not have access to this section.');
        }

        return $this->render('bundles/FOSUserBundle/Profile/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route(path: '/profile/edit', name: 'fos_user_profile_edit')]
    public function editAction(): Response
    {
        $user = $this->getUser();
        if (!is_object($user) || !$user instanceof User) {
            throw new AccessDeniedException('This user does not have access to this section.');
        }

        return $this->render('bundles/FOSUserBundle/Profile/edit.html.twig', [
            'user' => $user,
        ]);
    }
}