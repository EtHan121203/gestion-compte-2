<?php

namespace App\Form;

use App\Entity\Job;
use App\Form\DataTransformer\JobToNumberTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Entity hidden custom type class definition
 */
class JobHiddenType extends AbstractType
{

    private $transformer;

    public function __construct(JobToNumberTransformer $transformer)
    {
        $this->transformer = $transformer;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer($this->transformer);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'invalid_message' => 'Le type de créneau sélectionné n\'existe pas',
        ]);
    }

    public function getParent(): string
    {
        return HiddenType::class;
    }
}
