<?php

namespace App\Controller;

use App\Entity\Beneficiary;
use App\Entity\Shift;
use App\Entity\User;
use App\Entity\EmailTemplate;
use App\Form\AutocompleteBeneficiaryCollectionType;
use App\Form\MarkdownEditorType;
use App\Service\SearchUserFormHelper;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Michelf\Markdown;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;

#[Route('/admin/mail')]
#[IsGranted('ROLE_USER_MANAGER')]
class MailController extends AbstractController
{
    #[Route('/to/{id}', name: 'mail_edit_one_beneficiary', methods: ['GET', 'POST'])]
    public function editOneBeneficiary(Request $request, Beneficiary $beneficiary, MailerService $mailerService, EntityManagerInterface $em): Response
    {
        $mailform = $this->getMailForm($mailerService, [$beneficiary]);
        $non_members = $this->getNonMemberEmails($em);
        return $this->render('admin/mail/send.html.twig', [
            'form' => $mailform->createView(),
            'non_members' => $non_members
        ]);
    }

    #[Route('/to_bucket/{id}', name: 'mail_bucketshift', methods: ['GET', 'POST'])]
    public function mailBucketShift(Request $request, Shift $shift, MailerService $mailerService, EntityManagerInterface $em): Response
    {
        if ($shift) {
            $shifts = $em->getRepository(Shift::class)->findBy(['job' => $shift->getJob(), 'start' => $shift->getStart(), 'end' => $shift->getEnd()]);
            $beneficiaries = [];
            foreach ($shifts as $s) {
                if ($s->getShifter()) {
                    $beneficiaries[] = $s->getShifter();
                }
            }
            $mailform = $this->getMailForm($mailerService, $beneficiaries);
            $non_members = $this->getNonMemberEmails($em);
            return $this->render('admin/mail/send.html.twig', [
                'form' => $mailform->createView(),
                'non_members' => $non_members
            ]);
        }
        
        return $this->redirectToRoute('mail_edit');
    }

    #[Route('/', name: 'mail_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, SearchUserFormHelper $formHelper, MailerService $mailerService, EntityManagerInterface $em): Response
    {
        $form = $formHelper->getSearchForm($this->createFormBuilder());
        $form->handleRequest($request);
        $qb = $formHelper->initSearchQuery($em);

        $to = [];
        if ($form->isSubmitted() && $form->isValid()) {
            $qb = $formHelper->processSearchFormData($form, $qb);
            $members = $qb->getQuery()->getResult();
            foreach ($members as $member) {
                foreach ($member->getBeneficiaries() as $beneficiary) {
                    $to[] = $beneficiary;
                }
            }
        }
        $non_members = $this->getNonMemberEmails($em);

        $mailform = $this->getMailForm($mailerService, $to);
        return $this->render('admin/mail/send.html.twig', [
            'form' => $mailform->createView(),
            'non_members' => $non_members
        ]);
    }

    #[Route('/send', name: 'mail_send', methods: ['POST'])]
    public function send(Request $request, MailerInterface $mailer, MailerService $mailerService, EntityManagerInterface $em, Environment $twig): Response
    {
        $mailform = $this->getMailForm($mailerService);
        $mailform->handleRequest($request);
        if ($mailform->isSubmitted() && $mailform->isValid()) {
            $beneficiaries = $mailform->get('to')->getData();
            $cci = $mailform->get('cci')->getData();
            $nonMembers = json_decode($cci);
            
            if (is_array($nonMembers)) {
                foreach ($nonMembers as $nonMember) {
                    $user = $em->getRepository(User::class)->findOneBy(['email' => $nonMember]);
                    if (is_object($user)) {
                        $fake_beneficiary = new Beneficiary();
                        $fake_beneficiary->setFlying(false);
                        $fake_beneficiary->setUser($user);
                        $fake_beneficiary->setFirstname($user->getUsername());
                        $fake_beneficiary->setLastname(' ');
                        $beneficiaries[] = $fake_beneficiary;
                    }
                }
            }

            $nb = 0;
            $errored = [];

            $from_email = $mailform->get('from')->getData();
            $allowedEmails = $mailerService->getAllowedEmails();
            if (in_array($from_email, $allowedEmails)) {
                $fromName = array_search($from_email, $allowedEmails);
                $from = new Address($from_email, $fromName);
            } else {
                $this->addFlash('error', 'cet email n\'est pas autorisé !');
                return $this->redirectToRoute('mail_edit');
            }

            $content = $mailform->get('message')->getData();
            $parser = new Markdown;
            $parser->hard_wrap = true;
            $content = $parser->transform($content);
            $emailTemplate = $mailform->get('template')->getData();
            if ($emailTemplate) {
                $content = str_replace('{{template_content}}', $content, $emailTemplate->getContent());
            }

            $template = $twig->createTemplate($content);
            foreach ($beneficiaries as $beneficiary) {
                $body = $twig->render($template, ['beneficiary' => $beneficiary]);
                try {
                    $email = (new Email())
                        ->subject($mailform->get('subject')->getData())
                        ->from($from)
                        ->to(new Address($beneficiary->getEmail(), $beneficiary->getFirstname() . ' ' . $beneficiary->getLastname()))
                        ->html($body);
                    
                    $mailer->send($email);
                    $nb++;
                } catch (\Exception $exception) {
                    $errored[] = $beneficiary->getEmail();
                }
            }
            if ($nb > 1) {
                $this->addFlash('success', $nb . ' messages envoyés');
                if (!empty($errored)) {
                    $this->addFlash('warning', 'Impossible d\'envoyer à : ' . implode(', ', $errored));
                }
            } elseif ($nb === 1) {
                $this->addFlash('success', 'message envoyé');
            }
        }
        return $this->redirectToRoute('mail_edit');
    }

    private function getMailForm(MailerService $mailerService, array $to = [])
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('mail_send'))
            ->setMethod('POST')
            ->add('from', ChoiceType::class, [
                'label' => 'Depuis',
                'required' => false,
                'choices' => $mailerService->getAllowedEmails()
            ])
            ->add('to', AutocompleteBeneficiaryCollectionType::class, [
                'data' => $to,
                'label' => "Destinataire(s)",
            ])
            ->add('cci', HiddenType::class, ['label' => 'Non-membres', 'required' => false])
            ->add('template', EntityType::class, [
                'class' => EmailTemplate::class,
                'placeholder' => '',
                'choice_label' => 'name',
                'multiple' => false,
                'required' => false,
                'label' => 'Modèle'
            ])
            ->add('subject', TextType::class, ['label' => 'Sujet', 'required' => true])
            ->add('message', MarkdownEditorType::class, [
                'label' => 'Message',
                'required' => true,
                'attr' => ['class' => 'materialize-textarea']
            ])
            ->getForm();
    }

    private function getNonMemberEmails(EntityManagerInterface $em)
    {
        $non_members = $em->getRepository(User::class)->findActiveNonMembers();
        $list = [];
        foreach ($non_members as $non_member) {
            $list[$non_member->getEmail()] = '';
        }
        return $list;
    }
}
