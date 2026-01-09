<?php

namespace App\Controller;

use App\Entity\User;
use Ornicar\GravatarBundle\GravatarApi;
use Ornicar\GravatarBundle\Templating\Helper\GravatarHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
class ApiController extends AbstractController
{
    /**
     * Helper method to get the user and check if they are active.
     */
    protected function getApiUser(TokenStorageInterface $tokenStorage): array
    {
        $user = $tokenStorage->getToken()?->getUser();
        
        if (!$user instanceof User) {
            return ['user' => false, 'message' => 'User not found'];
        }

        $beneficiary = $user->getBeneficiary();
        $withDrawn = false;
        if ($beneficiary) {
            $withDrawn = $beneficiary->getMembership()->isWithdrawn();
        }

        if ($withDrawn || !$user->isEnabled()) {
            return ['user' => false, 'message' => 'User not found'];
        }

        return ['user' => $user];
    }

    #[Route('/swipe/in', name: 'api_swipe_in', methods: ['POST'])]
    #[IsGranted('ROLE_OAUTH_LOGIN')]
    public function swipeIn(): JsonResponse
    {
        return new JsonResponse(['success' => true]);
    }

    #[Route('/oauth/user', name: 'api_user', methods: ['GET'])]
    #[IsGranted('ROLE_OAUTH_LOGIN')]
    public function user(TokenStorageInterface $tokenStorage, AuthorizationCheckerInterface $authorizationChecker): JsonResponse
    {
        if ($authorizationChecker->isGranted('ROLE_PREVIOUS_ADMIN')) {
            throw $this->createAccessDeniedException();
        }
        
        $response = $this->getApiUser($tokenStorage);
        if (!$response['user']) {
            return new JsonResponse($response);
        }
        
        return new JsonResponse([
            'user' => [
                'email' => $response['user']->getEmail(),
                'username' => $response['user']->getUserIdentifier(),
            ]
        ]);
    }

    #[Route('/oauth/nextcloud_user', name: 'api_nextcloud_user', methods: ['GET'])]
    #[IsGranted('ROLE_OAUTH_LOGIN')]
    public function nextcloudUser(TokenStorageInterface $tokenStorage, AuthorizationCheckerInterface $authorizationChecker): JsonResponse
    {
        if ($authorizationChecker->isGranted('ROLE_PREVIOUS_ADMIN')) {
            throw $this->createAccessDeniedException();
        }
        
        $response = $this->getApiUser($tokenStorage);
        if (!$response['user']) {
            return new JsonResponse($response);
        }
        
        $groups = array_map(
            function ($group) { return $group->getName(); },
            $response['user']->getGroups()->toArray()
        );
        
        return new JsonResponse([
            'email' => $response['user']->getEmail(),
            'displayName' => $response['user']->getFirstName() . ' ' . $response['user']->getLastName(),
            'identifier' => $response['user']->getUserIdentifier(),
            'groups' => $groups
        ]);
    }

    #[Route('/v4/user', name: 'api_gitlab_user', methods: ['GET'])]
    public function gitlabUser(TokenStorageInterface $tokenStorage, AuthorizationCheckerInterface $authorizationChecker): JsonResponse
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw $this->createAccessDeniedException();
        }
        if ($authorizationChecker->isGranted('ROLE_PREVIOUS_ADMIN')) {
            throw $this->createAccessDeniedException();
        }
        
        $response = $this->getApiUser($tokenStorage);
        if (!$response['user']) {
            return new JsonResponse($response);
        }
        
        /** @var User $current_app_user */
        $current_app_user = $response['user'];
        // Note: OrnicarGravatarBundle might need to be replaced by a modern equivalent or service injection
        $gravatar_helper = new GravatarHelper(new GravatarApi());
        
        return new JsonResponse([
            'id' => $current_app_user->getId(),
            'username' => $current_app_user->getUserIdentifier(),
            'email' => $current_app_user->getEmail(),
            'name' => $current_app_user->getFirstName() . ' ' . $current_app_user->getLastName(),
            'state' => ($current_app_user->isEnabled()) ? "active" : "",
            'avatar_url' => $gravatar_helper->getUrl($current_app_user->getEmail()),
            'web_url' => "",
            "created_at" => "2012-05-23T08:00:58Z",
            "bio" => '',
            "location" => null,
            "skype" => "",
            "linkedin" => "",
            "twitter" => "",
            "website_url" => "",
            "organization" => "",
            "last_sign_in_at" => "2012-06-01T11:41:01Z",
            "confirmed_at" => "2012-05-23T09:05:22Z",
            "theme_id" => 1,
            "last_activity_on" => "2012-05-23",
            "color_scheme_id" => 2,
            "projects_limit" => 100,
            "current_sign_in_at" => "2012-06-02T06:36:55Z",
            "identities" => [],
            "can_create_group" => true,
            "can_create_project" => true,
            "two_factor_enabled" => false,
            "external" => false
        ]);
    }
}
