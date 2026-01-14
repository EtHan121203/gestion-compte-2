<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\AnonymousBeneficiary;
use App\Entity\Beneficiary;
use App\Entity\Client;
use App\Entity\Membership;
use App\Entity\Note;
use App\Entity\Registration;
use App\Entity\Shift;
use App\Entity\TimeLog;
use App\Entity\User;
use App\Event\AnonymousBeneficiaryCreatedEvent;
use App\Event\BeneficiaryAddEvent;
use App\Event\MemberCreatedEvent;
use App\Form\AutocompleteBeneficiaryType;
use App\Form\BeneficiaryType;
use App\Form\MembershipType;
use App\Form\NoteType;
use App\Form\RegistrationType;
use App\Form\TimeLogType;
use App\Security\MembershipVoter;
use App\Service\MailerService;
use App\Service\MembershipService;
use App\Helper\SwipeCard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use DateTime;

#[Route('/member')]
class MembershipController extends AbstractController
{
    private function getCurrentAppUser(TokenStorageInterface $tokenStorage)
    {
        return $tokenStorage->getToken()?->getUser();
    }

    #[Route('/{member_number}/show', name: 'member_show', methods: ['GET'])]
    public function show(
        Membership $member,
        MembershipService $membershipService,
        EntityManagerInterface $em,
        AuthorizationCheckerInterface $authChecker,
        TokenStorageInterface $tokenStorage,
        ParameterBagInterface $params,
        Request $request
    ): Response {
        if ($member->getMemberNumber() <= 0) {
            return $this->redirectToRoute("homepage");
        }
        $this->denyAccessUnlessGranted('view', $member);

        $freezeForm = $this->createFreezeForm($member);
        $unfreezeForm = $this->createUnfreezeForm($member);
        $freezeChangeForm = $this->createFreezeChangeForm($member);
        $closeForm = $this->createCloseForm($member);
        $openForm = $this->createOpenForm($member);
        $deleteForm = $this->createDeleteForm($member);

        $note = new Note();
        $note_form = $this->createForm(NoteType::class, $note, array(
            'action' => $this->generateUrl('ambassador_new_note', array("member_number" => $member->getMemberNumber())),
            'method' => 'POST',
        ));
        $notes_form = array();
        $notes_delete_form = array();
        $new_notes_form = array();
        foreach ($member->getNotes() as $n) {
            $notes_form[$n->getId()] = $this->createForm(NoteType::class, $n, array('action' => $this->generateUrl('note_edit', array('id' => $n->getId()))))->createView();
            $notes_delete_form[$n->getId()] = $this->createNoteDeleteForm($n)->createView();

            $response_note = clone $note;
            $response_note->setParent($n);
            $response_note_form = $this->createForm(NoteType::class, $response_note,
                array('action' => $this->generateUrl('note_reply', array('id' => $n->getId()))));

            $new_notes_form[$n->getId()] = $response_note_form->createView();
        }
        $newReg = new Registration();
        $remainder = $membershipService->getRemainder($member);
        if (!$remainder->invert) { //still some days
            $expire = $membershipService->getExpire($member);
            $expire->modify('+1 day');
            $newReg->setDate($expire);
        } else { //register now !
            $newReg->setDate(new DateTime('now'));
        }
        $newReg->setRegistrar($this->getCurrentAppUser($tokenStorage));
        if ($authChecker->isGranted('ROLE_ADMIN')) {
            $action = $this->generateUrl('member_new_registration', array('member_number' => $member->getMemberNumber()));
        } else {
            $action = $this->generateUrl('member_new_registration', array('member_number' => $member->getMemberNumber(), 'token' => $member->getTmpToken($request->getSession()->get('token_key') . $this->getCurrentAppUser($tokenStorage)->getUserIdentifier())));
        }

        $registrationForm = $this->createForm(RegistrationType::class, $newReg, array('action' => $action));
        $registrationForm->add('is_new', HiddenType::class, array('attr' => array('value' => '1')));

        $detachBeneficiaryForms = array();
        $deleteBeneficiaryForms = array();
        foreach ($member->getBeneficiaries() as $beneficiary) {
            if (!$beneficiary->isMain()) {
                $detachBeneficiaryForms[$beneficiary->getId()] = $this->createFormBuilder()
                    ->setAction($this->generateUrl('beneficiary_detach', array('id' => $beneficiary->getId())))
                    ->setMethod('POST')->getForm()->createView();
            } else {
                $detachBeneficiaryForms[$beneficiary->getId()] = array();
            }
            if ($authChecker->isGranted('ROLE_ADMIN')) {
                $deleteBeneficiaryForms[$beneficiary->getId()] = $this->createFormBuilder()
                    ->setAction($this->generateUrl('beneficiary_delete', array('id' => $beneficiary->getId())))
                    ->setMethod('DELETE')->getForm()->createView();
            } else {
                $user = $member->getMainBeneficiary()->getUser();
                $deleteBeneficiaryForms[$beneficiary->getId()] = $this->createFormBuilder()
                    ->setAction($this->generateUrl('beneficiary_delete', array(
                        'id' => $beneficiary->getId(),
                        'token' => $user->getTmpToken($request->getSession()->get('token_key') . $this->getCurrentAppUser($tokenStorage)->getUserIdentifier())
                    )))
                    ->setMethod('DELETE')->getForm()->createView();
            }
        }

        $beneficiaryForm = $this->createNewBeneficiaryForm($member);
        $timeLogForm = $this->createNewTimeLogForm($member);

        $period_positions = $em->getRepository(Note::class)->findByBeneficiaries($member->getBeneficiaries()); // FIXME: check Repo
        $previous_cycle_start = $membershipService->getStartOfCycle($member, -1 * $params->get('max_nb_of_past_cycles_to_display'));
        $next_cycle_end = $membershipService->getEndOfCycle($member, 1);
        $shifts_by_cycle = $em->getRepository(Shift::class)->findShiftsByCycles($member, $previous_cycle_start, $next_cycle_end);
        $shifts_by_cycle = array_reverse($shifts_by_cycle, true); 
        $shiftFreeForms = [];
        $shiftValidateInvalidateForms = [];
        foreach ($shifts_by_cycle as $shifts) {
            foreach ($shifts as $shift) {
                $shiftFreeForms[$shift->getId()] = $this->createShiftFreeAdminForm($shift)->createView();
                $shiftValidateInvalidateForms[$shift->getId()] = $this->createShiftValidateInvalidateForm($shift)->createView();
            }
        }

        $in_progress_and_upcoming_shifts = $em->getRepository(Shift::class)->findInProgressAndUpcomingShiftsForMembership($member);

        return $this->render('member/show.html.twig', array(
            'member' => $member,
            'note' => $note,
            'note_form' => $note_form->createView(),
            'new_registration_form' => $registrationForm->createView(),
            'new_beneficiary_form' => $beneficiaryForm->createView(),
            'notes_form' => $notes_form,
            'notes_delete_form' => $notes_delete_form,
            'new_notes_form' => $new_notes_form,
            'detach_beneficiary_forms' => $detachBeneficiaryForms,
            'delete_beneficiary_forms' => $deleteBeneficiaryForms,
            'freeze_form' => $freezeForm->createView(),
            'unfreeze_form' => $unfreezeForm->createView(),
            'freeze_change_form' => $freezeChangeForm->createView(),
            'close_form' => $closeForm->createView(),
            'open_form' => $openForm->createView(),
            'delete_form' => $deleteForm->createView(),
            'time_log_form' => $timeLogForm->createView(),
            'period_positions' => $period_positions,
            'in_progress_and_upcoming_shifts' => $in_progress_and_upcoming_shifts,
            'shifts_by_cycle' => $shifts_by_cycle,
            'shift_free_forms' => $shiftFreeForms,
            'shift_validate_invalidate_forms' => $shiftValidateInvalidateForms,
        ));
    }

