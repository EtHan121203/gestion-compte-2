<?php

namespace App\Controller;

use App\Entity\Code;
use App\Entity\User;
use App\Event\CodeNewEvent;
use App\Helper\SwipeCard as SwipeCardHelper;
use App\Security\CodeVoter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[Route('/codes')]
class CodeController extends AbstractController
{
    public function homepageDashboard(EntityManagerInterface $em): Response
    {
        $codes = $em->getRepository(Code::class)->findBy(['closed' => 0], ['createdAt' => 'DESC']);
        if (!$codes) {
            $codes[] = new Code();
        }
        return $this->render('default/code/home_dashboard.html.twig', ['codes' => $codes]);
    }

    #[Route('/', name: 'codes_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(LoggerInterface $logger, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $logger->info('CODE : codes_list', ['username' => $user->getUserIdentifier()]);

        if ($this->isGranted('ROLE_ADMIN')) {
            $codes = $em->getRepository(Code::class)->findBy([], ['createdAt' => 'DESC'], 100);
            $old_codes = null;
        } else {
            $codes = $em->getRepository(Code::class)->findBy(['closed' => 0], ['createdAt' => 'DESC'], 10);
            $old_codes = $em->getRepository(Code::class)->findBy(['closed' => 1], ['createdAt' => 'DESC'], 3);
        }

        if (count($codes) === 0) {
            $this->addFlash('warning', 'aucun code à lire');
            return $this->redirectToRoute('homepage');
        }

        $this->denyAccessUnlessGranted('view', $codes[0]);

