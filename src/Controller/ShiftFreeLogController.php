<?php

namespace App\Controller;

use App\Entity\ShiftFreeLog;
use App\Form\AutocompleteBeneficiaryType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/shifts/freelogs')]
class ShiftFreeLogController extends AbstractController
{
    private function filterFormFactory(Request $request): array
    {
        $res = [
            'created_at' => null,
            'shift_start_date' => null,
            'beneficiary' => null,
            'fixe' => 0
        ];

        $res['form'] = $this->createFormBuilder()
            ->setAction($this->generateUrl('admin_shiftfreelog_index'))
            ->add('created_at', DateType::class, [
                'widget' => 'single_text',
                'html5' => false,
                'label' => "Date de l'annulation",
                'required' => false,
                'attr' => ['class' => 'datepicker']
            ])
            ->add('shift_start_date', DateType::class, [
                'widget' => 'single_text',
                'html5' => false,
                'label' => 'Date du créneau',
                'required' => false,
                'attr' => ['class' => 'datepicker']
            ])
            ->add('beneficiary', AutocompleteBeneficiaryType::class, [
                'label' => 'Bénéficiaire',
                'required' => false,
            ])
            ->add('fixe', ChoiceType::class, [
                'label' => 'Type de créneau',
                'required' => false,
                'choices' => [
                    'fixe' => 2,
                    'volant' => 1,
                ]
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Filtrer',
                'attr' => ['class' => 'btn', 'value' => 'filtrer']
            ])
            ->getForm();

        $res['form']->handleRequest($request);

        if ($res['form']->isSubmitted() && $res['form']->isValid()) {
            $res['created_at'] = $res['form']->get('created_at')->getData();
            $res['shift_start_date'] = $res['form']->get('shift_start_date')->getData();
            $res['beneficiary'] = $res['form']->get('beneficiary')->getData();
            $res['fixe'] = $res['form']->get('fixe')->getData();
        }

        return $res;
    }

    #[Route('/', name: 'admin_shiftfreelog_index', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_SHIFT_MANAGER')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $filter = $this->filterFormFactory($request);
        $sort = 'createdAt';
        $order = 'DESC';

        $qb = $em->getRepository(ShiftFreeLog::class)->createQueryBuilder('sfl')
            ->leftJoin('sfl.shift', 's')
            ->orderBy('sfl.' . $sort, $order);

        if ($filter['created_at']) {
            $qb->andWhere("DATE_FORMAT(sfl.createdAt, '%Y-%m-%d') = :created_at")
                ->setParameter('created_at', $filter['created_at']->format('Y-m-d'));
        }
        if ($filter['shift_start_date']) {
            $qb->andWhere("DATE_FORMAT(s.start, '%Y-%m-%d') = :shift_start_date")
                ->setParameter('shift_start_date', $filter['shift_start_date']->format('Y-m-d'));
        }
        if ($filter['beneficiary']) {
            $qb->andWhere('sfl.beneficiary = :beneficiary')
                ->setParameter('beneficiary', $filter['beneficiary']);
        }
        if ($filter['fixe'] > 0) {
            $qb->andWhere('sfl.fixe = :fixe')
                ->setParameter('fixe', $filter['fixe'] - 1);
        }

        $limitPerPage = 25;
        $paginator = new Paginator($qb);
        $totalItems = count($paginator);
        $pagesCount = ($totalItems == 0) ? 1 : ceil($totalItems / $limitPerPage);
        $currentPage = $request->query->get('page', 1);
        $currentPage = ($currentPage > $pagesCount) ? $pagesCount : $currentPage;

        $paginator->getQuery()
            ->setFirstResult($limitPerPage * ($currentPage - 1))
            ->setMaxResults($limitPerPage);

        return $this->render('admin/shiftfreelog/index.html.twig', [
            'shiftFreeLogs' => $paginator,
            'filter_form' => $filter['form']->createView(),
            'current_page' => $currentPage,
            'pages_count' => $pagesCount,
        ]);
    }
}
