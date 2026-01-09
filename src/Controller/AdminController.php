<?php

namespace App\Controller;

use App\Entity\Job;
use App\Entity\Shift;
use App\Entity\User;
use App\Service\SearchUserFormHelper;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN_PANEL')]
class AdminController extends AbstractController
{
    #[Route('/', name: 'admin', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/index.html.twig');
    }

    #[Route('/users', name: 'user_index', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER_MANAGER')]
    public function users(Request $request, SearchUserFormHelper $formHelper, EntityManagerInterface $em): Response
    {
        $defaults = [
            'sort' => 'o.member_number',
            'dir' => 'ASC',
            'withdrawn' => 1,
        ];
        $form = $formHelper->createMemberFilterForm($this->createFormBuilder(), $defaults);
        $form->handleRequest($request);

        $action = $form->get('action')->getData();

        $qb = $formHelper->initSearchQuery($em);

        if ($form->isSubmitted() && $form->isValid()) {
            $formHelper->processSearchFormData($form, $qb);
            $sort = $form->get('sort')->getData();
            $order = $form->get('dir')->getData();
            $currentPage = $form->get('page')->getData();
        } else {
            $sort = $defaults['sort'];
            $order = $defaults['dir'];
            $currentPage = 1;
            $qb = $qb->andWhere('o.withdrawn = :withdrawn')
                ->setParameter('withdrawn', $defaults['withdrawn'] - 1);
        }
        $qb = $qb->orderBy($sort, $order);

        // Export CSV
        if ($action == "csv") {
            $members = $qb->getQuery()->getResult();
            $return = '';
            $d = ',';
            foreach ($members as $member) {
                foreach ($member->getBeneficiaries() as $beneficiary) {
                    $return .=
                        $beneficiary->getMemberNumber() . $d .
                        $beneficiary->getFirstname() . $d .
                        $beneficiary->getLastname() . $d .
                        $beneficiary->getEmail() . $d .
                        $beneficiary->getPhone() .
                        "\n";
                }
            }
            return new Response($return, 200, [
                'Content-Encoding: UTF-8',
                'Content-Type' => 'application/force-download; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="emails_' . date('dmyhis') . '.csv"'
            ]);
        } else if ($action === "mail") {
            return $this->redirectToRoute('mail_edit', [
                'request' => $request
            ], 307);
        } else {
            $limitPerPage = 25;
            $paginator = new Paginator($qb);
            $totalItems = count($paginator);
            $pagesCount = ($totalItems == 0) ? 1 : (int) ceil($totalItems / $limitPerPage);
            $currentPage = ($currentPage > $pagesCount) ? $pagesCount : $currentPage;

            $paginator
                ->getQuery()
                ->setFirstResult($limitPerPage * ($currentPage - 1))
                ->setMaxResults($limitPerPage);
        }

        return $this->render('admin/user/list.html.twig', [
            'members' => $paginator,
            'form' => $form->createView(),
            'nb_of_result' => $totalItems,
            'page' => $currentPage,
            'nb_of_pages' => $pagesCount
        ]);
    }

    #[Route('/admin_users', name: 'admins_list', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminUsers(EntityManagerInterface $em): Response
    {
        /** @var User[] $admins */
        $admins = $em->getRepository(User::class)->findByRole('ROLE_ADMIN');
        $delete_forms = [];
        foreach ($admins as $admin) {
            $delete_forms[$admin->getId()] = $this->createFormBuilder()
                ->setAction($this->generateUrl('user_delete', ['id' => $admin->getId()]))
                ->setMethod('DELETE')
                ->getForm()->createView();
        }

        return $this->render('admin/user/admin_list.html.twig', [
            'admins' => $admins,
            'delete_forms' => $delete_forms
        ]);
    }

    #[Route('/roles', name: 'roles_list', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function rolesList(EntityManagerInterface $em, ParameterBagInterface $parameterBag, Environment $twig): Response
    {
        $roles_hierarchy = $parameterBag->get('security.role_hierarchy.roles');
        $roles_list = array_merge(["ROLE_USER"], array_keys($roles_hierarchy));
        $roles_list_enriched = [];

        foreach ($roles_list as $role_code) {
            $role = [];
            $role_icon_key = strtolower($role_code) . "_icon";
            $role_name_key = strtolower($role_code) . "_name";
            $role["code"] = $role_code;
            $role["icon"] = $twig->getGlobals()[strtolower($role_icon_key)] ?? "";
            $role["name"] = $twig->getGlobals()[strtolower($role_name_key)] ?? "";
            $role["children"] = in_array($role_code, array_keys($roles_hierarchy)) ? implode(", ", $roles_hierarchy[$role_code]) : "";
            $role["user_count"] = count($em->getRepository(User::class)->findByRole($role_code));
            $roles_list_enriched[] = $role;
        }

        return $this->render('admin/user/roles_list.html.twig', [
            'roles' => $roles_list_enriched,
        ]);
    }

    #[Route('/widget', name: 'widget_generator', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_PROCESS_MANAGER')]
    public function widgetBuilder(Request $request): Response
    {
        $form = $this->createFormBuilder()
            ->add('job', EntityType::class, [
                'label' => 'Quel poste ?',
                'class' => Job::class,
                'choice_label' => 'name',
                'multiple' => false,
                'required' => true
            ])
            ->add('display_end', CheckboxType::class, ['required' => false, 'label' => 'Afficher l\'heure de fin ?'])
            ->add('display_on_empty', CheckboxType::class, ['required' => false, 'label' => 'Afficher les créneaux vides ?'])
            ->add('title', CheckboxType::class, ['required' => false, 'data' => true, 'label' => 'Afficher le titre ?'])
            ->add('generate', SubmitType::class, ['label' => 'Générer'])
            ->getForm();

        if ($form->handleRequest($request)->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            return $this->render('admin/widget/generate.html.twig', [
                'query_string' => 'job_id=' . $data['job']->getId() . '&display_end=' . ($data['display_end'] ? 1 : 0) . '&display_on_empty=' . ($data['display_on_empty'] ? 1 : 0) . '&title=' . ($data['title'] ? 1 : 0),
                'form' => $form->createView(),
            ]);
        }

        return $this->render('admin/widget/generate.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/importcsv', name: 'user_import_csv', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function csvImport(Request $request, KernelInterface $kernel): Response
    {
        $form = $this->createFormBuilder()
            ->add('submitFile', FileType::class, ['label' => 'File to Submit'])
            ->add('delimiter', ChoiceType::class, [
                'label' => 'delimiter',
                'choices'  => [
                    'virgule ,' => ',',
                    'point virgule ;' => ';',
                ]
            ])
            ->getForm();

        if ($form->handleRequest($request)->isSubmitted() && $form->isValid()) {
            $file = $form->get('submitFile');
            $delimiter = $form->get('delimiter')->getData() ?? ',';
            $filename = $file->getData()->getPathName();

            $application = new Application($kernel);
            $application->setAutoExit(false);

            $input = new ArrayInput([
                'command' => 'app:import:users',
                '--delimiter' => $delimiter,
                'file' => $filename,
                '--default_mapping' => true
            ]);

            $output = new BufferedOutput();
            $application->run($input, $output);

            $content = $output->fetch();

            $this->addFlash('notice', 'Le fichier a été traité.');

            return $this->render('admin/user/import_return.html.twig', [
                'content' => $content,
            ]);
        }

        return $this->render('admin/user/import.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
