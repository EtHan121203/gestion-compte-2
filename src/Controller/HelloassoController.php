<?php

namespace App\Controller;

use App\Entity\HelloassoPayment;
use App\Event\HelloassoEvent;
use App\Form\RegistrationType;
use App\Form\AutocompleteBeneficiaryType;
use App\Helper\Helloasso;
use App\Helper\SwipeCard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/helloasso')]
class HelloassoController extends AbstractController
{
    #[Route('/payments', name: 'helloasso_payments', methods: ['GET'])]
    #[IsGranted('ROLE_FINANCE_MANAGER')]
    public function helloassoPayments(Request $request, EntityManagerInterface $em, Helloasso $helloassoHelper): Response
    {
        $page = $request->query->get('page', 1);
        $limit = 50;
        
        $max = $em->createQueryBuilder()
            ->select('count(n.id)')
            ->from(HelloassoPayment::class, 'n')
            ->getQuery()
            ->getSingleScalarResult();

        $nb_of_pages = (int) ($max / $limit);
        if ($max > 0) {
            $nb_of_pages += (($max % $limit) > 0) ? 1 : 0;
        }
        
        $payments = $em->getRepository(HelloassoPayment::class)
            ->findBy([], ['createdAt' => 'DESC', 'date' => 'DESC'], $limit, ($page - 1) * $limit);
        
        $delete_forms = [];
        foreach ($payments as $payment) {
            $delete_forms[$payment->getId()] = $this->getPaymentDeleteForm($payment)->createView();
        }

        $campaigns_json = $helloassoHelper->get('campaigns');

        $campaigns = [];
        if ($campaigns_json && isset($campaigns_json->resources)) {
            foreach ($campaigns_json->resources as $c) {
                $campaigns[(int) $c->id] = $c;
            }
        } else {
            $campaign_ids = array_unique(array_map(function($payment) { return $payment->getCampaignId(); }, $payments));
            foreach ($campaign_ids as $id) {
                $campaigns[(int) $id] = (object) ["url" => null, "name" => null];
            }
        }

        return $this->render('admin/helloasso/payments.html.twig', [
            'payments' => $payments,
            'campaigns' => $campaigns,
            'delete_forms' => $delete_forms,
            'page' => $page,
            'nb_of_pages' => $nb_of_pages
        ]);
    }

    #[Route('/browser', name: 'helloasso_browser', methods: ['GET'])]
    #[IsGranted('ROLE_FINANCE_MANAGER')]
    public function helloassoBrowser(Request $request, Helloasso $helloassoHelper): Response
    {
        $page = $request->query->get('page', 1);
        $campaignId = $request->query->get('campaign');

        if (!$campaignId) {
            $campaigns_json = $helloassoHelper->get('campaigns');
            $campaigns = ($campaigns_json && isset($campaigns_json->resources)) ? $campaigns_json->resources : null;
            
            return $this->render('admin/helloasso/browser.html.twig', [
                'campaigns' => $campaigns
            ]);
        } else {
            $campaignId = str_pad($campaignId, 12, '0', STR_PAD_LEFT);
            $campaign_json = $helloassoHelper->get('campaigns/' . $campaignId);
            
            if (!$campaign_json) {
                $this->addFlash('error', 'campaign not found');
                return $this->redirectToRoute('helloasso_browser');
            }
            
            $payments_json = $helloassoHelper->get('campaigns/' . $campaignId . '/payments', ['page' => $page]);
            $page = $payments_json->pagination->page;
            $nb_of_pages = $payments_json->pagination->max_page;
            
            return $this->render('admin/helloasso/browser.html.twig', [
                'payments' => $payments_json->resources,
                'page' => $page,
                'campaign' => $campaign_json,
                'nb_of_pages' => $nb_of_pages
            ]);
        }
    }

    #[Route('/manualPaimentAdd/', name: 'helloasso_manual_paiement_add', methods: ['POST'])]
    #[IsGranted('ROLE_FINANCE_MANAGER')]
    public function helloassoManualPaimentAdd(Request $request, Helloasso $helloassoHelper, EntityManagerInterface $em, EventDispatcherInterface $dispatcher): Response
    {
        $paiementId = $request->request->get('paiementId');
        if (!$paiementId) {
            $this->addFlash('error', 'missing paiment id');
            return $this->redirectToRoute('helloasso_browser');
        }

        $payment_json = $helloassoHelper->get('payments/' . $paiementId);
        $exist = $em->getRepository(HelloassoPayment::class)->findOneBy(['paymentId' => $payment_json->id]);

        if ($exist) {
            $this->addFlash('error', 'Ce paiement est déjà enregistré');
            return $this->redirectToRoute('helloasso_browser', ['campaign' => $exist->getCampaignId()]);
        }

        $payments = [];
        $action_json = null;
        foreach ($payment_json->actions as $action) {
            $action_json = $helloassoHelper->get('actions/' . $action->id);
            $payment = $em->getRepository(HelloassoPayment::class)->findOneBy(['paymentId' => $payment_json->id]);
            
            if ($payment) {
                $amount = (float) str_replace(',', '.', $action_json->amount);
                $payment->setAmount($payment->getAmount() + $amount);
            } else {
                $payment = new HelloassoPayment();
                $payment->fromActionObj($action_json);
            }
            $em->persist($payment);
            $em->flush();
            $payments[$payment->getId()] = $payment;
        }

        foreach ($payments as $payment) {
            $dispatcher->dispatch(new HelloassoEvent($payment), HelloassoEvent::PAYMENT_AFTER_SAVE);
        }

        $this->addFlash('success', 'Ce paiement a bien été enregistré');
        return $this->redirectToRoute('helloasso_browser', ['campaign' => $action_json->id_campaign]);
    }

