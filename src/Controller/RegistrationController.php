<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use App\Security\AppAuthenticator;

class RegistrationController extends AbstractController
{
    #[Route('/inscription', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        UserAuthenticatorInterface $userAuthenticator,
        AppAuthenticator $authenticator,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($passwordHasher->hashPassword($user, $form->get('plainPassword')->getData()));
            $em->persist($user);

            try {
                $em->flush();
            } catch (UniqueConstraintViolationException) {
                $form->get('email')->addError(new FormError('Un compte existe déjà avec cet email.'));
                return $this->render('registration/register.html.twig', ['form' => $form]);
            }

            return $userAuthenticator->authenticateUser($user, $authenticator, $request);
        }

        return $this->render('registration/register.html.twig', ['form' => $form]);
    }
}