    private function createNewTimeLogForm(Membership $member)
    {
        $newTimeLogAction = $this->generateUrl('timelog_new', array('id' => $member->getId()));
        return $this->createForm(TimeLogType::class, new TimeLog(), array('action' => $newTimeLogAction));
    }

    #[Route('/newRegistration/{member_number}/', name: 'member_new_registration', methods: ['GET', 'POST'])]
    public function newRegistration(
        Request $request,
        Membership $member,
        MembershipService $membershipService,
        EntityManagerInterface $em,
        TokenStorageInterface $tokenStorage
    ): Response {
        $this->denyAccessUnlessGranted('edit', $member);
        $newReg = new Registration();
        $remainder = $membershipService->getRemainder($member);
        if (!$remainder->invert) {
            $expire = $membershipService->getExpire($member);
            $expire->modify('+1 day');
            $newReg->setDate($expire);
        } else {
            $newReg->setDate(new DateTime('now'));
        }
        $newReg->setRegistrar($this->getCurrentAppUser($tokenStorage));
        $registrationForm = $this->createForm(RegistrationType::class, $newReg);
        $registrationForm->add('is_new', HiddenType::class, array('attr' => array('value' => '1')));
        $registrationForm->handleRequest($request);
        if ($registrationForm->isSubmitted() && $registrationForm->isValid() && $registrationForm->get('is_new')->getData() != null) {
            $amount = floatval($registrationForm->get('amount')->getData());
            if ($amount <= 0) {
                $this->addFlash('error', 'Adhésion prix libre & non gratuit !');
                return $this->redirectToShow($member, $tokenStorage);
            }

            $currentUser = $this->getCurrentAppUser($tokenStorage);
            if ($currentUser->getBeneficiary() && $currentUser->getBeneficiary()->getMembership()->getId() == $member->getId()) {
                $this->addFlash('error', 'Tu ne peux pas enregistrer ta propre ré-adhésion, demande à un autre adhérent :)');
                return $this->redirectToShow($member, $tokenStorage);
            }
            $newReg->setRegistrar($currentUser);

            $date = $registrationForm->get('date')->getData();
            if ($membershipService->getExpire($member) >= $date) {
                $this->addFlash('warning', 'l\'adhésion précédente est encore valable à cette date !');
                return $this->redirectToShow($member, $tokenStorage);
            }
            $newReg->setMembership($member);
            $member->addRegistration($newReg);

            $em->persist($newReg);
            $em->flush();

            $this->addFlash('success', 'Enregistrement effectuée');
            return $this->redirectToShow($member, $tokenStorage);
        }

        $id = $request->request->get("registration_id");
        if ($id) {
            $registration = $em->getRepository(Registration::class)->find($id);
            if ($registration) {
                $form = $this->createForm(RegistrationType::class, $registration);
                $form->handleRequest($request);
                if ($form->isSubmitted() && $form->isValid()) {
                    $currentUser = $this->getCurrentAppUser($tokenStorage);
                    if ($currentUser->getBeneficiary() && $currentUser->getBeneficiary()->getMembership()->getId() == $member->getId()) {
                        $this->addFlash('error', 'Tu ne peux pas modifier tes propres adhésions :)');
                        return $this->redirectToShow($member, $tokenStorage);
                    }
                    $em->persist($registration);
                    $em->flush();
                    $this->addFlash('success', 'Mise à jour effectuée');
                    return $this->redirectToShow($member, $tokenStorage);
                }
            }
        }

        if ($member->isWithdrawn())
            $this->addFlash('warning', 'Ce compte est fermé');

        return $this->redirectToShow($member, $tokenStorage);
    }

