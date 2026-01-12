<?php

namespace App\EventListener;

use App\Entity\Beneficiary;
use App\Entity\Membership;
use App\Entity\User;

namespace App\EventListener;

use App\Entity\User;
use App\Entity\Beneficiary;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;

class SetFirstPasswordListener{

    const  ROLE_PASSWORD_TO_SET  = 'ROLE_PASSWORD_TO_SET';

    private $em;
    private $token_storage;
    private $router;


    public function __construct(EntityManagerInterface $entity_manager, TokenStorageInterface $token_storage, RouterInterface $router)
    {
        $this->em = $entity_manager;
        $this->token_storage = $token_storage;
        $this->router = $router;
    }

    #[AsDoctrineListener(event: Events::prePersist)]
    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        // only for users created trough "Beneficiary" entity
        if (!$entity instanceof Beneficiary) {
            return;
        }
        $user = $entity->getUser();

        if ($user && !$user->getId()){
            $user->addRole(self::ROLE_PASSWORD_TO_SET);
        }
    }

    #[AsEventListener(event: 'app.password_changed')]
    public function onPasswordChanged($event): void
    {
        // Custom event or logic moved to controller
        // For now, let's keep it as a placeholder if we want to dispatch app.password_changed
        $user = $event->getUser();
        $user->removeRole(self::ROLE_PASSWORD_TO_SET);
        $this->em->persist($user);
        $this->em->flush();
    }

    #[AsEventListener(event: RequestEvent::class)]
    public function forcePasswordChange(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->token_storage->getToken();
        if ($token){
            $currentUser = $token->getUser();
            if($currentUser instanceof User){
                if($currentUser->hasRole(self::ROLE_PASSWORD_TO_SET)){
                    $route = $event->getRequest()->attributes->get('_route');
                    if ($route && $route != 'user_change_password' && $route != 'app_logout'){
                        $changePassword = $this->router->generate('user_change_password');
                        $event->setResponse(new RedirectResponse($changePassword));
                    }
                }
            }
        }
    }
}