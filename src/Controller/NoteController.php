<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Beneficiary;
use App\Entity\Membership;
use App\Entity\Client;
use App\Entity\Note;
use App\Entity\Registration;
use App\Entity\Shift;
use App\Entity\TimeLog;
use App\Entity\User;
use App\Form\NoteType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/note')]
class NoteController extends AbstractController
{
    #[Route('/note/{id}/reply', name: 'note_reply', methods: ['POST'])]
    #[IsGranted('ROLE_USER_VIEWER')]
    public function noteReply(Request $request, Note $note, EntityManagerInterface $em): Response
    {
        $new_note = new Note();
        $new_note->setParent($note);
        $new_note->setAuthor($this->getUser());
        $new_note->setSubject($note->getSubject());

        $note_form = $this->createForm(NoteType::class, $new_note);
        $note_form->handleRequest($request);

        if ($note_form->isSubmitted() && $note_form->isValid()) {
            $em->persist($new_note);
            $em->flush();
            if ($new_note->getSubject()) {
                $this->addFlash('success', 'réponse enregistrée');
                return $this->redirectToShow($new_note->getSubject());
            }
            $this->addFlash('success', 'Post-it réponse enregistré');
        }
        return $this->redirectToRoute('user_office_tools');
    }

    #[Route('/note/{id}/edit', name: 'note_edit', methods: ['GET', 'POST'])]
    public function noteEdit(Request $request, Note $note, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('edit', $note);

        $note_form = $this->createForm(NoteType::class, $note);
        $note_form->handleRequest($request);

        if ($note_form->isSubmitted() && $note_form->isValid()) {
            $em->persist($note);
            $em->flush();
            if ($note->getSubject()) {
                $this->addFlash('success', 'note éditée');
                return $this->redirectToShow($note->getSubject());
            }
            $this->addFlash('success', 'Post-it édité');
        }
        return $this->redirectToRoute('user_office_tools');
    }

    #[Route('/note/{id}', name: 'note_delete', methods: ['DELETE'])]
    public function deleteNote(Request $request, Note $note, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('delete', $note);

        $form = $this->createNoteDeleteForm($note);
        $form->handleRequest($request);

        $member = $note->getSubject();

        if ($form->isSubmitted() && $form->isValid()) {
            $em->remove($note);
            $em->flush();
            $this->addFlash('success', 'la note a bien été supprimée');
        }

        if ($member) {
            return $this->redirectToShow($member);
        }
        return $this->redirectToRoute('user_office_tools');
    }

    private function createNoteDeleteForm(Note $note)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('note_delete', ['id' => $note->getId()]))
            ->setMethod('DELETE')
            ->getForm();
    }

    private function redirectToShow(Membership $member)
    {
        $user = $member->getMainBeneficiary()->getUser();
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('member_show', ['member_number' => $member->getMemberNumber()]);
        }
        
        $session = $this->container->get('request_stack')->getSession();
        return $this->redirectToRoute('member_show', [
            'member_number' => $member->getMemberNumber(),
            'token' => $user->getTmpToken($session->get('token_key') . $this->getUser()->getUserIdentifier())
        ]);
    }
}
