<?php

namespace App\Form;

use App\Form\DataTransformer\BeneficiaryToStringTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\AbstractType;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class AutocompleteBeneficiaryHiddenType extends AbstractType
{

    private $transformer;

    public function __construct(BeneficiaryToStringTransformer $transformer)
    {
        $this->transformer = $transformer;
    }

    public function getParent(): string
    {
        return HiddenType::class;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer($this->transformer);
    }

}