    #[Route('/payments/{id}', name: 'helloasso_payment_remove', methods: ['DELETE'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function removePayment(Request $request, HelloassoPayment $payment, EntityManagerInterface $em): Response
    {
        $form = $this->getPaymentDeleteForm($payment);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            if ($payment->getRegistration()) {
                $this->addFlash('error', 'ce paiement est lié à une adhésion');
                return $this->redirectToRoute('helloasso_payments');
            }
            $em->remove($payment);
            $em->flush();
            $this->addFlash('success', 'Le paiement a bien été supprimé !');
        }
        return $this->redirectToRoute('helloasso_payments');
    }

    #[Route('/payment/{id}/edit', name: 'helloasso_payment_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_FINANCE_MANAGER')]
    public function editPayment(Request $request, HelloassoPayment $payment, EventDispatcherInterface $dispatcher): Response
    {
        if ($payment->getRegistration()) {
            $this->addFlash('error', 'Désolé, cette adhésion est déjà associée à un membre valide');
            return $this->redirectToRoute('helloasso_payments');
        }

        $form = $this->createPaymentEditForm($payment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $beneficiary = $form->get("subscriber")->getData();
            $dispatcher->dispatch(new HelloassoEvent($payment, $beneficiary->getUser()), HelloassoEvent::ORPHAN_SOLVE);

            $this->addFlash('success', "L'adhésion a été mise à jour avec succès pour " . $beneficiary);
            return $this->redirectToRoute('helloasso_payments');
        }

        return $this->render('admin/helloasso/payment_modal.html.twig', [
            'payment' => $payment,
            'form' => $form->createView(),
        ]);
    }

    protected function getPaymentDeleteForm(HelloassoPayment $payment)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('helloasso_payment_remove', ['id' => $payment->getId()]))
            ->setMethod('DELETE')
            ->getForm();
    }

    private function createPaymentEditForm(HelloassoPayment $payment)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('helloasso_payment_edit', ['id' => $payment->getId()]))
            ->add('subscriber', AutocompleteBeneficiaryType::class, ['label' => 'Numéro d\'adhérent ou nom du membre', 'required' => true])
            ->getForm();
    }

    #[Route('/payment/{id}/resolve_orphan/{code}', name: 'helloasso_resolve_orphan', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function resolveOrphan(HelloassoPayment $payment, string $code, SwipeCard $swipeCardHelper): Response
    {
        $email = $swipeCardHelper->vigenereDecode(urldecode($code));
        if ($email == $payment->getEmail()) {
            if ($payment->getRegistration()) {
                $this->addFlash('error', 'Le paiement helloasso que tu cherches à corriger n\'a plus besoin de ton aide !');
            } else {
                return $this->render('user/helloasso_resolve_orphan.html.twig', ['payment' => $payment]);
            }
        } else {
            $this->addFlash('error', 'Oups, ce lien ne semble pas fonctionner !');
        }
        return $this->redirectToRoute('homepage');
    }

    #[Route('/payment/{id}/confirm_resolve_orphan/{code}', name: 'helloasso_confirm_resolve_orphan', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function confirmOrphan(HelloassoPayment $payment, string $code, SwipeCard $swipeCardHelper, EventDispatcherInterface $dispatcher): Response
    {
        $email = $swipeCardHelper->vigenereDecode(urldecode($code));
        if ($email == $payment->getEmail()) {
            $this->addFlash('success', 'Merci !');
            $dispatcher->dispatch(new HelloassoEvent($payment, $this->getUser()), HelloassoEvent::ORPHAN_SOLVE);
        } else {
            $this->addFlash('error', 'Oups, ce lien ne semble pas fonctionner !');
        }
        return $this->redirectToRoute('homepage');
    }

    #[Route('/payment/{id}/orphan_exit_and_back/{code}', name: 'helloasso_orphan_exit_and_back', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function orphanExitAndConfirm(Request $request, HelloassoPayment $payment, string $code): Response
    {
        $this->addFlash('info', 'Logging out...');
        // In Symfony 6/7, logging out should be handled by the security system or a specific redirect.
        // The original code was manually clearing the token.
        return $this->redirectToRoute('app_logout', ['_target_path' => $this->generateUrl('helloasso_resolve_orphan', ['id' => $payment->getId(), 'code' => $code])]);
    }
}