    #[Route('/newBeneficiary/{member_number}/', name: 'member_new_beneficiary', methods: ['GET', 'POST'])]
    public function newBeneficiary(
        Request $request,
        Membership $member,
        ValidatorInterface $validator,
        EventDispatcherInterface $eventDispatcher,
        EntityManagerInterface $em,
        ParameterBagInterface $params,
        TokenStorageInterface $tokenStorage
    ): Response {
        $this->denyAccessUnlessGranted(MembershipVoter::BENEFICIARY_ADD, $member);

        //check if member can host
        // FIXME: BeneficiaryCanHost constraint needs migration too if missing
        /* $violations = $validator->validate($member->getMainBeneficiary(), new BeneficiaryCanHost());
        if (0 !== count($violations)) {
            foreach ($violations as $violation) {
                $this->addFlash('error',$violation->getMessage());
            }
            $this->addFlash('warning','Veuillez réaliser une nouvelle adhésion');
            return $this->redirectToShow($member, $tokenStorage);
        } */

        $beneficiaryForm = $this->createNewBeneficiaryForm($member);
        $beneficiaryForm->handleRequest($request);
        if ($beneficiaryForm->isSubmitted() && $beneficiaryForm->isValid()) {
            $beneficiary = $beneficiaryForm->getData();

            if (count($member->getBeneficiaries()) <= $params->get('maximum_nb_of_beneficiaries_in_membership')) {
                $beneficiary->setMembership($member);
                $member->addBeneficiary($beneficiary);
                $em->persist($beneficiary);
                $em->flush();

                $eventDispatcher->dispatch(new BeneficiaryAddEvent($beneficiary), BeneficiaryAddEvent::NAME);

                $this->addFlash('success', 'Beneficiaire ajouté');
            } else {
                $this->addFlash('error', 'Maximum ' . ($params->get('maximum_nb_of_beneficiaries_in_membership')) . ' beneficiaires enregistrés');
            }
            return $this->redirectToShow($member, $tokenStorage);
        } elseif ($beneficiaryForm->isSubmitted()) {
            foreach ($beneficiaryForm->getErrors(true) as $key => $error) {
                $this->addFlash('error', 'Erreur ' . ($key + 1) . " : " . $error->getMessage());
            }
        }

        return $this->redirectToShow($member, $tokenStorage);
    }

