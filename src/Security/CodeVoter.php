<?php
// src/AppBundle/Security/CodeVoter.php
namespace App\Security;

use App\Entity\Code;
use App\Entity\User;
use App\Service\ShiftService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class CodeVoter extends Voter
{
    const VIEW = 'view';
    const GENERATE = 'generate';
    const EDIT = 'edit';
    const DELETE = 'delete';
    const CLOSE = 'close';
    const OPEN = 'open';

    private $decisionManager;
    private $parameterBag;
    private $shiftService;
    private $requestStack;

    public function __construct(ParameterBagInterface $parameterBag, AccessDecisionManagerInterface $decisionManager, ShiftService $shiftService, RequestStack $requestStack)
    {
        $this->parameterBag = $parameterBag;
        $this->decisionManager = $decisionManager;
        $this->shiftService = $shiftService;
        $this->requestStack = $requestStack;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        // if the attribute isn't one we support, return false
        if (!in_array($attribute, array(self::GENERATE, self::EDIT, self::VIEW, self::DELETE, self::OPEN, self::CLOSE))) {
            return false;
        }

        // only vote on Code objects inside this voter
        if (!$subject instanceof Code) {
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

        // ROLE_SUPER_ADMIN can do anything! The power!
        if ($this->decisionManager->decide($token, array('ROLE_SUPER_ADMIN'))) {
            if ($attribute == self::GENERATE && !$this->parameterBag->get('code_generation_enabled')) { //do not generate if fixed code
                return false;
            }
            return true;
        }

        // you know $subject is a Code object, thanks to supports
        /** @var Code $code */
        $code = $subject;

        // you know $subject is a Post object, thanks to supports
        switch ($attribute) {
            case self::VIEW:
            case self::CLOSE:
                if ($this->decisionManager->decide($token, array('ROLE_ADMIN'))) {
                    return true;
                }
                return $this->canView($code, $user);
            case self::GENERATE:
                if (!$this->parameterBag->get('code_generation_enabled')) {
                    return false;
                }
                if ($this->decisionManager->decide($token, array('ROLE_ADMIN'))) {
                    return true;
                }
                return $this->canAdd($code, $user);
            case self::OPEN:
            case self::EDIT:
                if ($this->decisionManager->decide($token, array('ROLE_ADMIN'))) {
                    return true;
                }
            case self::DELETE:
                if ($this->decisionManager->decide($token, array('ROLE_SUPER_ADMIN'))) {
                    return true;
                }
                return $this->canDelete($code, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    private function canAdd(Code $code, User $user)
    {
        $now = new \DateTime('now');
        if ($this->canView($code, $user)) { //can add only if last code can be seen
            if ($code->getRegistrar() != $user || $code->getCreatedAt()->format('Y m d') != ($now->format('Y m d'))) { // on ne change pas son propre code
                if ($this->isLocationOk()) { // et si l'utilisateur est physiquement à l'épicerie
                    return true;
                }
            }
        }

        return false;
    }


    private function canView(Code $code, User $user)
    {
        if (!$code->getId())
            return false;

        if ($code->getRegistrar() === $user) { // my code
            return true;
        }

        if ($user->getBeneficiary()) {
            if ($this->shiftService->isBeginner($user->getBeneficiary())) { // not for beginner
                return false;
            }

            $start_after = new \DateTime('Yesterday');
            $start_after->setTime(23, 59, 59);
            $end_after = new \DateTime();
            $end_after->sub(new \DateInterval("PT2H")); //time - 120min TODO put in conf
            $start_before = new \DateTime();
            $start_before->add(new \DateInterval("PT1H")); //time + 60min TODO put in conf

            return $this->shiftService->isBeneficiaryHasShifts($user->getBeneficiary(),
                $start_after,
                $start_before,
                $end_after,
                true
            );
        }

        return false;
    }

    private function canDelete(Code $code, User $user)
    {
        return false;
    }

    //\App\Security\UserVoter::isLocationOk DUPLICATED
    private function isLocationOk()
    {
        $ip = $this->requestStack->getCurrentRequest()->getClientIp();
        $checkIps = $this->parameterBag->get('enable_place_local_ip_address_check');
        $ips = $this->parameterBag->get('place_local_ip_address');
        $ips = explode(',', $ips);
        return (isset($checkIps) and !$checkIps) or (isset($ip) and in_array($ip, $ips));
    }
}
