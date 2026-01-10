<?php

namespace App\Controller;

use App\Entity\Membership;
use App\Entity\TimeLog;
use App\Form\TimeLogType;
use App\Service\TimeLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/time_log')]
class TimeLogController extends AbstractController
{
    #[Route('/{id}/timelog_delete/{timelog_id}', name: 'member_timelog_delete', methods: ['GET'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function timelogDeleteAction(Request , Membership , $timelog_id, EntityManagerInterface $em): RedirectResponse
    {
        $session = $request->getSession();
        $timeLog = $em->getRepository(TimeLog::class)->find($timelog_id);

        if (!$timeLog) {
            $session->getFlashBag()->add('error', 'Time log introuvable');
            return $this->redirectToShow($member, $request);
        }

        if ($timeLog->getMembership() === $member) {
            $em->remove($timeLog);
            $em->flush();
            $session->getFlashBag()->add('success', 'Time log supprimé');
        } else {
            $session->getFlashBag()->add('error', $timeLog->getMembership() . '<>' . $member);
            $session->getFlashBag()->add('error', $timeLog->getId());
        }
        return $this->redirectToShow($member, $request);
    }

    #[Route('/{id}/new', name: 'timelog_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_SHIFT_MANAGER')]
    public function newAction(Request $request, Membership $member, TimeLogService $timeLogService, EntityManagerInterface $em): RedirectResponse
    {
        $session = $request->getSession();
        $timeLog = $timeLogService->initCustomTimeLog($member);

        $form = $this->createForm(TimeLogType::class, $timeLog);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $timeLog->setTime($form->get('time')->getData());
            $timeLog->setDescription($form->get('description')->getData());

            $em->persist($timeLog);
            $em->flush();

            $session->getFlashBag()->add('success', 'Le log de temps a bien été créé !');
            return $this->redirectToShow($member, $request);
        }

        return $this->redirectToShow($member, $request);
    }

    private function redirectToShow(Membership $member, Request $request): RedirectResponse
    {
        if (!$this->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return $this->redirectToRoute('homepage');
        }
        $session = $request->getSession();
        if ($this->isGranted('ROLE_USER_MANAGER')) {
            return $this->redirectToRoute('member_show', ['member_number' => $member->getMemberNumber()]);
        } else {
            $username = $this->getUser()->getUserIdentifier();
            return $this->redirectToRoute('member_show', [
                'member_number' => $member->getMemberNumber(),
                'token' => $member->getTmpToken($session->get('token_key') . $username)
            ]);
        }
    }
}