    #[Route('/edit', name: 'member_edit_firewall', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER_VIEWER')]
    public function editFirewall(Request $request, EntityManagerInterface $em, TokenStorageInterface $tokenStorage): Response
    {
        if ($this->isGranted('ROLE_USER_VIEWER')) {
            $form = $this->createFormBuilder()
                ->add('member_number', IntegerType::class, array('label' => 'Numéro d\'adhérent'))
                ->add('username', HiddenType::class, array('attr' => array('value' => '')))
                ->add('email', HiddenType::class, array('label' => 'email'))
                ->add('edit', SubmitType::class, array('label' => 'Editer', 'attr' => array('class' => 'btn')))
                ->getForm();
        } else {
            $form = $this->createFormBuilder()
                ->add('member_number', IntegerType::class, array('label' => 'Numéro d\'adhérent'))
                ->add('username', HiddenType::class, array('attr' => array('value' => '')))
                ->add('email', EmailType::class, array('label' => 'email'))
                ->add('edit', SubmitType::class, array('label' => 'Editer', 'attr' => array('class' => 'btn')))
                ->getForm();
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $member_number = $form->get('member_number')->getData();
            $username = $form->get('username')->getData();
            $email = $form->get('email')->getData();

            $member = null;
            if ($username)
                $member = $em->getRepository(User::class)->findOneBy(array('username' => $username));
            else if ($member_number) {
                $member = $em->getRepository(Membership::class)->findOneBy(array('member_number' => $member_number));
            }

            if ($member && ($this->isGranted('view', $member))) {
                $request->getSession()->set('token_key', uniqid());
                return $this->redirectToShow($member, $tokenStorage);
            }

            if ($email)
                $this->addFlash('error', 'cet email n\'est pas associé à ce numéro');
            if (!$member)
                $this->addFlash('error', 'membre non trouvé');
        }

        return $this->render('user/edit_firewall.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    #[Route('/{id}/set_email', name: 'set_email', methods: ['POST'])]
    public function setEmail(Beneficiary $beneficiary, Request $request, MailerService $mailerService, EntityManagerInterface $em): Response
    {
        $email = $request->request->get('email');
        $user = $beneficiary->getUser();
        $oldEmail = $user->getEmail();

        if ($mailerService->isTemporaryEmail($oldEmail) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $user->setEmail($email);
            $em->persist($user);
            $em->flush();
            $this->addFlash('success', 'Merci ! votre email a bien été enregistré');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('warning', 'Oups, le format du courriel entré semble problématique');
        }
        return $this->render('beneficiary/confirm.html.twig', array(
            'beneficiary' => $beneficiary,
        ));
    }

    #[Route('/help_find_user', name: 'find_user_help')]
    public function findUserHelp(): Response
    {
        return $this->render('beneficiary/find_member_number.html.twig', [
            'form' => null,
            'beneficiaries' => null,
            'return_path' => null,
            'routeParam' => null,
            'params' => null,
        ]);
    }

    #[Route('/find_me', name: 'find_me')]
    public function activeUserAccount(Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createFormBuilder()
            ->add('member_number', IntegerType::class, array('label' => 'Numéro d\'adhérent', 'attr' => array(
                'placeholder' => '0',
            )))
            ->add('find', SubmitType::class, array('label' => 'Activer mon compte'))
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $member_number = $form->get('member_number')->getData();
            $ms = $em->getRepository(Membership::class)->findOneBy(array('member_number' => $member_number));

            if (!$ms){
                $this->addFlash('warning', 'Oups, aucun membre trouvé avec ce numéro d\'adhérent');
                return $this->render('user/tools/find_me.html.twig', array(
                    'form' => $form->createView(),
                ));
            }

            return $this->render('beneficiary/confirm.html.twig', array(
                'beneficiary' => $ms->getMainBeneficiary(),
            ));
        }
        return $this->render('user/tools/find_me.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    #[Route('/{id}/close', name: 'member_close', methods: ['POST'])]
    public function close(Request $request, Membership $member, EntityManagerInterface $em, TokenStorageInterface $tokenStorage): Response
    {
        $this->denyAccessUnlessGranted('close', $member);
        $currentUser = $this->getCurrentAppUser($tokenStorage);

        $form = $this->createCloseForm($member);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $member->setWithdrawn(true);
            $member->setWithdrawnDate(new \DateTime('now'));
            $member->setWithdrawnBy($currentUser);
            $em->persist($member);
            $em->flush();
            $this->addFlash('success', 'Compte fermé !');
        }
        return $this->redirectToShow($member, $tokenStorage);
    }

    #[Route('/{id}/open', name: 'member_open', methods: ['POST'])]
    public function open(Request $request, Membership $member, EntityManagerInterface $em, TokenStorageInterface $tokenStorage): Response
    {
        $this->denyAccessUnlessGranted('open', $member);

        $form = $this->createOpenForm($member);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $member->setWithdrawn(false);
            $em->persist($member);
            $em->flush();
            $this->addFlash('success', 'Compte ré-ouvert !');
        }
        return $this->redirectToShow($member, $tokenStorage);
    }

    #[Route('/{id}/freeze', name: 'member_freeze', methods: ['POST'])]
    public function freeze(Request $request, Membership $member, EntityManagerInterface $em, TokenStorageInterface $tokenStorage): Response
    {
        $this->denyAccessUnlessGranted('freeze', $member);

        $form = $this->createFreezeForm($member);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $member->setFrozen(true);
            $member->setFrozenChange(false);
            $em->persist($member);
            $em->flush();
            $this->addFlash('success', 'Compte gelé !');
        }
        return $this->redirectToShow($member, $tokenStorage);
    }

