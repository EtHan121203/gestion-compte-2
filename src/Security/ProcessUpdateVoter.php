<?php

namespace App\Security;

use App\Entity\EmailTemplate;
use App\Entity\ProcessUpdate;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ProcessUpdateVoter extends Voter
{
    const VIEW = 'view';
    const EDIT = 'edit';
    const DELETE = 'delete';

    private $decisionManager;

    public function __construct(AccessDecisionManagerInterface $decisionManager)
    {
        $this->decisionManager = $decisionManager;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        // if the attribute isn't one we support, return false
        if (!in_array($attribute, array(self::EDIT, self::VIEW, self::DELETE))) {
            return false;
        }

        // only vote on Task objects inside this voter
        if (!$subject instanceof ProcessUpdate) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            // the user must be logged in; if not, deny access
            return false;
        }

        return $this->decisionManager->decide($token, array('ROLE_PROCESS_MANAGER'));
    }

}