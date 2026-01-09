<?php

namespace App\Controller;

use App\Entity\Beneficiary;
use App\Entity\Event;
use App\Entity\Proxy;
use App\Form\EventType;
use App\Form\ProxyType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/event')]
class EventController extends AbstractController
{
    #[Route('/', name: 'event_list', methods: ['GET'])]
    #[IsGranted('ROLE_PROCESS_MANAGER')]
    public function list(EntityManagerInterface $em): Response
    {
        $events = $em->getRepository(Event::class)->findAll();

        return $this->render('admin/event/list.html.twig', [
            'events' => $events,
        ]);
    }

    #[Route('/proxies_list', name: 'proxies_list', methods: ['GET'])]
    #[IsGranted('ROLE_PROCESS_MANAGER')]
    public function listProxies(EntityManagerInterface $em): Response
    {
        $proxies = $em->getRepository(Proxy::class)->findAll();
        $delete_forms = [];
        foreach ($proxies as $proxy) {
            $delete_forms[$proxy->getId()] = $this->getProxyDeleteForm($proxy)->createView();
        }

        return $this->render('admin/event/proxy/list.html.twig', [
            'proxies' => $proxies,
            'delete_forms' => $delete_forms,
            'event' => null,
        ]);
    }

    #[Route('/{id}/proxies_list', name: 'event_proxies_list', methods: ['GET'])]
    #[IsGranted('ROLE_PROCESS_MANAGER')]
    public function listEventProxies(Event $event): Response
    {
        $proxies = $event->getProxies();
        $delete_forms = [];
        foreach ($proxies as $proxy) {
            $delete_forms[$proxy->getId()] = $this->getProxyDeleteForm($proxy)->createView();
        }

        return $this->render('admin/event/proxy/list.html.twig', [
            'proxies' => $proxies,
            'delete_forms' => $delete_forms,
            'event' => $event,
        ]);
    }

