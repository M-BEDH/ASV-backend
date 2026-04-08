<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class AdminSecurityController extends AbstractController
{
    #[Route('/admin/login', name: 'admin_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('admin');
        }

        return $this->render('@EasyAdmin/page/login.html.twig', [
            'error'                  => $authenticationUtils->getLastAuthenticationError(),
            'last_username'          => $authenticationUtils->getLastUsername(),
            'page_title'             => '<strong>ASV</strong> Administration',
            'csrf_token_intention'   => 'authenticate',
            'username_label'         => 'Email',
            'password_label'         => 'Mot de passe',
            'sign_in_label'          => 'Se connecter',
            'username_parameter'     => '_username',
            'password_parameter'     => '_password',
            'forgot_password_enabled' => false,
            'remember_me_enabled'    => false,
            'target_path'            => $this->generateUrl('admin'),
        ]);
    }

    #[Route('/admin/logout', name: 'admin_logout')]
    public function logout(): void
    {
        throw new \LogicException('Intercepté par le firewall Symfony.');
    }
}