        return $this->render('default/code/list.html.twig', [
            'codes' => $codes,
            'old_codes' => $old_codes
        ]);
    }

    #[Route('/new', name: 'code_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $codeform = $this->createFormBuilder()
            ->setAction($this->generateUrl('code_edit'))
            ->setMethod('POST')
            ->add('code', TextType::class, ['label' => 'code', 'required' => true])
            ->add('close_old_codes', CheckboxType::class, [
                'label' => 'fermer les anciens codes ?',
                'required' => false,
                'attr' => ['class' => 'filled-in']])
            ->getForm();

        $codeform->handleRequest($request);

        if ($codeform->isSubmitted() && $codeform->isValid()) {
            $value = $codeform->get('code')->getData();
            $code = new Code();
            $code->setValue($value);
            $code->setClosed(false);
            $code->setRegistrar($this->getUser());

            $em->persist($code);

            if ($codeform->get('close_old_codes')->getData()) {
                $open_codes = $em->getRepository(Code::class)->findBy(['closed' => 0]);
                foreach ($open_codes as $open_code) {
                    $open_code->setClosed(true);
                    $em->persist($open_code);
                }
            }

            $em->flush();
            $this->addFlash('success', '🎉 Nouveau code enregistré.');

            return $this->redirectToRoute('codes_list');
        }

        return $this->render('default/code/new.html.twig', [
            'form' => $codeform->createView()
        ]);
    }

    #[Route('/generate', name: 'code_generate', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function generate(Request $request, TokenStorageInterface $tokenStorage, EntityManagerInterface $em, LoggerInterface $logger, EventDispatcherInterface $eventDispatcher): Response
    {
        /** @var User $current_app_user */
        $current_app_user = $tokenStorage->getToken()?->getUser();

        $my_open_codes = $em->getRepository(Code::class)->findBy(['closed' => 0, 'registrar' => $current_app_user], ['createdAt' => 'DESC']);
        $old_codes = $em->getRepository(Code::class)->findBy(['closed' => 0], ['createdAt' => 'DESC']);

        $granted = false;
        foreach ($old_codes as $code) {
            if ($this->isGranted('view', $code)) {
                $granted = true;
                break;
            }
        }
        
        if (!$granted && count($old_codes) > 0) {
            throw $this->createAccessDeniedException('Oups, les anciens codes ne peuvent pas être lu.');
        }

        if (count($my_open_codes)) {
            $logger->info('CODE : code_new make change screen', ['username' => $current_app_user->getUserIdentifier()]);
            return $this->render('default/code/generate.html.twig', [
                'display' =>  true,
                'code' => $my_open_codes[0],
                'old_codes' => $old_codes,
            ]);
        }

        if ($request->get('generate') === null) {
            $logger->info('CODE : code_new create screen', ['username' => $current_app_user->getUserIdentifier()]);
            return $this->render('default/code/generate.html.twig');
        }

        $value = rand(0, 9999);
        $code = new Code();
        $code->setValue($value);
        $code->setClosed(false);
        $code->setRegistrar($current_app_user);

        $em->persist($code);
        $em->flush();

        $logger->info('CODE : code_new created', ['username' => $this->getUser()->getUserIdentifier()]);

        $eventDispatcher->dispatch(new CodeNewEvent($code, $old_codes), CodeNewEvent::NAME);

        $this->addFlash('success', '🎉 Bravo ! Note bien les deux codes ci-dessous ! <br>Tu peux aussi retrouver ces infos dans tes mails.');

        return $this->render('default/code/generate.html.twig', [
            'generate' =>  true,
            'code' => $code,
            'old_codes' => $old_codes,
        ]);
    }

    #[Route('/{id}/toggle', name: 'code_toggle', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function toggle(Code $code, EntityManagerInterface $em): Response
    {
        if ($code->getClosed()) {
            $this->denyAccessUnlessGranted('open', $code);
        } else {
            $this->denyAccessUnlessGranted('close', $code);
        }

        $code->setClosed(!$code->getClosed());
        $em->persist($code);
        $em->flush();

        $this->addFlash('success', 'Le code a bien été marqué ' . (($code->getClosed()) ? 'fermé' : 'ouvert') . ' !');

        return $this->redirectToRoute('codes_list');
    }

    #[Route('/close_all', name: 'code_change_done', methods: ['GET'])]
    public function closeAllButMine(Request $request, AuthorizationCheckerInterface $authorizationChecker, EntityManagerInterface $em, LoggerInterface $logger, TokenStorageInterface $tokenStorage, SwipeCardHelper $swipeCardHelper): Response
    {
        $logged_out = false;
        $previousToken = null;

        if ($authorizationChecker->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            /** @var User $current_app_user */
            $current_app_user = $tokenStorage->getToken()?->getUser();
            $logger->info('CODE : confirm code change (logged in)', ['username' => $current_app_user->getUserIdentifier()]);
        } else {
            $token = $request->get('token');
            $decoded = $swipeCardHelper->vigenereDecode($token);
            $username = explode(',', $decoded)[0];
            $current_app_user = $em->getRepository(User::class)->findOneBy(['username' => $username]);
            
            if ($current_app_user) {
                $previousToken = $tokenStorage->getToken();
                $logged_out = true;
                $newToken = new UsernamePasswordToken($current_app_user, 'main', $current_app_user->getRoles());
                $tokenStorage->setToken($newToken);
                $logger->info('CODE : confirm code change (logged out)', ['username' => $current_app_user->getUserIdentifier()]);
            } else {
                return $this->redirectToRoute('homepage');
            }
        }

        $my_open_codes = $em->getRepository(Code::class)->findBy(['closed' => 0, 'registrar' => $current_app_user], ['createdAt' => 'DESC']);
        if (count($my_open_codes) > 0) {
            $myLastCode = $my_open_codes[0];
            $codes = $em->getRepository(Code::class)->findBy(['closed' => 0], ['createdAt' => 'DESC']);
            foreach ($codes as $code) {
                if ($myLastCode->getCreatedAt() > $code->getCreatedAt()) {
                    if ($code->getRegistrar() !== $current_app_user) {
                        if ($authorizationChecker->isGranted(CodeVoter::VIEW, $code)) {
                            $code->setClosed(true);
                            $em->persist($code);
                        }
                    }
                }
            }
            $em->flush();
        }

        if ($logged_out) {
            $tokenStorage->setToken($previousToken);
        }

        $this->addFlash('success', 'Bien enregistré, merci !');

        return $this->redirectToRoute('homepage');
    }

    #[Route('/{id}', name: 'code_delete', methods: ['DELETE'])]
    public function remove(Request $request, Code $code, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('delete', $code);
        
        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('code_delete', ['id' => $code->getId()]))
            ->setMethod('DELETE')
            ->getForm();
            
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->remove($code);
            $em->flush();
            $this->addFlash('success', 'Le code a bien été supprimé !');
        }

        return $this->redirectToRoute('codes_list');
    }
}