    #[Route('/new', name: 'event_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $event = new Event();
        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($event);
            $em->flush();
            $this->addFlash('success', 'L\'événement a bien été créé !');
            return $this->redirectToRoute('event_edit', ['id' => $event->getId()]);
        }

        return $this->render('admin/event/new.html.twig', [
            'commission' => $event,
            'form' => $form->createView(),
            'errors' => $form->getErrors()
        ]);
    }

    #[Route('/{id}/edit', name: 'event_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, Event $event, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'L\'événement a bien été édité !');
            return $this->redirectToRoute('event_list');
        }

        return $this->render('admin/event/edit.html.twig', [
            'form' => $form->createView(),
            'event' => $event,
            'delete_form' => $this->getDeleteForm($event)->createView(),
        ]);
    }

    #[Route('/{id}', name: 'event_delete', methods: ['POST'])] // Changed from DELETE to POST for Symfony 6/7 default behavior or keep it as is if using MethodOverride
    #[IsGranted('ROLE_ADMIN')]
    public function remove(Request $request, Event $event, EntityManagerInterface $em): Response
    {
        $form = $this->getDeleteForm($event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->remove($event);
            $em->flush();
            $this->addFlash('success', 'L\'événement a bien été supprimé !');
        }

        return $this->redirectToRoute('event_list');
    }

    #[Route('/proxy/{id}', name: 'proxy_delete', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function removeProxy(Request $request, Proxy $proxy, EntityManagerInterface $em): Response
    {
        $form = $this->getProxyDeleteForm($proxy);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $eventId = $proxy->getEvent()->getId();
            $em->remove($proxy);
            $em->flush();
            $this->addFlash('success', 'La procuration a bien été supprimée !');
            return $this->redirectToRoute('event_proxies_list', ['id' => $eventId]);
        }

        return $this->redirectToRoute('proxies_list');
    }

    #[Route('/proxy/{id}/edit', name: 'proxy_edit', methods: ['GET', 'POST'])] // Added /edit to disambiguate from removeProxy
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function editProxy(Request $request, Proxy $proxy, EntityManagerInterface $em, MailerInterface $mailer): Response
    {
        $event = $proxy->getEvent();
        $form = $this->createForm(ProxyType::class, $proxy);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($proxy->getOwner()) {
                $existing_proxy = $em->getRepository(Proxy::class)->findOneBy(["event" => $event, "owner" => $proxy->getOwner()]);
                if ($existing_proxy && $existing_proxy !== $proxy) {
                    $this->addFlash('error', $existing_proxy->getOwner()->getFirstname() . ' accepte déjà une procuration.');
                    return $this->redirectToRoute('event_proxies_list', ['id' => $event->getId()]);
                }
            }
            if ($proxy->getGiver()) {
                $existing_proxy = $em->getRepository(Proxy::class)->findOneBy(["event" => $event, "giver" => $proxy->getGiver()]);
                if ($existing_proxy && $existing_proxy !== $proxy) {
                    $this->addFlash('error', $existing_proxy->getGiver()->getFirstname() . ' donne déjà une procuration.');
                    return $this->redirectToRoute('event_proxies_list', ['id' => $event->getId()]);
                }
            }
            if (!$proxy->getOwner() && $proxy->getGiver()) {
                $proxy_waiting = $em->getRepository(Proxy::class)->findOneBy(["event" => $event, "giver" => null]);
                if ($proxy_waiting && $proxy_waiting !== $proxy) {
                    $proxy_waiting->setGiver($proxy->getGiver());
                    $em->persist($proxy_waiting);
                    $em->remove($proxy);
                    $em->flush();
                    $this->addFlash('success', 'proxy ' . $proxy->getId() . ' deleted');
                    $this->addFlash('success', 'proxy ' . $proxy_waiting->getId() . ' updated');
                    $this->addFlash('success', $proxy_waiting->getGiver() . ' => ' . $proxy_waiting->getOwner());
                    $this->sendProxyMail($proxy_waiting, $mailer);
                    return $this->redirectToRoute('event_proxies_list', ['id' => $event->getId()]);
                }
                $em->persist($proxy);
                $em->flush();
                $this->addFlash('success', 'proxy ' . $proxy->getId() . ' saved');
                return $this->redirectToRoute('event_proxies_list', ['id' => $event->getId()]);
            } elseif ($proxy->getOwner() && !$proxy->getGiver()) {
                $proxy_waiting = $em->getRepository(Proxy::class)->findOneBy(["event" => $event, "owner" => null]);
                if ($proxy_waiting && $proxy_waiting !== $proxy) {
                    $proxy_waiting->setOwner($proxy->getOwner());
                    $em->persist($proxy_waiting);
                    $em->remove($proxy);
                    $em->flush();
                    $this->addFlash('success', 'proxy ' . $proxy->getId() . ' deleted');
                    $this->addFlash('success', 'proxy ' . $proxy_waiting->getId() . ' updated');
                    $this->addFlash('success', $proxy_waiting->getGiver() . ' => ' . $proxy_waiting->getOwner());
                    $this->sendProxyMail($proxy_waiting, $mailer);
                    return $this->redirectToRoute('event_proxies_list', ['id' => $event->getId()]);
                }
                $em->persist($proxy);
                $em->flush();
                $this->addFlash('success', 'proxy ' . $proxy->getId() . ' saved');
                return $this->redirectToRoute('event_proxies_list', ['id' => $event->getId()]);
            } elseif ($proxy->getOwner() && $proxy->getGiver()) {
                $em->persist($proxy);
                $em->flush();
                $this->addFlash('success', 'proxy ' . $proxy->getId() . ' saved');
                $this->addFlash('success', $proxy->getGiver() . ' => ' . $proxy->getOwner());
                $this->sendProxyMail($proxy, $mailer);
                return $this->redirectToRoute('event_proxies_list', ['id' => $event->getId()]);
            }

            return $this->redirectToRoute('event_proxies_list', ['id' => $event->getId()]);
        }

        return $this->render('admin/event/proxy/edit.html.twig', [
            'form' => $form->createView(),
            'delete_form' => $this->getProxyDeleteForm($proxy)->createView(),
        ]);
    }

    protected function getDeleteForm(Event $event)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('event_delete', ['id' => $event->getId()]))
            ->setMethod('DELETE')
            ->getForm();
    }

    protected function getProxyDeleteForm(Proxy $proxy)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('proxy_delete', ['id' => $proxy->getId()]))
            ->setMethod('DELETE')
            ->getForm();
    }

    #[Route('/{id}/proxy/give', name: 'event_proxy_give', methods: ['GET', 'POST'])]
    public function giveProxy(Event $event, Request $request, EntityManagerInterface $em, MailerInterface $mailer): Response
    {
        $current_app_user = $this->getUser();
        $max_event_proxy_per_member = $this->getParameter('max_event_proxy_per_member');

        $member_given_proxy = $em->getRepository(Proxy::class)->findOneBy(["event" => $event, "giver" => $current_app_user->getBeneficiary()->getMembership()]);
        if ($member_given_proxy) {
            $this->addFlash('error', 'Oups, tu as déjà donné une procuration');
            return $this->redirectToRoute('homepage');
        }

        $membership = $current_app_user->getBeneficiary()->getMembership();
        $beneficiaries = $membership->getBeneficiaries();
        $beneficiariesId = array_map(function (Beneficiary $beneficiary) {
            return $beneficiary->getId();
        }, $beneficiaries->toArray());
        
        $member_received_proxies = $em->getRepository(Proxy::class)->findBy([
            "owner" => $beneficiariesId,
            "event" => $event
        ]);

        if ($member_received_proxies) {
            foreach ($member_received_proxies as $rp) {
                if ($rp->getGiver()) {
                    $this->addFlash('error', 'Oups, ' . $rp->getGiver() . ' a donné une procuration à ' . $rp->getOwner() . ', il compte dessus !');
                    return $this->redirectToRoute('homepage');
                } else {
                    $em->remove($rp);
                }
            }
        }

        $registrationDuration = $this->getParameter('registration_duration');
        if ($registrationDuration) {
            $minDateOfLastRegistration = clone $event->getMaxDateOfLastRegistration();
            $minDateOfLastRegistration->modify('-' . $registrationDuration);
            if ($membership->getLastRegistration()->getDate() < $minDateOfLastRegistration) {
                $this->addFlash('error', 'Oups, seuls les membres qui ont adhéré ou ré-adhéré après le ' .
                    $minDateOfLastRegistration->format('d M Y') .
                    ' peuvent voter à cet événement. Pense à mettre à jour ton adhésion pour participer !');
                return $this->redirectToRoute('homepage');
            }
        }
        if (!$membership->hasValidRegistrationBefore($event->getMaxDateOfLastRegistration())) {
            $this->addFlash('error', 'Oups, seuls les membres qui ont adhéré ou ré-adhéré avant le ' .
                $event->getMaxDateOfLastRegistration()->format('d M Y') .
                ' peuvent voter à cet événement. Pense à mettre à jour ton adhésion pour participer !');
            return $this->redirectToRoute('homepage');
        }

        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('event_proxy_give', ['id' => $event->getId()]))
            ->setMethod('POST')
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $proxy = $em->getRepository(Proxy::class)->findOneBy(["event" => $event, "giver" => null]);
            if (!$proxy) {
                $proxy = new Proxy();
                $proxy->setEvent($event);
                $proxy->setCreatedAt(new \DateTime());
            }

            $proxy->setGiver($current_app_user->getBeneficiary()->getMembership());
            $em->persist($proxy);
            $em->flush();
            $this->addFlash('success', 'Procuration acceptée !');

            if ($proxy->getGiver() && $proxy->getOwner()) {
                $this->sendProxyMail($proxy, $mailer);
            }

            return $this->redirectToRoute('homepage');
        }

        if ($request->get("beneficiary") > 0) {
            $beneficiary = $em->getRepository(Beneficiary::class)->find($request->get("beneficiary"));
            if ($beneficiary) {
                $member_giver_proxies = $em->getRepository(Proxy::class)->findBy([
                    "giver" => $beneficiary->getMembership(),
                    "event" => $event
                ]);
                if (count($member_giver_proxies) > 0) {
                    $this->addFlash('error', $beneficiary->getPublicDisplayNameWithMemberNumber() . ' a déjà donné sa procuration');
                    return $this->redirectToRoute('homepage');
                }

                $beneficiaries_ids = [];
                foreach ($beneficiary->getMembership()->getBeneficiaries() as $b) {
                    $beneficiaries_ids[] = $b;
                }
                $member_owner_proxies = $em->getRepository(Proxy::class)->findBy([
                    "owner" => $beneficiaries_ids,
                    "event" => $event
                ]);
                if (count($member_owner_proxies) >= $max_event_proxy_per_member) {
                    $this->addFlash('error', $beneficiary->getPublicDisplayNameWithMemberNumber() . ' accepte déjà de prendre le nombre maximal de procurations (' . $max_event_proxy_per_member . ')');
                    return $this->redirectToRoute('homepage');
                }

                $proxy = new Proxy();
                $proxy->setEvent($event);
                $proxy->setCreatedAt(new \DateTime());
                $proxy->setOwner($beneficiary);
                $proxy->setGiver($current_app_user->getBeneficiary()->getMembership());

                $confirm_form = $this->createForm(ProxyType::class, $proxy);
                $confirm_form->handleRequest($request);

                if ($confirm_form->isSubmitted() && $confirm_form->isValid()) {
                    $em->persist($proxy);
                    $em->flush();
                    $this->addFlash('success', 'Procuration donnée à ' . $proxy->getOwner()->getMembership()->getMemberNumberWithBeneficiaryListString() . ' !');

                    if ($proxy->getGiver() && $proxy->getOwner()) {
                        $this->sendProxyMail($proxy, $mailer);
                    }

                    return $this->redirectToRoute('homepage');
                }

                return $this->render('default/event/proxy/give.html.twig', [
                    'event' => $event,
                    'form' => $form->createView(),
                    'confirm_form' => $confirm_form->createView(),
                ]);
            } else {
                return $this->redirectToRoute('homepage');
            }
        }

        $search_form = $this->createFormBuilder()
            ->setAction($this->generateUrl('event_proxy_find_beneficiary', ['id' => $event->getId()]))
            ->add('firstname', TextType::class, ['label' => 'le prénom'])
            ->setMethod('POST')
            ->getForm();

        return $this->render('default/event/proxy/give.html.twig', [
            'event' => $event,
            'form' => $form->createView(),
            'search_form' => $search_form->createView()
        ]);
    }

    #[Route('/{id}/proxy/find_beneficiary', name: 'event_proxy_find_beneficiary', methods: ['POST'])]
    public function findBeneficiary(Event $event, Request $request, EntityManagerInterface $em): Response
    {
        $current_app_user = $this->getUser();
        $membership = $current_app_user->getBeneficiary()->getMembership();

        $minLastRegistration = clone $event->getMaxDateOfLastRegistration();
        $registrationDuration = $this->getParameter('registration_duration');
        if ($registrationDuration) {
             $minLastRegistration->modify('-' . $registrationDuration);
        }

        $search_form = $this->createFormBuilder()
            ->setAction($this->generateUrl('event_proxy_find_beneficiary', ['id' => $event->getId()]))
            ->add('firstname', TextType::class, ['label' => 'le prénom'])
            ->setMethod('POST')
            ->getForm();

        if ($search_form->handleRequest($request)->isValid()) {
            $firstname = $search_form->get('firstname')->getData();
            $qb = $em->createQueryBuilder();
            $beneficiaries_request = $qb->select('b')->from(Beneficiary::class, 'b')
                ->join('b.user', 'u')
                ->join('b.membership', 'm')
                ->leftJoin("m.registrations", "r")
                ->where($qb->expr()->like('b.firstname', $qb->expr()->literal('%' . $firstname . '%')))
                ->andWhere("m.withdrawn != 1 or m.withdrawn is NULL")
                ->andWhere("m != :current_member")
                ->setParameter('current_member', $membership);

            if (!is_null($registrationDuration)) {
                $beneficiaries_request = $beneficiaries_request
                    ->andWhere('r.date >= :min_last_registration')
                    ->setParameter('min_last_registration', $minLastRegistration)
                    ->andWhere('r.date < :max_last_registration')
                    ->setParameter('max_last_registration', $event->getMaxDateOfLastRegistration());
            }

            $beneficiaries = $beneficiaries_request
                ->orderBy("m.member_number", 'ASC')
                ->getQuery()
                ->getResult();

            $min_time_count = $this->getParameter("time_after_which_members_are_late_with_shifts");

            $filtered_beneficiaries = array_filter(
                $beneficiaries,
                function ($b) use ($min_time_count) {
                    return $b->getMembership()->getShiftTimeCount() > $min_time_count * 60;
                }
            );

            if (count($filtered_beneficiaries) != count($beneficiaries)) {
                $this->addFlash('notice', "Certains bénéficiaires ne sont pas présents dans " .
                    "liste, car leur compte est en dessous de la limite d'heure de retard.");
            }

            return $this->render('beneficiary/find_member_number.html.twig', [
                'form' => null,
                'beneficiaries' => $filtered_beneficiaries,
                'return_path' => 'event_proxy_give',
                'routeParam' => 'beneficiary',
                'params' => ['id' => $event->getId()]
            ]);
        }

        $this->addFlash('error', "oups, quelque chose c'est mal passé");
        return $this->redirectToRoute("event_proxy_give", ['id' => $event->getId()]);
    }

    #[Route('/{event}/proxy/remove/{proxy}', name: 'event_proxy_lite_remove', methods: ['GET'])]
    public function removeProxyLite(Event $event, Proxy $proxy, EntityManagerInterface $em): Response
    {
        $current_app_user = $this->getUser();
        if (($proxy->getEvent() === $event) && ($proxy->getOwner()->getUser() == $current_app_user)) {
            $em->remove($proxy);
            $em->flush();
            $this->addFlash('success', 'Ok, bien reçu');
        }
        return $this->redirectToRoute('homepage');
    }

    #[Route('/{id}/proxy/take', name: 'event_proxy_take', methods: ['GET', 'POST'])]
    public function acceptProxy(Event $event, Request $request, EntityManagerInterface $em, MailerInterface $mailer): Response
    {
        $current_app_user = $this->getUser();

        $myproxy = $em->getRepository(Proxy::class)->findOneBy(
            ["event" => $event, "giver" => $current_app_user->getBeneficiary()->getMembership()]
        );
        if ($myproxy) {
            $this->addFlash('error', 'Oups, tu as déjà donné une procuration');
            return $this->redirectToRoute('homepage');
        }

        $registrationDuration = $this->getParameter('registration_duration');
        if ($registrationDuration) {
            $minDateOfLastRegistration = clone $event->getMaxDateOfLastRegistration();
            $minDateOfLastRegistration->modify('-' . $registrationDuration);
            if ($current_app_user->getBeneficiary()->getMembership()->getLastRegistration()->getDate() < $minDateOfLastRegistration) {
                $this->addFlash('error', 'Oups, seuls les membres qui ont adhéré ou ré-adhéré après le ' .
                    $minDateOfLastRegistration->format('d M Y') .
                    ' peuvent voter à cet événement. Pense à mettre à jour ton adhésion pour participer !');
                return $this->redirectToRoute('homepage');
            }
        }
        if (!$current_app_user->getBeneficiary()->getMembership()->hasValidRegistrationBefore($event->getMaxDateOfLastRegistration())) {
            $this->addFlash('error', 'Oups, seuls les membres qui ont adhéré ou ré-adhéré avant le ' .
                $event->getMaxDateOfLastRegistration()->format('d M Y') .
                ' peuvent voter à cet événement. Pense à mettre à jour ton adhésion pour participer !');
            return $this->redirectToRoute('homepage');
        }

        $proxy = $em->getRepository(Proxy::class)->findOneBy(["event" => $event, "owner" => null]);
        if (!$proxy) {
            $proxy = new Proxy();
            $proxy->setEvent($event);
            $proxy->setCreatedAt(new \DateTime());
        }
        $form = $this->createForm(ProxyType::class, $proxy);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $max_event_proxy_per_member = $this->getParameter("max_event_proxy_per_member");
            $myproxyCount = $em->getRepository(Proxy::class)->count(["event" => $event, "owner" => $form->getData()->getOwner()]);
            if ($myproxyCount >= $max_event_proxy_per_member) {
                $this->addFlash('error', $form->getData()->getOwner()->getFirstname() . ' accepte déjà ' . $max_event_proxy_per_member . ' procuration.');
                return $this->redirectToRoute('event_proxy_take', ['id' => $event->getId()]);
            }

            $em->persist($proxy);
            $em->flush();
            $this->addFlash('success', 'Procuration acceptée !');

            if ($proxy->getGiver() && $proxy->getOwner()) {
                $this->sendProxyMail($proxy, $mailer);
            }

            return $this->redirectToRoute('homepage');
        }

        return $this->render('default/event/proxy/take.html.twig', [
            'event' => $event,
            'form' => $form->createView()
        ]);
    }

    public function sendProxyMail(Proxy $proxy, MailerInterface $mailer)
    {
        $giverMainBeneficiary = $proxy->getGiver()->getMainBeneficiary();
        $memberEmail = $this->getParameter('emails.member');

        $ownerEmail = (new Email())
            ->subject('[' . $proxy->getEvent()->getTitle() . '] procuration')
            ->from($memberEmail['address'])
            ->to($proxy->getOwner()->getEmail())
            ->replyTo($giverMainBeneficiary->getEmail())
            ->html($this->renderView(
                'emails/proxy_owner.html.twig',
                [
                    'proxy' => $proxy,
                    'giverMainBeneficiary' => $giverMainBeneficiary
                ]
            ));

        $giverEmail = (new Email())
            ->subject('[' . $proxy->getEvent()->getTitle() . '] ta procuration')
            ->from($memberEmail['address'])
            ->to($giverMainBeneficiary->getEmail())
            ->replyTo($proxy->getOwner()->getEmail())
            ->html($this->renderView(
                'emails/proxy_giver.html.twig',
                [
                    'proxy' => $proxy,
                    'giverMainBeneficiary' => $giverMainBeneficiary
                ]
            ));

        $mailer->send($ownerEmail);
        $mailer->send($giverEmail);
    }

    #[Route('/{id}/signatures/', name: 'event_signatures', methods: ['GET', 'POST'])]
    public function signaturesList(Request $request, Event $event, EntityManagerInterface $em): Response
    {
        $qb = $em->getRepository(Beneficiary::class)->createQueryBuilder('b');
        $beneficiaries_request = $qb->leftJoin('b.membership', 'm')
            ->leftJoin("m.registrations", "r")
            ->andWhere("r.date is NOT NULL")
            ->andWhere("m.withdrawn != 1 or m.withdrawn is NULL");

        if (!is_null($registrationDuration = $this->getParameter('registration_duration'))) {
            $minLastRegistration = clone $event->getMaxDateOfLastRegistration();
            $minLastRegistration->modify('-' . $registrationDuration);

            $beneficiaries_request = $beneficiaries_request
                ->andWhere('r.date >= :min_last_registration')
                ->setParameter('min_last_registration', $minLastRegistration)
                ->andWhere('r.date < :max_last_registration')
                ->setParameter('max_last_registration', $event->getMaxDateOfLastRegistration());
        }

        $beneficiaries = $beneficiaries_request
            ->orderBy("b.lastname", 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/event/signatures.html.twig', [
            'event' => $event,
            'beneficiaries' => $beneficiaries,
        ]);
    }
}
