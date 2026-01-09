<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Mailer\MailerInterface;
use Twig\Environment;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class MailerService
{
    private $baseDomain;
    private $memberEmail;
    private $project_name;
    private $sendableEmails;
    private $entity_manager;
    private $mailer;
    private $router;
    private $twig;

    public function __construct(
        MailerInterface $mailer,
        #[Autowire(param: 'emails.base_domain')] string $baseDomain,
        #[Autowire(param: 'emails.member')] array $memberEmail,
        #[Autowire(param: 'project_name')] string $project_name,
        #[Autowire(param: 'emails.sendable')] array $sendableEmails,
        EntityManagerInterface $entity_manager,
        UrlGeneratorInterface $router,
        Environment $twig
    ) {
        $this->mailer = $mailer;
        $this->baseDomain = $baseDomain;
        $this->memberEmail = $memberEmail;
        $this->project_name = $project_name;
        $this->sendableEmails = $sendableEmails;
        $this->entity_manager = $entity_manager;
        $this->router = $router;
        $this->twig = $twig;
    }

    /**
     * Check if the given email is a temporary one
     * @param string $email
     * @return bool
     */
    public function isTemporaryEmail(string $email) : bool
    {
        $pattern = $this->getTemporaryEmailPattern();
        preg_match_all($pattern, $email, $matches, PREG_SET_ORDER, 0);
        return count($matches) > 0;
    }

    public function getAllowedEmails() : array
    {
        $return = array();
        foreach ($this->sendableEmails as $email){
            $key = $email['from_name'].' <'.$email['address'].'>';
            $return[$key] = $email['address'];
        }
        return $return;
    }

    private function getTemporaryEmailPattern() : string
    {
        return '/(membres\\+[0-9]+@' . preg_quote($this->baseDomain) . ')/i';
    }

    /**
     * Send an email to a user to confirm the account creation.
     *
     * @param User $user
     */
    public function sendConfirmationEmailMessage(User $user){
        $dynamicContent = $this->entity_manager->getRepository(\App\Entity\DynamicContent::class)->findOneByCode("WELCOME_EMAIL")->getContent();

        $login_url = $this->router->generate('homepage', array(), UrlGeneratorInterface::ABSOLUTE_URL);
        $welcome = (new \Symfony\Component\Mime\Email())
            ->from(new \Symfony\Component\Mime\Address($this->memberEmail['address'], $this->memberEmail['from_name']))
            ->to($user->getEmail())
            ->subject('Bienvenue à '.$this->project_name)
            ->html(
                $this->renderView(
                    'emails/welcome.html.twig',
                    array(
                        'user' => $user,
                        'dynamicContent' => $dynamicContent,
                        'login_url' => $login_url,
                    )
                )
            );
        $this->mailer->send($welcome);
    }

    /**
     * Send an email to a user to confirm the password reset.
     *
     * @param User $user
     */
    public function sendResettingEmailMessage(User $user){
        $confirmationUrl = $this->router->generate('homepage', array(), UrlGeneratorInterface::ABSOLUTE_URL);

        $forgot = (new \Symfony\Component\Mime\Email())
            ->from(new \Symfony\Component\Mime\Address($this->memberEmail['address'], $this->memberEmail['from_name']))
            ->to($user->getEmail())
            ->subject('Réinitialisation de ton mot de passe')
            ->html(
                $this->renderView(
                    'emails/forgot.html.twig',
                    array(
                        'user' => $user,
                        'confirmationUrl' => $confirmationUrl,
                    )
                )
            );
        $this->mailer->send($forgot);
    }


    /**
     * Returns a rendered view.
     *
     * @param string $view The view name
     * @param array $parameters An array of parameters to pass to the view
     *
     * @return string The rendered view
     * @throws \Exception
     */
    protected function renderView($view, array $parameters = array())
    {
        return $this->twig->render($view, $parameters);
    }
}