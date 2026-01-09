<?php

namespace App\Service;

use App\Entity\ShiftFreeLog;
use App\Entity\Beneficiary;
use App\Entity\Shift;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class ShiftFreeLogService
{
    protected $em;
    protected $requestStack;
    private $tokenStorage;

    public function __construct(EntityManagerInterface $em, RequestStack $requestStack, TokenStorage $tokenStorage)
    {
        $this->em = $em;
        $this->requestStack = $requestStack;
        $this->tokenStorage = $tokenStorage;
    }

    public function initShiftFreeLog(Shift $shift, Beneficiary $beneficiary, $fixe = false, $reason = null)
    {
        $current_user = $this->tokenStorage->getToken() ? $this->tokenStorage->getToken()->getUser() : null;
        $request = $this->requestStack->getCurrentRequest();

        $log = new ShiftFreeLog;
        $log->setShift($shift);
        $log->setBeneficiary($beneficiary);
        $log->setFixe($fixe);
        if ($reason) {
            $log->setReason($reason);
        }
        if (is_object($current_user)) {
            $log->setCreatedBy($current_user);
        }
        $log->setRequestRoute($request->get('_route'));

        return $log;
    }
}