    #[Route('/{id}/unfreeze', name: 'member_unfreeze', methods: ['POST'])]
    public function unfreeze(Request $request, Membership $member, EntityManagerInterface $em, TokenStorageInterface $tokenStorage): Response
    {
        $this->denyAccessUnlessGranted('freeze', $member);

        $form = $this->createUnfreezeForm($member);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $member->setFrozen(false);
            $member->setFrozenChange(false);
            $em->persist($member);
            $em->flush();
            $this->addFlash('success', 'Compte dégelé !');
        }
        return $this->redirectToShow($member, $tokenStorage);
    }

    #[Route('/{id}/freeze_change', name: 'member_freeze_change', methods: ['POST'])]
    public function freezeChange(Request $request, Membership $member, EntityManagerInterface $em, TokenStorageInterface $tokenStorage): Response
    {
        $this->denyAccessUnlessGranted('freeze_change', $member);

        $form = $this->createFreezeChangeForm($member);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $member->setFrozenChange(!$member->getFrozenChange());
            $em->persist($member);
            $em->flush();

            if ($member->isFrozen()) {
                if ($member->getFrozenChange()) {
                    $this->addFlash('success', 'Le compte sera dégelé à la fin du cycle !');
                } else {
                    $this->addFlash('success', 'La demande de dégel a été annulée !');
                }
            } else {
                if ($member->getFrozenChange()) {
                    $this->addFlash('success', 'Le compte sera gelé à la fin du cycle !');
                } else {
                    $this->addFlash('success', 'La demande de gel a été annulée !');
                }
            }
        }

        $currentUser = $this->getCurrentAppUser($tokenStorage);
        if ($currentUser->getBeneficiary() && $member === $currentUser->getBeneficiary()->getMembership()) {
            return $this->redirectToRoute("fos_user_profile_show");
        } else {
            return $this->redirectToShow($member, $tokenStorage);
        }
    }

    #[Route('/{id}', name: 'member_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function deleteAction(Request $request, Membership $member, EntityManagerInterface $em): Response
    {
        $form = $this->createDeleteForm($member);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->remove($member);
            $em->flush();
            $this->addFlash('success', "Le membre a bien été supprimé !");
        }
        return $this->redirectToRoute('user_index');
    }

    #[Route('/new', name: 'member_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, SwipeCard $swipeCard, TokenStorageInterface $tokenStorage, EventDispatcherInterface $eventDispatcher): Response
    {
        $code = $request->query->get('code');
        $a_beneficiary = null;
        if ($code) {
            $email = $swipeCard->vigenereDecode($code);
            if ($email) {
                $a_beneficiary = $em->getRepository(AnonymousBeneficiary::class)->findOneBy(array('email'=>$email));
            }
            if (!$a_beneficiary) {
                $this->addFlash('error', 'Cette url n\'est plus valide');
                return $this->redirectToRoute("homepage");
            } else {
                if ($a_beneficiary->getJoinTo()) {
                    return $this->redirectToRoute('member_add_beneficiary', array('code' => $swipeCard->vigenereEncode($email)));
                }
            }
        }

        if (!$a_beneficiary) {
            $this->denyAccessUnlessGranted('create', $this->getCurrentAppUser($tokenStorage));
        }

        $member = new Membership();
        if ($a_beneficiary) {
            $user = new User();
            $user->setEmail($a_beneficiary->getEmail());
            $beneficiary = new Beneficiary();
            $beneficiary->setUser($user);
            $beneficiary->setFlying(false);
            $member->setMainBeneficiary($beneficiary);
        }

        $m = $em->getRepository(Membership::class)->findOneBy(array(), array('member_number' => 'DESC'));
        $mm = 1;
        if ($m)
            $mm = $m->getMemberNumber() + 1;
        $member->setMemberNumber($mm);

        $registration = new Registration();
        if ($a_beneficiary) {
            $registration->setDate($a_beneficiary->getCreatedAt());
            $registration->setRegistrar($a_beneficiary->getRegistrar());
            $registration->setAmount($a_beneficiary->getAmount());
            $registration->setMode($a_beneficiary->getMode());
            if ($a_beneficiary->getMode()===Registration::TYPE_HELLOASSO) {
                $registration->setAmount('--');
            }
        } else {
            $registration->setDate(new DateTime('now'));
            $registration->setRegistrar($this->getUser());
        }
        $registration->setMembership($member);
        $member->addRegistration($registration);

        $form = $this->createForm(MembershipType::class, $member);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$a_beneficiary) {
                if (!$member->getLastRegistration()->getRegistrar())
                    $member->getLastRegistration()->setRegistrar($this->getUser());
            } else if ($a_beneficiary->getMode() === Registration::TYPE_HELLOASSO) {
                $member->removeRegistration($registration);
            }

            $member->setWithdrawn(false);
            $member->setFrozen(false);
            $member->setFrozenChange(false);

            $em->persist($member);
            if ($a_beneficiary) {
                $beneficiaries_emails = $a_beneficiary->getBeneficiariesEmailsAsArray();
                foreach ($beneficiaries_emails as $email){
                    $new_anonymous_beneficiary = new AnonymousBeneficiary();
                    $new_anonymous_beneficiary->setCreatedAtValue(new \DateTime());
                    $new_anonymous_beneficiary->setEmail($email);
                    $new_anonymous_beneficiary->setJoinTo($member->getMainBeneficiary());
                    $new_anonymous_beneficiary->setRegistrar($a_beneficiary->getRegistrar());
                    $em->persist($new_anonymous_beneficiary);
                    $eventDispatcher->dispatch(new AnonymousBeneficiaryCreatedEvent($new_anonymous_beneficiary), AnonymousBeneficiaryCreatedEvent::NAME);
                }
                $em->remove($a_beneficiary);
            }
            $em->flush();

            $eventDispatcher->dispatch(new MemberCreatedEvent($member), MemberCreatedEvent::NAME);

            if (!$this->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
                $this->addFlash('success', 'Merci '.$member->getMainBeneficiary()->getFirstname().' ! Ton adhésion est maintenant finalisée. Verifie tes emails pour te connecter.');
                return $this->redirectToRoute('homepage');
            } else {
                $this->addFlash('success', 'La nouvelle adhésion a bien été prise en compte !');
            }
            return $this->redirectToShow($member, $tokenStorage);
        } elseif ($form->isSubmitted()) {
            foreach ($form->getErrors(true) as $key => $error) {
                $this->addFlash('error', 'Erreur ' . ($key + 1) . " : " . $error->getMessage());
            }
        }

        return $this->render('member/new.html.twig', array(
            'member' => $member,
            'form' => $form->createView(),
        ));
    }

    #[Route('/add_beneficiary', name: 'member_add_beneficiary', methods: ['GET', 'POST'])]
    public function addBeneficiary(Request $request, EntityManagerInterface $em, SwipeCardHelper $swipeCard, EventDispatcherInterface $eventDispatcher): Response
    {
        $code = $request->query->get('code');
        $a_beneficiary = null;
        if ($code) {
            $email = $swipeCard->vigenereDecode($code);
            if ($email) {
                $a_beneficiary = $em->getRepository(AnonymousBeneficiary::class)->findOneBy(array('email' => $email));
            }
            if (!$a_beneficiary) {
                $this->addFlash('error', 'Cette url n\'est plus valide');
                return $this->redirectToRoute('homepage');
            }
        }

        if (!$a_beneficiary) {
            throw $this->createAccessDeniedException('Tu cherches ?');
        }
        if (!$a_beneficiary->getJoinTo()){
            $this->addFlash('error','destination non trouvé');
            return $this->redirectToRoute('homepage');
        }
        $member = $a_beneficiary->getJoinTo()->getMembership();

        $form = $this->createFormBuilder()
            ->add('beneficiary', BeneficiaryType::class)
            ->getForm();

        $beneficiary = new Beneficiary();
        $beneficiary->setUser(new User());
        $beneficiary->setFlying(false);
        $beneficiary->setEmail($a_beneficiary->getEmail());
        $form->get('beneficiary')->setData($beneficiary);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $beneficiary = $form->get('beneficiary')->getData();
            $beneficiary->setMembership($member);
            $em->persist($beneficiary);
            $em->remove($a_beneficiary);
            $em->flush();

            $eventDispatcher->dispatch(new BeneficiaryAddEvent($beneficiary), BeneficiaryAddEvent::NAME);

            $this->addFlash('success', 'Merci ' . $beneficiary->getFirstname() . ' ! Ton adhésion est maintenant finalisée');
            return $this->redirectToRoute('member_show', array('member_number' => $member->getMemberNumber()));

        } elseif ($form->isSubmitted()) {
            foreach ($form->getErrors(true) as $key => $error) {
                $this->addFlash('error', 'Erreur ' . ($key + 1) . " : " . $error->getMessage());
            }
        }

        return $this->render('member/add_beneficiary.html.twig', array(
            'member' => $member,
            'form' => $form->createView(),
        ));
    }

    #[Route('/join', name: 'member_join', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function join(Request $request, EntityManagerInterface $em, ParameterBagInterface $params, TokenStorageInterface $tokenStorage): Response
    {
        $form = $this->createFormBuilder()
            ->add('from_text', AutocompleteBeneficiaryType::class, array(
                'label' => 'Adhérent a joindre',
                'block_prefix' => 'autocomplete_beneficiary_from'
            ))
            ->add('dest_text', AutocompleteBeneficiaryType::class, array(
                'label' => 'au compte de l\'adhérent',
                'block_prefix' => 'autocomplete_beneficiary_dest'
            ))
            ->add('join', SubmitType::class, array('label' => 'Joindre les deux comptes', 'attr' => array('class' => 'btn')))
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $fromMember = $form->get('from_text')->getData()->getMembership();
            $destMember = $form->get('dest_text')->getData()->getMembership();
            $maxBeneficiaries = $params->get('maximum_nb_of_beneficiaries_in_membership');
            if ($fromMember == $destMember) {
                $this->addFlash('error', 'Impossible de joindre deux comptes identiques.');
            } else if ($fromMember->getBeneficiaries()->count() >= $maxBeneficiaries) {
                $this->addFlash('error', 'Le compte à lier a déjà le nombre maximum de bénéficiaires.');
            }else if ($destMember->getBeneficiaries()->count() >= $maxBeneficiaries) {
                $this->addFlash('error', 'Le compte de destination a déjà le nombre maximum de bénéficiaires.');
            } else if ($fromMember->getBeneficiaries()->count() + $destMember->getBeneficiaries()->count() > $maxBeneficiaries) {
                $this->addFlash('error', 'La somme des bénéficiaires du compte à lier dépasse le nombre maximum.');
            } else {
                foreach ($fromMember->getBeneficiaries() as $beneficiary) {
                    $destMember->addBeneficiary($beneficiary);
                    $fromMember->removeBeneficiary($beneficiary);
                    $beneficiary->setMembership($destMember);
                    $em->persist($beneficiary);
                }
                $em->persist($destMember);
                $em->flush();
                $fromMember->setMainBeneficiary(null);
                $em->remove($fromMember);
                $em->flush();

                $this->addFlash('success', 'Les deux comptes adhérents ont bien été fusionnés !');
                return $this->redirectToShow($destMember, $tokenStorage);
            }
        }
        return $this->render('admin/member/join.html.twig', array('form' => $form->createView()));
    }

    #[Route('/office_tools', name: 'user_office_tools', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER_VIEWER')]
    public function officeTools(Request $request, EntityManagerInterface $em, TokenStorageInterface $tokenStorage): Response
    {
        $note = new Note();
        $note->setAuthor($this->getCurrentAppUser($tokenStorage));
        $note_form = $this->createForm(NoteType::class, $note);
        $note_form->handleRequest($request);

        if ($note_form->isSubmitted()) {
            if ($note_form->isValid()) {
                $existing_note = $em->getRepository(Note::class)->findOneBy(array("subject" => null, "author" => $this->getCurrentAppUser($tokenStorage), "text" => $note->getText()));
                if ($existing_note) {
                    $this->addFlash('error', 'Ce post-it existe déjà');
                } else {
                    $em->persist($note);
                    $em->flush();
                    $this->addFlash('success', 'Post-it ajouté');
                }
            } else {
                $this->addFlash('error', 'Impossible d\'ajouter le post-it');
            }
        }

        $notes = $em->getRepository(Note::class)->findBy(array("subject" => null));
        $notes_form = array();
        $notes_delete_form = array();
        $new_notes_form = array();
        foreach ($notes as $n) {
            $notes_form[$n->getId()] = $this->createForm(NoteType::class, $n, array('action' => $this->generateUrl('note_edit', array('id' => $n->getId()))))->createView();
            $notes_delete_form[$n->getId()] = $this->createNoteDeleteForm($n)->createView();

            $response_note = clone $note;
            $response_note->setParent($n);
            $response_note_form = $this->createForm(NoteType::class, $response_note,
                array('action' => $this->generateUrl('note_reply', array('id' => $n->getId()))));

            $new_notes_form[$n->getId()] = $response_note_form->createView();
        }

        return $this->render('default/tools/office_tools.html.twig', array(
            'note_form' => $note_form->createView(),
            'notes_form' => $notes_form,
            'notes_delete_form' => $notes_delete_form,
            'new_notes_form' => $new_notes_form,
            'notes' => $notes
        ));
    }

    #[Route('/emails_csv', name: 'admin_emails_csv', methods: ['GET'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function exportEmails(EntityManagerInterface $em, MailerService $mailerService): Response
    {
        $beneficiaries = $em->getRepository(Beneficiary::class)->findAll();
        $return = '';
        if ($beneficiaries) {
            $d = ',';
            foreach ($beneficiaries as $beneficiary) {
                if (!$beneficiary->getMembership()->isWithdrawn()) {
                    if (!$mailerService->isTemporaryEmail($beneficiary->getEmail()) && filter_var($beneficiary->getEmail(), FILTER_VALIDATE_EMAIL)) {
                        $return .= $beneficiary->getFirstname() . $d . $beneficiary->getLastname() . $d . $beneficiary->getEmail() . "\n";
                    }
                }
            }
        }
        return new Response($return, 200, array(
            'Content-Encoding: UTF-8',
            'Content-Type' => 'application/force-download; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="emails_' . date('dmyhis') . '.csv"'
        ));
    }

    public function homepageFreezeAction(TokenStorageInterface $tokenStorage): Response
    {
        $member = $this->getCurrentAppUser($tokenStorage)->getBeneficiary()->getMembership();
        $freezeChangeForm = $this->createFreezeChangeForm($member);

        return $this->render('member/_partial/frozen.html.twig', array(
            'member' => $member,
            'freeze_change_form' => $freezeChangeForm->createView(),
        ));
    }

    private function createNewBeneficiaryForm(Membership $member)
    {
        $newBeneficiaryAction = $this->generateUrl('member_new_beneficiary', array('member_number' => $member->getMemberNumber()));
        return $this->createForm(BeneficiaryType::class, new Beneficiary(), array('action' => $newBeneficiaryAction));
    }

    private function createFreezeForm(Membership $member)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('member_freeze', array('id' => $member->getId())))
            ->setMethod('POST')
            ->getForm();
    }

    private function createUnfreezeForm(Membership $member)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('member_unfreeze', array('id' => $member->getId())))
            ->setMethod('POST')
            ->getForm();
    }

    private function createFreezeChangeForm(Membership $member)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('member_freeze_change', array('id' => $member->getId())))
            ->setMethod('POST')
            ->getForm();
    }

    private function createCloseForm(Membership $member)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('member_close', array('id' => $member->getId())))
            ->setMethod('POST')
            ->getForm();
    }

    private function createOpenForm(Membership $member)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('member_open', array('id' => $member->getId())))
            ->setMethod('POST')
            ->getForm();
    }

    private function createDeleteForm(Membership $member)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('member_delete', array('id' => $member->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }

    private function createNoteDeleteForm(Note $note)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('note_delete', array('id' => $note->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }

    private function redirectToShow(Membership $member, TokenStorageInterface $tokenStorage)
    {
        if (!$this->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return $this->redirectToRoute('homepage');
        }
        if ($this->isGranted('ROLE_USER_MANAGER'))
            return $this->redirectToRoute('member_show', array('member_number' => $member->getMemberNumber()));
        else
            return $this->redirectToRoute('member_show', array('member_number' => $member->getMemberNumber(), 'token' => $member->getTmpToken($this->getRequest()->getSession()->get('token_key') . $this->getCurrentAppUser($tokenStorage)->getUserIdentifier())));
    }

    private function createShiftFreeAdminForm(Shift $shift)
    {
        return $this->container->get('form.factory')->createNamedBuilder('shift_free_forms_' . $shift->getId())
            ->setAction($this->generateUrl('shift_free_admin', array('id' => $shift->getId())))
            ->add('reason', TextareaType::class, array('required' => false, 'label' => 'Justification éventuelle', 'attr' => array('class' => 'materialize-textarea')))
            ->setMethod('POST')
            ->getForm();
    }

    private function createShiftValidateInvalidateForm(Shift $shift)
    {
        return $this->container->get('form.factory')->createNamedBuilder('shift_validate_invalidate_forms_' . $shift->getId())
            ->setAction($this->generateUrl('shift_validate', array('id' => $shift->getId())))
            ->add('validate', HiddenType::class, [
                'data' => ($shift->getWasCarriedOut() ? 0 : 1),
            ])
            ->setMethod('POST')
            ->getForm();
    }
}
