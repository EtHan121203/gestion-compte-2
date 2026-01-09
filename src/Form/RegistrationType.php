<?php
// src/AppBundle/Form/RegistrationType.php

namespace App\Form;

use App\Entity\Registration;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class RegistrationType extends AbstractType
{
    private $localCurrency;
    private $tokenStorage;

    public function __construct(
        #[Autowire(param: 'local_currency_name')] string $localCurrency,
        TokenStorageInterface $tokenStorage
    ) {
        $this->localCurrency = $localCurrency;
        $this->tokenStorage = $tokenStorage;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

        // grab the user, do a quick sanity check that one exists
        $user = $this->tokenStorage->getToken()->getUser();
        if (!$user) {
            throw new \LogicException(
                'cannot be used without an authenticated user!'
            );
        }

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($user) {
            // ... adding the name field if needed
            $form = $event->getForm();
            $registration = $event->getData();

            $form->add('date', DateType::class,array(
                'label' => 'Date',
                'disabled' => !is_object($user)||(!($user->hasRole('ROLE_ADMIN')||$user->hasRole('ROLE_SUPER_ADMIN'))),
                'html5' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'datepicker'
                ]
            ));

            if (!is_object($user)||!$user->hasRole('ROLE_SUPER_ADMIN')){
                if ($registration){
                    if (!$registration->getAmount()){
                        $form->add('amount', TextType::class, array('label' => 'Montant','attr'=>array('placeholder'=>'15'),
                            'constraints' => [ new GreaterThan(0) ]));
                    }else{
                        $form->add('amount', TextType::class, array('label' => 'Montant','attr'=>array('disabled'=>'true')));
                    }
                    $form->add('registrar', EntityType::class, array(
                        'label' => 'Enregistré par',
                        'class' => \App\Entity\User::class,
                        'query_builder' => function (EntityRepository $er) {
                            return $er->createQueryBuilder('u')
                                ->orderBy('u.username', 'ASC');
                        },
                        'choice_label' => 'username',
                        'attr' => array(
                            'disabled' => !is_null($registration->getRegistrar())
                        )
                    ));
                    $form->add('mode', ChoiceType::class, array(
                        'choices' => array(
                            'Espèce' => Registration::TYPE_CASH,
                            'Chèque' => Registration::TYPE_CHECK,
                            $this->localCurrency => Registration::TYPE_LOCAL,
                            'HelloAsso' => Registration::TYPE_HELLOASSO,
                        ),
                        'label' => 'Mode de réglement',
                        'attr' => array(
                            'disabled' => !is_null($registration->getMode())
                        )
                    )); //todo, make it dynamic
                }
            }else{
                $form->add('amount', TextType::class, array('label' => 'Montant','attr'=>array('placeholder'=>'15')));
                $form->add('registrar',EntityType::class,array(
                    'label' => 'Enregistré par',
                    'class' => 'App\Entity\User',
                    'query_builder' => function (EntityRepository $er) {
                        return $er->createQueryBuilder('u')
                            ->orderBy('u.username', 'ASC');
                    },
                    'choice_label' => 'username',
                ));
                $form->add('mode', ChoiceType::class, array('choices'  => array(
                    'Espèce' => Registration::TYPE_CASH,
                    'Chèque' => Registration::TYPE_CHECK,
                    $this->localCurrency => Registration::TYPE_LOCAL,
//                    'CB' => Registration::TYPE_CREDIT_CARD,
                ),'label' => 'Mode de réglement')); //todo, make it dynamic
            }

        });

    }
}
