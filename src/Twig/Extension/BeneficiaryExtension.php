<?php
namespace App\Twig\Extension;

use App\Entity\AbstractRegistration;
use App\Entity\Beneficiary;
use App\Service\BeneficiaryService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class BeneficiaryExtension extends AbstractExtension
{
    private $beneficiaryService;

    public function __construct(BeneficiaryService $beneficiaryService) {
        $this->beneficiaryService = $beneficiaryService;
    }

    public function getFilters()
    {
        return array(
            new TwigFilter('print_with_number_and_status_icon', array($this->beneficiaryService, 'getDisplayNameWithMemberNumberAndStatusIcon')),
        );
    }

}
